<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRecoveryQueue extends Model
{
    protected $table = 'payment_recovery_queue';

    protected $fillable = [
        'user_id',
        'payment_method',
        'payment_id',
        'gateway_reference',
        'amount',
        'checkout_payload',
        'verification_payload',
        'status',
        'resolved_order_id',
        'last_error',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'checkout_payload' => 'array',
        'verification_payload' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedOrder()
    {
        return $this->belongsTo(Order::class, 'resolved_order_id');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
