<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * swaad-only: a movie theatre venue for in-seat food ordering. See
 * movie-theatre-in-seat-ordering project memory for why this exists and why
 * it is deliberately not part of Swado.
 */
class Theatre extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'logo',
        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_user_id',
        'country',
        'state',
        'city',
        'address',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'aisle_handoff_only',
        'tags',
        'status',
        'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geofence_radius_meters' => 'integer',
        'aisle_handoff_only' => 'boolean',
        'tags' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(TheatreUser::class);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(TheatreScreen::class);
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'theatre_restaurants')
            ->withPivot(['is_active', 'added_by', 'approval_status', 'approved_by', 'approved_at'])
            ->withTimestamps();
    }

    public function runnerRoster(): HasMany
    {
        return $this->hasMany(TheatreRunner::class);
    }

    public function runnerShifts(): HasMany
    {
        return $this->hasMany(DriverGig::class, 'theatre_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderGroups(): HasMany
    {
        return $this->hasMany(OrderGroup::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * The real, server-side "customer is physically at this theatre" check.
     * Never trust a client-only version of this — see TheatreManagementService
     * and the /api/theatre/checkout presence re-validation in the plan.
     */
    public function containsPoint(float $lat, float $lng): bool
    {
        if ($this->latitude === null || $this->longitude === null) {
            return false;
        }

        $earthRadiusMeters = 6371000;
        $latFrom = deg2rad((float) $this->latitude);
        $lngFrom = deg2rad((float) $this->longitude);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $distanceMeters = $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $distanceMeters <= $this->geofence_radius_meters;
    }
}
