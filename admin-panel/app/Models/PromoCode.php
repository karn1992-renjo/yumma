<?php

namespace App\Models;

use App\Services\MediaStorage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCode extends Model
{
    protected $fillable = [
        'restaurant_id', 'code', 'title', 'description', 'promo_image', 'created_by_type',
        'discount_type', 'discount_value',
        'min_order_amount', 'max_discount_amount', 'usage_limit', 'used_count',
        'audience_type', 'coupon_type', 'assigned_to', 'start_date', 'end_date', 'is_active'
    ];
    
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
    ];
    
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function getPromoImageUrlAttribute(): ?string
    {
        return MediaStorage::url($this->promo_image);
    }
    
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_date && now()->lt($this->start_date)) return false;
        if ($this->end_date && now()->gt($this->end_date)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        
        return true;
    }
    
    public function calculateDiscount($orderAmount): float
    {
        if (!$this->isValid()) return 0;
        if ($this->min_order_amount && $orderAmount < $this->min_order_amount) return 0;
        
        $discount = 0;
        
        if ($this->discount_type === 'percentage') {
            $discount = ($orderAmount * $this->discount_value) / 100;
        } else {
            $discount = $this->discount_value;
        }
        
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }
        
        return min($discount, $orderAmount);
    }
    // app/Models/PromoCode.php - Add these methods

    public function isEligibleForUser($userId)
    {
        if ($this->coupon_type === 'prepaid' && $this->assigned_to != $userId) {
            return false;
        }
        
        if ($this->audience_type === 'new_customer') {
            $orderCount = Order::where('customer_id', $userId)->count();
            return $orderCount == 0;
        }
        
        if ($this->audience_type === 'returning_customer') {
            $orderCount = Order::where('customer_id', $userId)->count();
            return $orderCount > 0;
        }
        
        return true;
    }
}
