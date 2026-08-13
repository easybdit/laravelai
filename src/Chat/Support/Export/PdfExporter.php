<?php

namespace EasyAI\LaravelAI\Chat\Support\Export;

use Dompdf\Dompdf;
use Illuminate\Support\Collection;

/**
 * PDF export via dompdf/dompdf (composer require dompdf/dompdf) — optional,
 * same graceful-degradation pattern as the Word/Excel/PowerPoint exporters.
 */
class PdfExporter
{
    public const MIME = 'application/pdf';
    public const EXTENSION = 'pdf';

    public static function isAvailable(): bool
    {
        return class_exists(Dompdf::class);
    }

    public static function build(string $title, Collection $messages): string
    {
        $html = view('laravelai::pdf.conversation', [
            'title'      => $title,
            'messages'   => $messages,
            'exportedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}
