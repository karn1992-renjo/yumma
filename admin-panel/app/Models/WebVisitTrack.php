<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebVisitTrack extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'source',
        'panel',
        'url',
        'path',
        'referrer',
        'country_code',
        'country',
        'timezone',
        'local_time',
        'latitude',
        'longitude',
        'location_accuracy',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'local_time' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
