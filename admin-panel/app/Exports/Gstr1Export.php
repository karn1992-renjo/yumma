<?php

namespace App\Exports;

use App\Exports\Concerns\WithStatutoryStyling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class Gstr1Export implements WithMultipleSheets
{
    public function __construct(
        private readonly array $report,
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {
    }

    public function sheets(): array
    {
        return array_values(array_filter([
            new Gstr1SummarySheet($this->report),
            new Gstr1B2csSheet($this->report),
            isset($this->report['b2b_commission']) ? new Gstr1B2bSheet($this->report) : null,
            new Gstr1HsnSheet($this->report),
            new Gstr1DocsSheet($this->report),
        ]));
    }
}

class Gstr1SummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'Summary';
    }

    protected function statutoryTitle(): string
    {
        return 'FORM GSTR-1  ·  Details of outward supplies of goods or services  [Rule 59(1)]';
    }

    protected function statutoryNote(): string
    {
        $r = $this->report;

        return 'GSTIN ' . ($r['gstin'] ?: '—') . '  ·  Tax period ' . $r['period']['from'] . ' to ' . $r['period']['to'];
    }

    public function headings(): array
    {
        return ['Description', 'Value (₹)'];
    }

    public function collection(): Collection
    {
        $r = $this->report;

        return collect([
            ['Aggregate turnover — tax invoices in period', $r['invoice_count']],
            ['Total taxable value (Tables 7 + 4)', $r['totals']['taxable_value']],
            ['Total Central Tax (CGST)', $r['totals']['cgst']],
            ['Total State/UT Tax (SGST)', $r['totals']['sgst']],
            ['Total Integrated Tax (IGST)', $r['totals']['igst'] ?? 0],
            ['Total invoice value', $r['totals']['invoice_value']],
        ]);
    }
}

class Gstr1B2csSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'B2CS(7)';
    }

    protected function statutoryTitle(): string
    {
        return 'Table 7  ·  B2C (Others) — supplies to unregistered persons';
    }

    public function headings(): array
    {
        return ['Type', 'Place of Supply (State/UT)', 'Rate (%)', 'Taxable Value (₹)', 'Cess (₹)', 'Central Tax (₹)', 'State/UT Tax (₹)'];
    }

    public function collection(): Collection
    {
        return collect($this->report['b2cs'])->map(fn ($r) => [
            'OE',
            $r['place_of_supply'],
            $r['rate'],
            $r['taxable_value'],
            0,
            $r['cgst'],
            $r['sgst'],
        ]);
    }
}

class Gstr1B2bSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'B2B(4)';
    }

    protected function statutoryTitle(): string
    {
        return 'Table 4  ·  B2B — commission invoices to registered restaurants (supplier: ECO)';
    }

    public function headings(): array
    {
        return ['Recipient GSTIN/UIN', 'Recipient Name', 'Invoice Value (₹)', 'Rate (%)', 'Taxable Value (₹)', 'Central Tax (₹)', 'State/UT Tax (₹)'];
    }

    public function collection(): Collection
    {
        return collect($this->report['b2b_commission'] ?? [])->map(fn ($r) => [
            $r['gstin'],
            $r['name'],
            round((float) $r['taxable_value'] + (float) $r['cgst'] + (float) $r['sgst'], 2),
            $r['rate'] ?? 18,
            $r['taxable_value'],
            $r['cgst'],
            $r['sgst'],
        ]);
    }
}

class Gstr1HsnSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'HSN(12)';
    }

    protected function statutoryTitle(): string
    {
        return 'Table 12  ·  HSN-wise summary of outward supplies';
    }

    public function headings(): array
    {
        return ['HSN / SAC', 'Description', 'UQC', 'Total Quantity', 'Rate (%)', 'Taxable Value (₹)', 'Central Tax (₹)', 'State/UT Tax (₹)'];
    }

    public function collection(): Collection
    {
        return collect($this->report['hsn'])->map(fn ($r) => [
            $r['hsn'],
            $r['description'] ?? 'Restaurant & platform services',
            'NA',
            $r['quantity'],
            $r['rate'],
            $r['taxable_value'],
            $r['cgst'],
            $r['sgst'],
        ]);
    }
}

class Gstr1DocsSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $report)
    {
    }

    public function title(): string
    {
        return 'Docs(13)';
    }

    protected function statutoryTitle(): string
    {
        return 'Table 13  ·  Documents issued during the tax period';
    }

    public function headings(): array
    {
        return ['Nature of Document', 'Sr. No. From', 'Sr. No. To', 'Total Number', 'Cancelled', 'Net Issued'];
    }

    public function collection(): Collection
    {
        $r = $this->report;
        $count = (int) ($r['invoice_count'] ?? 0);

        return collect([
            ['Invoices for outward supply', $r['first_invoice_no'] ?? '-', $r['last_invoice_no'] ?? '-', $count, 0, $count],
        ]);
    }
}
