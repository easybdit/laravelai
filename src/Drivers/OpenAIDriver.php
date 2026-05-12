<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Contracts\AIResponseInterface;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Response\AIResponse;
use EasyAI\LaravelAI\Support\MessageFormatter;
use Illuminate\Support\Facades\Http;

class OpenAIDriver extends AbstractDriver
{
    public function getProviderName(): string
    {
        return 'openai';
    }

    public function chat(array $messages): AIResponseInterface
    {
        $messages = $this->prependSystemPrompt($messages);
        $formatted = MessageFormatter::normalize($messages, 'openai');
        $url = rtrim($this->config['url'], '/') . '/chat/completions';
        $isStream = $this->streamCallback !== null;

        $body = [
            'model'       => $this->currentModel,
            'messages'    => $formatted['messages'],
            'temperature' => $this->getTemperature(),
            'stream'      => $isStream,
        ];

        if ($maxTokens = $this->getMaxTokens()) {
            $body['max_tokens'] = $maxTokens;
        }

        $this->log('Request', ['model' => $this->currentModel, 'messages_count' => count($messages)]);

        try {
            if ($isStream) {
                return $this->handleStream($url, $body);
            }

            $response = Http::timeout($this->getTimeout())
                ->withToken($this->config['api_key'])
                ->post($url, $body);

            if (!$response->successful()) {
                throw new ProviderException(
                    "{$this->getProviderName()} error: {$response->status()} - {$response->body()}",
                    $this->getProviderName(),
                    ['status' => $response->status()],
                    $response->status()
                );
            }

            $data = $response->json();

            $result = new AIResponse(
                content:          $data['choices'][0]['message']['content'] ?? '',
                promptTokens:     $data['usage']['prompt_tokens'] ?? 0,
                completionTokens: $data['usage']['completion_tokens'] ?? 0,
                model:            $data['model'] ?? $this->currentModel,
                provider:         $this->getProviderName(),
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
                "{$this->getProviderName()} connection failed: {$e->getMessage()}",
                $this->getProviderName(),
                ['url' => $url],
                0,
                $e
            );
        }
    }

    protected function handleStream(string $url, array $body): AIResponseInterface
    {
        $callback = $this->streamCallback;
        $fullContent = '';

        $response = Http::timeout($this->getTimeout())
            ->withToken($this->config['api_key'])
            ->withOptions(['stream' => true])
            ->post($url, $body);

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;

                $data = substr($line, 6);
                if ($data === '[DONE]') break;

                $json = json_decode($data, true);
                if (!$json) continue;

                $chunk = $json['choices'][0]['delta']['content'] ?? '';
                if ($chunk !== '') {
                    $fullContent .= $chunk;
                    $callback($chunk);
                }
            }
        }

        $this->resetOverrides();

        return new AIResponse(
            content:          $fullContent,
            promptTokens:     $this->estimateTokens(json_encode($body['messages'])),
            completionTokens: $this->estimateTokens($fullContent),
            model:            $this->currentModel,
            provider:         $this->getProviderName(),
            raw:              [],
        );
    }

    public function health(): bool
    {
        try {
            $url = rtrim($this->config['url'], '/') . '/models';
            $response = Http::timeout(10)
                ->withToken($this->config['api_key'])
                ->get($url);
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function models(): array
    {
        try {
            $url = rtrim($this->config['url'], '/') . '/models';
            $response = Http::timeout(10)
                ->withToken($this->config['api_key'])
                ->get($url);

            if (!$response->successful()) return [];

            return array_column($response->json('data', []), 'id');
        } catch (\Throwable) {
            return [];
        }
    }
}
