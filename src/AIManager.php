<?php

namespace EasyAI\LaravelAI;

use EasyAI\LaravelAI\Contracts\AIProviderInterface;
use EasyAI\LaravelAI\Drivers\AnthropicDriver;
use EasyAI\LaravelAI\Drivers\CustomDriver;
use EasyAI\LaravelAI\Drivers\DeepSeekDriver;
use EasyAI\LaravelAI\Drivers\GeminiDriver;
use EasyAI\LaravelAI\Drivers\OllamaDriver;
use EasyAI\LaravelAI\Drivers\OpenAIDriver;
use EasyAI\LaravelAI\Drivers\TogetherDriver;
use EasyAI\LaravelAI\Exceptions\AIException;
use EasyAI\LaravelAI\Support\TokenEstimator;
use Illuminate\Support\Manager;

/**
 * @method AIProviderInterface provider(string $name = null)
 */
class AIManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'ollama');
    }

    /**
     * Alias for driver() — more readable. Also the entry point for named
     * custom (OpenAI-compatible) endpoints via "custom:slug", since
     * Manager's create{Driver}Driver() convention can't address a
     * config('ai.custom_providers') entry by an arbitrary key.
     */
    public function provider(?string $name = null): AIProviderInterface
    {
        $name ??= $this->getDefaultDriver();

        if (str_starts_with($name, 'custom:')) {
            return $this->custom(substr($name, 7));
        }

        return $this->driver($name);
    }

    /**
     * Resolve a named custom (OpenAI-compatible) provider from
     * config('ai.custom_providers.<key>'). Not cached by Manager's driver
     * registry — each call builds a fresh instance, matching how the other
     * drivers behave once forgetDrivers() is called between requests.
     */
    public function custom(string $key): CustomDriver
    {
        $config = $this->config->get("ai.custom_providers.{$key}");

        if (!$config) {
            throw new AIException("Custom AI provider [{$key}] is not configured.", "custom:{$key}");
        }

        return new CustomDriver($config, "custom:{$key}");
    }

    protected function createOllamaDriver(): OllamaDriver
    {
        return new OllamaDriver($this->getProviderConfig('ollama'));
    }

    protected function createOpenaiDriver(): OpenAIDriver
    {
        return new OpenAIDriver($this->getProviderConfig('openai'));
    }

    protected function createAnthropicDriver(): AnthropicDriver
    {
        return new AnthropicDriver($this->getProviderConfig('anthropic'));
    }

    protected function createDeepseekDriver(): DeepSeekDriver
    {
        return new DeepSeekDriver($this->getProviderConfig('deepseek'));
    }

    protected function createGeminiDriver(): GeminiDriver
    {
        return new GeminiDriver($this->getProviderConfig('gemini'));
    }

    protected function createTogetherDriver(): TogetherDriver
    {
        return new TogetherDriver($this->getProviderConfig('together'));
    }

    protected function getProviderConfig(string $name): array
    {
        $config = $this->config->get("ai.providers.{$name}");

        if (!$config) {
            throw new AIException("AI provider [{$name}] is not configured.", $name);
        }

        return $config;
    }

    /**
     * Estimate tokens for a string or messages array.
     */
    public function estimateTokens(string|array $input): int
    {
        if (is_string($input)) {
            return TokenEstimator::estimate($input);
        }

        return TokenEstimator::estimateMessages($input);
    }

    public function rag(): \EasyAI\LaravelAI\RAG\RAGManager
{
    return $this->container->make(\EasyAI\LaravelAI\RAG\RAGManager::class);
}

}
