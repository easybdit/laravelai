<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\RAG\Jobs\IngestDocumentJob;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Direct unit test of IngestDocumentJob::handle() in isolation — confirms
 * it actually runs RAGManager::ingest() with the job's content/source, the
 * same work the synchronous path does, just off the request thread.
 */
class IngestDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_ingests_the_content_under_the_given_source(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [[1, 0, 0]]]),
        ]);

        $job = new IngestDocumentJob('Some content to embed and store.', 'unit_test_source');
        $job->handle();

        $rows = DB::table(config('ai.rag.table', 'ai_documents'))
            ->where('source', 'unit_test_source')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Some content to embed and store.', $rows->first()->content);
    }

    public function test_handle_lets_an_embedding_failure_propagate_rather_than_swallowing_it(): void
    {
        // No Http::fake() registered for the embed endpoint here — the real
        // AI provider call will fail (connection refused / no fake match),
        // and that failure must propagate so the queue's normal failure
        // handling (failed_jobs, retries) sees it, per the documented
        // tradeoff: queueing must not create a new way to silently lose data.
        Http::fake([
            '127.0.0.1:11434/api/embed' => Http::response(['error' => 'boom'], 500),
        ]);

        $job = new IngestDocumentJob('content', 'unit_test_source_failure');

        $this->expectException(\Throwable::class);
        $job->handle();
    }
}
