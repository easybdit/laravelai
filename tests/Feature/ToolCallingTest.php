<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Agent\Tool;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Covers the agent-loop (tool/function calling) pattern for OpenAI,
 * Anthropic, and Ollama — parsing a provider's tool call into a ToolCall,
 * running AbstractDriver::run()'s round-trip loop end to end, respecting
 * maxSteps, and tolerating an unknown tool name gracefully.
 */
class ToolCallingTest extends TestCase
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

    // ─── OpenAI ─────────────────────────────────────────────────

    public function test_openai_tool_call_is_parsed_correctly(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => null,
                        'tool_calls' => [[
                            'id'       => 'call_abc123',
                            'type'     => 'function',
                            'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                'model' => 'gpt-4o-mini',
            ]),
        ]);

        $response = AI::provider('openai')
            ->tools([$this->weatherTool()])
            ->chat([['role' => 'user', 'content' => 'Weather in Paris?']]);

        $this->assertTrue($response->hasToolCalls());
        $calls = $response->getToolCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('call_abc123', $calls[0]->id);
        $this->assertSame('get_weather', $calls[0]->name);
        $this->assertSame(['city' => 'Paris'], $calls[0]->arguments);
    }

    public function test_openai_run_executes_tool_and_returns_final_answer(): void
    {
        $invokedWith = null;

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role'       => 'assistant',
                            'content'    => null,
                            'tool_calls' => [[
                                'id'       => 'call_1',
                                'type'     => 'function',
                                'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                            ]],
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'It is sunny in Paris.']]],
                    'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                ]),
        ]);

        $response = AI::provider('openai')
            ->tools([$this->weatherTool($invokedWith)])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']]);

        $this->assertSame('It is sunny in Paris.', $response->getContent());
        $this->assertSame(['city' => 'Paris'], $invokedWith);
        Http::assertSentCount(2);
    }

    public function test_openai_run_respects_max_steps_without_throwing(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => null,
                        'tool_calls' => [[
                            'id'       => 'call_1',
                            'type'     => 'function',
                            'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ]),
        ]);

        $response = AI::provider('openai')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']], 2);

        $this->assertTrue($response->hasToolCalls());
        Http::assertSentCount(2);
    }

    public function test_openai_unknown_tool_name_does_not_throw(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role'       => 'assistant',
                            'content'    => null,
                            'tool_calls' => [[
                                'id'       => 'call_1',
                                'type'     => 'function',
                                'function' => ['name' => 'not_a_real_tool', 'arguments' => '{}'],
                            ]],
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'Okay, done.']]],
                    'usage'   => ['prompt_tokens' => 5, 'completion_tokens' => 5],
                ]),
        ]);

        $response = AI::provider('openai')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Do the thing.']]);

        $this->assertSame('Okay, done.', $response->getContent());
    }

    // ─── Anthropic ──────────────────────────────────────────────

    public function test_anthropic_tool_call_is_parsed_correctly(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'tool_use', 'id' => 'toolu_123', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
                'model' => 'claude-sonnet-4-20250514',
            ]),
        ]);

        $response = AI::provider('anthropic')
            ->tools([$this->weatherTool()])
            ->chat([['role' => 'user', 'content' => 'Weather in Paris?']]);

        $this->assertTrue($response->hasToolCalls());
        $calls = $response->getToolCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('toolu_123', $calls[0]->id);
        $this->assertSame('get_weather', $calls[0]->name);
        $this->assertSame(['city' => 'Paris'], $calls[0]->arguments);
    }

    public function test_anthropic_run_executes_tool_and_returns_final_answer(): void
    {
        $invokedWith = null;

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [
                        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
                    ],
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'It is sunny in Paris.']],
                    'usage'   => ['input_tokens' => 5, 'output_tokens' => 5],
                ]),
        ]);

        $response = AI::provider('anthropic')
            ->tools([$this->weatherTool($invokedWith)])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']]);

        $this->assertSame('It is sunny in Paris.', $response->getContent());
        $this->assertSame(['city' => 'Paris'], $invokedWith);
        Http::assertSentCount(2);
    }

    public function test_anthropic_run_respects_max_steps_without_throwing(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
            ]),
        ]);

        $response = AI::provider('anthropic')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']], 2);

        $this->assertTrue($response->hasToolCalls());
        Http::assertSentCount(2);
    }

    public function test_anthropic_unknown_tool_name_does_not_throw(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [
                        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'not_a_real_tool', 'input' => []],
                    ],
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'Okay, done.']],
                    'usage'   => ['input_tokens' => 5, 'output_tokens' => 5],
                ]),
        ]);

        $response = AI::provider('anthropic')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Do the thing.']]);

        $this->assertSame('Okay, done.', $response->getContent());
    }

    /**
     * Directly covers the MessageFormatter concern: tool_use/tool_result
     * content blocks are not text/image blocks, so toProviderContent()'s
     * translation loop must pass them through unchanged rather than
     * silently dropping them, and normalize()'s consecutive-same-role
     * merge must not attempt to string-concatenate array content.
     */
    public function test_anthropic_tool_result_message_reaches_the_api_unmangled(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [
                        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
                    ],
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'It is sunny in Paris.']],
                    'usage'   => ['input_tokens' => 5, 'output_tokens' => 5],
                ]),
        ]);

        AI::provider('anthropic')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $messages = $body['messages'] ?? [];

            // Find the assistant tool_use turn and the following user
            // tool_result turn — both should have survived intact as
            // arrays of content blocks, not been dropped or string-merged.
            $assistantToolUse = null;
            $userToolResult = null;
            foreach ($messages as $msg) {
                if (($msg['role'] ?? null) === 'assistant' && is_array($msg['content'] ?? null)) {
                    foreach ($msg['content'] as $block) {
                        if (($block['type'] ?? null) === 'tool_use') {
                            $assistantToolUse = $block;
                        }
                    }
                }
                if (($msg['role'] ?? null) === 'user' && is_array($msg['content'] ?? null)) {
                    foreach ($msg['content'] as $block) {
                        if (($block['type'] ?? null) === 'tool_result') {
                            $userToolResult = $block;
                        }
                    }
                }
            }

            if ($assistantToolUse === null || $userToolResult === null) {
                return false;
            }

            return $assistantToolUse['id'] === 'toolu_1'
                && $assistantToolUse['name'] === 'get_weather'
                && $userToolResult['tool_use_id'] === 'toolu_1'
                && str_contains((string) $userToolResult['content'], 'Sunny in Paris');
        });
    }

    // ─── Ollama ─────────────────────────────────────────────────

    public function test_ollama_tool_call_is_parsed_correctly(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'role'       => 'assistant',
                    'content'    => '',
                    'tool_calls' => [
                        ['function' => ['name' => 'get_weather', 'arguments' => ['city' => 'Paris']]],
                    ],
                ],
                'done' => true,
            ]),
        ]);

        $response = AI::provider('ollama')
            ->tools([$this->weatherTool()])
            ->chat([['role' => 'user', 'content' => 'Weather in Paris?']]);

        $this->assertTrue($response->hasToolCalls());
        $calls = $response->getToolCalls();
        $this->assertCount(1, $calls);
        $this->assertNull($calls[0]->id);
        $this->assertSame('get_weather', $calls[0]->name);
        $this->assertSame(['city' => 'Paris'], $calls[0]->arguments);
    }

    public function test_ollama_run_executes_tool_and_returns_final_answer(): void
    {
        $invokedWith = null;

        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push([
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => '',
                        'tool_calls' => [
                            ['function' => ['name' => 'get_weather', 'arguments' => ['city' => 'Paris']]],
                        ],
                    ],
                    'done' => true,
                ])
                ->push([
                    'message' => ['role' => 'assistant', 'content' => 'It is sunny in Paris.'],
                    'done'    => true,
                ]),
        ]);

        $response = AI::provider('ollama')
            ->tools([$this->weatherTool($invokedWith)])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']]);

        $this->assertSame('It is sunny in Paris.', $response->getContent());
        $this->assertSame(['city' => 'Paris'], $invokedWith);
        Http::assertSentCount(2);
    }

    public function test_ollama_run_respects_max_steps_without_throwing(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'role'       => 'assistant',
                    'content'    => '',
                    'tool_calls' => [
                        ['function' => ['name' => 'get_weather', 'arguments' => ['city' => 'Paris']]],
                    ],
                ],
                'done' => true,
            ]),
        ]);

        $response = AI::provider('ollama')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Weather in Paris?']], 2);

        $this->assertTrue($response->hasToolCalls());
        Http::assertSentCount(2);
    }

    public function test_ollama_unknown_tool_name_does_not_throw(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push([
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => '',
                        'tool_calls' => [
                            ['function' => ['name' => 'not_a_real_tool', 'arguments' => []]],
                        ],
                    ],
                    'done' => true,
                ])
                ->push([
                    'message' => ['role' => 'assistant', 'content' => 'Okay, done.'],
                    'done'    => true,
                ]),
        ]);

        $response = AI::provider('ollama')
            ->tools([$this->weatherTool()])
            ->run([['role' => 'user', 'content' => 'Do the thing.']]);

        $this->assertSame('Okay, done.', $response->getContent());
    }
}
