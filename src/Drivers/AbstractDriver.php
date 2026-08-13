<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Agent\Tool;
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
    protected ?bool $currentThink = null;

    /** @var Tool[] */
    protected array $currentTools = [];

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

    public function think(bool $think): static
    {
        $this->currentThink = $think;
        return $this;
    }

    public function tools(array $tools): static
    {
        $this->currentTools = $tools;
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
     * The agent loop — see AIProviderInterface::run() for the contract.
     * Provider-agnostic: repeatedly calls chat() (which each driver still
     * implements its own wire format for) and, when the model asks to
     * call a tool, executes it and hands the result back via
     * appendToolExchange() — the one method each driver DOES implement,
     * since how a tool call/result gets replayed into the next request is
     * genuinely different per provider (OpenAI's tool_calls + role:"tool"
     * messages, Anthropic's tool_use/tool_result content blocks, Gemini's
     * functionCall/functionResponse parts, Ollama's native tool_calls).
     */
    public function run(array $messages, int $maxSteps = 5): AIResponseInterface
    {
        $tools    = $this->currentTools;
        $maxSteps = max(1, $maxSteps);
        $response = null;

        for ($step = 0; $step < $maxSteps; $step++) {
            $this->currentTools = $tools; // chat() resets this via resetOverrides() each call
            $response = $this->chat($messages);

            if (!$response->hasToolCalls()) {
                return $response;
            }

            $results = [];
            foreach ($response->getToolCalls() as $call) {
                $tool = $this->findTool($tools, $call->name);
                $results[] = [
                    'call'   => $call,
                    'result' => $tool
                        ? $tool->execute($call->arguments)
                        : ['error' => "Unknown tool requested: {$call->name}"],
                ];
            }

            $messages = $this->appendToolExchange($messages, $response, $results);
        }

        // Ran out of steps without converging — return the last response
        // as-is rather than throwing it away. Its content may be empty (a
        // pure tool-call turn); that's a legitimate signal to the caller
        // that the agent didn't finish, not something to paper over here.
        return $response;
    }

    /** @param Tool[] $tools */
    private function findTool(array $tools, string $name): ?Tool
    {
        foreach ($tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * Appends this turn's tool-call exchange (the assistant's tool call(s)
     * plus each tool's result) onto $messages in this provider's own wire
     * format, ready for the next chat() call in the loop. $results is a
     * list of ['call' => ToolCall, 'result' => mixed] in the same order as
     * $response->getToolCalls().
     */
    abstract protected function appendToolExchange(array $messages, AIResponseInterface $response, array $results): array;

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
        $this->currentThink        = null;
        $this->currentTools        = [];
    }
}
