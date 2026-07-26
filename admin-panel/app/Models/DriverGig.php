<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverGig extends Model
{
    protected $appends = [
        'duration',
        'estimated_earning',
        'date_short',
        'slot_start_local',
        'slot_end_local',
        'time_range',
        'booked_count',
        'available_seats',
    ];

    protected $fillable = [
        'title',
        'description',
        'driver_id',
        'area_id',
        'capacity',
        'date',
        'start_time',
        'end_time',
        'status',
        'earnings',
        'orders_count',
        'base_pay',
        'order_incentive',
        'login_incentive',
        'min_orders_required',
        'min_login_minutes',
        'max_cancellations_allowed',
        'terms_conditions',
        'booked_at',
    ];
    
    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'earnings' => 'integer',
        'orders_count' => 'integer',
        'capacity' => 'integer',
        'base_pay' => 'decimal:2',
        'order_incentive' => 'decimal:2',
        'login_incentive' => 'decimal:2',
        'min_orders_required' => 'integer',
        'min_login_minutes' => 'integer',
        'max_cancellations_allowed' => 'integer',
        'booked_at' => 'datetime',
    ];
    
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
    
    public function area(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(DriverGigBooking::class, 'driver_gig_id');
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()->whereIn('status', ['booked', 'completed']);
    }
    
    public function getDurationAttribute(): string
    {
        return $this->time_range;
    }

    public function getEstimatedEarningAttribute(): float
    {
        return round(
            (float) $this->base_pay
            + ((float) $this->order_incentive * (int) $this->min_orders_required)
            + (float) $this->login_incentive,
            2
        );
    }

    public function getBookedCountAttribute(): int
    {
        if (array_key_exists('active_bookings_count', $this->attributes)) {
            return (int) $this->attributes['active_bookings_count'];
        }

        if ($this->relationLoaded('activeBookings')) {
            return $this->activeBookings->count();
        }

        return $this->activeBookings()->count();
    }

    public function getAvailableSeatsAttribute(): int
    {
        return max(0, (int) $this->capacity - $this->booked_count);
    }

    public function getDateShortAttribute(): ?string
    {
        return $this->date?->format('d M');
    }

    public function getSlotStartLocalAttribute(): ?string
    {
        return $this->localSlotTime('start_time')?->format('Y-m-d H:i:s');
    }

    public function getSlotEndLocalAttribute(): ?string
    {
        return $this->localSlotTime('end_time')?->format('Y-m-d H:i:s');
    }

    public function getTimeRangeAttribute(): string
    {
        $start = $this->localSlotTime('start_time');
        $end = $this->localSlotTime('end_time');

        if (! $start || ! $end) {
            return '';
        }

        return $start->format('h:i A') . ' - ' . $end->format('h:i A');
    }

    private function localSlotTime(string $attribute): ?\Carbon\Carbon
    {
        $date = $this->date;
        $time = $this->{$attribute};

        if (! $date || ! $time) {
            return null;
        }

        return \Carbon\Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
            config('app.timezone')
        );
    }
}
