<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Gstr1Export;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class GstReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $report = $this->build($from, $to);

        return view('admin.gst-reports.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'report' => $report,
            'symbol' => AppSetting::sanitizedCurrencySymbol(),
            'decimals' => AppSetting::currencyDecimals(),
            'gstEnabled' => (string) AppSetting::getValue('business_gst_enabled', '0') === '1',
        ]);
    }

    public function exportGstr1(Request $request)
    {
        [$from, $to] = $this->range($request);
        $report = $this->build($from, $to);
        $name = 'gstr1-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';

        return Excel::download(new Gstr1Export($report, $from, $to), $name);
    }

    public function exportJson(Request $request)
    {
        [$from, $to] = $this->range($request);

        return response()->json($this->build($from, $to));
    }

    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * GSTR-1 style aggregation from the persisted per-order `tax_breakdown`.
     * B2CS (Table 7) grouped by place of supply + rate; HSN summary
     * (Table 12) grouped by HSN + rate.
     */
    private function build(Carbon $from, Carbon $to): array
    {
        $orders = Order::query()
            ->where('invoice_type', 'tax_invoice')
            ->whereBetween('invoice_date', [$from, $to])
            ->orWhere(function ($q) use ($from, $to) {
                $q->where('invoice_type', 'tax_invoice')
                    ->whereNull('invoice_date')
                    ->whereBetween('created_at', [$from, $to]);
            })
            ->get(['id', 'order_number', 'invoice_number', 'invoice_date', 'created_at', 'place_of_supply', 'tax_breakdown', 'cgst_amount', 'sgst_amount', 'total']);

        $b2cs = [];
        $hsn = [];
        $round = static fn ($v) => round((float) $v, 2);

        foreach ($orders as $order) {
            $bd = is_array($order->tax_breakdown) ? $order->tax_breakdown : [];
            $pos = $order->place_of_supply ?: ($bd['place_of_supply'] ?? 'Unknown');

            foreach (($bd['rate_summary'] ?? []) as $r) {
                $key = $pos . '|' . number_format((float) $r['rate'], 2, '.', '');
                $b2cs[$key] ??= [
                    'place_of_supply' => $pos,
                    'rate' => (float) $r['rate'],
                    'taxable_value' => 0.0,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                ];
                $b2cs[$key]['taxable_value'] += (float) $r['taxable_value'];
                $b2cs[$key]['cgst'] += (float) $r['cgst'];
                $b2cs[$key]['sgst'] += (float) $r['sgst'];
            }

            foreach (array_merge($bd['lines'] ?? [], $bd['charges'] ?? []) as $row) {
                $code = $row['hsn'] ?? 'NA';
                $rate = (float) ($row['rate'] ?? 0);
                $key = $code . '|' . number_format($rate, 2, '.', '');
                $hsn[$key] ??= [
                    'hsn' => $code,
                    'rate' => $rate,
                    'quantity' => 0,
                    'taxable_value' => 0.0,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                ];
                $hsn[$key]['quantity'] += (int) ($row['qty'] ?? 0);
                $hsn[$key]['taxable_value'] += (float) ($row['taxable_value'] ?? 0);
                $hsn[$key]['cgst'] += (float) ($row['cgst'] ?? 0);
                $hsn[$key]['sgst'] += (float) ($row['sgst'] ?? 0);
            }
        }

        $normalise = function (array $group) use ($round) {
            return collect($group)->map(function ($r) use ($round) {
                foreach (['taxable_value', 'cgst', 'sgst'] as $k) {
                    $r[$k] = $round($r[$k]);
                }

                return $r;
            })->values()->all();
        };

        $b2cs = $normalise($b2cs);
        $hsn = $normalise($hsn);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'gstin' => (string) (AppSetting::getValue('business_gstin') ?: AppSetting::getValue('einvoice_gstin') ?: ''),
            'invoice_count' => $orders->count(),
            'totals' => [
                'taxable_value' => $round(collect($b2cs)->sum('taxable_value')),
                'cgst' => $round(collect($b2cs)->sum('cgst')),
                'sgst' => $round(collect($b2cs)->sum('sgst')),
                'invoice_value' => $round($orders->sum('total')),
            ],
            'b2cs' => $b2cs,
            'hsn' => $hsn,
        ];
    }
}
