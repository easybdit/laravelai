<?php

namespace EasyAI\LaravelAI\Response;

use EasyAI\LaravelAI\Agent\ToolCall;
use EasyAI\LaravelAI\Contracts\AIResponseInterface;

/**
 * @property-read string $content
 * @property-read int    $promptTokens
 * @property-read int    $completionTokens
 * @property-read int    $totalTokens
 * @property-read string $model
 * @property-read string $provider
 * @property-read ToolCall[] $toolCalls
 * @property-read ?array $structured
 */
class AIResponse implements AIResponseInterface
{
    /**
     * @param ToolCall[] $toolCalls Tool invocations the model asked for — empty means
     *                              this is a final answer, not a request to call anything.
     * @param mixed      $rawAssistantMessage Provider-native shape of this turn (e.g. Anthropic's
     *                              full content-blocks array, OpenAI's message object with
     *                              tool_calls) — opaque to callers, only meaningful when a
     *                              driver replays it back into the next request in an agent
     *                              loop. Null when not applicable (no tool calls, or a driver
     *                              that doesn't need it).
     * @param ?array     $structured Parsed structured-output data — set only when the call was
     *                              made with ->format($schema) (or ->format('json')) and the
     *                              provider's response could be decoded as JSON. Null for a
     *                              normal free-text response, and also null if the provider
     *                              returned something that failed to decode (getContent() still
     *                              has the raw text either way).
     */
    public function __construct(
        protected string $content,
        protected int    $promptTokens,
        protected int    $completionTokens,
        protected string $model,
        protected string $provider,
        protected array  $raw = [],
        protected array  $toolCalls = [],
        protected mixed  $rawAssistantMessage = null,
        protected ?array $structured = null,
    ) {}

    public function __get(string $name): mixed
    {
        return match ($name) {
            'content'          => $this->content,
            'promptTokens'     => $this->promptTokens,
            'completionTokens' => $this->completionTokens,
            'totalTokens'      => $this->getTotalTokens(),
            'model'            => $this->model,
            'provider'         => $this->provider,
            'toolCalls'        => $this->toolCalls,
            'structured'       => $this->structured,
            default            => null,
        };
    }

    /** @return ToolCall[] */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    public function getStructuredData(): ?array
    {
        return $this->structured;
    }

    public function hasStructuredData(): bool
    {
        return $this->structured !== null;
    }

    /**
     * USD cost estimate from config('ai.pricing.{provider}.{model}') — an
     * ['input' => $perThousandTokens, 'output' => $perThousandTokens] rate
     * you supply yourself (empty by default; see that config key's own
     * docblock for why this package doesn't ship guessed prices). Null
     * whenever no rate is configured for this exact provider/model pair,
     * never a silently-wrong number extrapolated from a different model.
     */
    public function getEstimatedCost(): ?float
    {
        $rate = config("ai.pricing.{$this->provider}.{$this->model}");

        if (!is_array($rate) || !isset($rate['input'], $rate['output'])) {
            return null;
        }

        return ($this->promptTokens / 1000 * $rate['input'])
            + ($this->completionTokens / 1000 * $rate['output']);
    }

    public function getRawAssistantMessage(): mixed
    {
        return $this->rawAssistantMessage;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function toArray(): array
    {
        return [
            'content'           => $this->content,
            'prompt_tokens'     => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens'      => $this->getTotalTokens(),
            'model'             => $this->model,
            'provider'          => $this->provider,
        ];
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
