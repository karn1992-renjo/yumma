<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $fillable = [
        'order_id',
        'order_call_mapping_id',
        'direction',
        'initiator_role',
        'initiator_user_id',
        'leg_from',
        'leg_to',
        'exotel_call_sid',
        'exophone',
        'provider_used',
        'status',
        'duration_seconds',
        'recording_url',
        'failure_reason',
        'initiated_at',
        'answered_at',
        'completed_at',
        'raw_webhook_payload',
    ];

    protected $casts = [
        'initiated_at' => 'datetime',
        'answered_at' => 'datetime',
        'completed_at' => 'datetime',
        'raw_webhook_payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(OrderCallMapping::class, 'order_call_mapping_id');
    }
}
