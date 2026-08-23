<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigPayoutApproval extends Model
{
    protected $fillable = [
        'gig_incentive_id',
        'driver_gig_id',
        'driver_gig_booking_id',
        'driver_id',
        'amount',
        'status',
        'risk_summary',
        'approved_at',
        'approved_by',
        'admin_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'risk_summary' => 'array',
        'approved_at' => 'datetime',
    ];

    public function incentive()
    {
        return $this->belongsTo(GigIncentive::class, 'gig_incentive_id');
    }

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