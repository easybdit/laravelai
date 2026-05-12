<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Contracts\AIResponseInterface;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Response\AIResponse;
use Illuminate\Support\Facades\Http;

class OllamaDriver extends AbstractDriver
{
    public function getProviderName(): string
    {
        return 'ollama';
    }

    public function chat(array $messages): AIResponseInterface
    {
        $messages = $this->prependSystemPrompt($messages);
        $url      = rtrim($this->config['url'], '/') . '/api/chat';
        $isStream = $this->streamCallback !== null;

        $body = [
            'model'    => $this->currentModel,
            'messages' => $messages,
            'stream'   => $isStream,
            'options'  => $this->buildOptions(),
        ];

        // JSON mode or structured output schema
        if ($this->currentFormat !== null) {
            $body['format'] = $this->currentFormat;
        }

        // keep_alive — control model memory lifetime
        if ($this->currentKeepAlive !== null) {
            $body['keep_alive'] = $this->currentKeepAlive;
        }

        $this->log('Request', ['model' => $this->currentModel, 'messages_count' => count($messages)]);

        try {
            if ($isStream) {
                return $this->handleStream($url, $body);
            }

            $response = Http::timeout($this->getTimeout())
                ->post($url, $body);

            if (!$response->successful()) {
                throw new ProviderException(
                    "Ollama error: {$response->status()} - {$response->body()}",
                    'ollama',
                    ['status' => $response->status()],
                    $response->status()
                );
            }

            $data = $response->json();

            $result = new AIResponse(
                content:          $data['message']['content'] ?? '',
                promptTokens:     $data['prompt_eval_count'] ?? $this->estimateTokens(json_encode($messages)),
                completionTokens: $data['eval_count'] ?? $this->estimateTokens($data['message']['content'] ?? ''),
                model:            $data['model'] ?? $this->currentModel,
                provider:         'ollama',
                raw:              $data,
            );

            $this->log('Response', ['tokens' => $result->getTotalTokens()]);
            $this->resetOverrides();

            return $result;
        } catch (ProviderException $e) {
            $this->resetOverrides();
            throw $e;
        } catch (\Throwable $e) {
            $this->resetOverrides();
            throw new ConnectionException(
                "Ollama connection failed: {$e->getMessage()}",
                'ollama',
                ['url' => $url],
                0,
                $e
            );
        }
    }

    /**
     * Build Ollama options array — merges temperature, num_predict, and custom options.
     */
    protected function buildOptions(): array
    {
        $options = array_merge(
            $this->config['options'] ?? [],
            $this->currentOptions
        );

        // Always set temperature
        $options['temperature'] = $this->getTemperature();

        // Map maxTokens → num_predict (Ollama's name for max output tokens)
        $maxTokens = $this->getMaxTokens();
        if ($maxTokens !== null) {
            $options['num_predict'] = $maxTokens;
        }

        // Remove keys that are not Ollama options
        unset($options['max_tokens']);

        return $options;
    }

    // ─── Streaming ───────────────────────────────────────────────

    protected function handleStream(string $url, array $body): AIResponseInterface
    {
        $callback = $this->streamCallback;
        $fullContent = '';

        $response = Http::timeout($this->getTimeout())
            ->withOptions(['stream' => true])
            ->post($url, $body);

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // keep incomplete line

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $json = json_decode($line, true);
                if (!$json) continue;

                $chunk = $json['message']['content'] ?? '';
                if ($chunk !== '') {
                    $fullContent .= $chunk;
                    $callback($chunk);
                }

                if (!empty($json['done'])) {
                    $result = new AIResponse(
                        content:          $fullContent,
                        promptTokens:     $json['prompt_eval_count'] ?? $this->estimateTokens(json_encode($body['messages'])),
                        completionTokens: $json['eval_count'] ?? $this->estimateTokens($fullContent),
                        model:            $json['model'] ?? $this->currentModel,
                        provider:         'ollama',
                        raw:              $json,
                    );
                    $this->resetOverrides();
                    return $result;
                }
            }
        }

        $this->resetOverrides();

        return new AIResponse(
            content:          $fullContent,
            promptTokens:     $this->estimateTokens(json_encode($body['messages'])),
            completionTokens: $this->estimateTokens($fullContent),
            model:            $this->currentModel,
            provider:         'ollama',
            raw:              [],
        );
    }

    // ─── Embeddings ──────────────────────────────────────────────

    /**
     * Generate embeddings for one or more inputs.
     *
     * @param  string|array  $input  Single string or array of strings
     * @return array  Array of embedding vectors (array of floats)
     */
    public function embed(string|array $input): array
    {
        $url = rtrim($this->config['url'], '/') . '/api/embed';

        $body = [
            'model' => $this->currentModel,
            'input' => $input,
        ];

        if ($this->currentKeepAlive !== null) {
            $body['keep_alive'] = $this->currentKeepAlive;
        }

        $this->log('Embed', ['model' => $this->currentModel, 'inputs' => is_array($input) ? count($input) : 1]);

        try {
            $response = Http::timeout($this->getTimeout())
                ->post($url, $body);

            if (!$response->successful()) {
                throw new ProviderException(
                    "Ollama embed error: {$response->status()} - {$response->body()}",
                    'ollama',
                    ['status' => $response->status()],
                    $response->status()
                );
            }

            $data = $response->json();
            $this->resetOverrides();

            return $data['embeddings'] ?? [];
        } catch (ProviderException $e) {
            $this->resetOverrides();
            throw $e;
        } catch (\Throwable $e) {
            $this->resetOverrides();
            throw new ConnectionException(
                "Ollama embed connection failed: {$e->getMessage()}",
                'ollama',
                ['url' => $url],
                0,
                $e
            );
        }
    }

    // ─── Model Management ────────────────────────────────────────

    /**
     * Show detailed model information (size, parameters, template, etc.)
     */
    public function showModel(?string $model = null): array
    {
        $url = rtrim($this->config['url'], '/') . '/api/show';

        try {
            $response = Http::timeout(15)
                ->post($url, ['model' => $model ?? $this->currentModel]);

            if (!$response->successful()) return [];

            return $response->json();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Pull (download) a model from Ollama library.
     *
     * @param  string        $model     Model name e.g. 'llama3.1:8b'
     * @param  callable|null $callback  Progress callback: fn(array $status)
     */
    public function pullModel(string $model, ?callable $callback = null): bool
    {
        $url = rtrim($this->config['url'], '/') . '/api/pull';
        $stream = $callback !== null;

        try {
            if ($stream) {
                $response = Http::timeout(600)
                    ->withOptions(['stream' => true])
                    ->post($url, ['model' => $model, 'stream' => true]);

                $body = $response->toPsrResponse()->getBody();
                $buffer = '';

                while (!$body->eof()) {
                    $buffer .= $body->read(1024);
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        $json = json_decode($line, true);
                        if ($json) $callback($json);
                    }
                }
                return true;
            }

            $response = Http::timeout(600)
                ->post($url, ['model' => $model, 'stream' => false]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delete a local model.
     */
    public function deleteModel(string $model): bool
    {
        $url = rtrim($this->config['url'], '/') . '/api/delete';

        try {
            $response = Http::timeout(15)
                ->delete($url, ['model' => $model]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Copy/alias a model.
     */
    public function copyModel(string $source, string $destination): bool
    {
        $url = rtrim($this->config['url'], '/') . '/api/copy';

        try {
            $response = Http::timeout(15)
                ->post($url, ['source' => $source, 'destination' => $destination]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * List currently running/loaded models with VRAM usage.
     */
    public function runningModels(): array
    {
        $url = rtrim($this->config['url'], '/') . '/api/ps';

        try {
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) return [];

            return $response->json('models', []);
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Health & Models ─────────────────────────────────────────

    public function health(): bool
    {
        try {
            $url = rtrim($this->config['url'], '/');
            $response = Http::timeout(5)->get($url);
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function models(): array
    {
        try {
            $url = rtrim($this->config['url'], '/') . '/api/tags';
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) return [];

            return array_column($response->json('models', []), 'name');
        } catch (\Throwable) {
            return [];
        }
    }
}
