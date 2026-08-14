<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * ->format('json' | $schema) — every provider translates it into its own
 * native structured-output mechanism (OpenAI response_format, Gemini
 * responseSchema, Anthropic a forced tool call, Ollama's native format
 * param) but the call site and the resulting ->getStructuredData() /
 * ->hasStructuredData() contract on AIResponse is identical everywhere.
 */
class StructuredOutputTest extends TestCase
{
    private const SCHEMA = [
        'type'       => 'object',
        'properties' => ['city' => ['type' => 'string'], 'temp_c' => ['type' => 'number']],
        'required'   => ['city', 'temp_c'],
    ];

    // ─── No ->format(): never invents structured data ─────────────

    public function test_plain_chat_without_format_has_no_structured_data_even_if_content_looks_like_json(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"city":"Paris","temp_c":22}']]],
                'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ]),
        ]);

        $response = AI::provider('openai')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertFalse($response->hasStructuredData());
        $this->assertNull($response->getStructuredData());
    }

    // ─── OpenAI (and DeepSeek/Groq/Together/Custom inherit this) ──

    public function test_openai_format_json_sends_json_object_response_format(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
                'usage'   => ['prompt_tokens' => 3, 'completion_tokens' => 3],
            ]),
        ]);

        AI::provider('openai')->format('json')->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(fn ($request) => ($request->data()['response_format'] ?? null) === ['type' => 'json_object']);
    }

    public function test_openai_format_schema_sends_json_schema_and_decodes_structured_data(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"city":"Paris","temp_c":22}']]],
                'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ]),
        ]);

        $response = AI::provider('openai')->format(self::SCHEMA)->chat([['role' => 'user', 'content' => 'Weather in Paris?']]);

        Http::assertSent(function ($request) {
            $format = $request->data()['response_format'] ?? [];
            return $format['type'] === 'json_schema'
                && $format['json_schema']['strict'] === true
                && $format['json_schema']['schema'] === self::SCHEMA;
        });

        $this->assertTrue($response->hasStructuredData());
        $this->assertSame(['city' => 'Paris', 'temp_c' => 22], $response->getStructuredData());
    }

    public function test_groq_inherits_structured_output_from_openai_driver(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"city":"Dhaka","temp_c":31}']]],
                'usage'   => ['prompt_tokens' => 4, 'completion_tokens' => 4],
            ]),
        ]);

        AI::provider('groq')->format(self::SCHEMA)->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(fn ($request) => ($request->data()['response_format']['type'] ?? null) === 'json_schema');
    }

    // ─── Gemini ─────────────────────────────────────────────────

    public function test_gemini_format_schema_sends_response_schema_and_decodes_structured_data(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"city":"Tokyo","temp_c":18}']]]]],
            ]),
        ]);

        $response = AI::provider('gemini')->format(self::SCHEMA)->chat([['role' => 'user', 'content' => 'Weather in Tokyo?']]);

        Http::assertSent(function ($request) {
            $config = $request->data()['generationConfig'] ?? [];
            return $config['responseMimeType'] === 'application/json'
                && $config['responseSchema'] === self::SCHEMA;
        });

        $this->assertSame(['city' => 'Tokyo', 'temp_c' => 18], $response->getStructuredData());
    }

    public function test_gemini_format_json_omits_schema_but_sets_mime_type(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]],
            ]),
        ]);

        AI::provider('gemini')->format('json')->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(function ($request) {
            $config = $request->data()['generationConfig'] ?? [];
            return $config['responseMimeType'] === 'application/json' && !array_key_exists('responseSchema', $config);
        });
    }

    // ─── Ollama ─────────────────────────────────────────────────

    public function test_ollama_format_schema_is_passed_through_and_decoded(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message'           => ['content' => '{"city":"Rome","temp_c":25}'],
                'prompt_eval_count' => 4,
                'eval_count'        => 4,
                'done'              => true,
            ]),
        ]);

        $response = AI::provider('ollama')->format(self::SCHEMA)->chat([['role' => 'user', 'content' => 'Weather in Rome?']]);

        Http::assertSent(fn ($request) => ($request->data()['format'] ?? null) === self::SCHEMA);
        $this->assertSame(['city' => 'Rome', 'temp_c' => 25], $response->getStructuredData());
    }

    // ─── Anthropic (forced tool call — no native JSON mode) ───────

    public function test_anthropic_format_schema_forces_a_synthetic_tool_call(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'tool_use', 'name' => '__laravelai_structured_response', 'input' => ['city' => 'Berlin', 'temp_c' => 15]]],
                'usage'   => ['input_tokens' => 5, 'output_tokens' => 5],
            ]),
        ]);

        $response = AI::provider('anthropic')->format(self::SCHEMA)->chat([['role' => 'user', 'content' => 'Weather in Berlin?']]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ($body['tool_choice'] ?? null) === ['type' => 'tool', 'name' => '__laravelai_structured_response']
                && $body['tools'][0]['input_schema'] === self::SCHEMA;
        });

        $this->assertSame(['city' => 'Berlin', 'temp_c' => 15], $response->getStructuredData());
        // The synthetic tool call is internal plumbing, not a real tool call.
        $this->assertFalse($response->hasToolCalls());
        $this->assertSame('{"city":"Berlin","temp_c":15}', $response->getContent());
    }

    public function test_anthropic_format_json_without_schema_forces_a_bare_object_tool(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'tool_use', 'name' => '__laravelai_structured_response', 'input' => ['ok' => true]]],
                'usage'   => ['input_tokens' => 3, 'output_tokens' => 3],
            ]),
        ]);

        AI::provider('anthropic')->format('json')->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(fn ($request) => ($request->data()['tools'][0]['input_schema'] ?? null) === ['type' => 'object']);
    }

    public function test_anthropic_format_with_stream_throws_instead_of_silently_returning_empty(): void
    {
        $this->expectException(\BadMethodCallException::class);

        AI::provider('anthropic')->format(self::SCHEMA)->stream(
            [['role' => 'user', 'content' => 'Hi']],
            function () {}
        );
    }
}
