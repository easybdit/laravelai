<?php

namespace EasyAI\LaravelAI\Contracts;

interface AIResponseInterface
{
    public function getContent(): string;

    public function getPromptTokens(): int;

    public function getCompletionTokens(): int;

    public function getTotalTokens(): int;

    public function getModel(): string;

    public function getProvider(): string;

    public function getRaw(): array;

    public function toArray(): array;

    /** @return \EasyAI\LaravelAI\Agent\ToolCall[] */
    public function getToolCalls(): array;

    public function hasToolCalls(): bool;
}
