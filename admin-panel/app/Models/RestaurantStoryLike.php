<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantStoryLike extends Model
{
    public $timestamps = false;

    protected $fillable = ['restaurant_story_id', 'user_id', 'liked_at'];

    protected $casts = ['liked_at' => 'datetime'];
}
