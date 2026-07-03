<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'name', 'type', 'rate', 'calculation_type', 'is_active'
    ];
    
    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    public static function getRate($type)
    {
        $setting = self::where('type', $type)->where('is_active', true)->first();
        return $setting ? $setting->rate : 0;
    }

    public static function getCalculationType(string $type): string
    {
        return self::where('type', $type)->where('is_active', true)->value('calculation_type')
            ?: self::TYPE_PERCENTAGE;
    }

    public static function calculate(string $type, float $base, float $fallbackRate = 0): float
    {
        $base = max(0, $base);
        $setting = self::where('type', $type)->where('is_active', true)->first();
        $rate = (float) ($setting?->rate ?? $fallbackRate);
        $calculationType = $setting?->calculation_type ?: self::TYPE_PERCENTAGE;
        $amount = $calculationType === self::TYPE_FIXED
            ? $rate
            : $base * ($rate / 100);

        return round(min($base, max(0, $amount)), 2);
    }
}
