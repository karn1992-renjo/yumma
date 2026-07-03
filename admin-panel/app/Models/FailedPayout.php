<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FailedPayout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payout_id',
        'gateway',
        'error_code',
        'error_message',
        'payload',
        'retry_count',
        'next_retry_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
