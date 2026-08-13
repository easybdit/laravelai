<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class NewProvidersTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai.providers.gemini', [
            'driver'  => 'gemini',
            'api_key' => 'test-key',
            'url'     => 'https://generativelanguage.googleapis.com/v1beta',
            'model'   => 'gemini-2.0-flash',
            'timeout' => 30,
            'options' => ['temperature' => 0.7],
        ]);

        $app['config']->set('ai.providers.together', [
            'driver'  => 'together',
            'api_key' => 'test-key',
            'url'     => 'https://api.together.xyz/v1',
            'model'   => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            'timeout' => 30,
            'options' => ['temperature' => 0.7],
        ]);

        $app['config']->set('ai.custom_providers.lmstudio', [
            'label'   => 'LM Studio (local)',
            'url'     => 'http://127.0.0.1:1234/v1',
            'api_key' => null,
            'model'   => 'local-model',
            'timeout' => 30,
        ]);
    }

    public function test_gemini_chat(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Hello from Gemini!']]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 3],
            ]),
        ]);

        $response = AI::provider('gemini')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello from Gemini!', $response->getContent());
        $this->assertEquals('gemini', $response->getProvider());
    }

    public function test_gemini_sends_system_instruction_separately(): void
    {
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ]),
        ]);

        AI::provider('gemini')->systemPrompt('Be terse.')->chat([['role' => 'user', 'content' => 'Hi']]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['systemInstruction']['parts'][0]['text'] === 'Be terse.'
                && $body['contents'][0]['role'] === 'user';
        });
    }

    public function test_together_chat_reuses_openai_wire_format(): void
    {
        Http::fake([
            'api.together.xyz/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello from Together!']]],
                'model'   => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
                'usage'   => ['prompt_tokens' => 4, 'completion_tokens' => 3],
            ]),
        ]);

        $response = AI::provider('together')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello from Together!', $response->getContent());
        $this->assertEquals('together', $response->getProvider());
    }

    public function test_custom_provider_resolves_by_slug(): void
    {
        Http::fake([
            '127.0.0.1:1234/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello from LM Studio!']]],
            ]),
        ]);

        $response = AI::provider('custom:lmstudio')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello from LM Studio!', $response->getContent());
        $this->assertEquals('custom:lmstudio', $response->getProvider());
    }

    public function test_unknown_custom_provider_throws(): void
    {
        $this->expectException(\EasyAI\LaravelAI\Exceptions\AIException::class);
        AI::provider('custom:does-not-exist')->chat([['role' => 'user', 'content' => 'Hi']]);
    }
}
