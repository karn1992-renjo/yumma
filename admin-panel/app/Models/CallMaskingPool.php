<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallMaskingPool extends Model
{
    protected $table = 'call_masking_pool';

    protected $fillable = [
        'exophone',
        'exotel_sid',
        'status',
        'current_order_id',
        'assigned_at',
        'released_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }
}
