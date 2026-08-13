<?php

namespace EasyAI\LaravelAI\Chat\Support;

/**
 * A deliberately small markdown-ish → HTML converter for PDF export —
 * not a CommonMark implementation, just enough of what AI replies
 * actually produce (code fences, inline code, bold/italic, headings,
 * lists, paragraphs) to read well on a page, without pulling in a full
 * markdown package on top of dompdf. Input is HTML-escaped before any
 * tag is reintroduced, so nothing in a message can inject markup.
 */
class PdfMarkdown
{
    public static function toHtml(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $blocks = preg_split('/\n{2,}/', trim($text));
        $html = [];

        foreach ($blocks as $block) {
            $html[] = self::renderBlock($block);
        }

        return implode("\n", array_filter($html, fn ($b) => $b !== ''));
    }

    private static function renderBlock(string $block): string
    {
        $block = trim($block);
        if ($block === '') {
            return '';
        }

        // Fenced code block
        if (preg_match('/^```[a-zA-Z0-9_+-]*\n(.*?)\n?```$/s', $block, $m)) {
            return '<pre><code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code></pre>';
        }

        // Heading
        if (preg_match('/^(#{1,3})\s+(.*)$/', $block, $m)) {
            $level = strlen($m[1]);
            return "<h{$level}>" . self::inline($m[2]) . "</h{$level}>";
        }

        $lines = explode("\n", $block);

        // Bullet list — every line starts with -, *, or •
        if (self::allLinesMatch($lines, '/^[-*•]\s+/')) {
            $items = array_map(fn ($l) => '<li>' . self::inline(preg_replace('/^[-*•]\s+/', '', $l)) . '</li>', $lines);
            return '<ul>' . implode('', $items) . '</ul>';
        }

        // Numbered list — every line starts with "1. "
        if (self::allLinesMatch($lines, '/^\d+\.\s+/')) {
            $items = array_map(fn ($l) => '<li>' . self::inline(preg_replace('/^\d+\.\s+/', '', $l)) . '</li>', $lines);
            return '<ol>' . implode('', $items) . '</ol>';
        }

        // Plain paragraph — single line breaks inside it become <br>
        return '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
    }

    private static function allLinesMatch(array $lines, string $pattern): bool
    {
        foreach ($lines as $line) {
            if (!preg_match($pattern, trim($line))) {
                return false;
            }
        }
        return true;
    }

    /** Inline spans within an already-block-level line: bold, italic, inline code. */
    private static function inline(string $line): string
    {
        $escaped = htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8');

        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped);

        return $escaped;
    }
}
