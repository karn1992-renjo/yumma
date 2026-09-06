<?php

namespace App\Exports;

use App\Exports\Concerns\WithStatutoryStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class Gstr3bExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'GSTR-3B';
    }

    protected function statutoryTitle(): string
    {
        return 'FORM GSTR-3B  ·  Monthly summary return  [Rule 61(5)]';
    }

    protected function statutoryNote(): string
    {
        $p = $this->report['period'] ?? [];

        return 'Tax period ' . ($p['from'] ?? '') . ' to ' . ($p['to'] ?? '');
    }

    public function headings(): array
    {
        return ['Nature of Supplies', 'Total Taxable Value', 'Integrated Tax', 'Central Tax', 'State/UT Tax', 'Cess'];
    }

    public function collection(): Collection
    {
        $r = $this->report;

        return collect([
            ['3.1(a) Outward taxable supplies (other than zero rated, nil rated and exempted)', $r['outward']['taxable_value'], 0, $r['outward']['cgst'], $r['outward']['sgst'], 0],
            ['3.1.1(i) Taxable supplies on which ECO pays tax u/s 9(5)', $r['eco_9_5']['taxable_value'], 0, $r['eco_9_5']['cgst'], $r['eco_9_5']['sgst'], 0],
            ['4(A)(5) ITC available — All other ITC', '', 0, $r['itc']['cgst'], $r['itc']['sgst'], 0],
            ['4(C) Net ITC available', '', 0, $r['itc']['cgst'], $r['itc']['sgst'], 0],
        ]);
    }
}
