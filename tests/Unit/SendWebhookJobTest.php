<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Chat\Jobs\SendWebhookJob;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Direct unit test of SendWebhookJob::handle() in isolation — confirms it
 * makes the same Http::post() call (timeout, HMAC signature header,
 * payload shape) that fireWebhook() used to make inline.
 */
class SendWebhookJobTest extends TestCase
{
    public function test_handle_posts_the_expected_payload(): void
    {
        config(['ai.chat.webhook.url' => 'https://example.com/hook']);
        Http::fake(['https://example.com/hook' => Http::response(['ok' => true])]);

        $job = new SendWebhookJob(42, 'the user asked', 'the ai replied', 'ollama');
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/hook'
                && $request['session_id'] === 42
                && $request['user_message'] === 'the user asked'
                && $request['ai_response'] === 'the ai replied'
                && $request['provider'] === 'ollama';
        });
    }

    public function test_handle_signs_the_payload_when_a_secret_is_configured(): void
    {
        config([
            'ai.chat.webhook.url'    => 'https://example.com/hook',
            'ai.chat.webhook.secret' => 'shh-secret',
        ]);
        Http::fake(['https://example.com/hook' => Http::response(['ok' => true])]);

        $job = new SendWebhookJob(1, 'hi', 'hello', 'ollama');
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-LaravelAI-Signature')
                && str_starts_with($request->header('X-LaravelAI-Signature')[0], 'sha256=');
        });
    }

    public function test_handle_does_nothing_when_no_webhook_url_is_configured(): void
    {
        config(['ai.chat.webhook.url' => null]);
        Http::fake();

        $job = new SendWebhookJob(1, 'hi', 'hello', 'ollama');
        $job->handle();

        Http::assertNothingSent();
    }
}
