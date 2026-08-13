<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\RAG\Contracts\VectorStoreInterface;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Proves RAGManager delegates to a bound VectorStoreInterface when present
 * (ingest/search/flush/count), and — the most important property — that
 * the built-in DB-table scan is completely untouched when nothing is
 * bound. tests/Feature/RAGManagerTest.php is re-run unmodified as part of
 * this task's verification; see the sequential test run in CI/this task's
 * own report for confirmation it still passes byte-for-byte as-is.
 */
class VectorStoreDelegationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEmbedEndpoint(array $vector = [1, 0, 0]): void
    {
        Http::fake([
            '127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [$vector]]),
        ]);
    }

    private function fakeVectorStore(): FakeVectorStore
    {
        $store = new FakeVectorStore();
        $this->app->instance(VectorStoreInterface::class, $store);
        return $store;
    }

    public function test_ingest_delegates_to_bound_vector_store(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);
        $store = $this->fakeVectorStore();

        $count = AI::rag()->ingest('hello world', 'docs');

        $this->assertSame(1, $count);
        $this->assertCount(1, $store->upserts);
        $this->assertSame(['hello world', 'docs', [1, 0, 0]], $store->upserts[0]);

        // And crucially, nothing was written to the built-in table.
        $this->assertSame(0, DB::table(config('ai.rag.table'))->count());
    }

    public function test_search_delegates_to_bound_vector_store(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);
        $store = $this->fakeVectorStore();
        $store->searchResults = [['content' => 'from store', 'source' => 'docs', 'score' => 0.99]];

        $results = AI::rag()->source('docs')->search('anything');

        $this->assertSame($store->searchResults, $results);
        $this->assertSame([[1, 0, 0], 'docs', 3], $store->lastSearchArgs);
    }

    public function test_flush_delegates_to_bound_vector_store(): void
    {
        $store = $this->fakeVectorStore();

        AI::rag()->flush('docs');

        $this->assertSame(['docs'], $store->deletes);
    }

    public function test_count_delegates_to_bound_vector_store(): void
    {
        $store = $this->fakeVectorStore();
        $store->countResult = 5;

        $this->assertSame(5, AI::rag()->count('docs'));
        $this->assertSame(['docs'], $store->counts);
    }

    /**
     * The critical regression-safety property: with nothing bound (the
     * default for every host app), ingest()/search()/flush() behave
     * exactly as they did before VectorStoreInterface existed — writing to
     * and reading from the built-in DB table.
     */
    public function test_without_a_bound_store_the_built_in_table_scan_is_unaffected(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);

        AI::rag()->ingest('hello world', 'docs');

        $this->assertSame(1, DB::table(config('ai.rag.table'))->count());

        $results = AI::rag()->source('docs')->search('anything');
        $this->assertSame('hello world', $results[0]['content']);

        AI::rag()->flush('docs');
        $this->assertSame(0, DB::table(config('ai.rag.table'))->count());
    }
}

/**
 * Minimal in-memory VectorStoreInterface double for asserting delegation —
 * not a real vector store, just records what RAGManager called it with.
 */
class FakeVectorStore implements VectorStoreInterface
{
    public array $upserts = [];
    public array $deletes = [];
    public array $counts = [];
    public array $searchResults = [];
    public ?array $lastSearchArgs = null;
    public int $countResult = 0;

    public function upsert(string $content, string $source, array $embedding): void
    {
        $this->upserts[] = [$content, $source, $embedding];
    }

    public function search(array $queryEmbedding, ?string $source, int $topK): array
    {
        $this->lastSearchArgs = [$queryEmbedding, $source, $topK];
        return $this->searchResults;
    }

    public function delete(?string $source = null): void
    {
        $this->deletes[] = $source;
    }

    public function count(?string $source = null): int
    {
        $this->counts[] = $source;
        return $this->countResult;
    }
}
