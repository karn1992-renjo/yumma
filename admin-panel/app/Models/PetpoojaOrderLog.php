<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetpoojaOrderLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'direction',
        'request_payload',
        'response_payload',
        'http_status',
        'success',
        'message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
