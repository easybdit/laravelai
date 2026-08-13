<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Extends OllamaThinkingTest's coverage to Anthropic (extended thinking)
 * and Gemini (thinkingConfig) — same "thinking" vs "content" chunk-type
 * contract, same arity-safety guarantee for legacy single-parameter
 * callbacks.
 */
class MultiProviderThinkingTest extends TestCase
{
    // ─── Anthropic ─────────────────────────────────────────────

    public function test_anthropic_think_true_sends_thinking_block_and_bumps_max_tokens(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage'   => ['input_tokens' => 1, 'output_tokens' => 1],
            ]),
        ]);

        AI::provider('anthropic')->think(true)->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['thinking']['type'] ?? null) === 'enabled'
                && $body['thinking']['budget_tokens'] === 10000
                && $body['max_tokens'] > $body['thinking']['budget_tokens']
                && !array_key_exists('temperature', $body);
        });
    }

    public function test_anthropic_stream_forwards_thinking_and_text_deltas_distinctly(): void
    {
        $events = [
            ['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 3]]],
            ['type' => 'content_block_delta', 'delta' => ['type' => 'thinking_delta', 'thinking' => 'Considering the question... ']],
            ['type' => 'content_block_delta', 'delta' => ['type' => 'thinking_delta', 'thinking' => 'seems straightforward.']],
            ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hello']],
            ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => ' there!']],
            ['type' => 'message_delta', 'usage' => ['output_tokens' => 5]],
        ];
        $sse = collect($events)->map(fn ($e) => 'data: ' . json_encode($e))->join("\n\n") . "\n\n";

        Http::fake(['api.anthropic.com/*' => Http::response($sse)]);

        $thinking = '';
        $content  = '';

        $response = AI::provider('anthropic')->think(true)->stream(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk, string $type = 'content') use (&$thinking, &$content) {
                $type === 'thinking' ? $thinking .= $chunk : $content .= $chunk;
            }
        );

        $this->assertSame('Considering the question... seems straightforward.', $thinking);
        $this->assertSame('Hello there!', $content);
        $this->assertSame('Hello there!', $response->getContent());
    }

    public function test_anthropic_legacy_single_param_callback_never_receives_thinking(): void
    {
        $events = [
            ['type' => 'content_block_delta', 'delta' => ['type' => 'thinking_delta', 'thinking' => 'hmm ']],
            ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Hi!']],
        ];
        $sse = collect($events)->map(fn ($e) => 'data: ' . json_encode($e))->join("\n\n") . "\n\n";

        Http::fake(['api.anthropic.com/*' => Http::response($sse)]);

        $received = '';
        AI::provider('anthropic')->think(true)->stream(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk) use (&$received) { $received .= $chunk; }
        );

        $this->assertSame('Hi!', $received);
    }

    // ─── Gemini ─────────────────────────────────────────────────

    public function test_gemini_think_true_sends_thinking_config(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ]),
        ]);

        AI::provider('gemini')->think(true)->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(fn ($request) => ($request->data()['generationConfig']['thinkingConfig']['includeThoughts'] ?? null) === true);
    }

    public function test_gemini_non_streaming_excludes_thought_parts_from_content(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [
                    ['text' => 'reasoning about it...', 'thought' => true],
                    ['text' => 'Hello there!'],
                ]]]],
            ]),
        ]);

        $response = AI::provider('gemini')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('Hello there!', $response->getContent());
    }

    public function test_gemini_stream_routes_thought_parts_as_thinking_chunks(): void
    {
        $events = [
            ['candidates' => [['content' => ['parts' => [['text' => 'let me think... ', 'thought' => true]]]]]],
            ['candidates' => [['content' => ['parts' => [['text' => 'Hello', 'thought' => false]]]]]],
            ['candidates' => [['content' => ['parts' => [['text' => ' there!']]]]]],
        ];
        $sse = collect($events)->map(fn ($e) => 'data: ' . json_encode($e))->join("\n\n") . "\n\n";

        Http::fake(['*generativelanguage.googleapis.com*' => Http::response($sse)]);

        $thinking = '';
        $content  = '';

        $response = AI::provider('gemini')->think(true)->stream(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk, string $type = 'content') use (&$thinking, &$content) {
                $type === 'thinking' ? $thinking .= $chunk : $content .= $chunk;
            }
        );

        $this->assertSame('let me think... ', $thinking);
        $this->assertSame('Hello there!', $content);
        $this->assertSame('Hello there!', $response->getContent());
    }
}
