<?php

namespace EasyAI\LaravelAI\Response;

use EasyAI\LaravelAI\Contracts\AIResponseInterface;

/**
 * @property-read string $content
 * @property-read int    $promptTokens
 * @property-read int    $completionTokens
 * @property-read int    $totalTokens
 * @property-read string $model
 * @property-read string $provider
 */
class AIResponse implements AIResponseInterface
{
    public function __construct(
        protected string $content,
        protected int    $promptTokens,
        protected int    $completionTokens,
        protected string $model,
        protected string $provider,
        protected array  $raw = [],
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
            default            => null,
        };
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
