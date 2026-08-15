<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Agent\Tool;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * AbstractDriver::run($messages, $maxSteps, $onToolCall, $onChunk) — every
 * step streams instead of a single non-streaming chat() call when $onChunk
 * is given, and a turn that requests a tool must still be detected
 * correctly. Each driver's stream handler reassembles tool_calls from that
 * provider's own real incremental format:
 *   - OpenAI:    delta.tool_calls[] fragments, keyed by index, function.name/
 *                arguments accumulated as strings (verified against OpenAI's
 *                own streaming-events API reference).
 *   - Anthropic: content_block_start (id/name complete) + content_block_delta
 *                input_json_delta.partial_json fragments, keyed by block
 *                index (verified against platform.claude.com's real
 *                "Streaming request with tool use" example).
 *   - Gemini:    a functionCall part arrives whole in a single chunk, no
 *                fragmentation (verified: Gemini streams a complete
 *                GenerateContentResponse structure per SSE event).
 *   - Ollama:    message.tool_calls arrives whole in a single NDJSON line,
 *                no fragmentation, arguments already a parsed object
 *                (verified against a real reported streaming quirk,
 *                ollama/ollama#12557 — the empty final done:true chunk
 *                pattern used below is exactly what that issue reported).
 */
class StreamingAgentTest extends TestCase
{
    private function weatherTool(?array &$invokedWith = null): Tool
    {
        return Tool::make(
            'get_weather',
            'Gets the current weather for a city.',
            ['type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city']],
            function (array $args) use (&$invokedWith) {
                $invokedWith = $args;
                return "Sunny in {$args['city']}";
            }
        );
    }

    private function sse(array $lines): string
    {
        return implode("\n", $lines) . "\n\n";
    }

    // ─── OpenAI ─────────────────────────────────────────────────

    public function test_openai_run_streams_and_still_detects_a_fragmented_tool_call(): void
    {
        $invokedWith = null;
        $streamed = '';

        $toolCallChunk = $this->sse([
            'data: ' . json_encode(['choices' => [['delta' => ['tool_calls' => [
                ['index' => 0, 'id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '']],
            ]]]]]),
            'data: ' . json_encode(['choices' => [['delta' => ['tool_calls' => [
                ['index' => 0, 'function' => ['arguments' => '{"city":']],
            ]]]]]),
            'data: ' . json_encode(['choices' => [['delta' => ['tool_calls' => [
                ['index' => 0, 'function' => ['arguments' => '"Paris"}']],
            ]]]]]),
            'data: [DONE]',
        ]);

        $finalChunk = $this->sse([
            'data: ' . json_encode(['choices' => [['delta' => ['content' => 'It is ']]]]),
            'data: ' . json_encode(['choices' => [['delta' => ['content' => 'sunny.']]]]),
            'data: [DONE]',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::sequence()->push($toolCallChunk)->push($finalChunk),
        ]);

        $response = AI::provider('openai')
            ->tools([$this->weatherTool($invokedWith)])
            ->run(
                [['role' => 'user', 'content' => 'Weather in Paris?']],
                5,
                null,
                function (string $chunk) use (&$streamed) { $streamed .= $chunk; }
            );

        $this->assertSame(['city' => 'Paris'], $invokedWith, 'The fragmented arguments ("{\"city\":" + "\"Paris\"}") must reassemble into a valid tool call.');
        $this->assertSame('It is sunny.', $response->getContent());
        $this->assertSame('It is sunny.', $streamed, 'The final answer must reach the caller via $onChunk, not just the returned response.');
    }

    // ─── Anthropic ──────────────────────────────────────────────

    public function test_anthropic_run_streams_and_still_detects_a_fragmented_tool_call(): void
    {
        $invokedWith = null;
        $streamed = '';

        $toolCallChunk = $this->sse([
            'data: ' . json_encode(['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 10]]]),
            'data: ' . json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => new \stdClass()]]),
            'data: ' . json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"city":']]),
            'data: ' . json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '"Paris"}']]),
            'data: ' . json_encode(['type' => 'content_block_stop', 'index' => 0]),
            'data: ' . json_encode(['type' => 'message_delta', 'delta' => ['stop_reason' => 'tool_use'], 'usage' => ['output_tokens' => 20]]),
            'data: ' . json_encode(['type' => 'message_stop']),
        ]);

        $finalChunk = $this->sse([
            'data: ' . json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]),
            'data: ' . json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'It is sunny.']]),
            'data: ' . json_encode(['type' => 'content_block_stop', 'index' => 0]),
            'data: ' . json_encode(['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn']]),
            'data: ' . json_encode(['type' => 'message_stop']),
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()->push($toolCallChunk)->push($finalChunk),
        ]);

        $response = AI::provider('anthropic')
            ->tools([$this->weatherTool($invokedWith)])
            ->run(
                [['role' => 'user', 'content' => 'Weather in Paris?']],
                5,
                null,
                function (string $chunk) use (&$streamed) { $streamed .= $chunk; }
            );

        $this->assertSame(['city' => 'Paris'], $invokedWith, 'The fragmented input_json_delta partials must reassemble into a valid tool call.');
        $this->assertSame('It is sunny.', $response->getContent());
        $this->assertSame('It is sunny.', $streamed);
    }

    // ─── Gemini ─────────────────────────────────────────────────

    public function test_gemini_run_streams_and_still_detects_a_whole_function_call(): void
    {
        $invokedWith = null;
        $streamed = '';

        $toolCallChunk = $this->sse([
            'data: ' . json_encode(['candidates' => [['content' => ['parts' => [
                ['functionCall' => ['name' => 'get_weather', 'args' => ['city' => 'Paris']]],
            ]]]]]),
        ]);

        $finalChunk = $this->sse([
            'data: ' . json_encode(['candidates' => [['content' => ['parts' => [['text' => 'It is ']]]]]]),
            'data: ' . json_encode(['candidates' => [['content' => ['parts' => [['text' => 'sunny.']]]]]]),
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()->push($toolCallChunk)->push($finalChunk),
        ]);

        $response = AI::provider('gemini')
            ->tools([$this->weatherTool($invokedWith)])
            ->run(
                [['role' => 'user', 'content' => 'Weather in Paris?']],
                5,
                null,
                function (string $chunk) use (&$streamed) { $streamed .= $chunk; }
            );

        $this->assertSame(['city' => 'Paris'], $invokedWith);
        $this->assertSame('It is sunny.', $response->getContent());
        $this->assertSame('It is sunny.', $streamed);
    }

    // ─── Ollama ─────────────────────────────────────────────────

    public function test_ollama_run_streams_and_still_detects_a_whole_tool_call(): void
    {
        $invokedWith = null;
        $streamed = '';

        // Matches the real quirk reported in ollama/ollama#12557: the tool
        // call arrives complete in a done:false line, followed by an empty
        // done:true line — not a fragmented, gradually-built arguments string.
        $toolCallChunk = implode("\n", [
            json_encode(['message' => ['tool_calls' => [
                ['function' => ['name' => 'get_weather', 'arguments' => ['city' => 'Paris']]],
            ]], 'done' => false]),
            json_encode(['message' => ['content' => ''], 'done' => true]),
        ]) . "\n";

        $finalChunk = implode("\n", [
            json_encode(['message' => ['content' => 'It is '], 'done' => false]),
            json_encode(['message' => ['content' => 'sunny.'], 'done' => true]),
        ]) . "\n";

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()->push($toolCallChunk)->push($finalChunk),
        ]);

        $response = AI::provider('ollama')
            ->tools([$this->weatherTool($invokedWith)])
            ->run(
                [['role' => 'user', 'content' => 'Weather in Paris?']],
                5,
                null,
                function (string $chunk) use (&$streamed) { $streamed .= $chunk; }
            );

        $this->assertSame(['city' => 'Paris'], $invokedWith);
        $this->assertSame('It is sunny.', $response->getContent());
        $this->assertSame('It is sunny.', $streamed);
    }

    // ─── Backward compatibility ─────────────────────────────────

    public function test_run_without_on_chunk_is_unaffected(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hi there.']]],
                'usage'   => ['prompt_tokens' => 3, 'completion_tokens' => 3],
            ]),
        ]);

        $response = AI::provider('openai')->run([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('Hi there.', $response->getContent());
        Http::assertSent(fn ($request) => ($request->data()['stream'] ?? null) === false);
    }
}
