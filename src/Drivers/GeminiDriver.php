<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Contracts\AIResponseInterface;
use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Response\AIResponse;
use EasyAI\LaravelAI\Support\MessageFormatter;
use Illuminate\Support\Facades\Http;

class GeminiDriver extends AbstractDriver
{
    public function getProviderName(): string
    {
        return 'gemini';
    }

    /**
     * Gemini's wire shape (contents[].parts[], role "user"/"model", a
     * separate systemInstruction) doesn't match the OpenAI-style messages
     * array MessageFormatter::normalize() produces for the other providers,
     * so this driver builds it directly — reusing only the multipart image
     * translation from MessageFormatter::toProviderContent().
     *
     * @return array{system: ?string, contents: array}
     */
    private function toGeminiContents(array $messages): array
    {
        $system = null;
        $filtered = [];

        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system = $system ? $system . "\n\n" . $msg['content'] : $msg['content'];
                continue;
            }
            $filtered[] = $msg;
        }

        $filtered = MessageFormatter::toProviderContent($filtered, 'gemini');

        $contents = array_map(function ($msg) {
            $parts = is_array($msg['content']) ? $msg['content'] : [['text' => (string) $msg['content']]];
            return [
                'role'  => ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => $parts,
            ];
        }, $filtered);

        return ['system' => $system, 'contents' => $contents];
    }

    private function baseBody(array $formatted): array
    {
        $body = ['contents' => $formatted['contents']];

        if ($formatted['system']) {
            $body['systemInstruction'] = ['parts' => [['text' => $formatted['system']]]];
        }

        $generationConfig = [];
        if ($this->currentTemp !== null) {
            $generationConfig['temperature'] = $this->getTemperature();
        }
        if ($maxTokens = $this->getMaxTokens()) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }
        if ($generationConfig) {
            $body['generationConfig'] = $generationConfig;
        }

        return $body;
    }

    public function chat(array $messages): AIResponseInterface
    {
        $messages  = $this->prependSystemPrompt($messages);
        $formatted = $this->toGeminiContents($messages);
        $body      = $this->baseBody($formatted);
        $isStream  = $this->streamCallback !== null;

        $action = $isStream ? 'streamGenerateContent' : 'generateContent';
        $url = rtrim($this->config['url'], '/') . "/models/{$this->currentModel}:{$action}";

        $this->log('Request', ['model' => $this->currentModel, 'messages_count' => count($formatted['contents'])]);

        try {
            if ($isStream) {
                return $this->handleStream($url, $body);
            }

            $response = Http::timeout($this->getTimeout())
                ->withQueryParameters(['key' => $this->config['api_key']])
                ->post($url, $body);

            if (!$response->successful()) {
                throw new ProviderException(
                    "gemini error: {$response->status()} - {$response->body()}",
                    'gemini',
                    ['status' => $response->status()],
                    $response->status()
                );
            }

            $data = $response->json();
            $content = '';
            foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
                $content .= $part['text'] ?? '';
            }

            $result = new AIResponse(
                content:          $content,
                promptTokens:     $data['usageMetadata']['promptTokenCount'] ?? 0,
                completionTokens: $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                model:            $this->currentModel,
                provider:         'gemini',
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
                "gemini connection failed: {$e->getMessage()}",
                'gemini',
                ['url' => $url],
                0,
                $e
            );
        }
    }

    protected function handleStream(string $url, array $body): AIResponseInterface
    {
        $callback    = $this->streamCallback;
        $fullContent = '';

        $response = Http::timeout($this->getTimeout())
            ->withQueryParameters(['key' => $this->config['api_key'], 'alt' => 'sse'])
            ->withOptions(['stream' => true])
            ->post($url, $body);

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        $handleLine = function (string $line) use ($callback, &$fullContent) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) {
                return;
            }
            $json = json_decode(substr($line, 6), true);
            if (!$json) {
                return;
            }
            $chunk = '';
            foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) {
                $chunk .= $part['text'] ?? '';
            }
            if ($chunk !== '') {
                $fullContent .= $chunk;
                $callback($chunk);
            }
        };

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);
            $lines  = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $handleLine($line);
            }
        }
        // A final line with no trailing newline (arrives in the same read()
        // that hits EOF) would otherwise sit in $buffer unparsed.
        $handleLine($buffer);

        $this->resetOverrides();

        return new AIResponse(
            content:          $fullContent,
            promptTokens:     $this->estimateTokens(json_encode($body['contents'])),
            completionTokens: $this->estimateTokens($fullContent),
            model:            $this->currentModel,
            provider:         'gemini',
            raw:              [],
        );
    }

    public function health(): bool
    {
        try {
            $url = rtrim($this->config['url'], '/') . '/models';
            $response = Http::timeout(10)->withQueryParameters(['key' => $this->config['api_key']])->get($url);
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function models(): array
    {
        try {
            $url = rtrim($this->config['url'], '/') . '/models';
            $response = Http::timeout(10)->withQueryParameters(['key' => $this->config['api_key']])->get($url);
            if (!$response->successful()) {
                return [];
            }
            return array_map(
                fn ($m) => str_replace('models/', '', $m['name'] ?? ''),
                $response->json('models', [])
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
