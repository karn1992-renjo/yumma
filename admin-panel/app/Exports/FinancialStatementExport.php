<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * A presentation-grade financial statement sheet: entity letterhead block,
 * Schedule III ordering for Balance Sheet / P&L, comparative columns, bold
 * totals with accounting rules, and a signature block.
 */
class FinancialStatementExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    /** @var array<int, array{0:string,1:string,2:string,3:string, style?:string}> */
    private array $rows = [];

    private array $boldRows = [];
    private array $ruleTopRows = [];
    private array $doubleRuleRows = [];
    private array $headingRows = [];

    public function __construct(private readonly string $name, private readonly array $report)
    {
        $this->build();
    }

    public function title(): string
    {
        return substr($this->name, 0, 28);
    }

    public function columnWidths(): array
    {
        return ['A' => 52, 'B' => 8, 'C' => 20, 'D' => 20];
    }

    public function array(): array
    {
        return $this->rows;
    }

    /* ----------------------------------------------------------------- */

    private function push(array $cells, ?string $style = null): void
    {
        $this->rows[] = array_pad($cells, 4, '');
        $i = count($this->rows); // 1-based sheet row (FromArray writes from row 1)
        if ($style === 'bold') {
            $this->boldRows[] = $i;
        } elseif ($style === 'heading') {
            $this->headingRows[] = $i;
        } elseif ($style === 'rule') {
            $this->ruleTopRows[] = $i;
            $this->boldRows[] = $i;
        } elseif ($style === 'double') {
            $this->doubleRuleRows[] = $i;
            $this->boldRows[] = $i;
        }
    }

    private function build(): void
    {
        $e = $this->report['entity'] ?? [];
        $sym = $e['currency'] ?? '';

        // Letterhead (rows 1-5)
        $this->rows[] = [strtoupper($e['name'] ?? 'COMPANY'), '', '', ''];
        $meta = array_filter([
            ! empty($e['cin']) ? 'CIN: ' . $e['cin'] : null,
            ! empty($e['pan']) ? 'PAN: ' . $e['pan'] : null,
            ! empty($e['gstin']) ? 'GSTIN: ' . $e['gstin'] : null,
        ]);
        $this->rows[] = [implode('   |   ', $meta), '', '', ''];
        $this->rows[] = [$e['address'] ?? '', '', '', ''];
        $this->rows[] = ['', '', '', ''];
        $this->headingRows[] = 1;

        if (isset($this->report['equity_and_liabilities'])) {
            $this->buildBalanceSheet($sym);
        } elseif (isset($this->report['rows']) && isset($this->report['notes'])) {
            $this->buildProfitLoss($sym);
        } elseif (isset($this->report['rows'])) {
            $this->buildTrialBalance($sym);
        } else {
            $this->buildCashFlow($sym);
        }
    }

    private function money($v): string
    {
        $v = (float) $v;

        return ($v < 0 ? '(' : '') . number_format(abs($v), 2) . ($v < 0 ? ')' : '');
    }

    private function buildBalanceSheet(string $sym): void
    {
        $r = $this->report;
        $as = \Illuminate\Support\Carbon::parse($r['as_of'])->format('d M Y');
        $pr = \Illuminate\Support\Carbon::parse($r['prior_as_of'])->format('d M Y');

        $this->push(['Balance Sheet as at ' . $as], 'heading');
        $this->push(['Division I, Schedule III to the Companies Act, 2013   ·   Amounts in ' . $sym]);
        $this->push(['']);
        $this->push(['Particulars', 'Note', 'As at ' . $as, 'As at ' . $pr], 'bold');

        $this->push(['I.  EQUITY AND LIABILITIES'], 'heading');
        foreach ($r['equity_and_liabilities'] as $i => $section) {
            $this->push(['    ' . ($i + 1) . '. ' . $section['head']], 'bold');
            foreach ($section['lines'] as $l) {
                $this->push(['        ' . $l['label'], $l['note'], $this->money($l['current']), $this->money($l['prior'])]);
            }
        }
        $t = $r['totals']['total_equity_liabilities'];
        $this->push(['TOTAL EQUITY AND LIABILITIES', '', $this->money($t['current']), $this->money($t['prior'])], 'double');

        $this->push(['']);
        $this->push(['II.  ASSETS'], 'heading');
        foreach ($r['assets'] as $i => $section) {
            $this->push(['    ' . ($i + 1) . '. ' . $section['head']], 'bold');
            foreach ($section['lines'] as $l) {
                $this->push(['        ' . $l['label'], $l['note'], $this->money($l['current']), $this->money($l['prior'])]);
            }
        }
        $ta = $r['totals']['total_assets'];
        $this->push(['TOTAL ASSETS', '', $this->money($ta['current']), $this->money($ta['prior'])], 'double');

        $this->push(['']);
        $this->push(['Notes 1-' . count($r['notes']) . ' form an integral part of these financial statements.']);
        $this->push(['']);
        $this->push(['']);
        $this->push(['For and on behalf of the Board', '', 'As per our report of even date', '']);
        $this->push(['', '', '', '']);
        $this->push(['Director', '', 'Statutory Auditor', '']);

        $this->buildNotes($r['notes'], $as, $pr);
    }

    private function buildProfitLoss(string $sym): void
    {
        $r = $this->report;
        $cf = \Illuminate\Support\Carbon::parse($r['period']['from'])->format('d M Y');
        $ct = \Illuminate\Support\Carbon::parse($r['period']['to'])->format('d M Y');
        $pf = \Illuminate\Support\Carbon::parse($r['prior_period']['from'])->format('d M Y');
        $pt = \Illuminate\Support\Carbon::parse($r['prior_period']['to'])->format('d M Y');

        $this->push(['Statement of Profit and Loss for the period ' . $cf . ' to ' . $ct], 'heading');
        $this->push(['Division I, Schedule III to the Companies Act, 2013   ·   Amounts in ' . $sym]);
        $this->push(['']);
        $this->push(['Particulars', 'Note', $cf . ' - ' . $ct, $pf . ' - ' . $pt], 'bold');

        foreach ($r['rows'] as $row) {
            $isTotal = ! empty($row['total']);
            $this->push([
                ($isTotal ? strtoupper($row['label']) : '    ' . $row['label']),
                $row['note'] ?? '',
                $this->money($row['current']),
                $this->money($row['prior']),
            ], $isTotal ? 'rule' : null);
        }

        $this->push(['']);
        $this->push(['Notes 1-' . count($r['notes']) . ' form an integral part of these financial statements.']);
        $this->push(['']);
        $this->push(['']);
        $this->push(['For and on behalf of the Board', '', 'As per our report of even date', '']);
        $this->push(['', '', '', '']);
        $this->push(['Director', '', 'Statutory Auditor', '']);

        $this->buildNotes($r['notes'], 'Current', 'Prior');
    }

    private function buildNotes(array $notes, string $curLabel, string $priorLabel): void
    {
        $this->push(['']);
        $this->push(['NOTES FORMING PART OF THE FINANCIAL STATEMENTS'], 'heading');
        foreach ($notes as $note) {
            $this->push(['']);
            $this->push(['Note ' . $note['no'] . ' — ' . $note['title']], 'bold');
            $this->push(['Particulars', '', $curLabel, $priorLabel], 'bold');
            $curBy = collect($note['current'])->keyBy('name');
            $prBy = collect($note['prior'])->keyBy('name');
            $names = $curBy->keys()->merge($prBy->keys())->unique();
            if ($names->isEmpty()) {
                $this->push(['    Nil', '', '', '']);
            }
            foreach ($names as $name) {
                $this->push([
                    '    ' . $name, '',
                    $this->money(optional($curBy->get($name))['amount'] ?? 0),
                    $this->money(optional($prBy->get($name))['amount'] ?? 0),
                ]);
            }
            $this->push(['Total', '', $this->money($note['current_total']), $this->money($note['prior_total'])], 'rule');
        }
    }

    private function buildTrialBalance(string $sym): void
    {
        $r = $this->report;
        $this->push(['Trial Balance as at ' . \Illuminate\Support\Carbon::parse($r['as_of'] ?? now())->format('d M Y')], 'heading');
        $this->push(['Amounts in ' . $sym]);
        $this->push(['']);
        $this->push(['Account', 'Type', 'Debit', 'Credit'], 'bold');
        foreach ($r['rows'] as $x) {
            $this->push([$x['code'] . '  ' . $x['name'], ucfirst($x['type']), $this->money($x['debit']), $this->money($x['credit'])]);
        }
        $this->push(['TOTAL', '', $this->money($r['total_debit']), $this->money($r['total_credit'])], 'double');
    }

    private function buildCashFlow(string $sym): void
    {
        $r = $this->report;
        $this->push(['Cash Flow Statement'], 'heading');
        $this->push(['Amounts in ' . $sym]);
        $this->push(['']);
        $this->push(['Particulars', '', 'Amount', ''], 'bold');
        $this->push(['Opening cash and cash equivalents', '', $this->money($r['opening_cash'] ?? 0), '']);
        foreach (($r['by_activity'] ?? []) as $activity => $amount) {
            $this->push(['    Net cash from ' . $activity . ' activities', '', $this->money($amount), '']);
        }
        $this->push(['Net increase / (decrease) in cash', '', $this->money($r['net_change'] ?? 0), ''], 'rule');
        $this->push(['Closing cash and cash equivalents', '', $this->money($r['closing_cash'] ?? 0), ''], 'double');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $sheet->getHighestRow();

                $sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(15);
                $sheet->getStyle('A2:D3')->getFont()->setSize(9)->getColor()->setRGB('555555');
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');
                $sheet->mergeCells('A3:D3');

                $sheet->getStyle("A1:D$last")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("C1:D$last")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A1:D$last")->getFont()->setName('Calibri');

                foreach ($this->headingRows as $r) {
                    $sheet->getStyle("A$r:D$r")->getFont()->setBold(true)->setSize(11);
                    $sheet->getStyle("A$r:D$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDEDED');
                }
                foreach (array_unique($this->boldRows) as $r) {
                    $sheet->getStyle("A$r:D$r")->getFont()->setBold(true);
                }
                foreach (array_unique($this->ruleTopRows) as $r) {
                    $sheet->getStyle("A$r:D$r")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                }
                foreach (array_unique($this->doubleRuleRows) as $r) {
                    $sheet->getStyle("A$r:D$r")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("A$r:D$r")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
                }
            },
        ];
    }
}
