<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Jobs\SendWebhookJob;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * fireWebhook() previously always made its Http::post() call synchronously
 * inside the SSE streaming closure, delaying the browser's [DONE] event by
 * however long the webhook endpoint took to respond even though its result
 * is never shown to the user. These cover both the unchanged synchronous
 * default and the new opt-in queued path.
 */
class QueuedWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function fakeChatResponse(): void
    {
        // First message in the session -> auto-title call, then the real reply.
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(['message' => ['role' => 'assistant', 'content' => 'A Short Title'], 'done' => true])
                ->push(['message' => ['role' => 'assistant', 'content' => 'Hello there!'], 'model' => 'llama3.1:8b', 'done' => true]),
            'https://example.com/hook' => Http::response(['ok' => true]),
        ]);
    }

    public function test_default_behavior_sends_the_webhook_synchronously(): void
    {
        config([
            'ai.chat.webhook.url'   => 'https://example.com/hook',
            'ai.chat.webhook.queue' => false,
        ]);
        $this->fakeChatResponse();

        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Hi there',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('[DONE]', $content);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/hook'
                && $request['ai_response'] === 'Hello there!';
        });
    }

    public function test_queued_config_dispatches_the_job_instead_of_calling_http_directly(): void
    {
        config([
            'ai.chat.webhook.url'   => 'https://example.com/hook',
            'ai.chat.webhook.queue' => true,
        ]);
        Queue::fake();
        $this->fakeChatResponse();

        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Hi there',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('[DONE]', $content);

        Queue::assertPushed(SendWebhookJob::class, function (SendWebhookJob $job) use ($session) {
            return $job->sessionId === $session->id
                && $job->userMessage === 'Hi there'
                && $job->aiReply === 'Hello there!'
                && $job->provider === 'ollama';
        });

        Http::assertNotSent(function ($request) {
            return $request->url() === 'https://example.com/hook';
        });
    }
}
