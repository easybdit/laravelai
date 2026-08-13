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

    /**
     * Sets the tools available for the *next* call — chat()/run() will
     * include them on the outgoing request in this provider's own wire
     * format. Cleared after each call, same lifecycle as every other
     * per-request override (model, temperature, etc.).
     *
     * @param \EasyAI\LaravelAI\Agent\Tool[] $tools
     */
    public function tools(array $tools): static;

    /**
     * The agent loop: sends $messages with the tools set via tools(),
     * and — as long as the model keeps asking to call one — executes the
     * matching Tool's handler and feeds the result back, up to $maxSteps
     * round-trips. Returns the first response with no further tool calls
     * (or the last response, with whatever it has, if $maxSteps is hit —
     * an agent that can't finish shouldn't loop forever, but it also
     * shouldn't throw away a partial answer).
     *
     * $onToolCall, when given, is called as $onToolCall($call, $result)
     * right after each individual tool call executes — purely an
     * observability hook (e.g. surfacing "a tool was just called" to a
     * caller), never changes what run() returns. Omit for the exact same
     * behavior as before this parameter existed.
     */
    public function run(array $messages, int $maxSteps = 5, ?callable $onToolCall = null): AIResponseInterface;
}
