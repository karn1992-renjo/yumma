<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantLocationChangeRequest extends Model
{
    protected $fillable = [
        'restaurant_id',
        'requested_by',
        'current_latitude',
        'current_longitude',
        'requested_latitude',
        'requested_longitude',
        'fssai_license_path',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'requested_latitude' => 'decimal:8',
        'requested_longitude' => 'decimal:8',
        'reviewed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
