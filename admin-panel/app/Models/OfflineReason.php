<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineReason extends Model
{
    protected $fillable = ['reason', 'sub_reasons', 'is_active'];
    
    protected $casts = [
        'sub_reasons' => 'array',
        'is_active' => 'boolean',
    ];
}