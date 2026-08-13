<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end check that the new migrations, identity scoping, security
 * checks, and streaming controller all actually wire together against a
 * real (sqlite, in-memory) database — the unit tests exercise the pieces
 * in isolation, this exercises the seams between them.
 */
class ChatFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_no_sessions(): void
    {
        $this->get('/ai-chat')->assertOk();
    }

    public function test_new_session_creates_a_guest_scoped_session(): void
    {
        $response = $this->postJson('/ai-chat/api/sessions', []);
        $response->assertOk();
        $this->assertDatabaseHas('chat_sessions', ['id' => $response->json('session.id')]);

        $session = ChatSession::first();
        $this->assertNull($session->user_id);
        $this->assertNotNull($session->guest_token);
    }

    public function test_stream_persists_messages_and_returns_sse(): void
    {
        // Two entries, not one: this is the first message in the session, so
        // the controller makes two separate HTTP calls (auto-title, then the
        // real reply) against the same cached driver instance. Http::fake()
        // reuses one response/stream object per matched call — reading a
        // streamed body once exhausts it — so each call needs its own
        // sequence entry or the second (real) call sees an already-EOF
        // stream. Real Ollama requests don't share this limitation.
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(['message' => ['role' => 'assistant', 'content' => 'A Short Title'], 'done' => true])
                ->push(['message' => ['role' => 'assistant', 'content' => 'Hello there!'], 'model' => 'llama3.1:8b', 'done' => true]),
        ]);

        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Hi there',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Hello there!', $content);
        $this->assertStringContainsString('[DONE]', $content);
        $this->assertDatabaseHas('chat_messages', ['chat_session_id' => $session->id, 'role' => 'user']);
        $this->assertDatabaseHas('chat_messages', ['chat_session_id' => $session->id, 'role' => 'assistant']);
    }

    public function test_stream_blocks_message_over_configured_length(): void
    {
        // 50 is a hard floor enforced by ChatGuard regardless of config —
        // mirrors the WordPress plugin's own minimum — so the test message
        // must clear that floor to actually exercise the configured limit.
        config(['ai.chat.max_message_length' => 60]);
        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->postJson('/ai-chat/api/stream', [
            'message'    => str_repeat('a', 100),
            'session_id' => $session->id,
        ]);

        $response->assertStatus(400)->assertJsonStructure(['error']);
        $this->assertDatabaseMissing('chat_messages', ['chat_session_id' => $session->id]);
    }

    public function test_feedback_persists_rating(): void
    {
        $session = ChatSession::create(['title' => 'New Chat']);
        $message = ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => 'Hi']);

        $response = $this->postJson("/ai-chat/api/messages/{$message->id}/feedback", ['rating' => 1]);

        $response->assertOk();
        $this->assertDatabaseHas('chat_messages', ['id' => $message->id, 'rating' => 1]);
    }

    public function test_ip_blocklist_rejects_the_request(): void
    {
        config(['ai.chat.ip_blocklist' => ['127.0.0.1']]);
        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->postJson('/ai-chat/api/stream', [
            'message'    => 'Hi',
            'session_id' => $session->id,
        ]);

        $response->assertStatus(403);
    }
}
