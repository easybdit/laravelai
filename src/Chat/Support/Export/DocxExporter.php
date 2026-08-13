<?php

namespace EasyAI\LaravelAI\Chat\Support\Export;

use EasyAI\LaravelAI\Chat\Support\PdfMarkdown;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

/**
 * Word export via phpoffice/phpword (composer require phpoffice/phpword) —
 * optional, same graceful-degradation pattern as the PDF/Excel/PowerPoint
 * exporters. Reuses PdfMarkdown::toHtml() for message bodies via PhpWord's
 * own HTML importer, so formatting logic isn't duplicated per format.
 */
class DocxExporter
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    public const EXTENSION = 'docx';

    public static function isAvailable(): bool
    {
        return class_exists(PhpWord::class);
    }

    public static function build(string $title, Collection $messages): string
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection();
        $section->addTitle(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), 1);
        $section->addText(
            'Exported ' . now()->format('Y-m-d H:i') . ' · ' . $messages->count() . ' messages · laraveleasyai',
            ['size' => 8, 'color' => '6B7280']
        );
        $section->addTextBreak();

        foreach ($messages as $msg) {
            $label = $msg->role === 'user' ? 'You' : 'Assistant';
            $time  = optional($msg->created_at)->format('Y-m-d H:i');

            $section->addText("{$label}    {$time}", [
                'bold'  => true,
                'size'  => 9,
                'color' => $msg->role === 'user' ? '1A56DB' : '4F46E5',
            ]);

            Html::addHtml($section, '<div>' . PdfMarkdown::toHtml($msg->content) . '</div>', false, false);
            $section->addTextBreak();
        }

        return self::renderToString($phpWord);
    }

    private static function renderToString(PhpWord $phpWord): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'laravelai_docx_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        $contents = file_get_contents($tmpPath) ?: '';
        @unlink($tmpPath);

        return $contents;
    }
}
