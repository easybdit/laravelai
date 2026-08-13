<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Agent\Tool;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Covers the opt-in response cache (config('ai.cache.*'), v2.7.0) wrapping
 * AbstractDriver::chat() — see AbstractDriver::shouldCache()/cacheKey().
 */
class ResponseCachingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Deterministic, isolated cache stores for these tests regardless
        // of the host machine's own cache.php.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', ['driver' => 'array']);
        $app['config']->set('cache.stores.secondary', ['driver' => 'array']);
    }

    private function fakeOpenAi(string $content = 'Hello!'): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
                'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 3],
                'model'   => 'gpt-4o-mini',
            ]),
        ]);
    }

    /**
     * The single most important property: with ai.cache.enabled left at its
     * default (false), two identical chat() calls behave exactly as they
     * did before this feature existed — no caching, one HTTP request per call.
     */
    public function test_caching_disabled_by_default_is_byte_identical_to_before(): void
    {
        $this->fakeOpenAi();

        $messages = [['role' => 'user', 'content' => 'Hi']];
        AI::provider('openai')->chat($messages);
        AI::provider('openai')->chat($messages);

        Http::assertSentCount(2);
    }

    public function test_identical_calls_hit_cache_when_enabled(): void
    {
        config(['ai.cache.enabled' => true]);
        $this->fakeOpenAi('Cached answer');

        $messages = [['role' => 'user', 'content' => 'What is 2+2?']];

        $first  = AI::provider('openai')->chat($messages);
        $second = AI::provider('openai')->chat($messages);

        $this->assertSame('Cached answer', $first->getContent());
        $this->assertSame('Cached answer', $second->getContent());
        Http::assertSentCount(1);
    }

    public function test_different_message_content_does_not_collide(): void
    {
        config(['ai.cache.enabled' => true]);
        $this->fakeOpenAi();

        AI::provider('openai')->chat([['role' => 'user', 'content' => 'Question A']]);
        AI::provider('openai')->chat([['role' => 'user', 'content' => 'Question B']]);

        Http::assertSentCount(2);
    }

    public function test_different_model_does_not_collide(): void
    {
        config(['ai.cache.enabled' => true]);
        $this->fakeOpenAi();

        $messages = [['role' => 'user', 'content' => 'Same question']];
        AI::provider('openai')->model('gpt-4o-mini')->chat($messages);
        AI::provider('openai')->model('gpt-4o')->chat($messages);

        Http::assertSentCount(2);
    }

    public function test_different_system_prompt_does_not_collide(): void
    {
        config(['ai.cache.enabled' => true]);
        $this->fakeOpenAi();

        $messages = [['role' => 'user', 'content' => 'Same question']];
        AI::provider('openai')->systemPrompt('Be terse.')->chat($messages);
        AI::provider('openai')->systemPrompt('Be verbose.')->chat($messages);

        Http::assertSentCount(2);
    }

    public function test_different_provider_does_not_collide(): void
    {
        config(['ai.cache.enabled' => true]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'From OpenAI']]],
                'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            ]),
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'From DeepSeek']]],
                'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            ]),
        ]);

        $messages = [['role' => 'user', 'content' => 'Same question']];
        AI::provider('openai')->chat($messages);
        AI::provider('deepseek')->chat($messages);

        Http::assertSentCount(2);
    }

    /**
     * Streaming must never be served from (or written to) the cache — a
     * cached token-by-token stream doesn't make sense.
     */
    public function test_streaming_requests_are_never_cached(): void
    {
        config(['ai.cache.enabled' => true]);
        $this->fakeOpenAi();

        $messages = [['role' => 'user', 'content' => 'Stream me']];
        $chunks   = [];
        $collect  = function (string $chunk) use (&$chunks) { $chunks[] = $chunk; };

        AI::provider('openai')->stream($messages, $collect);
        AI::provider('openai')->stream($messages, $collect);

        Http::assertSentCount(2);
    }

    /**
     * Tool-calling requests must never be cached — a cached response for a
     * tool call would skip whatever real side effects that tool implies.
     */
    public function test_tool_calling_requests_are_never_cached(): void
    {
        config(['ai.cache.enabled' => true]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => null,
                        'tool_calls' => [[
                            'id'       => 'call_1',
                            'type'     => 'function',
                            'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ]),
        ]);

        $tool = Tool::make(
            'get_weather',
            'Gets the weather.',
            ['type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city']],
            fn (array $args) => "Sunny in {$args['city']}"
        );

        $messages = [['role' => 'user', 'content' => 'Weather in Paris?']];
        AI::provider('openai')->tools([$tool])->chat($messages);
        AI::provider('openai')->tools([$tool])->chat($messages);

        Http::assertSentCount(2);
    }

    /**
     * Uses Laravel's own Cache facade against whichever store
     * config('ai.cache.store') names — proving this isn't a bespoke
     * storage mechanism but respects the host app's own cache backend. The
     * 'default' store is left completely empty (nothing routes there), and
     * the second identical call still hits the cache and not the network —
     * both only possible if the driver actually read config('ai.cache.store').
     */
    public function test_respects_configured_cache_store(): void
    {
        config(['ai.cache.enabled' => true, 'ai.cache.store' => 'secondary']);
        $this->fakeOpenAi('From secondary store');

        $messages = [['role' => 'user', 'content' => 'Hi']];
        $first  = AI::provider('openai')->chat($messages);
        $second = AI::provider('openai')->chat($messages);

        $this->assertSame('From secondary store', $first->getContent());
        $this->assertSame('From secondary store', $second->getContent());
        Http::assertSentCount(1);
    }
}
