<?php

namespace EasyAI\LaravelAI\RAG\Contracts;

/**
 * Bind your own implementation of this in your app's service provider:
 *
 *   $this->app->bind(VectorStoreInterface::class, MyVectorStore::class);
 *
 * RAGManager's built-in default is a zero-setup in-PHP cosine scan over a
 * plain DB table (see config('ai.rag.max_scan_rows')'s docblock) — genuinely
 * fine up to a few tens of thousands of chunks, but not a substitute for a
 * real vector database at larger scale or when you need an ANN index for
 * low-latency search. Bind an implementation of this contract and
 * RAGManager::ingest()/search()/flush()/count() delegate to it instead,
 * with zero setup required and zero behavior change for every host app that
 * never binds anything — this package creates no hard dependency on any
 * vector database.
 *
 * This package ships one built-in implementation,
 * {@see \EasyAI\LaravelAI\RAG\VectorStores\PgVectorStore} (Postgres +
 * pgvector), since Postgres+pgvector is the most realistic "already have
 * this" option that doesn't require standing up a wholly separate service.
 * Nothing about this contract is Postgres-specific though — implement it
 * against Pinecone, Weaviate, Milvus, Qdrant, a managed service, anything
 * that can store a vector and return nearest neighbors.
 *
 * Implementors MUST NOT let network/connection failures escape as
 * uncaught exceptions from search() in a way that takes down an entire
 * chat request — same "never let one flaky piece take down the whole
 * request" philosophy as the rest of this package (see
 * {@see \EasyAI\LaravelAI\Agent\Contracts\WebSearchProvider}). Returning an
 * empty result set on failure is preferable to throwing, where reasonable.
 *
 * Note on scope: RAGManager::searchAutoIndexed() (the "Ask This Site"
 * auto-indexing feature) and RAGManager::countMatches() (the full-corpus
 * keyword scan behind "how many X" answers) are NOT covered by this
 * delegation — searchAutoIndexed() relies on a source-*prefix* match
 * ("site_posts:42" under a "site_posts" filter) that doesn't map onto this
 * contract's exact-source search() below, and countMatches() is a keyword
 * scan over stored text, not a similarity search. Both continue to use the
 * built-in DB table even when a VectorStoreInterface is bound. If you use
 * config('ai.rag.auto_index') together with a bound vector store, be aware
 * searchAutoIndexed() will not see rows delegated to your store.
 */
interface VectorStoreInterface
{
    /**
     * Store one chunk's content, source, and embedding vector. Called once
     * per chunk from RAGManager::ingest() — implementations may insert or
     * upsert (e.g. AutoIndexer re-indexes a model by de-indexing its old
     * source key first, so a plain insert is fine too).
     */
    public function upsert(string $content, string $source, array $embedding): void;

    /**
     * Nearest-neighbor search. $source === null means search across every
     * source; otherwise scope to an exact source match (mirrors
     * RAGManager::source()). Must return at most $topK results, ordered by
     * descending relevance (best match first).
     *
     * @param  float[]      $queryEmbedding
     * @return array<int, array{content: string, source: string, score: float}>
     *         score: higher is more relevant — this package's built-in scan
     *         uses raw cosine similarity (-1..1); implementations backed by
     *         a distance metric (e.g. pgvector's cosine *distance*) should
     *         convert to a similarity-shaped score (e.g. 1 - distance) so
     *         callers can treat "higher is better" consistently regardless
     *         of which store answered the search.
     */
    public function search(array $queryEmbedding, ?string $source, int $topK): array;

    /**
     * Delete stored vectors. $source === null means delete everything
     * (mirrors RAGManager::flush() with no argument); otherwise delete only
     * that exact source's rows.
     */
    public function delete(?string $source = null): void;

    /**
     * Count stored vectors, optionally scoped to one exact source.
     */
    public function count(?string $source = null): int;
}
