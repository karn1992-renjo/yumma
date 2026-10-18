<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantStoryView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_story_id',
        'user_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(RestaurantStory::class, 'restaurant_story_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
