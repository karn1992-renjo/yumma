<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Itemised settlement statement (I-flow for restaurants, J-flow for drivers)
 * from the `TaxReportService::settlements()` shape.
 */
class TaxSettlementExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly array $report)
    {
    }

    public function headings(): array
    {
        if (($this->report['type'] ?? 'restaurant') === 'driver') {
            return ['Payout', 'Driver', 'Date', 'Gross Earnings', 'Driver Commission', 'Pre-tax', 'TDS 194C', 'Net Payout', 'Status'];
        }

        return ['Payout', 'Restaurant', 'Date', 'Gross Sales', 'Commission', 'GST on Commission', 'Gateway Fee', 'Pre-tax', 'TDS 194O', 'TCS', 'Net Payout', 'Status'];
    }

    public function collection(): Collection
    {
        $driver = ($this->report['type'] ?? 'restaurant') === 'driver';

        return collect($this->report['rows'] ?? [])->map(function ($r) use ($driver) {
            if ($driver) {
                return [
                    $r['uuid'], $r['party'], $r['created_at'],
                    $r['gross_amount'], $r['platform_commission'],
                    $r['pre_tax_amount'], $r['tds_amount'], $r['net_amount'], $r['status'],
                ];
            }

            return [
                $r['uuid'], $r['party'], $r['created_at'],
                $r['gross_amount'], $r['platform_commission'], $r['gst_on_commission'], $r['payment_gateway_fee'],
                $r['pre_tax_amount'], $r['tds_amount'], $r['tcs_amount'], $r['net_amount'], $r['status'],
            ];
        });
    }
}
