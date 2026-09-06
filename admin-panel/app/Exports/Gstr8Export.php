<?php

namespace App\Exports;

use App\Exports\Concerns\WithStatutoryStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class Gstr8Export implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'GSTR-8';
    }

    protected function statutoryTitle(): string
    {
        return 'FORM GSTR-8  ·  Statement of TCS collected u/s 52  [Rule 67(1)]';
    }

    protected function statutoryNote(): ?string
    {
        $p = $this->report['period'] ?? [];

        return isset($p['from']) ? 'Tax period ' . $p['from'] . ' to ' . $p['to'] : null;
    }

    public function headings(): array
    {
        return ['GSTIN of Supplier', 'Legal / Trade Name', 'Gross Value of Supplies (₹)', 'Value on which TCS to be collected (₹)', 'Central Tax (₹)', 'State/UT Tax (₹)', 'Total TCS (₹)'];
    }

    public function collection(): Collection
    {
        return collect($this->report['suppliers'] ?? [])->map(fn ($r) => [
            $r['gstin'] ?: 'URP',
            $r['restaurant'],
            $r['gross_value'],
            $r['gross_value'],
            $r['cgst'],
            $r['sgst'],
            $r['tcs'],
        ]);
    }
}
