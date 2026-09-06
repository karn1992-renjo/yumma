<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per taxable event. The single source for every GST/TDS/TCS report.
 *
 * kind: gst_9_5 | gst_service | gst_commission | tcs | tds_194o | tds_194c | gig_cess
 * party: the entity the entry is about (restaurant / driver); null == the
 *        platform's own liability.
 */
class TaxLedgerEntry extends Model
{
    public const KIND_GST_9_5 = 'gst_9_5';
    public const KIND_GST_SERVICE = 'gst_service';
    public const KIND_GST_COMMISSION = 'gst_commission';
    public const KIND_TCS = 'tcs';
    public const KIND_TDS_194O = 'tds_194o';
    public const KIND_TDS_194C = 'tds_194c';
    public const KIND_GIG_CESS = 'gig_cess';

    protected $fillable = [
        'party_type', 'party_id', 'order_id', 'payout_id', 'kind', 'section',
        'taxable_value', 'rate', 'cgst', 'sgst', 'igst', 'amount',
        'fy', 'period', 'status', 'challan_no', 'challan_date', 'meta',
    ];

    protected $casts = [
        'taxable_value' => 'decimal:2',
        'rate' => 'decimal:3',
        'cgst' => 'decimal:2',
        'sgst' => 'decimal:2',
        'igst' => 'decimal:2',
        'amount' => 'decimal:2',
        'challan_date' => 'date',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo($this->party_type ?: Restaurant::class, 'party_id');
    }
}
