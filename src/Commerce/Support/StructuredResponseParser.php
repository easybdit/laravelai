<?php

namespace EasyAI\LaravelAI\Commerce\Support;

/**
 * Extracts a single flat JSON block the AI was instructed to emit on its
 * own line — e.g. {"query":{"type":"revenue","from":"2026-07-01"}} — and
 * strips it back out of the human-readable reply. Shared by every commerce
 * controller so the "ask the model for a structured action" pattern isn't
 * reimplemented three times.
 *
 * Deliberately matches only flat (non-nested) objects, same constraint the
 * WordPress plugin's equivalent parser has — the prompts that use this
 * only ever ask for flat key/value blocks.
 */
class StructuredResponseParser
{
    /**
     * @return array{text: string, data: array|null}
     */
    public static function extract(string $response, string $key): array
    {
        $pattern = '/\{"' . preg_quote($key, '/') . '"\s*:\s*\{[^{}]*\}\s*\}/s';

        $data = null;
        if (preg_match($pattern, $response, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && isset($decoded[$key]) && is_array($decoded[$key])) {
                $data = $decoded[$key];
            }
        }

        $text = trim((string) preg_replace($pattern, '', $response));

        return ['text' => $text, 'data' => $data];
    }
}
