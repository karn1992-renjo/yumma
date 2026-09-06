<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "Statutory Compliance Calendar" — the format a company secretary / auditor
 * expects: Act, nature, form, authority, frequency, statutory due rule, status,
 * filing date, reference. Entity letterhead on top, grouped by law, frozen head.
 */
class ComplianceRegisterExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    private const CAT_LABELS = [
        'gst' => 'Goods and Services Tax', 'tds' => 'Income-tax — Tax Deducted at Source',
        'income_tax' => 'Income-tax Act, 1961', 'roc' => 'Companies Act, 2013 / LLP Act, 2008',
        'labour' => 'Labour & Social Security', 'licence' => 'Licences & Registrations',
        'cess' => 'Welfare Cess', 'other' => 'Other',
    ];

    private array $rows = [];
    private array $headingRows = [];
    private array $tableHeadRows = [];

    /**
     * @param  Collection<int, \App\Models\ComplianceItem>  $items
     */
    public function __construct(Collection $items, private readonly array $entity)
    {
        $this->build($items);
    }

    public function title(): string
    {
        return 'Compliance Calendar';
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 40, 'C' => 22, 'D' => 16, 'E' => 12, 'F' => 10, 'G' => 26, 'H' => 12, 'I' => 12, 'J' => 18, 'K' => 30];
    }

    public function array(): array
    {
        return $this->rows;
    }

    private function build(Collection $items): void
    {
        $e = $this->entity;
        $this->rows[] = [strtoupper($e['name'] ?? 'COMPANY')];
        $this->rows[] = [trim(implode('   |   ', array_filter([
            ! empty($e['cin']) ? 'CIN: ' . $e['cin'] : null,
            ! empty($e['pan']) ? 'PAN: ' . $e['pan'] : null,
            ! empty($e['gstin']) ? 'GSTIN: ' . $e['gstin'] : null,
        ])))];
        $this->rows[] = ['STATUTORY COMPLIANCE CALENDAR — as on ' . now()->format('d M Y')];
        $this->rows[] = ['Entity classification: ' . ucwords(str_replace('_', ' ', $e['entity_type'] ?? 'private limited company'))];
        $this->rows[] = [''];
        $this->headingRows = [1, 3];

        $head = ['Sr.', 'Nature of Compliance', 'Form / Return', 'Authority', 'Frequency', 'Period', 'Statutory Due (rule)', 'Due Date', 'Status', 'Date of Filing', 'Reference / ARN & Remarks'];

        $grouped = $items->groupBy('category');
        $sr = 0;
        foreach (self::CAT_LABELS as $cat => $label) {
            $group = $grouped->get($cat);
            if (! $group || $group->isEmpty()) {
                continue;
            }
            $this->rows[] = [$label];
            $this->headingRows[] = count($this->rows);
            $this->rows[] = $head;
            $this->tableHeadRows[] = count($this->rows);

            foreach ($group as $item) {
                $sr++;
                $this->rows[] = [
                    $sr,
                    $item->name,
                    $this->formName($item),
                    $item->authority,
                    ucfirst($item->frequency),
                    $item->period ?: '—',
                    $item->due_rule ?: '—',
                    $item->due_date ? $item->due_date->format('d-M-Y') : '—',
                    strtoupper(str_replace('_', ' ', $item->status)),
                    $item->filed_on ? $item->filed_on->format('d-M-Y') : '—',
                    trim(($item->reference_no ? $item->reference_no . ' — ' : '') . ($item->notes ?? '')),
                ];
            }
            $this->rows[] = [''];
        }
    }

    private function formName(\App\Models\ComplianceItem $item): string
    {
        return match ($item->code) {
            'GSTR1' => 'GSTR-1', 'GSTR3B' => 'GSTR-3B', 'GSTR8' => 'GSTR-8',
            'GSTR9' => 'GSTR-9', 'GSTR9C' => 'GSTR-9C', 'TDS26Q' => 'Form 26Q',
            'FORM16A' => 'Form 16A', 'TDS_CHALLAN' => 'ITNS-281', 'ITR' => 'ITR-6 / ITR-5',
            'TAX_AUDIT' => 'Form 3CA-3CD', 'ROC_AOC4' => 'Form AOC-4', 'ROC_MGT7' => 'Form MGT-7 / 7A',
            'DIR3_KYC' => 'Form DIR-3 KYC', 'LLP_FORM8' => 'LLP Form 8', 'LLP_FORM11' => 'LLP Form 11',
            'PF' => 'ECR', 'ESIC' => 'ESIC Return', 'ADVANCE_TAX' => 'Challan 280',
            default => '—',
        };
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $sheet->getHighestRow();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('555555');
                $sheet->getStyle('A3:A4')->getFont()->setBold(true)->setSize(10);
                foreach (['A1:K1', 'A2:K2', 'A3:K3', 'A4:K4'] as $r) {
                    $sheet->mergeCells($r);
                }

                $sheet->getStyle("A1:K$last")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                $sheet->getStyle("A1:K$last")->getFont()->setName('Calibri')->setSize(9);

                foreach ($this->headingRows as $r) {
                    $sheet->getStyle("A$r:K$r")->getFont()->setBold(true);
                    $sheet->getStyle("A$r:K$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E4E9F0');
                }
                foreach ($this->tableHeadRows as $r) {
                    $sheet->getStyle("A$r:K$r")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle("A$r:K$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('33475B');
                    $sheet->getStyle("A$r:K$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }
                $sheet->getStyle("A1:K$last")->getBorders()->getInside()->setBorderStyle(Border::BORDER_HAIR);
                $sheet->getStyle("A1:K$last")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                $sheet->freezePane('A6');
            },
        ];
    }
}
