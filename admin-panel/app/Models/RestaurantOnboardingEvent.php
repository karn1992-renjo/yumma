<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantOnboardingEvent extends Model
{
    protected $fillable = [
        'restaurant_onboarding_id',
        'actor_id',
        'actor_type',
        'event',
        'old_values',
        'new_values',
        'notes',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(RestaurantOnboarding::class, 'restaurant_onboarding_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
