<?php

namespace EasyAI\LaravelAI\Chat\Support\Export;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel export via phpoffice/phpspreadsheet (composer require
 * phpoffice/phpspreadsheet) — optional. One row per message rather than a
 * document layout: the natural tabular shape for filtering/searching a
 * long conversation, or feeding it into another tool.
 */
class XlsxExporter
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    public const EXTENSION = 'xlsx';

    public static function isAvailable(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    public static function build(string $title, Collection $messages): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setTitle($title);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Conversation');

        $headers = ['#', 'Role', 'Timestamp', 'Message'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');

        $row = 2;
        foreach ($messages as $i => $msg) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $msg->role === 'user' ? 'You' : 'Assistant');
            $sheet->setCellValueExplicit(
                "C{$row}",
                optional($msg->created_at)->format('Y-m-d H:i'),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValue("D{$row}", $msg->content);
            $sheet->getStyle("D{$row}")->getAlignment()->setWrapText(true)->setVertical('top');
            $row++;
        }

        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('D')->setWidth(100);
        $sheet->freezePane('A2');

        return self::renderToString($spreadsheet);
    }

    private static function renderToString(Spreadsheet $spreadsheet): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'laravelai_xlsx_');
        (new Xlsx($spreadsheet))->save($tmpPath);
        $contents = file_get_contents($tmpPath) ?: '';
        @unlink($tmpPath);

        return $contents;
    }
}
