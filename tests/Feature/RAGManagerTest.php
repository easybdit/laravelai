<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RAGManager::search()/searchAutoIndexed()/countMatches() previously loaded
 * the *entire* matched corpus into memory via ->get() before scoring/
 * counting — every row, every embedding vector, all at once. Fine for a
 * few hundred chunks, a real problem at scale (RAG usage grows with every
 * ingested document, unlike most other tables in this package). These
 * cover the bounded-batch (chunkById) rewrite behaves identically for
 * correctness, and that the new scan cap actually engages.
 */
class RAGManagerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEmbedEndpoint(array $vector = [1, 0, 0]): void
    {
        Http::fake([
            '127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [$vector]]),
        ]);
    }

    private function insertChunk(string $content, array $embedding, string $source = 'test'): void
    {
        DB::table(config('ai.rag.table', 'ai_documents'))->insert([
            'content'    => $content,
            'source'     => $source,
            'embedding'  => json_encode($embedding),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_search_still_ranks_the_closest_match_first(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);

        $this->insertChunk('opposite', [-1, 0, 0]);
        $this->insertChunk('orthogonal', [0, 1, 0]);
        $this->insertChunk('exact match', [1, 0, 0]);

        $results = AI::rag()->source('test')->search('anything');

        $this->assertSame('exact match', $results[0]['content']);
    }

    public function test_search_respects_top_k(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);
        config(['ai.rag.top_k' => 2]);

        foreach (range(1, 5) as $i) {
            $this->insertChunk("chunk {$i}", [1, 0, 0]);
        }

        $results = AI::rag()->source('test')->search('anything');

        $this->assertCount(2, $results);
    }

    public function test_search_is_scoped_to_the_given_source(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);

        $this->insertChunk('in scope', [1, 0, 0], 'project_1');
        $this->insertChunk('out of scope', [1, 0, 0], 'project_2');

        $results = AI::rag()->source('project_1')->search('anything');

        $this->assertCount(1, $results);
        $this->assertSame('in scope', $results[0]['content']);
    }

    public function test_count_matches_scans_in_bounded_batches_and_still_counts_correctly(): void
    {
        $this->insertChunk('this mentions apple once', []);
        $this->insertChunk('this mentions apple and apples', []);
        $this->insertChunk('no fruit here', []);

        $count = AI::rag()->countMatches('apple');

        $this->assertSame(2, $count);
    }

    public function test_a_search_that_exceeds_max_scan_rows_logs_a_warning_and_still_returns_results(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);
        config(['ai.rag.max_scan_rows' => 2]);

        foreach (range(1, 5) as $i) {
            $this->insertChunk("chunk {$i}", [1, 0, 0]);
        }

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'max_scan_rows'));

        $results = AI::rag()->source('test')->search('anything');

        $this->assertNotEmpty($results, 'A capped scan should still return whatever it found, not nothing.');
    }

    public function test_a_search_within_max_scan_rows_never_logs_a_warning(): void
    {
        $this->fakeEmbedEndpoint([1, 0, 0]);
        config(['ai.rag.max_scan_rows' => 50000]);

        $this->insertChunk('one chunk', [1, 0, 0]);

        Log::shouldReceive('warning')->never();

        AI::rag()->source('test')->search('anything');
        $this->addToAssertionCount(1);
    }
}
