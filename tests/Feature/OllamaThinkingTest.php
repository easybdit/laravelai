<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Reasoning models (qwen3, etc.) stream a separate "thinking" field ahead
 * of "content", often for many seconds with nothing visible at all. This
 * covers both ends of the fix: the ->think() request toggle, and the
 * stream callback actually receiving thinking chunks tagged distinctly
 * from real content.
 */
class OllamaThinkingTest extends TestCase
{
    public function test_think_true_is_sent_in_the_request_body(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done'    => true,
            ]),
        ]);

        AI::provider('ollama')->think(true)->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(fn ($request) => ($request->data()['think'] ?? null) === true);
    }

    public function test_think_omitted_by_default(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done'    => true,
            ]),
        ]);

        AI::provider('ollama')->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(fn ($request) => !array_key_exists('think', $request->data()));
    }

    public function test_stream_forwards_thinking_and_content_as_distinct_chunk_types(): void
    {
        $ndjson = implode("\n", [
            json_encode(['message' => ['role' => 'assistant', 'thinking' => 'Let me consider... ']]),
            json_encode(['message' => ['role' => 'assistant', 'thinking' => 'okay, done reasoning.']]),
            json_encode(['message' => ['role' => 'assistant', 'content' => 'Hello']]),
            json_encode(['message' => ['role' => 'assistant', 'content' => ' there!'], 'done' => true, 'eval_count' => 5]),
        ]) . "\n";

        Http::fake(['127.0.0.1:11434/api/chat' => Http::response($ndjson)]);

        $thinking = '';
        $content  = '';

        $response = AI::provider('ollama')->stream(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk, string $type = 'content') use (&$thinking, &$content) {
                if ($type === 'thinking') {
                    $thinking .= $chunk;
                } else {
                    $content .= $chunk;
                }
            }
        );

        $this->assertSame('Let me consider... okay, done reasoning.', $thinking);
        $this->assertSame('Hello there!', $content);
        // The final AIResponse content must be the real answer only — thinking never leaks into it.
        $this->assertSame('Hello there!', $response->getContent());
    }

    public function test_stream_callback_without_second_parameter_still_works(): void
    {
        $ndjson = json_encode(['message' => ['thinking' => 'hmm'], 'done' => false]) . "\n"
            . json_encode(['message' => ['content' => 'Hi!'], 'done' => true]) . "\n";

        Http::fake(['127.0.0.1:11434/api/chat' => Http::response($ndjson)]);

        $received = '';

        // Legacy-shaped single-parameter callback — must never receive
        // thinking chunks at all (not just "not error"), or raw reasoning
        // text would silently leak into whatever the caller does with it.
        AI::provider('ollama')->stream(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk) use (&$received) { $received .= $chunk; }
        );

        $this->assertSame('Hi!', $received);
    }
}
