<?php
// app/Models/Coupon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount',
        'min_order',
        'expires_at',
        'usage_limit',
        'used_count',
        'is_active'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];
    
    public function isValid()
    {
        return $this->is_active && 
               $this->expires_at > now() && 
               ($this->usage_limit > $this->used_count);
    }
}