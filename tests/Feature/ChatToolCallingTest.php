<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Covers config('ai.chat.tools_enabled') — wiring the agent module's
 * tool/function calling into the built-in /ai-chat SSE stream (v2.7.0).
 * See config('ai.chat.tools_enabled')'s docblock for the accepted
 * non-streaming tradeoff when a request actually uses a tool.
 */
class ChatToolCallingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The most important property, as always: with tools_enabled left at
     * its default (false), the endpoint behaves exactly as it did before
     * this feature existed — plain token-by-token streaming, no tool_call
     * event ever appears.
     */
    public function test_tools_disabled_by_default_behaves_exactly_like_before(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(['message' => ['role' => 'assistant', 'content' => 'A Short Title'], 'done' => true])
                ->push(['message' => ['role' => 'assistant', 'content' => 'Hello there!'], 'done' => true]),
        ]);

        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Hi there',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringNotContainsString('tool_call', $content);
        $this->assertStringContainsString('Hello there!', $content);
        $this->assertStringContainsString('[DONE]', $content);
    }

    public function test_enabled_tools_streams_a_tool_call_event_then_the_final_answer(): void
    {
        config([
            'ai.chat.tools_enabled' => true,
            'ai.chat.enabled_tools' => ['web_search'],
        ]);

        // No Tavily API key configured in tests, so WebSearchTool's handler
        // returns "no results" locally without an outbound HTTP call —
        // only the Ollama chat endpoint needs faking here.
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push([
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => '',
                        'tool_calls' => [
                            ['function' => ['name' => 'web_search', 'arguments' => ['query' => 'laravel news']]],
                        ],
                    ],
                    'done' => true,
                ])
                ->push([
                    'message' => ['role' => 'assistant', 'content' => 'Here is the latest Laravel news.'],
                    'done'    => true,
                ]),
        ]);

        // Title != 'New Chat' so this doesn't also trigger the separate
        // auto-title HTTP call the first-message path makes — keeps this
        // test's fake sequence to exactly the two calls run() will make.
        $session = ChatSession::create(['title' => 'Existing Session']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Any Laravel news?',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('"tool_call"', $content);
        $this->assertStringContainsString('web_search', $content);
        $this->assertStringContainsString('Here is the latest Laravel news.', $content);
        $this->assertStringContainsString('[DONE]', $content);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role'            => 'assistant',
            'content'         => 'Here is the latest Laravel news.',
        ]);
    }

    /**
     * A typo/unknown name in ai.chat.enabled_tools shouldn't break chat —
     * it's silently skipped, leaving an empty tool list, which falls back
     * to the plain streaming path exactly as if tools_enabled were off.
     */
    public function test_unknown_tool_names_are_skipped_not_fatal(): void
    {
        config([
            'ai.chat.tools_enabled' => true,
            'ai.chat.enabled_tools' => ['not_a_real_tool'],
        ]);

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(['message' => ['role' => 'assistant', 'content' => 'A Short Title'], 'done' => true])
                ->push(['message' => ['role' => 'assistant', 'content' => 'Hello there!'], 'done' => true]),
        ]);

        $session = ChatSession::create(['title' => 'New Chat']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Hi',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringNotContainsString('tool_call', $content);
        $this->assertStringContainsString('Hello there!', $content);
    }
}
