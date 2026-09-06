<?php

namespace App\Exports;

use App\Models\TaxLedgerEntry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TaxLedgerExport implements FromCollection, WithHeadings, WithMapping
{
    /** @param \Illuminate\Support\Collection<TaxLedgerEntry> $entries */
    public function __construct(private readonly Collection $entries)
    {
    }

    public function collection(): Collection
    {
        return $this->entries;
    }

    public function headings(): array
    {
        return ['Date', 'Kind', 'Section', 'Party', 'Order', 'Payout', 'Taxable Value', 'Rate %', 'CGST', 'SGST', 'Amount', 'FY', 'Period', 'Status', 'Challan No'];
    }

    public function map($e): array
    {
        return [
            optional($e->created_at)->toDateTimeString(),
            $e->kind,
            $e->section,
            $e->party_type ? class_basename($e->party_type) . ' #' . $e->party_id : 'Platform',
            $e->order_id,
            $e->payout_id,
            (float) $e->taxable_value,
            (float) $e->rate,
            (float) $e->cgst,
            (float) $e->sgst,
            (float) $e->amount,
            $e->fy,
            $e->period,
            $e->status,
            $e->challan_no,
        ];
    }
}
