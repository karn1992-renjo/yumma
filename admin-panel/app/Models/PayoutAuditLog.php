<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAuditLog extends Model
{
    protected $fillable = [
        'payout_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'meta',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
