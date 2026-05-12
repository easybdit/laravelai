<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class DriverTest extends TestCase
{
    public function test_manager_resolves_default_driver(): void
    {
        $driver = AI::provider();
        $this->assertEquals('ollama', $driver->getProviderName());
    }

    public function test_manager_resolves_named_driver(): void
    {
        $this->assertEquals('openai', AI::provider('openai')->getProviderName());
        $this->assertEquals('anthropic', AI::provider('anthropic')->getProviderName());
        $this->assertEquals('deepseek', AI::provider('deepseek')->getProviderName());
    }

    public function test_ollama_chat(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message'           => ['role' => 'assistant', 'content' => 'Hello from Ollama!'],
                'model'             => 'llama3.1:8b',
                'prompt_eval_count' => 15,
                'eval_count'        => 8,
                'done'              => true,
            ]),
        ]);

        $response = AI::provider('ollama')->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertEquals('Hello from Ollama!', $response->getContent());
        $this->assertEquals(15, $response->getPromptTokens());
        $this->assertEquals(8, $response->getCompletionTokens());
        $this->assertEquals('ollama', $response->getProvider());
    }

    public function test_openai_chat(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Hello from OpenAI!']]],
                'usage'   => ['prompt_tokens' => 12, 'completion_tokens' => 6],
                'model'   => 'gpt-4o-mini',
            ]),
        ]);

        $response = AI::provider('openai')->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertEquals('Hello from OpenAI!', $response->getContent());
        $this->assertEquals(12, $response->getPromptTokens());
        $this->assertEquals('openai', $response->getProvider());
    }

    public function test_anthropic_chat(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Hello from Claude!']],
                'usage'   => ['input_tokens' => 10, 'output_tokens' => 7],
                'model'   => 'claude-sonnet-4-20250514',
            ]),
        ]);

        $response = AI::provider('anthropic')->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertEquals('Hello from Claude!', $response->getContent());
        $this->assertEquals(10, $response->getPromptTokens());
        $this->assertEquals('anthropic', $response->getProvider());
    }

    public function test_deepseek_chat(): void
    {
        Http::fake([
            'api.deepseek.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Hello from DeepSeek!']]],
                'usage'   => ['prompt_tokens' => 9, 'completion_tokens' => 5],
                'model'   => 'deepseek-chat',
            ]),
        ]);

        $response = AI::provider('deepseek')->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertEquals('Hello from DeepSeek!', $response->getContent());
        $this->assertEquals('deepseek', $response->getProvider());
    }

    public function test_ollama_health(): void
    {
        Http::fake(['http://127.0.0.1:11434' => Http::response('Ollama is running')]);

        $this->assertTrue(AI::provider('ollama')->health());
    }

    public function test_ollama_health_down(): void
    {
        Http::fake(['http://127.0.0.1:11434' => Http::response('', 500)]);

        $this->assertFalse(AI::provider('ollama')->health());
    }

    public function test_system_prompt_chain(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::response([
                'message'           => ['role' => 'assistant', 'content' => 'Yes teacher!'],
                'model'             => 'llama3.1:8b',
                'prompt_eval_count' => 20,
                'eval_count'        => 5,
                'done'              => true,
            ]),
        ]);

        $response = AI::provider('ollama')
            ->model('llama3.1:8b')
            ->systemPrompt('You are a teacher.')
            ->temperature(0.5)
            ->chat([['role' => 'user', 'content' => 'Teach me']]);

        $this->assertEquals('Yes teacher!', $response->getContent());

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['messages'][0]['role'] === 'system'
                && str_contains($body['messages'][0]['content'], 'teacher');
        });
    }

    public function test_estimate_tokens(): void
    {
        $tokens = AI::estimateTokens('Hello world, how are you?');
        $this->assertGreaterThan(0, $tokens);

        $tokens = AI::estimateTokens([
            ['role' => 'user', 'content' => 'Hello'],
        ]);
        $this->assertGreaterThan(0, $tokens);
    }

    public function test_provider_error_throws_exception(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->expectException(\EasyAI\LaravelAI\Exceptions\ProviderException::class);

        AI::provider('openai')->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);
    }
}
