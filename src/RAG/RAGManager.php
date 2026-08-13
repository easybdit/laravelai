<?php
namespace EasyAI\LaravelAI\RAG;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\RAG\Contracts\VectorStoreInterface;
use EasyAI\LaravelAI\RAG\Jobs\IngestDocumentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RAGManager
{
    protected ?string $sourceFilter = null;

    public function source(string $source): static
    {
        $this->sourceFilter = $source;
        return $this;
    }

    /**
     * A host-bound VectorStoreInterface implementation takes over
     * ingest()/search()/flush()/count() entirely; null (the default, for
     * every app that never binds one) means "keep using the built-in
     * DB-table scan" — every method below is unchanged from before this
     * existed in that case.
     */
    private function vectorStore(): ?VectorStoreInterface
    {
        return app()->bound(VectorStoreInterface::class) ? app(VectorStoreInterface::class) : null;
    }

    public function ingest(string $content, string $source = ''): int
    {
        $store = $this->vectorStore();
        $count = 0;

        foreach ($this->chunk($content) as $chunk) {
            $vector = $this->embed($chunk);

            if ($store) {
                $store->upsert($chunk, $source, $vector);
            } else {
                DB::table(config('ai.rag.table'))->insert([
                    'content'    => $chunk,
                    'source'     => $source,
                    'embedding'  => json_encode($vector),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Opt-in queued counterpart to ingest() — dispatches IngestDocumentJob
     * instead of embedding every chunk inline. ingest() itself keeps its
     * synchronous default unchanged; callers that want the non-blocking
     * behavior (e.g. ProjectFileController, gated by
     * config('ai.rag.queue_ingestion')) call this instead.
     */
    public function ingestAsync(string $content, string $source = ''): void
    {
        IngestDocumentJob::dispatch($content, $source);
    }

    public function ask(string $question): string
    {
        $context   = collect($this->search($question))->pluck('content')->join("\n\n---\n\n");
        $provider  = config('ai.rag.chat_provider') ?? config('ai.default');
        $chatModel = config("ai.providers.{$provider}.model");
        $ai        = AI::provider($provider)->model($chatModel);

        if ($context) {
            $ai->systemPrompt(config('ai.rag.system_prompt') . "\n\nCONTEXT:\n" . $context);
        }

        return $ai->chat([['role' => 'user', 'content' => $question]])->content;
    }

    public function search(string $query): array
    {
        $queryVector = $this->embed($query);
        $source      = $this->sourceFilter;
        $this->sourceFilter = null;

        if ($store = $this->vectorStore()) {
            return $store->search($queryVector, $source, (int) config('ai.rag.top_k', 3));
        }

        $dbQuery = DB::table(config('ai.rag.table'))
            ->select(['id', 'content', 'source', 'embedding']);

        if ($source) {
            $dbQuery->where('source', $source);
        }

        return $this->topKScan($dbQuery, $queryVector, (int) config('ai.rag.top_k', 3));
    }

    /**
     * Search across every source AutoIndexer has written (each auto-indexed
     * row is stored under "{configured source}:{model id}" so a single row
     * can be de-indexed precisely on update/delete — this collects all of
     * them back together at query time via a source-prefix match instead
     * of the exact-match source() scoping used by ingest()/search().
     *
     * NOT delegated to a bound VectorStoreInterface (see that contract's
     * docblock "Note on scope") — its search() takes a single exact
     * source, not the prefix match this needs, so this always queries the
     * built-in DB table directly regardless of what's bound.
     */
    public function searchAutoIndexed(string $query): array
    {
        $prefixes = collect(config('ai.rag.auto_index', []))->pluck('source')->filter()->unique()->values();
        if ($prefixes->isEmpty()) {
            return [];
        }

        $queryVector = $this->embed($query);

        $dbQuery = DB::table(config('ai.rag.table'))
            ->select(['id', 'content', 'source', 'embedding'])
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('source', 'like', $prefix . ':%');
                }
            });

        return $this->topKScan($dbQuery, $queryVector, (int) config('ai.rag.top_k', 3));
    }

    /**
     * Full-corpus keyword scan for "how many X" style questions. Top-K
     * semantic search alone is unreliable for exact counts (relevant chunks
     * can rank outside K, or several chunks can describe the same item) —
     * this scans every stored chunk instead, with basic plural/singular
     * matching, mirroring the WordPress plugin's "accurate counting" fix.
     */
    public function countMatches(string $term, ?string $source = null): int
    {
        $term = trim(mb_strtolower($term));
        if ($term === '') {
            return 0;
        }

        $variants = array_unique(array_filter([
            $term,
            str_ends_with($term, 's') ? mb_substr($term, 0, -1) : $term . 's',
        ]));

        $query = DB::table(config('ai.rag.table'))->select(['id', 'content']);
        if ($source) {
            $query->where('source', $source);
        }

        // Processed in bounded batches (chunkById) rather than ->get() —
        // a full-corpus scan already has to visit every row here (there's
        // no way to count matches without reading the content), but there
        // is no reason the *entire* corpus needs to sit in memory at once
        // to do it.
        $count = 0;
        $query->chunkById(500, function ($rows) use (&$count, $variants) {
            foreach ($rows as $row) {
                $content = mb_strtolower($row->content);
                foreach ($variants as $variant) {
                    if ($variant !== '' && str_contains($content, $variant)) {
                        $count++;
                        break;
                    }
                }
            }
        });

        return $count;
    }

    public function flush(?string $source = null): void
    {
        $target = $source ?? $this->sourceFilter;
        $this->sourceFilter = null;

        if ($store = $this->vectorStore()) {
            $store->delete($target);
            return;
        }

        if ($target) {
            DB::table(config('ai.rag.table'))->where('source', $target)->delete();
        } else {
            DB::table(config('ai.rag.table'))->truncate();
        }
    }

    /**
     * Total stored chunk count, optionally scoped to one exact source —
     * mirrors flush()'s targeting (explicit $source, else a prior
     * ->source() call, else "everything"). New in v2.7.0 alongside
     * VectorStoreInterface — not a behavior change to any existing method.
     */
    public function count(?string $source = null): int
    {
        $target = $source ?? $this->sourceFilter;
        $this->sourceFilter = null;

        if ($store = $this->vectorStore()) {
            return $store->count($target);
        }

        $query = DB::table(config('ai.rag.table'));
        if ($target) {
            $query->where('source', $target);
        }

        return $query->count();
    }

    private function embed(string $text): array
    {
        $embedProvider = config('ai.rag.embed_provider', 'ollama');
        $embedModel    = config('ai.rag.embed_model', 'nomic-embed-text');

        $vector = AI::provider($embedProvider)
            ->model($embedModel)
            ->embed($text)[0];

        // Purge cached driver so next AI::provider() gets fresh instance with correct model
        app(\EasyAI\LaravelAI\AIManager::class)->forgetDrivers();

        return $vector;
    }

    private function chunk(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/', $text);
        $chunks     = [];
        $current    = '';
        foreach ($paragraphs as $para) {
            if (strlen($current . $para) > config('ai.rag.chunk_size', 2000) && $current) {
                $chunks[] = trim($current);
                $current  = $para;
            } else {
                $current .= "\n\n" . $para;
            }
        }
        if (trim($current)) $chunks[] = trim($current);
        return $chunks ?: [trim($text)];
    }

    private function cosine(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) return 0.0;
        $dot = $normA = $normB = 0.0;
        foreach ($a as $i => $val) {
            $dot   += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }
        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /**
     * Cosine-scores $query's matches in bounded batches (chunkById) instead
     * of materializing the whole result set — including every row's
     * decoded embedding vector — into memory at once via ->get(). The
     * running "best so far" list is also re-trimmed to $k after every
     * batch, so memory stays roughly constant regardless of corpus size:
     * this is still an O(corpus) *scan* (no ANN index, by design — no
     * external vector DB required for the zero-setup default), just no
     * longer an O(corpus) *memory allocation*.
     *
     * Stops early at config('ai.rag.max_scan_rows') with a logged warning
     * rather than let one search take an unbounded amount of time — a
     * slower-than-ideal answer beats a request that never returns.
     */
    private function topKScan($query, array $queryVector, int $k): array
    {
        $k = max(1, $k);
        $best    = [];
        $scanned = 0;
        $maxScan = max(1, (int) config('ai.rag.max_scan_rows', 50000));
        $capped  = false;

        $query->chunkById(500, function ($rows) use (&$best, &$scanned, $queryVector, $k, $maxScan, &$capped) {
            foreach ($rows as $row) {
                $best[] = [
                    'content' => $row->content,
                    'source'  => $row->source,
                    'score'   => $this->cosine($queryVector, json_decode($row->embedding, true) ?: []),
                ];
            }
            $scanned += count($rows);

            usort($best, fn ($a, $b) => $b['score'] <=> $a['score']);
            $best = array_slice($best, 0, $k);

            if ($scanned >= $maxScan) {
                $capped = true;
                return false; // stop chunking — max_scan_rows reached
            }
        });

        if ($capped) {
            Log::warning("laraveleasyai: RAG search stopped after scanning {$maxScan} rows (config('ai.rag.max_scan_rows')) — results may be incomplete for this corpus size. Consider a dedicated vector store at this scale.");
        }

        return array_values($best);
    }
}
