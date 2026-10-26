<?php

namespace App\Modules\Reporting\Application;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ReportExporter
{
    public function pdf(array $report): string
    {
        $temp = storage_path('framework/pdf-font-cache');
        if (!is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $pdf = new Mpdf(['mode' => 'utf-8', 'format' => ($report['kind'] ?? '') === 'ticket' ? 'A4' : 'A4-L', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 16, 'tempDir' => $temp, 'fontDir' => [resource_path('fonts')], 'fontdata' => ['vazirmatn' => ['R' => 'Vazirmatn-Regular.ttf', 'useOTL' => 0xFF, 'useKashida' => 0]], 'default_font' => 'vazirmatn', 'default_font_size' => 10]);
        $pdf->SetTitle('گزارش خصوصی مطب');
        $pdf->SetDirectionality('rtl');
        $pdf->SetHTMLFooter('<div style="text-align:center;font-size:9pt">صفحه {PAGENO} از {nbpg}</div>');
        $pdf->WriteHTML(view('reports.document', ['report' => $report, 'pdf' => true])->render());
        return $pdf->Output('', 'S');
    }
    public function xlsx(array $report): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('Ani Doctor')->setTitle('گزارش خصوصی مطب');
        $summary = $book->getActiveSheet();
        $summary->setTitle('خلاصه');
        $summary->setRightToLeft(true);
        $this->cell($summary, 1, 1, $report['title']);
        $summary->mergeCells('A1:B1');
        $this->cell($summary, 1, 2, 'بازه شمسی');
        $this->cell($summary, 2, 2, $report['from'].' تا '.$report['to']);
        $this->cell($summary, 1, 3, 'مبنای گزارش');
        $this->cell($summary, 2, 3, $report['basis']);
        $this->cell($summary, 1, 4, 'زمان تهیه گزارش');
        $this->cell($summary, 2, 4, $report['generated_label'] ?? $report['generated_at']);
        $row = 6;
        foreach ($report['summary'] as $label => $value) {
            $this->cell($summary, 1, $row, $label);
            $this->cell($summary, 2, $row++, $value);
        }
        if (isset($report['reconciliation'])) {
            $this->cell($summary, 1, ++$row, 'تطبیق جاری کل مطب، مستقل از بازه');
            foreach ($report['reconciliation'] as $label => $value) {
                $this->cell($summary, 1, ++$row, $label);
                $this->cell($summary, 2, $row, $value);
            }
        }
        $summary->getColumnDimension('A')->setWidth(58);
        $summary->getColumnDimension('B')->setWidth(70);
        $summary->getDefaultRowDimension()->setRowHeight(30);
        $summary->getStyle('A1:B'.$row)->getAlignment()->setWrapText(true)->setVertical('center');
        $summary->getRowDimension(3)->setRowHeight(72);
        $this->style($summary, 2, $row);
        foreach ($report['sections'] as $section) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr($section['title'], 0, 31));
            $sheet->setRightToLeft(true);
            foreach ($section['headers'] as $column => $header) {
                $this->cell($sheet, $column + 1, 1, $header);
                $sheet->getColumnDimensionByColumn($column + 1)->setWidth($column === 0 ? 40 : 28);
            }
            foreach ($section['rows'] as $index => $values) {
                foreach ($values as $column => $value) {
                    $this->cell($sheet, $column + 1, $index + 2, $value);
                }
                $sheet->getRowDimension($index + 2)->setRowHeight(48);
            }
            $last = max(1, count($section['rows']) + 1);
            $this->style($sheet, count($section['headers']), $last);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter([1, 1, count($section['headers']), $last]);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getStyle([1, 1, count($section['headers']), $last])->getAlignment()->setWrapText(true)->setVertical('center');
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        }
        // Stream to memory: no report containing patient/finance data is left under public or storage.
        $stream = fopen('php://memory', 'w+b');
        try {
            (new Xlsx($book))->save($stream);
            rewind($stream);
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
            $book->disconnectWorksheets();
        }
    }
    private function cell($sheet, int $column, int $row, mixed $value): void
    {
        // Exact numeric amounts; untrusted text is never interpreted as a formula or hyperlink.
        $numeric = is_int($value) && abs($value) <= 999999999999999;
        $sheet->setCellValueExplicit([$column, $row], $numeric ? $value : (string) $value, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        $sheet->getStyle([$column, $row])->getAlignment()
            ->setReadOrder($numeric || preg_match('/^[A-Za-z0-9_:,. +#\-]+$/D', (string) $value) ? Alignment::READORDER_LTR : Alignment::READORDER_RTL)
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if ($numeric) {
            $sheet->getStyle([$column, $row])->getNumberFormat()->setFormatCode('#,##0');
        }
    }
    private function style($sheet, int $columns, int $lastRow): void
    {
        $sheet->getStyle([1, 1, $columns, $lastRow])->getFont()->setName('Vazirmatn')->setSize(11);
        $sheet->getStyle([1, 1, $columns, 1])->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle([1, 1, $columns, 1])->getFill()->setFillType('solid')->getStartColor()->setRGB('176F78');
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getSheetView()->setZoomScale(90);
    }
}
