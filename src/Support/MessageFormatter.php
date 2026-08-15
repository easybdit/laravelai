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

        // Ollama, OpenAI, DeepSeek, Together, Custom accept system role in messages array
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

        // Claude requires alternating user/assistant. Merge consecutive same-role
        // (skip merging when either side carries multipart/vision content —
        // string concatenation doesn't apply to an array of content blocks).
        $merged = [];
        foreach ($filtered as $msg) {
            $last = end($merged);
            $canMerge = $last
                && $last['role'] === $msg['role']
                && is_string($last['content'] ?? null)
                && is_string($msg['content'] ?? null);

            if ($canMerge) {
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

    /**
     * Build a universal multipart "content" array for a message that carries
     * an image alongside text — used for vision-capable providers. Each
     * driver's toProviderContent() translates this into its own wire format.
     */
    public static function withImage(string $text, string $base64, string $mime): array
    {
        return self::withImages($text, [['mime' => $mime, 'data' => $base64]]);
    }

    /**
     * Same as withImage(), for more than one image in a single message —
     * e.g. several page images rendered from one attached PDF (see
     * PdfPageRenderer). toProviderContent()/imageBlock() already convert an
     * arbitrary number of 'image' blocks per message; this is just the
     * builder for that same shape when there's more than one.
     *
     * @param array<int, array{mime: string, data: string}> $images
     */
    public static function withImages(string $text, array $images): array
    {
        $blocks = [['type' => 'text', 'text' => $text]];
        foreach ($images as $image) {
            $blocks[] = ['type' => 'image', 'mime' => $image['mime'], 'data' => $image['data']];
        }
        return $blocks;
    }

    /**
     * Translate a message array that may contain universal image blocks
     * (see withImage()) into the wire format a specific provider expects.
     * Messages with plain string content pass through untouched.
     */
    public static function toProviderContent(array $messages, string $provider): array
    {
        return array_map(function (array $msg) use ($provider) {
            if (!is_array($msg['content'] ?? null)) {
                return $msg;
            }

            $blocks = [];
            foreach ($msg['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $blocks[] = self::textBlock($block['text'], $provider);
                } elseif (($block['type'] ?? '') === 'image') {
                    $blocks[] = self::imageBlock($block['mime'], $block['data'], $provider);
                } else {
                    // Unknown block type (e.g. Anthropic's tool_use/tool_result,
                    // already in that provider's native wire shape courtesy of
                    // AbstractDriver::run()'s appendToolExchange()) — pass through
                    // unchanged rather than silently dropping it.
                    $blocks[] = $block;
                }
            }
            $msg['content'] = $blocks;
            return $msg;
        }, $messages);
    }

    private static function textBlock(string $text, string $provider): array
    {
        return match ($provider) {
            'gemini' => ['text' => $text],
            default  => ['type' => 'text', 'text' => $text],
        };
    }

    private static function imageBlock(string $mime, string $base64, string $provider): array
    {
        return match ($provider) {
            'anthropic' => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
            'gemini'    => ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
            default     => ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]], // OpenAI-compatible
        };
    }
}
