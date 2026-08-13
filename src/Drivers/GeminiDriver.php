<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Agent\Tool;
use EasyAI\LaravelAI\Agent\ToolCall;
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
        $contents = [];

        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system = $system ? $system . "\n\n" . $msg['content'] : $msg['content'];
                continue;
            }

            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';

            // Appended by appendToolExchange() below — already a raw Gemini
            // parts array (functionCall/functionResponse), not text/image
            // content needing MessageFormatter's translation. Handled
            // per-message (not batched through toProviderContent()) so
            // these can sit right where they belong in the conversation
            // without disturbing the translation of every other message.
            if (!empty($msg['_gemini_native'])) {
                $contents[] = ['role' => $role, 'parts' => $msg['content']];
                continue;
            }

            $translated = MessageFormatter::toProviderContent([$msg], 'gemini')[0];
            $parts = is_array($translated['content']) ? $translated['content'] : [['text' => (string) $translated['content']]];
            $contents[] = ['role' => $role, 'parts' => $parts];
        }

        return ['system' => $system, 'contents' => $contents];
    }

    /** @param Tool[] $tools */
    private function toGeminiTools(array $tools): array
    {
        if (empty($tools)) {
            return [];
        }

        return [[
            'function_declarations' => array_map(fn (Tool $t) => [
                'name'        => $t->name,
                'description' => $t->description,
                'parameters'  => $t->parameters,
            ], $tools),
        ]];
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

        // Thinking mode — reasoning parts come back flagged thought:true in
        // the same parts array as the final answer, rather than a separate
        // field like Ollama/Anthropic.
        $think = $this->currentThink ?? ($this->config['think'] ?? null);
        if ($think !== null) {
            $generationConfig['thinkingConfig'] = ['includeThoughts' => $think];
        }

        if ($generationConfig) {
            $body['generationConfig'] = $generationConfig;
        }

        if ($this->currentTools) {
            $body['tools'] = $this->toGeminiTools($this->currentTools);
        }

        return $body;
    }

    protected function doChat(array $messages): AIResponseInterface
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
            $toolCalls = [];
            $parts = $data['candidates'][0]['content']['parts'] ?? [];

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    // Gemini doesn't assign an id to function calls — matched
                    // by name/position in appendToolExchange() instead.
                    $toolCalls[] = new ToolCall(
                        id:        null,
                        name:      $part['functionCall']['name'] ?? '',
                        arguments: $part['functionCall']['args'] ?? [],
                    );
                } elseif (empty($part['thought'])) {
                    $content .= $part['text'] ?? '';
                }
            }

            $result = new AIResponse(
                content:             $content,
                promptTokens:        $data['usageMetadata']['promptTokenCount'] ?? 0,
                completionTokens:    $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                model:               $this->currentModel,
                provider:            'gemini',
                raw:                 $data,
                toolCalls:           $toolCalls,
                rawAssistantMessage: $parts,
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

        // Thinking mode flags reasoning parts with "thought": true in the
        // same parts array as the real answer, rather than a separate
        // field — each part is routed individually and, like Ollama/
        // Anthropic, gated by callback arity so a legacy single-parameter
        // callback never receives thinking text at all.
        $callbackAcceptsType = (new \ReflectionFunction(\Closure::fromCallable($callback)))->getNumberOfParameters() > 1;

        $handleLine = function (string $line) use ($callback, $callbackAcceptsType, &$fullContent) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) {
                return;
            }
            $json = json_decode(substr($line, 6), true);
            if (!$json) {
                return;
            }
            foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) {
                $text = $part['text'] ?? '';
                if ($text === '') {
                    continue;
                }
                if (!empty($part['thought'])) {
                    if ($callbackAcceptsType) {
                        $callback($text, 'thinking');
                    }
                    continue;
                }
                $fullContent .= $text;
                $callbackAcceptsType ? $callback($text, 'content') : $callback($text);
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

    /**
     * @param array{call: ToolCall, result: mixed}[] $results
     */
    protected function appendToolExchange(array $messages, AIResponseInterface $response, array $results): array
    {
        // The model's turn: replay its exact functionCall part(s) back
        // verbatim (rawAssistantMessage), not a reconstruction from
        // getContent() — a tool-call-only turn has empty text content.
        $messages[] = [
            'role'            => 'assistant',
            'content'         => $response->getRawAssistantMessage() ?? [],
            '_gemini_native'  => true,
        ];

        $responseParts = [];
        foreach ($results as $r) {
            $responseParts[] = [
                'functionResponse' => [
                    'name'     => $r['call']->name,
                    'response' => is_array($r['result']) ? $r['result'] : ['result' => $r['result']],
                ],
            ];
        }

        $messages[] = [
            'role'           => 'user',
            'content'        => $responseParts,
            '_gemini_native' => true,
        ];

        return $messages;
    }
}
