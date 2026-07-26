<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionTarget extends Model
{
    protected $fillable = ['promotion_id', 'target_type', 'target_id', 'payload'];

    protected $casts = [
        'target_id' => 'integer',
        'payload' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
