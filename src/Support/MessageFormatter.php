<?php

namespace EasyAI\LaravelAI\Support;

class MessageFormatter
{
    /**
     * Normalize messages for a specific provider.
     *
     * @return array{system: string|null, messages: array}
     */
    public static function normalize(array $messages, string $provider): array
    {
        if ($provider === 'anthropic') {
            return static::forAnthropic($messages);
        }

        // Ollama, OpenAI, DeepSeek accept system role in messages array
        return ['system' => null, 'messages' => $messages];
    }

    /**
     * Anthropic: extract system into separate param, ensure alternating turns.
     */
    protected static function forAnthropic(array $messages): array
    {
        $system = null;
        $filtered = [];

        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                // Concatenate multiple system messages
                $system = $system
                    ? $system . "\n\n" . $msg['content']
                    : $msg['content'];
                continue;
            }
            $filtered[] = $msg;
        }

        // Claude requires alternating user/assistant. Merge consecutive same-role.
        $merged = [];
        foreach ($filtered as $msg) {
            $last = end($merged);
            if ($last && $last['role'] === $msg['role']) {
                $merged[array_key_last($merged)]['content'] .= "\n\n" . $msg['content'];
            } else {
                $merged[] = $msg;
            }
        }

        // Claude requires first message to be 'user'
        if (!empty($merged) && $merged[0]['role'] !== 'user') {
            array_unshift($merged, ['role' => 'user', 'content' => 'Continue.']);
        }

        return ['system' => $system, 'messages' => $merged];
    }
}
