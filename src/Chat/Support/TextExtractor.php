<?php

namespace EasyAI\LaravelAI\Chat\Support;

/**
 * Plain-text extraction shared by Project file ingestion and chat message
 * attachments — .txt/.md read directly, .pdf via smalot/pdfparser (optional
 * dependency, same as Projects already required).
 */
class TextExtractor
{
    public static function extract(string $absolutePath, string $mimeType): string
    {
        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("File not found at: {$absolutePath}");
        }

        if (str_contains($mimeType, 'pdf')) {
            if (!class_exists(\Smalot\PdfParser\Parser::class)) {
                throw new \RuntimeException('PDF text extraction requires: composer require smalot/pdfparser');
            }
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($absolutePath)->getText();
        }

        $text = file_get_contents($absolutePath);
        if ($text === false) {
            throw new \RuntimeException('Could not read file contents.');
        }

        return $text;
    }
}
