<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_CUSTOMER_APP = 'customer_app';
    public const SOURCE_DRIVER_QR = 'driver_qr';
    public const SOURCE_DRIVER_CASH = 'driver_cash';

    protected $fillable = [
        'order_id',
        'gateway',
        'source',
        'status',
        'amount',
        'currency',
        'transaction_id',
        'gateway_reference',
        'payment_link_id',
        'payment_link',
        'qr_reference',
        'expires_at',
        'created_by',
        'driver_id',
        'webhook_event_id',
        'idempotency_key',
        'collection_notes',
        'gateway_payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_payload' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
