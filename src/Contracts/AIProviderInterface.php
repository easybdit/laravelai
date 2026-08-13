<?php

namespace EasyAI\LaravelAI\Contracts;

interface AIProviderInterface
{
    public function chat(array $messages): AIResponseInterface;

    public function stream(array $messages, callable $callback): AIResponseInterface;

    public function health(): bool;

    public function models(): array;

    public function model(string $model): static;

    public function temperature(float $temp): static;

    public function maxTokens(int $tokens): static;

    public function systemPrompt(string $prompt): static;

    public function timeout(int $seconds): static;

    public function keepAlive(string|int $duration): static;

    public function format(string|array $format): static;

    public function options(array $options): static;

    /**
     * Toggle reasoning/"thinking" mode on models that support it (currently
     * Ollama only — e.g. qwen3). Silently ignored by drivers that don't
     * support it, same as format()/keepAlive() on non-Ollama drivers.
     */
    public function think(bool $think): static;

    public function embed(string|array $input): array;

    public function getProviderName(): string;
}
