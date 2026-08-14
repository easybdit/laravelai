<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * config('ai.retry.*') / ->retries() — opt-in, off by default (times=0),
 * same "nobody's behavior changes on upgrade" pattern as every other
 * cross-cutting feature this package ships (caching, queued ingestion,
 * etc.). Every test here passes an explicit small sleep via ->retries() to
 * keep the suite fast rather than waiting out the real default backoff.
 */
class RetryTest extends TestCase
{
    public function test_retries_are_off_by_default_single_attempt_on_failure(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'server error'], 500)]);

        $this->expectException(ProviderException::class);

        try {
            AI::provider('openai')->chat([['role' => 'user', 'content' => 'Hi']]);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_retries_on_transient_500_then_succeeds(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => 'server error'], 500)
                ->push(['choices' => [['message' => ['content' => 'Hello!']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]], 200),
        ]);

        $response = AI::provider('openai')->retries(2, 1)->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('Hello!', $response->getContent());
        Http::assertSentCount(2);
    }

    public function test_does_not_retry_a_non_retryable_client_error(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'bad request'], 400)]);

        try {
            AI::provider('openai')->retries(3, 1)->chat([['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected a ProviderException.');
        } catch (ProviderException) {
            // A 400 will never succeed on retry — only the first attempt
            // should have been sent, not 4.
            Http::assertSentCount(1);
        }
    }

    public function test_retries_exhausted_still_raises_the_normal_provider_exception(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'still down'], 503)]);

        try {
            AI::provider('openai')->retries(3, 1)->chat([['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected a ProviderException.');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
            // ->retries(3, ...) is 3 total attempts (Laravel's own Http::retry()
            // semantics), not 3 retries on top of a first attempt.
            Http::assertSentCount(3);
        }
    }

    public function test_config_default_retry_times_is_honored_without_a_per_call_override(): void
    {
        config(['ai.retry.times' => 2, 'ai.retry.sleep' => 1]);

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => 'server error'], 500)
                ->push(['choices' => [['message' => ['content' => 'ok']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]], 200),
        ]);

        $response = AI::provider('openai')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('ok', $response->getContent());
        Http::assertSentCount(2);
    }

    public function test_streaming_never_retries_even_when_configured(): void
    {
        // A streaming request that fails outright (connection refused/non-200
        // before any bytes arrive) must not be retried — see config('ai.retry')'s
        // docblock for why. Faking a plain non-stream response here is enough
        // to prove only one attempt was made; handleStream() never calls
        // withRetry() at all.
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'server error'], 500)]);

        try {
            AI::provider('openai')->retries(3, 1)->stream(
                [['role' => 'user', 'content' => 'Hi']],
                function () {}
            );
        } catch (\Throwable) {
            // The stream path doesn't raise ProviderException the same way
            // (it reads the body directly) — only the attempt count matters.
        }

        Http::assertSentCount(1);
    }

    public function test_connection_failure_still_wraps_into_package_connection_exception_after_retries(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->expectException(\EasyAI\LaravelAI\Exceptions\ConnectionException::class);

        AI::provider('openai')->retries(1, 1)->chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_ollama_and_gemini_also_retry_transient_failures(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(['error' => 'server error'], 500)
                ->push(['message' => ['content' => 'hi from ollama'], 'done' => true], 200),
        ]);
        $ollama = AI::provider('ollama')->retries(2, 1)->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertSame('hi from ollama', $ollama->getContent());

        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::sequence()
                ->push(['error' => 'server error'], 503)
                ->push(['candidates' => [['content' => ['parts' => [['text' => 'hi from gemini']]]]]], 200),
        ]);
        $gemini = AI::provider('gemini')->retries(2, 1)->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertSame('hi from gemini', $gemini->getContent());
    }
}
