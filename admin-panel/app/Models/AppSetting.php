<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description'];
    
    protected $casts = [
        'value' => 'string',
    ];
    
    public static function getValue($key, $default = null)
    {
        try {
            $settings = Cache::remember('app_settings', 3600, function () {
                return self::all()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            try {
                $settings = self::all()->pluck('value', 'key')->toArray();
            } catch (\Throwable $inner) {
                return $default;
            }
        }

        return $settings[$key] ?? $default;
    }

    public static function currencyDecimals(): int
    {
        $value = (int) self::getValue('currency_decimals', 2);

        return max(2, min(5, $value));
    }

    public static function sanitizedCurrencySymbol(): string
    {
        $symbol = trim((string) self::getValue('currency_symbol', '₹'));

        if ($symbol === '') {
            return '₹';
        }

        $normalized = preg_replace('/\s+/', '', $symbol);

        if (preg_match('/\{\{.*\}\}/', $normalized)
            || str_contains($normalized, 'currencySymbol')
            || str_contains($normalized, 'â')
            || str_contains($normalized, 'Ã¢')
            || str_contains($normalized, 'Â')
        ) {
            return html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8');
        }

        if (mb_strlen($normalized) > 5) {
            return html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8');
        }

        return $symbol;
    }
    
    public static function setValue($key, $value)
    {
        self::updateOrCreate(['key' => $key], ['value' => $value === null ? '' : $value]);

        try {
            Cache::forget('app_settings');
        } catch (\Throwable $e) {
            // Ignore cache store failures when the database/cache isn't ready yet.
        }
    }

    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = $value === null ? '' : (string) $value;
    }
}
