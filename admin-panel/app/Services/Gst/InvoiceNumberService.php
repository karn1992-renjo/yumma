<?php

namespace App\Services\Gst;

use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sequential, per-financial-year invoice numbers. Allocated exactly once per
 * order and then stored on it -- never recomputed.
 */
class InvoiceNumberService
{
    public function allocate(Order $order): string
    {
        if (! empty($order->invoice_number)) {
            return $order->invoice_number;
        }

        $series = strtoupper(trim((string) (AppSetting::getValue('invoice_number_prefix') ?: 'INV'))) ?: 'INV';
        $fy = $this->financialYear($order->created_at ?: now());

        $number = DB::transaction(function () use ($series, $fy) {
            $row = DB::table('invoice_counters')
                ->where('series', $series)
                ->where('financial_year', $fy)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('invoice_counters')->insert([
                    'series' => $series,
                    'financial_year' => $fy,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->last_number + 1;
            DB::table('invoice_counters')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        $formatted = sprintf('%s/%s/%05d', $series, $fy, $number);

        $order->forceFill([
            'invoice_number' => $formatted,
            'invoice_series' => $series,
            'invoice_date' => $order->invoice_date ?: now(),
        ])->saveQuietly();

        return $formatted;
    }

    public function financialYear(Carbon|string $date): string
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return $startYear . '-' . str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);
    }
}
