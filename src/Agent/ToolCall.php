<?php

namespace EasyAI\LaravelAI\Agent;

/**
 * One tool invocation the model asked for, normalized from whatever shape
 * the provider returned it in. $id is provider-assigned and must be echoed
 * back verbatim in the tool-result message for OpenAI/Anthropic — Gemini
 * and Ollama don't use it (matched by name/position instead), so it's
 * nullable rather than every driver inventing a fake one.
 */
class ToolCall
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
