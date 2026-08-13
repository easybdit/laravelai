<?php

namespace EasyAI\LaravelAI\Chat\Support\Export;

use Illuminate\Support\Collection;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

/**
 * PowerPoint export via phpoffice/phppresentation (composer require
 * phpoffice/phppresentation) — optional. A slide deck is a genuinely
 * unusual shape for a chat transcript (slides don't paginate long text the
 * way a document does), so this is the one export format best suited to
 * short-to-medium conversations — one slide per message, font size steps
 * down for longer replies rather than silently truncating them. For a
 * long transcript, PDF or Word read far better; this exists because it
 * was asked for, not because it's the natural fit.
 */
class PptxExporter
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    public const EXTENSION = 'pptx';

    private const SLIDE_WIDTH  = 860;
    private const SLIDE_MARGIN = 40;

    public static function isAvailable(): bool
    {
        return class_exists(PhpPresentation::class);
    }

    public static function build(string $title, Collection $messages): string
    {
        $presentation = new PhpPresentation();
        $presentation->removeSlideByIndex(0);

        self::addTitleSlide($presentation, $title, $messages->count());

        foreach ($messages as $msg) {
            self::addMessageSlide($presentation, $msg);
        }

        return self::renderToString($presentation);
    }

    private static function addTitleSlide(PhpPresentation $presentation, string $title, int $count): void
    {
        $slide = $presentation->createSlide();

        $shape = $slide->createRichTextShape()
            ->setHeight(150)->setWidth(self::SLIDE_WIDTH)
            ->setOffsetX(self::SLIDE_MARGIN)->setOffsetY(220);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $shape->createTextRun($title)->getFont()->setSize(32)->setBold(true)->setColor(new Color('FF1A1D27'));

        $sub = $shape->createParagraph();
        $sub->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sub->createTextRun(now()->format('Y-m-d H:i') . ' · ' . $count . ' messages · laraveleasyai')
            ->getFont()->setSize(14)->setColor(new Color('FF6B7280'));
    }

    private static function addMessageSlide(PhpPresentation $presentation, $msg): void
    {
        $slide = $presentation->createSlide();
        $label = $msg->role === 'user' ? 'You' : 'Assistant';

        $labelShape = $slide->createRichTextShape()
            ->setHeight(40)->setWidth(self::SLIDE_WIDTH)
            ->setOffsetX(self::SLIDE_MARGIN)->setOffsetY(28);
        $labelShape->createTextRun($label . '   ·   ' . optional($msg->created_at)->format('Y-m-d H:i'))
            ->getFont()->setBold(true)->setSize(14)
            ->setColor(new Color($msg->role === 'user' ? 'FF1A56DB' : 'FF4F46E5'));

        $plain = self::toPlainText((string) $msg->content);

        $bodyShape = $slide->createRichTextShape()
            ->setHeight(480)->setWidth(self::SLIDE_WIDTH)
            ->setOffsetX(self::SLIDE_MARGIN)->setOffsetY(85);
        $bodyShape->setWrap(true);
        $bodyShape->createTextRun($plain)
            ->getFont()->setSize(mb_strlen($plain) > 900 ? 12 : 16)->setColor(new Color('FF1A1D27'));
    }

    /** Slides show plain text, not markup — strip markdown syntax rather than trying to render it. */
    private static function toPlainText(string $markdown): string
    {
        $text = preg_replace('/```[a-zA-Z0-9_+-]*\n(.*?)\n?```/s', '$1', $markdown);
        $text = preg_replace('/[`*_#]/', '', (string) $text);

        return trim((string) $text);
    }

    private static function renderToString(PhpPresentation $presentation): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'laravelai_pptx_');
        IOFactory::createWriter($presentation, 'PowerPoint2007')->save($tmpPath);
        $contents = file_get_contents($tmpPath) ?: '';
        @unlink($tmpPath);

        return $contents;
    }
}
