<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigFraudSignal extends Model
{
    protected $fillable = [
        'driver_gig_id',
        'driver_gig_booking_id',
        'driver_id',
        'signal_type',
        'severity',
        'score',
        'status',
        'evidence',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'evidence' => 'array',
        'score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function gig()
    {
        return $this->belongsTo(DriverGig::class, 'driver_gig_id');
    }

    public function booking()
    {
        return $this->belongsTo(DriverGigBooking::class, 'driver_gig_booking_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}