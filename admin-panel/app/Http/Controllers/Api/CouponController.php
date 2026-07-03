<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'restaurant_id' => 'required|exists:restaurants,id',
            'subtotal' => 'required|numeric|min:0',
        ]);
        
        $promo = PromoCode::where('code', $request->code)
            ->where(function ($query) use ($request) {
                $query->where('restaurant_id', $request->restaurant_id)
                    ->orWhereNull('restaurant_id');
            })
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', now());
            })
            ->first();
            
        if (!$promo) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired coupon code'
            ], 400);
        }
        
        if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon usage limit exceeded'
            ], 400);
        }
        
        if ($request->subtotal < $promo->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount of ' . AppSetting::getValue('currency_symbol', '₹') . $promo->min_order_amount . ' required'
            ], 400);
        }
        
        $discountAmount = 0;
        if ($promo->discount_type === 'percentage') {
            $discountAmount = ($request->subtotal * $promo->discount_value) / 100;
            if ($promo->max_discount_amount) {
                $discountAmount = min($discountAmount, $promo->max_discount_amount);
            }
        } else {
            $discountAmount = $promo->discount_value;
        }
        
        $currencyDecimals = AppSetting::currencyDecimals();
        $discountAmount = round((float) $discountAmount, $currencyDecimals);

        return response()->json([
            'success' => true,
            'data' => [
                'discount_amount' => $discountAmount,
                'final_total' => round($request->subtotal - $discountAmount, $currencyDecimals),
                'coupon_code' => $promo->code,
                'coupon' => $promo
            ]
        ]);
    }
}
