<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_number',
        'partner_type',
        'business_name',
        'business_email',
        'business_phone',
        'full_name',
        'email',
        'phone',
        'city',
        'city_id',
        'address',
        'area_id',
        'latitude',
        'longitude',
        'pincode',
        'cuisine',
        'is_pure_veg',
        'contact_name',
        'contact_designation',
        'contact_email',
        'contact_phone',
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'password',
        'bank_details',
        'onboarding_meta',
        'gst_certificate',
        'fssai_license',
        'license_document',
        'profile_photo',
        'vehicle_image',
        'aadhar_card',
        'pan_card',
        'vehicle_rc',
        'insurance_document',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_pure_veg' => 'boolean',
        'onboarding_meta' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeRestaurant($query)
    {
        return $query->where('partner_type', 'restaurant');
    }

    public function scopeDriver($query)
    {
        return $query->where('partner_type', 'driver');
    }
}
