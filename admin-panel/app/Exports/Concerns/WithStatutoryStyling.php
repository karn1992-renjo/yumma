<?php

namespace App\Exports\Concerns;

use App\Models\AppSetting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Gives a plain FromCollection/WithHeadings export the look of a filed return
 * worksheet: entity letterhead + return title above the data, a dark bold
 * heading band, thin gridlines and auto-fit columns.
 *
 * The using class may define:
 *   protected function statutoryTitle(): string   — e.g. "FORM GSTR-1  ·  Details of outward supplies"
 *   protected function statutoryNote(): ?string    — e.g. "GSTIN 27AB.... · Apr 2026"
 */
trait WithStatutoryStyling
{
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);
                $span = 'A1:' . $lastCol . '1';

                // Push data down and stamp the letterhead.
                $sheet->insertNewRowBefore(1, 4);
                $entity = AppSetting::getValue('business_legal_name')
                    ?: AppSetting::getValue('invoice_company_name')
                    ?: AppSetting::getValue('app_name', 'Company');
                $ids = array_filter([
                    AppSetting::getValue('business_gstin') ? 'GSTIN: ' . AppSetting::getValue('business_gstin') : null,
                    AppSetting::getValue('business_pan') ? 'PAN: ' . AppSetting::getValue('business_pan') : null,
                    AppSetting::getValue('business_tan') ? 'TAN: ' . AppSetting::getValue('business_tan') : null,
                ]);

                $sheet->setCellValue('A1', strtoupper((string) $entity));
                $sheet->setCellValue('A2', implode('   |   ', $ids));
                $sheet->setCellValue('A3', method_exists($this, 'statutoryTitle') ? $this->statutoryTitle() : 'Statutory return worksheet');
                $note = method_exists($this, 'statutoryNote') ? $this->statutoryNote() : null;
                $sheet->setCellValue('A4', trim(($note ? $note . '  ·  ' : '') . 'Generated ' . now()->format('d M Y, H:i') . '  ·  Working paper, not a filed return'));

                foreach (['A1', 'A2', 'A3', 'A4'] as $i => $cell) {
                    $sheet->mergeCells('A' . ($i + 1) . ':' . $lastCol . ($i + 1));
                }
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('555555');
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A4')->getFont()->setSize(8)->getColor()->setRGB('777777');

                // Heading band is now row 5.
                $headSpan = 'A5:' . $lastCol . '5';
                $sheet->getStyle($headSpan)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($headSpan)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('33475B');
                $sheet->getStyle($headSpan)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

                $last = $sheet->getHighestRow();
                $sheet->getStyle("A5:$lastCol$last")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
                $sheet->getStyle("A5:$lastCol$last")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

                for ($c = 1; $c <= $lastColIdx; $c++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
                }
                $sheet->freezePane('A6');
                unset($span);
            },
        ];
    }
}
