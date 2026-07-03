<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
    protected $table = 'search_indexes';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'title',
        'description',
        'keywords',
        'tags',
        'city_id',
        'zone_id',
        'restaurant_id',
        'branch_id',
        'latitude',
        'longitude',
        'is_active',
        'search_score',
    ];

    protected $casts = [
        'tags' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'search_score' => 'float',
    ];
}
