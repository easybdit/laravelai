<?php
namespace EasyAI\LaravelAI\RAG;

use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Support\Facades\DB;

class RAGManager
{
    protected ?string $sourceFilter = null;

    public function source(string $source): static
    {
        $this->sourceFilter = $source;
        return $this;
    }

    public function ingest(string $content, string $source = ''): int
    {
        $count = 0;
        foreach ($this->chunk($content) as $chunk) {
            $vector = $this->embed($chunk);
            DB::table(config('ai.rag.table'))->insert([
                'content'    => $chunk,
                'source'     => $source,
                'embedding'  => json_encode($vector),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }
        return $count;
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

        $dbQuery = DB::table(config('ai.rag.table'))
            ->select(['content', 'source', 'embedding']);

        if ($this->sourceFilter) {
            $dbQuery->where('source', $this->sourceFilter);
        }

        $results = $dbQuery->get()
            ->map(fn($row) => [
                'content' => $row->content,
                'source'  => $row->source,
                'score'   => $this->cosine($queryVector, json_decode($row->embedding, true)),
            ])
            ->sortByDesc('score')
            ->take(config('ai.rag.top_k', 3))
            ->values()
            ->toArray();

        $this->sourceFilter = null;

        return $results;
    }

    public function flush(?string $source = null): void
    {
        $target = $source ?? $this->sourceFilter;
        $this->sourceFilter = null;

        if ($target) {
            DB::table(config('ai.rag.table'))->where('source', $target)->delete();
        } else {
            DB::table(config('ai.rag.table'))->truncate();
        }
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
}
