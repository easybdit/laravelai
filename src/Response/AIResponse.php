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
