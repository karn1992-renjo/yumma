<?php
// app/Models/Address.php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'city',
        'state',
        'pincode',
        'phone',
        'is_default',
        'latitude',
        'longitude'
    ];
    
    protected $casts = [
        'is_default' => 'boolean'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize(
            $value,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
    }
}
