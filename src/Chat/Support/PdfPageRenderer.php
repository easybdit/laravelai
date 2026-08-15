<?php

namespace EasyAI\LaravelAI\Chat\Support;

/**
 * Rasterizes a PDF's pages to PNG images — the missing half of PDF
 * "understanding" that plain text extraction (TextExtractor, always on)
 * can't cover: a chart, diagram, scanned table, or photo embedded in the
 * PDF has no text layer at all, or one too mangled to be useful, but is
 * completely legible to a vision-capable model once it's an actual image.
 *
 * Requires the PHP `imagick` extension with a Ghostscript delegate for PDF
 * support — genuinely a system-level dependency (a native PDF rasterizer,
 * not something a pure-PHP composer package can provide), so this is
 * opt-in (config('ai.chat.attachments.pdf_vision_enabled')) and throws a
 * clear, actionable message rather than a cryptic fatal when it's missing.
 */
class PdfPageRenderer
{
    /**
     * @return string[] Raw PNG bytes, one entry per rendered page, in page order.
     */
    public static function render(string $pdfPath, int $maxPages): array
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            throw new \RuntimeException(
                'PDF page rendering requires the PHP "imagick" extension (with a Ghostscript '
                . 'delegate) — not installed on this server. Install it at the system level '
                . '(e.g. `apt-get install php-imagick ghostscript` or your platform\'s equivalent); '
                . 'there is no composer package that can substitute for it.'
            );
        }

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF not found at: {$pdfPath}");
        }

        $maxPages = max(1, $maxPages);

        $probe = new \Imagick();
        try {
            $probe->pingImage($pdfPath);
            $pageCount = min($probe->getNumberImages(), $maxPages);
        } finally {
            $probe->clear();
        }

        $images = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $page = new \Imagick();
            try {
                // Resolution must be set before readImage() for a PDF source —
                // it controls the rasterization DPI, not a post-hoc resize.
                $page->setResolution(150, 150);
                $page->readImage("{$pdfPath}[{$i}]");
                $page->setImageFormat('png');
                // PDFs are commonly transparent-background; flatten onto white
                // so the rendered page reads the way it would printed, and so
                // downstream JPEG re-encoding (if any) never shows black where
                // transparency was.
                $page->setImageBackgroundColor('white');
                $flattened = $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $images[] = $flattened->getImageBlob();
                $flattened->clear();
            } finally {
                $page->clear();
            }
        }

        return $images;
    }
}
