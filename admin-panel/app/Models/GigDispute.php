<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigDispute extends Model
{
    protected $fillable = [
        'driver_gig_id',
        'driver_gig_booking_id',
        'driver_id',
        'gig_incentive_id',
        'reason',
        'message',
        'status',
        'resolution_note',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
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

    public function incentive()
    {
        return $this->belongsTo(GigIncentive::class, 'gig_incentive_id');
    }
}