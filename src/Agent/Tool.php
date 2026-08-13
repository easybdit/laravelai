<?php

namespace EasyAI\LaravelAI\Agent;

/**
 * A single callable capability an AI provider can choose to invoke —
 * "function calling" / "tool use" in each provider's own terminology,
 * normalized here into one provider-agnostic shape. Each driver translates
 * this into its own wire format (OpenAI's `tools` array, Anthropic's
 * `input_schema`, Gemini's `function_declarations`, Ollama's native
 * `tools` field).
 */
class Tool
{
    /**
     * @param  string   $name         Must match [a-zA-Z0-9_-]+ — every provider restricts this.
     * @param  string   $description  What the tool does and when to use it — this is what the
     *                                model actually reads to decide whether to call it, so be specific.
     * @param  array    $parameters   JSON Schema (object type) describing the arguments, e.g.:
     *                                ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']]
     * @param  \Closure $handler      fn (array $arguments): mixed — executed with the model's
     *                                parsed arguments; the return value is JSON-encoded back to the model.
     *                                Throwing turns into a {"error": "..."} result sent back to the model
     *                                rather than crashing the whole agent run.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly \Closure $handler,
    ) {
    }

    public static function make(string $name, string $description, array $parameters, callable $handler): self
    {
        return new self($name, $description, $parameters, \Closure::fromCallable($handler));
    }

    /**
     * Runs the handler, catching any failure into a result shape the model
     * can reason about ("the search failed") instead of the whole agent
     * loop dying on one flaky tool.
     */
    public function execute(array $arguments): mixed
    {
        try {
            return ($this->handler)($arguments);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
