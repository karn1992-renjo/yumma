<?php

namespace App\Services\Tax;

use App\Models\Order;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;
use App\Models\TdsDeducteeTotal;
use Illuminate\Support\Carbon;

/**
 * All Business Accounting aggregations, read from `tax_ledger_entries`
 * (+ payouts / orders for the settlement statements). Nothing here recomputes
 * tax -- it only rolls up what was already accrued.
 */
class TaxReportService
{
    public function __construct(private readonly TaxConfig $config = new TaxConfig())
    {
    }

    /* ---------------- period helpers ---------------- */

    public function range(?string $from, ?string $to): array
    {
        $f = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $t = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        if ($t->lt($f)) {
            [$f, $t] = [$t->copy()->startOfDay(), $f->copy()->endOfDay()];
        }

        return [$f, $t];
    }

    /* ---------------- overview ---------------- */

    public function overview(Carbon $from, Carbon $to): array
    {
        $rows = TaxLedgerEntry::whereBetween('created_at', [$from, $to])->get();

        $sum = fn (string $kind, string $col = 'amount') => round((float) $rows->where('kind', $kind)->sum($col), 2);

        $fy = $this->config->fy(now());
        $ytd = TaxLedgerEntry::where('fy', $fy)->get();
        $ytdSum = fn (string $kind) => round((float) $ytd->where('kind', $kind)->sum('amount'), 2);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'fy' => $fy],
            'gst_9_5' => $sum(TaxLedgerEntry::KIND_GST_9_5),
            'gst_service' => $sum(TaxLedgerEntry::KIND_GST_SERVICE),
            'gst_commission' => $sum(TaxLedgerEntry::KIND_GST_COMMISSION),
            'gst_output_total' => $sum(TaxLedgerEntry::KIND_GST_9_5) + $sum(TaxLedgerEntry::KIND_GST_SERVICE) + $sum(TaxLedgerEntry::KIND_GST_COMMISSION),
            'tcs' => $sum(TaxLedgerEntry::KIND_TCS),
            'tds_194o' => $sum(TaxLedgerEntry::KIND_TDS_194O),
            'tds_194c' => $sum(TaxLedgerEntry::KIND_TDS_194C),
            'ytd' => [
                'gst' => $ytdSum(TaxLedgerEntry::KIND_GST_9_5) + $ytdSum(TaxLedgerEntry::KIND_GST_SERVICE) + $ytdSum(TaxLedgerEntry::KIND_GST_COMMISSION),
                'tcs' => $ytdSum(TaxLedgerEntry::KIND_TCS),
                'tds_194o' => $ytdSum(TaxLedgerEntry::KIND_TDS_194O),
                'tds_194c' => $ytdSum(TaxLedgerEntry::KIND_TDS_194C),
            ],
            'due_dates' => $this->dueDates($to),
        ];
    }

    private function dueDates(Carbon $ref): array
    {
        $nextMonth = $ref->copy()->addMonthNoOverflow()->startOfMonth();
        // GSTR-1 11th, GSTR-3B 20th, GSTR-8 10th of the following month.
        // 26Q: last day of the month following the quarter end.
        $q = (int) ceil($ref->month / 3);
        $qEndMonth = $q * 3;
        $q26q = Carbon::create($ref->year, $qEndMonth, 1)->addMonthNoOverflow()->endOfMonth();

        return [
            'gstr1' => $nextMonth->copy()->day(11)->toDateString(),
            'gstr3b' => $nextMonth->copy()->day(20)->toDateString(),
            'gstr8' => $nextMonth->copy()->day(10)->toDateString(),
            'form26q' => $q26q->toDateString(),
        ];
    }

    /* ---------------- GSTR-1 (B2CS + B2B commission + HSN) ---------------- */

    public function gstr1(Carbon $from, Carbon $to): array
    {
        $orders = Order::query()
            ->where('invoice_type', 'tax_invoice')
            ->whereBetween($this->invoiceDateColumn(), [$from, $to])
            ->get(['id', 'order_number', 'invoice_number', 'invoice_date', 'created_at', 'place_of_supply', 'tax_breakdown', 'total', 'supplier_gstin']);

        $b2cs = [];
        $hsn = [];
        foreach ($orders as $order) {
            $bd = is_array($order->tax_breakdown) ? $order->tax_breakdown : [];
            $pos = $order->place_of_supply ?: ($bd['place_of_supply'] ?? 'Unknown');
            foreach (($bd['rate_summary'] ?? []) as $r) {
                $k = $pos . '|' . number_format((float) $r['rate'], 2, '.', '');
                $b2cs[$k] ??= ['place_of_supply' => $pos, 'rate' => (float) $r['rate'], 'taxable_value' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
                $b2cs[$k]['taxable_value'] += (float) $r['taxable_value'];
                $b2cs[$k]['cgst'] += (float) $r['cgst'];
                $b2cs[$k]['sgst'] += (float) $r['sgst'];
            }
            foreach (array_merge($bd['lines'] ?? [], $bd['charges'] ?? []) as $row) {
                $code = $row['hsn'] ?? 'NA';
                $rate = (float) ($row['rate'] ?? 0);
                $k = $code . '|' . number_format($rate, 2, '.', '');
                $hsn[$k] ??= ['hsn' => $code, 'rate' => $rate, 'quantity' => 0, 'taxable_value' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
                $hsn[$k]['quantity'] += (int) ($row['qty'] ?? 0);
                $hsn[$k]['taxable_value'] += (float) ($row['taxable_value'] ?? 0);
                $hsn[$k]['cgst'] += (float) ($row['cgst'] ?? 0);
                $hsn[$k]['sgst'] += (float) ($row['sgst'] ?? 0);
            }
        }

        // B2B: the 18% commission invoices raised to registered restaurants.
        $b2b = TaxLedgerEntry::where('kind', TaxLedgerEntry::KIND_GST_COMMISSION)
            ->whereBetween('created_at', [$from, $to])
            ->with('order.restaurant:id,name,gstin')
            ->get()
            ->groupBy(fn ($e) => optional($e->order?->restaurant)->gstin ?: 'URP')
            ->map(fn ($g, $gstin) => [
                'gstin' => $gstin,
                'name' => optional($g->first()->order?->restaurant)->name,
                'taxable_value' => round((float) $g->sum('taxable_value'), 2),
                'cgst' => round((float) $g->sum('cgst'), 2),
                'sgst' => round((float) $g->sum('sgst'), 2),
            ])->values()->all();

        $b2csRows = $this->roundRows($b2cs);
        $b2bRows = $b2b;
        $taxable = round(collect($b2csRows)->sum('taxable_value') + collect($b2bRows)->sum('taxable_value'), 2);
        $cgst = round(collect($b2csRows)->sum('cgst') + collect($b2bRows)->sum('cgst'), 2);
        $sgst = round(collect($b2csRows)->sum('sgst') + collect($b2bRows)->sum('sgst'), 2);
        $invoiceNos = $orders->pluck('invoice_number')->filter()->sort()->values();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'gstin' => \App\Models\AppSetting::getValue('business_gstin') ?: \App\Models\AppSetting::getValue('invoice_company_tax_id'),
            'invoice_count' => $orders->count(),
            'first_invoice_no' => $invoiceNos->first(),
            'last_invoice_no' => $invoiceNos->last(),
            'b2cs' => $b2csRows,
            'b2b_commission' => $b2bRows,
            'hsn' => $this->roundRows($hsn),
            'totals' => [
                'taxable_value' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => 0.0,
                'invoice_value' => round($taxable + $cgst + $sgst, 2),
            ],
        ];
    }

    /* ---------------- GSTR-3B worksheet ---------------- */

    public function gstr3b(Carbon $from, Carbon $to): array
    {
        $rows = TaxLedgerEntry::whereBetween('created_at', [$from, $to])->get();
        $s = fn (string $kind, string $c) => round((float) $rows->where('kind', $kind)->sum($c), 2);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'outward' => [
                // 3.1(a) taxable outward -- platform service GST (18%)
                'taxable_value' => $s(TaxLedgerEntry::KIND_GST_SERVICE, 'taxable_value') + $s(TaxLedgerEntry::KIND_GST_COMMISSION, 'taxable_value'),
                'cgst' => $s(TaxLedgerEntry::KIND_GST_SERVICE, 'cgst') + $s(TaxLedgerEntry::KIND_GST_COMMISSION, 'cgst'),
                'sgst' => $s(TaxLedgerEntry::KIND_GST_SERVICE, 'sgst') + $s(TaxLedgerEntry::KIND_GST_COMMISSION, 'sgst'),
            ],
            'eco_9_5' => [
                // 3.1.1(i) supplies u/s 9(5) on which ECO pays tax
                'taxable_value' => $s(TaxLedgerEntry::KIND_GST_9_5, 'taxable_value'),
                'cgst' => $s(TaxLedgerEntry::KIND_GST_9_5, 'cgst'),
                'sgst' => $s(TaxLedgerEntry::KIND_GST_9_5, 'sgst'),
            ],
            'itc' => [
                // No ITC modelled for the platform here; commission ITC is the restaurant's.
                'cgst' => 0.0,
                'sgst' => 0.0,
            ],
        ];
    }

    /* ---------------- GSTR-8 (TCS) ---------------- */

    public function gstr8(Carbon $from, Carbon $to): array
    {
        $rows = TaxLedgerEntry::where('kind', TaxLedgerEntry::KIND_TCS)
            ->whereBetween('created_at', [$from, $to])
            ->with('payout.restaurant:id,name,gstin')
            ->get()
            ->groupBy('party_id')
            ->map(function ($g) {
                $rest = $g->first()->payout?->restaurant;

                return [
                    'restaurant' => $rest?->name,
                    'gstin' => $rest?->gstin,
                    'gross_value' => round((float) $g->sum('taxable_value'), 2),
                    'cgst' => round((float) $g->sum('cgst'), 2),
                    'sgst' => round((float) $g->sum('sgst'), 2),
                    'tcs' => round((float) $g->sum('amount'), 2),
                ];
            })->values()->all();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'suppliers' => $rows,
            'total_tcs' => round(collect($rows)->sum('tcs'), 2),
        ];
    }

    /* ---------------- TDS statements / Form 26Q ---------------- */

    public function tdsStatement(string $section, Carbon $from, Carbon $to): array
    {
        $kind = $section === '194C' ? TaxLedgerEntry::KIND_TDS_194C : TaxLedgerEntry::KIND_TDS_194O;

        $entries = TaxLedgerEntry::where('kind', $kind)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('party_id')
            ->map(function ($g) {
                $first = $g->first();
                $meta = $first->meta ?? [];
                $party = $first->party_type ? app($first->party_type)::find($first->party_id) : null;

                return [
                    'party_type' => $first->party_type,
                    'party_id' => $first->party_id,
                    'name' => $party->name ?? null,
                    'deductee_type' => $party->tax_deductee_type ?? ($meta['deductee_type'] ?? 'individual'),
                    'pan' => $meta['pan'] ?? ($party->pan ?? null),
                    'gross' => round((float) $g->sum(fn ($e) => (float) ($e->meta['taxable_base'] ?? $e->taxable_value)), 2),
                    'rate' => (float) $g->max('rate'),
                    'tds' => round((float) $g->sum('amount'), 2),
                    'deductions' => $g->count(),
                ];
            })->values();

        return [
            'section' => $section,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rows' => $entries->all(),
            'total_tds' => round($entries->sum('tds'), 2),
        ];
    }

    /* ---------------- settlement statements ---------------- */

    public function settlements(string $type, Carbon $from, Carbon $to): array
    {
        $q = Payout::query()
            ->when($type === 'restaurant', fn ($x) => $x->whereNotNull('restaurant_id')->with('restaurant:id,name,gstin'))
            ->when($type === 'driver', fn ($x) => $x->whereNotNull('driver_id')->with('driver:id,name,pan'))
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        return [
            'type' => $type,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rows' => $q->map(fn (Payout $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'party' => $type === 'restaurant' ? $p->restaurant?->name : $p->driver?->name,
                'created_at' => optional($p->created_at)->toDateString(),
                'gross_amount' => (float) $p->gross_amount,
                'platform_commission' => (float) $p->platform_commission,
                'gst_on_commission' => (float) $p->gst_on_commission,
                'payment_gateway_fee' => (float) $p->payment_gateway_fee,
                'pre_tax_amount' => (float) ($p->pre_tax_amount ?? $p->net_amount),
                'tds_amount' => (float) ($p->tds_amount ?? 0),
                'tds_section' => $p->tds_section,
                'tcs_amount' => (float) ($p->tcs_amount ?? 0),
                'net_amount' => (float) $p->net_amount,
                'status' => $p->status,
            ])->all(),
        ];
    }

    /* ---------------- Form 16A data ---------------- */

    public function form16a(string $section, string $fy): array
    {
        return TdsDeducteeTotal::where('section', $section === '194C' ? '194C' : '194O')
            ->where('fy', $fy)
            ->get()
            ->map(fn ($t) => [
                'party_type' => $t->party_type,
                'party_id' => $t->party_id,
                'pan' => $t->pan,
                'fy' => $t->fy,
                'section' => $t->section,
                'amount_paid' => (float) $t->gross_ytd,
                'tds_deducted' => (float) $t->tds_ytd,
            ])->all();
    }

    /**
     * Full Form 16A certificate payload for a single deductee: identity block,
     * summary of payment, quarter-wise TDS, and challan / book-entry detail
     * derived from tax_ledger_entries. Returns null when nothing was deducted.
     */
    public function form16aCertificate(string $section, string $fy, string $partyType, int $partyId): ?array
    {
        $kind = $section === '194C' ? TaxLedgerEntry::KIND_TDS_194C : TaxLedgerEntry::KIND_TDS_194O;

        $entries = TaxLedgerEntry::where('kind', $kind)
            ->where('fy', $fy)
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->orderBy('period')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $fyStartYear = (int) substr($fy, 0, 4);
        $quarterOf = function (string $period) use ($fyStartYear): string {
            [$y, $m] = array_map('intval', explode('-', $period));
            $idx = ($m - $this->config->fyStartMonth() + 12) % 12; // 0..11 from FY start
            return 'Q' . (intdiv($idx, 3) + 1);
        };

        $byQuarter = ['Q1' => [], 'Q2' => [], 'Q3' => [], 'Q4' => []];
        foreach ($entries as $e) {
            $byQuarter[$quarterOf($e->period)][] = $e;
        }

        $quarters = [];
        foreach ($byQuarter as $q => $rows) {
            $coll = collect($rows);
            $receipts = $coll->pluck('challan_no')->filter()->unique()->implode(', ');
            $quarters[] = [
                'quarter' => $q,
                'receipt_no' => $receipts ?: '-',
                'tds_deducted' => round((float) $coll->sum('amount'), 2),
                'tds_deposited' => round((float) $coll->where('status', 'filed')->sum('amount'), 2),
            ];
        }

        // Challan detail: one row per distinct challan_no/date on filed entries.
        $challans = $entries->where('status', 'filed')
            ->groupBy(fn ($e) => ($e->challan_no ?: 'NA') . '|' . optional($e->challan_date)->toDateString())
            ->map(function ($g) {
                $first = $g->first();

                return [
                    'amount' => round((float) $g->sum('amount'), 2),
                    'bsr_code' => data_get($first->meta, 'bsr_code', ''),
                    'challan_no' => $first->challan_no ?: '',
                    'deposit_date' => optional($first->challan_date)->format('d-M-Y') ?: '',
                    'status' => 'F',
                ];
            })->values()->all();

        $totalPaid = round((float) $entries->sum(fn ($e) => (float) (data_get($e->meta, 'taxable_base', $e->taxable_value))), 2);
        $totalTds = round((float) $entries->sum('amount'), 2);
        $totalDeposited = round((float) $entries->where('status', 'filed')->sum('amount'), 2);

        $party = app($partyType)::find($partyId);

        return [
            'section' => $section,
            'fy' => $fy,
            'assessment_year' => ($fyStartYear + 1) . '-' . str_pad((string) (($fyStartYear + 2) % 100), 2, '0', STR_PAD_LEFT),
            'period' => [
                'from' => sprintf('%02d-%02d-%d', 1, $this->config->fyStartMonth(), $fyStartYear),
                'to' => sprintf('%02d-%02d-%d', 31, ($this->config->fyStartMonth() + 11) % 12 ?: 12, $fyStartYear + 1),
            ],
            'certificate_no' => strtoupper(substr(md5($partyType . $partyId . $fy . $section), 0, 8)),
            'last_updated' => now()->format('d-M-Y'),
            'nature_of_payment' => $section === '194C' ? 'Payments to contractors (194C)' : 'E-commerce operator (194-O)',
            'deductee' => [
                'name' => $party->name ?? ($partyType . ' #' . $partyId),
                'pan' => $entries->first()->meta['pan'] ?? ($party->pan ?? $party->gstin ?? 'PANNOTAVBL'),
                'address' => trim(implode(', ', array_filter([
                    $party->address ?? null, $party->city ?? null, $party->state ?? null,
                ]))) ?: '-',
            ],
            'summary_of_payment' => [
                'amount_paid' => $totalPaid,
                'tds_total' => $totalTds,
                'tds_deposited' => $totalDeposited,
            ],
            'quarters' => $quarters,
            'challans' => $challans,
        ];
    }

    /* ---------------- helpers ---------------- */

    private function roundRows(array $group): array
    {
        return collect($group)->map(function ($r) {
            foreach (['taxable_value', 'cgst', 'sgst'] as $k) {
                $r[$k] = round((float) $r[$k], 2);
            }

            return $r;
        })->values()->all();
    }

    private function invoiceDateColumn(): string
    {
        return 'created_at'; // orders.invoice_date is often null on older rows; created_at is always set
    }
}
