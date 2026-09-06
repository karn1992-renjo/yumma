<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantOnboarding extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_CORRECTION_REQUIRED = 'correction_required';
    public const STATUS_RESUBMITTED = 'resubmitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'application_number',
        'driver_id',
        'partner_application_id',
        'restaurant_id',
        'registration_source',
        'status',
        'incentive_amount',
        'incentive_status',
        'owner_mobile_verified',
        'owner_mobile_verified_at',
        'driver_latitude',
        'driver_longitude',
        'restaurant_latitude',
        'restaurant_longitude',
        'distance_from_restaurant',
        'location_captured_at',
        'draft_payload',
        'duplicate_check',
        'correction_fields',
        'correction_notes',
        'rejection_reason',
        'onboarding_started_at',
        'submitted_at',
        'approved_at',
        'activated_at',
        'rejected_at',
    ];

    protected $casts = [
        'incentive_amount' => 'decimal:2',
        'owner_mobile_verified' => 'boolean',
        'driver_latitude' => 'decimal:8',
        'driver_longitude' => 'decimal:8',
        'restaurant_latitude' => 'decimal:8',
        'restaurant_longitude' => 'decimal:8',
        'distance_from_restaurant' => 'decimal:3',
        'draft_payload' => 'array',
        'duplicate_check' => 'array',
        'correction_fields' => 'array',
        'owner_mobile_verified_at' => 'datetime',
        'location_captured_at' => 'datetime',
        'onboarding_started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function partnerApplication(): BelongsTo
    {
        return $this->belongsTo(PartnerApplication::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function incentive(): HasOne
    {
        return $this->hasOne(RestaurantOnboardingIncentive::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RestaurantOnboardingEvent::class);
    }
}
