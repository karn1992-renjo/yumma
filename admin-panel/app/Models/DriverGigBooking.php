<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverGigBooking extends Model
{
    protected $fillable = [
        'driver_gig_id',
        'driver_id',
        'status',
        'booked_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'booked_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function gig(): BelongsTo
    {
        return $this->belongsTo(DriverGig::class, 'driver_gig_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
