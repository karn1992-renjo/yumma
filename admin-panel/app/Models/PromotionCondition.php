<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    protected $fillable = ['promotion_id', 'condition_type', 'operator', 'value', 'payload'];

    protected $casts = [
        'value' => 'array',
        'payload' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
