<?php
// app/Models/RefundPolicy.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundPolicy extends Model
{
    protected $fillable = [
        'title',
        'content',
        'status',
        'refund_window_hours',
        'delivery_charge_refund_percentage',
        'cancellation_refund_rules'
    ];

    protected $casts = [
        'cancellation_refund_rules' => 'array',
        'refund_window_hours' => 'integer',
        'delivery_charge_refund_percentage' => 'decimal:2'
    ];

    public static function getActivePolicy(): self
    {
        return static::where('status', 'active')->first() ?? static::defaultPolicy();
    }

    /**
     * Keep cancellation/refund flows operational before an administrator has
     * created the first policy record.
     */
    public static function defaultPolicy(): self
    {
        return new static([
            'title' => 'Standard Refund Policy',
            'content' => 'Standard refund rules apply based on the current order status.',
            'status' => 'active',
            'refund_window_hours' => 2,
            'delivery_charge_refund_percentage' => 100,
            'cancellation_refund_rules' => [
                'pending' => 95,
                'confirmed' => 85,
                'preparing' => 70,
            ],
        ]);
    }

    public function calculateRefundAmount($order, $cancellationReason)
    {
        $policy = $this;
        $rules = $policy->cancellation_refund_rules ?? [];
        
        // Default refund calculation
        $refundableAmount = 0;
        
        // Check if order is within refund window
        $orderAge = now()->diffInHours($order->created_at);
        
        if ($orderAge > $policy->refund_window_hours) {
            return 0; // No refund after window
        }
        
        // Apply rules based on order status
        switch ($order->status) {
            case 'pending':
                $refundableAmount = $order->total * 0.95; // 95% refund for pending orders
                break;
            case 'confirmed':
                $refundableAmount = $order->total * 0.85; // 85% refund for confirmed orders
                break;
            case 'preparing':
                $refundableAmount = $order->total * 0.70; // 70% refund for preparing orders
                break;
            case 'cancelled':
                $refundableAmount = 0;
                break;
            default:
                $refundableAmount = 0;
        }
        
        // Apply custom rules if any
        if (isset($rules[$order->status])) {
            $refundableAmount = $order->total * ($rules[$order->status] / 100);
        }
        
        return max(0, round($refundableAmount, 2));
    }
}
