<?php

namespace EasyAI\LaravelAI\Facades;

use EasyAI\LaravelAI\AIManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface provider(string $name = null)
 * @method static \EasyAI\LaravelAI\Contracts\AIResponseInterface chat(array $messages)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface model(string $model)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface temperature(float $temp)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface maxTokens(int $tokens)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface systemPrompt(string $prompt)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface keepAlive(string|int $duration)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface format(string|array $format)
 * @method static \EasyAI\LaravelAI\Contracts\AIProviderInterface options(array $options)
 * @method static array embed(string|array $input)
 * @method static bool health()
 * @method static array models()
 * @method static int estimateTokens(string|array $input)
 *
 * @see AIManager
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-ai';
    }
}
