<?php

namespace App\Services\Integration;

use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;

/**
 * Ships the balanced journal that admin/'s LedgerPostingService just posted
 * locally to the Accounts/ app, so its independent GL stays in step. Called
 * right after the local post, inside the existing try/catch. A no-op unless the
 * accounts integration toggle is on.
 */
class LedgerEventEmitter
{
    /** Emit a journal entry (with its lines) that was just posted locally. */
    public static function journal(?JournalEntry $entry, array $mirror = []): void
    {
        if (! $entry) {
            return;
        }
        $entry->loadMissing('lines.account');

        WebhookDispatcher::emit('accounts', 'journal.posted', [
            'entry_no' => $entry->entry_no,
            'kind' => $entry->kind,
            'date' => optional($entry->date)->toDateString(),
            'narration' => $entry->narration,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'is_manual' => (bool) $entry->is_manual,
            'lines' => $entry->lines->map(fn ($l) => [
                'code' => $l->account?->code,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
                'party_type' => $l->party_type,
                'party_id' => $l->party_id,
                'memo' => $l->memo,
            ])->all(),
            'mirror' => $mirror,
        ]);
    }

    /** Tax accrual — also carried as a row for the GST/TDS/TCS read models. */
    public static function taxAccrued(TaxLedgerEntry $e): void
    {
        WebhookDispatcher::emit('accounts', 'tax.accrued', [
            'id' => $e->id,
            'kind' => $e->kind,
            'section' => $e->section,
            'order_id' => $e->order_id,
            'payout_id' => $e->payout_id,
            'party_type' => $e->party_type,
            'party_id' => $e->party_id,
            'taxable_value' => (float) $e->taxable_value,
            'rate' => (float) $e->rate,
            'cgst' => (float) $e->cgst,
            'sgst' => (float) $e->sgst,
            'igst' => (float) ($e->igst ?? 0),
            'amount' => (float) $e->amount,
            'fy' => $e->fy,
            'period' => $e->period,
            'status' => $e->status,
            'meta' => $e->meta,
            'created_at' => optional($e->created_at)->toIso8601String(),
        ]);
    }

    public static function orderMirror(Order $o): array
    {
        return [
            'table' => 'ext_orders',
            'row' => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'invoice_type' => $o->invoice_type,
                'invoice_number' => $o->invoice_number,
                'invoice_date' => optional($o->invoice_date ?? $o->created_at)->toDateString(),
                'place_of_supply' => $o->place_of_supply,
                'supplier_gstin' => $o->supplier_gstin,
                'subtotal' => (float) $o->subtotal,
                'delivery_fee' => (float) $o->delivery_fee,
                'platform_fee' => (float) $o->platform_fee,
                'total' => (float) $o->total,
                'restaurant_id' => $o->restaurant_id,
                'branch_id' => $o->branch_id,
                'tax_breakdown' => is_array($o->tax_breakdown) ? json_encode($o->tax_breakdown) : null,
                'created_at' => optional($o->created_at)->toDateTimeString(),
            ],
        ];
    }

    public static function payoutMirror(Payout $p): array
    {
        return [
            'table' => 'ext_payouts',
            'row' => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'restaurant_id' => $p->restaurant_id,
                'driver_id' => $p->driver_id,
                'restaurant_name' => $p->restaurant?->name,
                'driver_name' => $p->driver?->name,
                'gross_amount' => (float) $p->gross_amount,
                'platform_commission' => (float) ($p->platform_commission ?? 0),
                'gst_on_commission' => (float) ($p->gst_on_commission ?? 0),
                'payment_gateway_fee' => (float) ($p->payment_gateway_fee ?? 0),
                'pre_tax_amount' => (float) ($p->pre_tax_amount ?? $p->net_amount),
                'tds_amount' => (float) ($p->tds_amount ?? 0),
                'tds_section' => $p->tds_section,
                'tcs_amount' => (float) ($p->tcs_amount ?? 0),
                'net_amount' => (float) $p->net_amount,
                'status' => $p->status,
                'created_at' => optional($p->created_at)->toDateTimeString(),
            ],
        ];
    }
}
