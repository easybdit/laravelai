<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class GroqDriverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai.providers.groq', [
            'driver'  => 'groq',
            'api_key' => 'test-key',
            'url'     => 'https://api.groq.com/openai/v1',
            'model'   => 'llama-3.3-70b-versatile',
            'timeout' => 30,
            'options' => ['temperature' => 0.7, 'max_tokens' => 100],
        ]);
    }

    public function test_manager_resolves_groq_driver(): void
    {
        $this->assertEquals('groq', AI::provider('groq')->getProviderName());
    }

    public function test_groq_chat_reuses_openai_wire_format(): void
    {
        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Hello from Groq!']]],
                'usage'   => ['prompt_tokens' => 7, 'completion_tokens' => 4],
                'model'   => 'llama-3.3-70b-versatile',
            ]),
        ]);

        $response = AI::provider('groq')->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertEquals('Hello from Groq!', $response->getContent());
        $this->assertEquals('groq', $response->getProvider());
        $this->assertEquals(7, $response->getPromptTokens());
        $this->assertEquals(4, $response->getCompletionTokens());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request['model'] === 'llama-3.3-70b-versatile';
        });
    }
}
