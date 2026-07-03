<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendingSearch extends Model
{
    protected $fillable = [
        'keyword',
        'total_searches',
        'last_searched_at',
    ];

    protected $casts = [
        'last_searched_at' => 'datetime',
    ];
}
