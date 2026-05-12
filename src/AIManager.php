<?php

namespace EasyAI\LaravelAI;

use EasyAI\LaravelAI\Contracts\AIProviderInterface;
use EasyAI\LaravelAI\Drivers\AnthropicDriver;
use EasyAI\LaravelAI\Drivers\DeepSeekDriver;
use EasyAI\LaravelAI\Drivers\OllamaDriver;
use EasyAI\LaravelAI\Drivers\OpenAIDriver;
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
     * Alias for driver() — more readable.
     */
    public function provider(?string $name = null): AIProviderInterface
    {
        return $this->driver($name);
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
