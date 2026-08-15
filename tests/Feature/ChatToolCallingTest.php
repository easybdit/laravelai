<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Covers config('ai.chat.tools_enabled') — wiring the agent module's
 * tool/function calling into the built-in /ai-chat SSE stream (v2.7.0).
 * Since v2.14 the final answer streams token-by-token here too (see
 * StreamingAgentTest.php for the underlying AbstractDriver::run()
 * $onChunk coverage across all 4 providers) — this file's own
 * test_enabled_tools_final_answer_streams_token_by_token() locks that in
 * at the /ai-chat/api/stream endpoint level specifically.
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

        $this->assertDatabaseHas('ai_chat_messages', [
            'chat_session_id' => $session->id,
            'role'            => 'assistant',
            'content'         => 'Here is the latest Laravel news.',
        ]);
    }

    /**
     * Locks in the fix itself: before AbstractDriver::run()'s $onChunk
     * param existed, a tool-using reply's final answer arrived as exactly
     * one "data: {"text": "..."}" event (the whole thing, only after run()
     * finished completely) — now it arrives as several, one per streamed
     * chunk, same as the plain (no tools) streaming path always has.
     */
    public function test_enabled_tools_final_answer_streams_token_by_token(): void
    {
        config([
            'ai.chat.tools_enabled' => true,
            'ai.chat.enabled_tools' => ['web_search'],
        ]);

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push(implode("\n", [
                    json_encode(['message' => ['tool_calls' => [
                        ['function' => ['name' => 'web_search', 'arguments' => ['query' => 'laravel news']]],
                    ]], 'done' => false]),
                    json_encode(['message' => ['content' => ''], 'done' => true]),
                ]) . "\n")
                ->push(implode("\n", [
                    json_encode(['message' => ['content' => 'Here '], 'done' => false]),
                    json_encode(['message' => ['content' => 'is '], 'done' => false]),
                    json_encode(['message' => ['content' => 'the news.'], 'done' => true]),
                ]) . "\n"),
        ]);

        $session = ChatSession::create(['title' => 'Existing Session']);

        $response = $this->call('POST', '/ai-chat/api/stream', [
            'message'    => 'Any Laravel news?',
            'session_id' => $session->id,
        ], [], [], ['HTTP_ACCEPT' => 'text/event-stream']);

        $response->assertOk();
        $content = $response->streamedContent();

        $textEvents = array_filter(
            explode("\n\n", $content),
            fn ($event) => str_starts_with($event, 'data: ') && str_contains($event, '"text"')
        );

        $this->assertGreaterThanOrEqual(3, count($textEvents), 'The final answer should arrive as several separate text events, not one.');
        $this->assertDatabaseHas('ai_chat_messages', [
            'chat_session_id' => $session->id,
            'role'            => 'assistant',
            'content'         => 'Here is the news.',
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
