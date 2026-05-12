<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Contracts\AIProviderInterface;
use EasyAI\LaravelAI\Contracts\AIResponseInterface;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Support\TokenEstimator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractDriver implements AIProviderInterface
{
    protected array  $config;
    protected string $currentModel;
    protected ?float $currentTemp   = null;
    protected ?int   $currentMaxTokens = null;
    protected ?string $currentSystemPrompt = null;
    protected ?int   $currentTimeout = null;
    protected ?\Closure $streamCallback = null;
    protected string|int|null $currentKeepAlive = null;
    protected string|array|null $currentFormat = null;
    protected array $currentOptions = [];

    public function __construct(array $config)
    {
        $this->config       = $config;
        $this->currentModel = $config['model'] ?? '';
    }

    public function model(string $model): static
    {
        $this->currentModel = $model;
        return $this;
    }

    public function temperature(float $temp): static
    {
        $this->currentTemp = $temp;
        return $this;
    }

    public function maxTokens(int $tokens): static
    {
        $this->currentMaxTokens = $tokens;
        return $this;
    }

    public function systemPrompt(string $prompt): static
    {
        $this->currentSystemPrompt = $prompt;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->currentTimeout = $seconds;
        return $this;
    }

    public function keepAlive(string|int $duration): static
    {
        $this->currentKeepAlive = $duration;
        return $this;
    }

    public function format(string|array $format): static
    {
        $this->currentFormat = $format;
        return $this;
    }

    public function options(array $options): static
    {
        $this->currentOptions = $options;
        return $this;
    }

    public function stream(array $messages, callable $callback): AIResponseInterface
    {
        $this->streamCallback = $callback;
        $response = $this->chat($messages);
        $this->streamCallback = null;
        return $response;
    }

    /**
     * Default embed — override in drivers that support it.
     */
    public function embed(string|array $input): array
    {
        throw new \BadMethodCallException(
            "Embeddings are not supported by the [{$this->getProviderName()}] provider."
        );
    }

    protected function getTimeout(): int
    {
        return $this->currentTimeout ?? ($this->config['timeout'] ?? 60);
    }

    protected function getTemperature(): float
    {
        return $this->currentTemp ?? ($this->config['options']['temperature'] ?? 0.7);
    }

    protected function getMaxTokens(): ?int
    {
        return $this->currentMaxTokens ?? ($this->config['options']['max_tokens'] ?? null);
    }

    protected function prependSystemPrompt(array $messages): array
    {
        if ($this->currentSystemPrompt) {
            array_unshift($messages, [
                'role'    => 'system',
                'content' => $this->currentSystemPrompt,
            ]);
        }
        return $messages;
    }

    protected function log(string $message, array $context = []): void
    {
        if (config('ai.logging.enabled', false)) {
            Log::channel(config('ai.logging.channel', 'stack'))
                ->info("[LaravelAI:{$this->getProviderName()}] {$message}", $context);
        }
    }

    protected function estimateTokens(string $text): int
    {
        return TokenEstimator::estimate($text);
    }

    /**
     * Reset per-request overrides after each call.
     */
    protected function resetOverrides(): void
    {
        $this->currentTemp         = null;
        $this->currentMaxTokens    = null;
        $this->currentSystemPrompt = null;
        $this->currentTimeout      = null;
        $this->streamCallback      = null;
        $this->currentKeepAlive    = null;
        $this->currentFormat       = null;
        $this->currentOptions      = [];
    }
}
