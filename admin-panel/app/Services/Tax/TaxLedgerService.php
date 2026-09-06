<?php

namespace App\Services\Tax;

use App\Models\Order;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;
use App\Models\TdsDeducteeTotal;
use Illuminate\Support\Facades\DB;

/**
 * Writes rows into `tax_ledger_entries` -- the single source for every
 * GST/TDS/TCS report. Every writer is idempotent on the unique
 * (order_id|payout_id, kind) key.
 */
class TaxLedgerService
{
    public function __construct(
        private readonly TaxConfig $config = new TaxConfig(),
    ) {
    }

    /** GST accrued when an order is confirmed: 9(5) food GST + service GST (both the platform's liability). */
    public function recordOrderGst(Order $order): void
    {
        if (! $this->config->gstEnabled()) {
            return;
        }

        $date = $order->invoice_date ?: $order->created_at ?: now();
        $fy = $this->config->fy($date);
        $period = $this->config->period($date);

        $ecoCgst = (float) ($order->eco_gst_food_cgst ?? 0);
        $ecoSgst = (float) ($order->eco_gst_food_sgst ?? 0);
        if ($ecoCgst + $ecoSgst > 0) {
            $this->upsertByOrder($order, TaxLedgerEntry::KIND_GST_9_5, [
                'section' => '9(5)',
                'taxable_value' => (float) $order->subtotal,
                'rate' => $this->config->ecoFoodRate(),
                'cgst' => $ecoCgst,
                'sgst' => $ecoSgst,
                'amount' => round($ecoCgst + $ecoSgst, 2),
                'fy' => $fy,
                'period' => $period,
                'meta' => ['restaurant_id' => $order->restaurant_id, 'liable' => 'eco'],
            ]);
        }

        $svcCgst = (float) ($order->service_gst_cgst ?? 0);
        $svcSgst = (float) ($order->service_gst_sgst ?? 0);
        if ($svcCgst + $svcSgst > 0) {
            $this->upsertByOrder($order, TaxLedgerEntry::KIND_GST_SERVICE, [
                'section' => 'service',
                'taxable_value' => round((float) $order->delivery_fee + (float) $order->platform_fee, 2),
                'rate' => $this->config->serviceRate(),
                'cgst' => $svcCgst,
                'sgst' => $svcSgst,
                'amount' => round($svcCgst + $svcSgst, 2),
                'fy' => $fy,
                'period' => $period,
                'meta' => ['restaurant_id' => $order->restaurant_id, 'liable' => 'platform'],
            ]);
        }
    }

    /**
     * Gig-worker welfare cess accrued when an order is delivered. Independent of
     * GST registration -- driven only by its own toggle + rate. Posted to the
     * cess payable (2360) via postTaxAccrual.
     */
    public function recordGigCess(Order $order): void
    {
        if (! $this->config->gigCessEnabled()) {
            return;
        }

        $base = $this->config->gigCessBase() === 'driver_payout'
            ? (float) ($order->driver_earning ?: 0)
            : (float) ($order->total ?: 0);

        $amount = round($base * $this->config->gigCessRate() / 100, 2);
        if ($base <= 0 || $amount <= 0) {
            return;
        }

        $date = $order->delivered_at ?: $order->updated_at ?: now();

        $this->upsertByOrder($order, TaxLedgerEntry::KIND_GIG_CESS, [
            'section' => 'gig_cess',
            'taxable_value' => round($base, 2),
            'rate' => $this->config->gigCessRate(),
            'cgst' => 0,
            'sgst' => 0,
            'amount' => $amount,
            'fy' => $this->config->fy($date),
            'period' => $this->config->period($date),
            'meta' => [
                'base' => $this->config->gigCessBase(),
                'borne_by' => $this->config->gigCessBorneBy(),
                'state' => \App\Models\AppSetting::getValue('gig_welfare_cess_state', ''),
                'driver_id' => $order->driver_id,
            ],
        ]);
    }

    /** 18% GST on the commission invoice the platform raises to the restaurant. */
    public function recordCommissionGst(Order $order, float $commission, float $gstAmount): void
    {
        if (! $this->config->gstEnabled() || $gstAmount <= 0) {
            return;
        }
        $date = $order->delivered_at ?: $order->created_at ?: now();

        $this->upsertByOrder($order, TaxLedgerEntry::KIND_GST_COMMISSION, [
            'party_type' => \App\Models\Restaurant::class,
            'party_id' => $order->restaurant_id,
            'section' => 'commission',
            'taxable_value' => round($commission, 2),
            'rate' => $this->config->commissionGstRate(),
            'cgst' => round($gstAmount / 2, 2),
            'sgst' => round($gstAmount / 2, 2),
            'amount' => round($gstAmount, 2),
            'fy' => $this->config->fy($date),
            'period' => $this->config->period($date),
            'meta' => ['restaurant_id' => $order->restaurant_id],
        ]);
    }

    /**
     * A TDS / TCS deduction taken at payout time. Writes the ledger row and
     * bumps the deductee's YTD totals in one transaction.
     */
    public function recordPayoutDeduction(
        Payout $payout,
        string $kind,
        TaxEntity $party,
        string $section,
        float $taxableValue,
        float $rate,
        float $amount,
        array $meta = [],
        ?float $grossForYtd = null
    ): void {
        if ($amount <= 0 && $kind !== TaxLedgerEntry::KIND_TDS_194O && $kind !== TaxLedgerEntry::KIND_TDS_194C) {
            return;
        }

        $date = $payout->created_at ?: now();
        $fy = $this->config->fy($date);
        $isTcs = $kind === TaxLedgerEntry::KIND_TCS;
        $grossForYtd = $grossForYtd ?? $taxableValue;

        DB::transaction(function () use ($payout, $kind, $party, $section, $taxableValue, $rate, $amount, $meta, $date, $fy, $isTcs, $grossForYtd) {
            $exists = TaxLedgerEntry::where('payout_id', $payout->id)->where('kind', $kind)->exists();
            if ($exists) {
                return;
            }

            $entry = TaxLedgerEntry::create([
                'party_type' => $party->partyType ?: null,
                'party_id' => $party->partyId,
                'payout_id' => $payout->id,
                'kind' => $kind,
                'section' => $section,
                'taxable_value' => round($taxableValue, 2),
                'rate' => $rate,
                'cgst' => $isTcs ? round($amount / 2, 2) : 0,
                'sgst' => $isTcs ? round($amount / 2, 2) : 0,
                'amount' => round($amount, 2),
                'fy' => $fy,
                'period' => $this->config->period($date),
                'meta' => $meta + ['pan' => $party->pan],
            ]);

            // The payout journal (postPayout) already books the TDS/TCS
            // withholding against its payable, so we don't double-post here.

            if (! $isTcs && $party->partyId) {
                $total = TdsDeducteeTotal::firstOrCreate([
                    'party_type' => $party->partyType ?: \App\Models\Restaurant::class,
                    'party_id' => $party->partyId,
                    'fy' => $fy,
                    'section' => str_contains($section, '194O') || str_contains($section, '194-O') ? '194O' : '194C',
                ], ['pan' => $party->pan, 'gross_ytd' => 0, 'tds_ytd' => 0]);

                $total->increment('gross_ytd', round($grossForYtd, 2));
                $total->increment('tds_ytd', round($amount, 2));
                if ($party->pan && ! $total->pan) {
                    $total->update(['pan' => $party->pan]);
                }
            }
        });
    }

    private function upsertByOrder(Order $order, string $kind, array $attrs): void
    {
        if (TaxLedgerEntry::where('order_id', $order->id)->where('kind', $kind)->exists()) {
            return;
        }

        $entry = TaxLedgerEntry::create(array_merge([
            'party_type' => null,
            'party_id' => null,
            'order_id' => $order->id,
            'kind' => $kind,
        ], $attrs));

        $this->postToLedger($entry);
    }

    private function postToLedger(TaxLedgerEntry $entry): void
    {
        try {
            $je = app(\App\Services\Accounting\LedgerPostingService::class)->postTaxAccrual($entry);
            \App\Services\Integration\LedgerEventEmitter::taxAccrued($entry);
            \App\Services\Integration\LedgerEventEmitter::journal($je);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Tax accrual journal failed.', ['entry_id' => $entry->id, 'message' => $e->getMessage()]);
        }
    }
}
