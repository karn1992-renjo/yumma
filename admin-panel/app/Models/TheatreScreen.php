<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single auditorium within a Theatre. `seat_map_config` is the full
 * drag-and-drop layout saved by the seat-map builder (row labels, seat
 * counts, curve control points, aisle gaps, section tags) -- built in a
 * later phase. This never tracks seat availability/booking; a seat is only
 * ever a delivery-location label the customer picks, not inventory.
 */
class TheatreScreen extends Model
{
    protected $fillable = [
        'theatre_id',
        'name',
        'seat_map_config',
        'is_active',
    ];

    protected $casts = [
        'seat_map_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'screen_id');
    }

    public function hasSeatMap(): bool
    {
        return ! empty($this->seat_map_config);
    }
}
