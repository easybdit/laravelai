<?php

namespace EasyAI\LaravelAI\Tests;

use EasyAI\LaravelAI\AIServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AIServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['AI' => \EasyAI\LaravelAI\Facades\AI::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.default', 'ollama');
        $app['config']->set('ai.providers.ollama', [
            'driver'  => 'ollama',
            'url'     => 'http://127.0.0.1:11434',
            'model'   => 'llama3.1:8b',
            'timeout' => 30,
            'options' => ['temperature' => 0.7],
        ]);
        $app['config']->set('ai.providers.openai', [
            'driver'  => 'openai',
            'api_key' => 'test-key',
            'url'     => 'https://api.openai.com/v1',
            'model'   => 'gpt-4o-mini',
            'timeout' => 30,
            'options' => ['temperature' => 0.7, 'max_tokens' => 100],
        ]);
        $app['config']->set('ai.providers.anthropic', [
            'driver'  => 'anthropic',
            'api_key' => 'test-key',
            'url'     => 'https://api.anthropic.com/v1',
            'model'   => 'claude-sonnet-4-20250514',
            'version' => '2023-06-01',
            'timeout' => 30,
            'options' => ['max_tokens' => 100],
        ]);
        $app['config']->set('ai.providers.deepseek', [
            'driver'  => 'deepseek',
            'api_key' => 'test-key',
            'url'     => 'https://api.deepseek.com/v1',
            'model'   => 'deepseek-chat',
            'timeout' => 30,
            'options' => ['temperature' => 0.7, 'max_tokens' => 100],
        ]);
    }
}
