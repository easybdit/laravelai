<?php

namespace EasyAI\LaravelAI\Support;

class TokenEstimator
{
    /**
     * Estimate tokens for a string (~0.75 tokens per word, ~4 chars per token).
     */
    public static function estimate(string $text): int
    {
        if (empty(trim($text))) {
            return 0;
        }

        // Average: 1 token ≈ 4 characters for English
        $charEstimate = (int) ceil(mb_strlen($text) / 4);

        // Average: 1 token ≈ 0.75 words
        $wordEstimate = (int) ceil(str_word_count($text) * 1.3);

        // Use the higher estimate for safety
        return max($charEstimate, $wordEstimate);
    }

    /**
     * Estimate tokens for a messages array.
     */
    public static function estimateMessages(array $messages): int
    {
        $total = 0;
        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            $total += static::estimate($content);
            $total += 4; // overhead per message (role, formatting)
        }

        return $total + 3; // reply priming
    }
}
