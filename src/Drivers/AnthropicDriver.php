<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Contracts\AIResponseInterface;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Response\AIResponse;
use EasyAI\LaravelAI\Support\MessageFormatter;
use Illuminate\Support\Facades\Http;

class AnthropicDriver extends AbstractDriver
{
    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function chat(array $messages): AIResponseInterface
    {
        $messages  = $this->prependSystemPrompt($messages);
        $formatted = MessageFormatter::normalize($messages, 'anthropic');
        $url       = rtrim($this->config['url'], '/') . '/messages';
        $isStream  = $this->streamCallback !== null;

        $body = [
            'model'      => $this->currentModel,
            'messages'   => $formatted['messages'],
            'max_tokens' => $this->getMaxTokens() ?? 2000,
            'stream'     => $isStream,
        ];

        if ($formatted['system']) {
            $body['system'] = $formatted['system'];
        }

        if ($this->currentTemp !== null) {
            $body['temperature'] = $this->getTemperature();
        }

        $this->log('Request', ['model' => $this->currentModel, 'messages_count' => count($formatted['messages'])]);

        try {
            if ($isStream) {
                return $this->handleStream($url, $body);
            }

            $response = Http::timeout($this->getTimeout())
                ->withHeaders([
                    'x-api-key'         => $this->config['api_key'],
                    'anthropic-version' => $this->config['version'] ?? '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post($url, $body);

            if (!$response->successful()) {
                throw new ProviderException(
                    "Anthropic error: {$response->status()} - {$response->body()}",
                    'anthropic',
                    ['status' => $response->status()],
                    $response->status()
                );
            }

            $data = $response->json();

            // Extract text from content blocks
            $content = '';
            foreach ($data['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'];
                }
            }

            $result = new AIResponse(
                content:          $content,
                promptTokens:     $data['usage']['input_tokens'] ?? 0,
                completionTokens: $data['usage']['output_tokens'] ?? 0,
                model:            $data['model'] ?? $this->currentModel,
                provider:         'anthropic',
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
                "Anthropic connection failed: {$e->getMessage()}",
                'anthropic',
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
        $inputTokens = 0;
        $outputTokens = 0;

        $response = Http::timeout($this->getTimeout())
            ->withHeaders([
                'x-api-key'         => $this->config['api_key'],
                'anthropic-version' => $this->config['version'] ?? '2023-06-01',
                'content-type'      => 'application/json',
            ])
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

                $json = json_decode(substr($line, 6), true);
                if (!$json) continue;

                $type = $json['type'] ?? '';

                if ($type === 'content_block_delta') {
                    $chunk = $json['delta']['text'] ?? '';
                    if ($chunk !== '') {
                        $fullContent .= $chunk;
                        $callback($chunk);
                    }
                }

                if ($type === 'message_delta') {
                    $outputTokens = $json['usage']['output_tokens'] ?? $outputTokens;
                }

                if ($type === 'message_start') {
                    $inputTokens = $json['message']['usage']['input_tokens'] ?? 0;
                }
            }
        }

        $this->resetOverrides();

        return new AIResponse(
            content:          $fullContent,
            promptTokens:     $inputTokens,
            completionTokens: $outputTokens ?: $this->estimateTokens($fullContent),
            model:            $this->currentModel,
            provider:         'anthropic',
            raw:              [],
        );
    }

    public function health(): bool
    {
        // Anthropic has no free health endpoint; attempt a minimal call
        try {
            $url = rtrim($this->config['url'], '/') . '/messages';
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key'         => $this->config['api_key'],
                    'anthropic-version' => $this->config['version'] ?? '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post($url, [
                    'model'      => $this->currentModel,
                    'max_tokens' => 1,
                    'messages'   => [['role' => 'user', 'content' => 'Hi']],
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function models(): array
    {
        // Anthropic doesn't have a public models list endpoint
        return [
            'claude-opus-4-20250514',
            'claude-sonnet-4-20250514',
            'claude-haiku-4-20250414',
        ];
    }
}
