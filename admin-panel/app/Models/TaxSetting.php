<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    protected $fillable = [
        'name', 'rate', 'type', 'calculation_type', 'description', 'is_active'
    ];
    
    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    public static function getActiveTaxes()
    {
        return self::where('is_active', true)->get();
    }
    
    public static function calculateTax($subtotal, $deliveryFee = 0)
    {
        return collect(self::calculateBreakdown($subtotal, $deliveryFee))->sum('amount');
    }

    public static function calculateBreakdown($subtotal, $deliveryFee = 0): array
    {
        return self::getActiveTaxes()
            ->map(function ($tax) use ($subtotal, $deliveryFee) {
                $rate = (float) $tax->rate;
                $calculationType = $tax->calculation_type === 'fixed' ? 'fixed' : 'percentage';
                $taxableAmount = match ($tax->type) {
                    'delivery_charge_tax' => (float) $deliveryFee,
                    'gst', 'service_charge', 'packaging_charge' => (float) $subtotal,
                    default => 0.0,
                };
                $amount = $calculationType === 'fixed'
                    ? $rate
                    : $taxableAmount * ($rate / 100);

                return [
                    'name' => $tax->name,
                    'type' => $tax->type,
                    'calculation_type' => $calculationType,
                    'rate' => $rate,
                    'taxable_amount' => round($calculationType === 'fixed' ? 0 : $taxableAmount, 2),
                    'amount' => round($amount, 2),
                    'description' => $tax->description,
                ];
            })
            ->filter(fn ($item) => $item['amount'] > 0)
            ->values()
            ->all();
    }
}
