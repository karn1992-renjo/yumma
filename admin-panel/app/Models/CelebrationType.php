<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CelebrationType extends Model
{
    protected $fillable = ['name', 'icon', 'is_active', 'display_order'];
    
    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];
}