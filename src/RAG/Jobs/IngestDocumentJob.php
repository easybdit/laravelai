<?php

namespace EasyAI\LaravelAI\RAG\Jobs;

use EasyAI\LaravelAI\RAG\RAGManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued counterpart to RAGManager::ingest() — dispatched by
 * RAGManager::ingestAsync() (opt-in via config('ai.rag.queue_ingestion'))
 * so a large document's chunk-by-chunk embedding calls don't block the HTTP
 * request that uploaded it.
 *
 * Deliberately takes primitives (content/source strings), not an Eloquent
 * model — ingestion doesn't depend on any specific model, and keeping the
 * job payload to plain strings avoids re-fetching anything in handle().
 *
 * Tradeoff, by design: if ingest() throws (e.g. the embedding API is down),
 * this job is left to fail normally through Laravel's queue failure
 * handling (failed_jobs table / your queue:work --tries, etc.) rather than
 * swallowing the exception here. A silently-lost failure with zero
 * visibility would be worse than today's synchronous path, where a failure
 * at least surfaces immediately to the uploader as a 422. Queueing trades
 * "the uploader sees the error right away" for "the request returns
 * instantly" — it must not also trade away "the error is visible at all".
 * Anyone queueing ingestion should be monitoring failed jobs.
 */
class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $content,
        public string $source = ''
    ) {
    }

    public function handle(): void
    {
        app(RAGManager::class)->ingest($this->content, $this->source);
    }
}
