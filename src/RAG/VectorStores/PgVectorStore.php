<?php

namespace EasyAI\LaravelAI\RAG\VectorStores;

use EasyAI\LaravelAI\RAG\Contracts\VectorStoreInterface;
use Illuminate\Support\Facades\DB;

/**
 * A real vector-database backend for RAG, using Postgres's `pgvector`
 * extension via raw SQL through Laravel's DB facade — no new Composer
 * dependency, since pgvector is a Postgres extension, not a PHP library.
 *
 * NOT usable out of the box the way the rest of this package is: this
 * requires (a) a Postgres database as the host app's connection, and (b)
 * the pgvector extension available and enabled on it — something this
 * package cannot assume or install for you. Bind it explicitly once your
 * Postgres instance is ready:
 *
 *   $this->app->bind(VectorStoreInterface::class, fn () => new PgVectorStore(
 *       table: 'ai_document_vectors',
 *       dimensions: 1536, // must match your embedding model's output size
 *   ));
 *
 * One-time setup (run as your own migration or directly against your
 * Postgres database — this package deliberately does NOT auto-run this via
 * its own migrations, since every other host app's non-Postgres migrate
 * would break the moment a Postgres-only CREATE EXTENSION/vector column
 * statement ran automatically):
 *
 *   CREATE EXTENSION IF NOT EXISTS vector;
 *
 *   CREATE TABLE ai_document_vectors (
 *       id BIGSERIAL PRIMARY KEY,
 *       content TEXT NOT NULL,
 *       source VARCHAR(255) NOT NULL DEFAULT '',
 *       embedding VECTOR(1536) NOT NULL, -- match $dimensions below
 *       created_at TIMESTAMP NOT NULL DEFAULT now(),
 *       updated_at TIMESTAMP NOT NULL DEFAULT now()
 *   );
 *
 *   CREATE INDEX ai_document_vectors_source_index ON ai_document_vectors (source);
 *
 *   -- Optional, recommended once your corpus is large enough that an exact
 *   -- scan gets slow — an approximate nearest-neighbor index:
 *   CREATE INDEX ON ai_document_vectors
 *       USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
 *
 * $dimensions must match whatever embedding model you use — 1536 for
 * OpenAI's text-embedding-3-small/ada-002, 768 for nomic-embed-text, etc.
 * This class doesn't enforce it; a mismatch surfaces as a Postgres error
 * from pgvector itself on insert.
 *
 * Uses raw SQL (DB::insert/select/delete/statement) rather than the query
 * builder because pgvector's `<=>` cosine-distance operator and `::vector`
 * cast aren't expressible through it.
 */
class PgVectorStore implements VectorStoreInterface
{
    public function __construct(
        protected string $table = 'ai_document_vectors',
        protected int $dimensions = 1536,
    ) {}

    public function upsert(string $content, string $source, array $embedding): void
    {
        DB::insert(
            "insert into {$this->table} (content, source, embedding, created_at, updated_at) values (?, ?, ?::vector, ?, ?)",
            [$content, $source, $this->toVectorLiteral($embedding), now()->toDateTimeString(), now()->toDateTimeString()]
        );
    }

    /**
     * @return array<int, array{content: string, source: string, score: float}>
     */
    public function search(array $queryEmbedding, ?string $source, int $topK): array
    {
        $literal  = $this->toVectorLiteral($queryEmbedding);
        $bindings = [$literal];

        // 1 - cosine_distance = cosine_similarity, so "score" stays in the
        // same "higher is better" direction as the built-in in-PHP scan
        // regardless of which store answered the search.
        $sql = "select content, source, 1 - (embedding <=> ?::vector) as score from {$this->table}";

        if ($source !== null) {
            $sql .= ' where source = ?';
            $bindings[] = $source;
        }

        $sql .= ' order by embedding <=> ?::vector limit ?';
        $bindings[] = $literal;
        $bindings[] = max(1, $topK);

        try {
            $rows = DB::select($sql, $bindings);
        } catch (\Throwable) {
            // A pgvector-specific SQL failure (extension missing, dimension
            // mismatch, connection down) shouldn't take down the whole
            // request — same defensive stance as WebSearchProvider::search().
            return [];
        }

        return array_map(fn ($row) => [
            'content' => $row->content,
            'source'  => $row->source,
            'score'   => (float) $row->score,
        ], $rows);
    }

    public function delete(?string $source = null): void
    {
        if ($source !== null) {
            DB::delete("delete from {$this->table} where source = ?", [$source]);
            return;
        }

        DB::statement("truncate table {$this->table}");
    }

    public function count(?string $source = null): int
    {
        $row = $source !== null
            ? DB::selectOne("select count(*) as c from {$this->table} where source = ?", [$source])
            : DB::selectOne("select count(*) as c from {$this->table}");

        return (int) ($row->c ?? 0);
    }

    /**
     * pgvector's text input format: "[0.1,0.2,0.3]". Cast to float per
     * element so an accidentally-string-typed embedding value doesn't leak
     * into the literal as-is.
     */
    private function toVectorLiteral(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn ($v) => (float) $v, $embedding)) . ']';
    }
}
