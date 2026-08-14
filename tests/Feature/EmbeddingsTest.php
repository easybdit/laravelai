<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * ->embed() beyond Ollama (which already worked) — OpenAI's real
 * /embeddings endpoint (inherited by Together, which has a genuine
 * OpenAI-shaped embeddings endpoint of its own) and Gemini's native
 * embedContent/batchEmbedContents. Every driver returns the same shape:
 * an array of vectors, one per input, so ->embed($singleString)[0] is the
 * vector — exactly the contract RAGManager already depends on.
 */
class EmbeddingsTest extends TestCase
{
    use RefreshDatabase;


    public function test_openai_embed_single_string_returns_array_of_one_vector(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'object' => 'list',
                'data'   => [['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2, 0.3]]],
                'model'  => 'text-embedding-3-small',
                'usage'  => ['prompt_tokens' => 2, 'total_tokens' => 2],
            ]),
        ]);

        $vectors = AI::provider('openai')->model('text-embedding-3-small')->embed('hello world');

        $this->assertSame([[0.1, 0.2, 0.3]], $vectors);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request->data()['input'] === 'hello world'
            && $request->data()['model'] === 'text-embedding-3-small');
    }

    public function test_openai_embed_batch_is_reordered_by_index_not_array_order(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    // Deliberately out of order — the driver must sort by
                    // "index", not trust response array order.
                    ['index' => 1, 'embedding' => [9, 9]],
                    ['index' => 0, 'embedding' => [1, 1]],
                ],
            ]),
        ]);

        $vectors = AI::provider('openai')->embed(['first', 'second']);

        $this->assertSame([[1, 1], [9, 9]], $vectors);
    }

    public function test_openai_embed_error_response_throws_provider_exception(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response(['error' => ['message' => 'invalid model']], 400),
        ]);

        $this->expectException(\EasyAI\LaravelAI\Exceptions\ProviderException::class);

        AI::provider('openai')->embed('hello');
    }

    public function test_together_inherits_embed_from_openai_driver(): void
    {
        Http::fake([
            'api.together.xyz/v1/embeddings' => Http::response([
                'data' => [['index' => 0, 'embedding' => [0.5, 0.5]]],
            ]),
        ]);

        $vectors = AI::provider('together')->model('BAAI/bge-large-en-v1.5')->embed('hello');

        $this->assertSame([[0.5, 0.5]], $vectors);
    }

    public function test_gemini_embed_single_string_uses_embedcontent(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*embedContent*' => Http::response([
                'embedding' => ['values' => [0.4, 0.5, 0.6]],
            ]),
        ]);

        $vectors = AI::provider('gemini')->model('gemini-embedding-001')->embed('hello world');

        $this->assertSame([[0.4, 0.5, 0.6]], $vectors);

        Http::assertSent(fn ($request) => str_contains($request->url(), ':embedContent')
            && $request->data()['content']['parts'][0]['text'] === 'hello world');
    }

    public function test_gemini_embed_batch_uses_batchembedcontents_with_model_per_request(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*batchEmbedContents*' => Http::response([
                'embeddings' => [
                    ['values' => [1, 1]],
                    ['values' => [2, 2]],
                ],
            ]),
        ]);

        $vectors = AI::provider('gemini')->model('gemini-embedding-001')->embed(['a', 'b']);

        $this->assertSame([[1, 1], [2, 2]], $vectors);

        Http::assertSent(function ($request) {
            $requests = $request->data()['requests'];
            return str_contains($request->url(), ':batchEmbedContents')
                && $requests[0]['model'] === 'models/gemini-embedding-001'
                && $requests[0]['content']['parts'][0]['text'] === 'a'
                && $requests[1]['model'] === 'models/gemini-embedding-001'
                && $requests[1]['content']['parts'][0]['text'] === 'b';
        });
    }

    public function test_anthropic_embed_still_throws_no_native_support(): void
    {
        // Anthropic offers no embeddings API of its own (recommends a
        // third-party provider, Voyage AI, entirely outside this
        // package's scope) — confirms AbstractDriver's default
        // BadMethodCallException still applies here, unchanged.
        $this->expectException(\BadMethodCallException::class);

        AI::provider('anthropic')->embed('hello');
    }

    public function test_rag_manager_can_embed_via_openai_provider_end_to_end(): void
    {
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['index' => 0, 'embedding' => [0.1, 0.2]]],
            ]),
        ]);

        config(['ai.rag.embed_provider' => 'openai', 'ai.rag.embed_model' => 'text-embedding-3-small']);

        $count = AI::rag()->ingest('Laravel is a PHP framework.', 'docs-openai');

        $this->assertSame(1, $count);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com/v1/embeddings'));
    }
}
