<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigExternalSignal extends Model
{
    protected $fillable = ['area_id', 'date', 'hour', 'source', 'score', 'payload', 'fetched_at'];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'score' => 'decimal:2',
        'payload' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function area()
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }
}