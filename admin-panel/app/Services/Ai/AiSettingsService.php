<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Cache;

class AiSettingsService
{
    public const DEFAULTS = [
        'ai_enabled' => false,
        'ai_kill_switch' => false,
        'ai_provider' => 'gemini',
        'ai_fallback_provider' => 'openai',
        'ai_autonomy_mode' => 'monitor',
        'ai_simulation_mode' => true,
        'ai_daily_budget_usd' => 5,
        'ai_max_action_amount' => 1000,
        'ai_min_margin_percent' => 8,
        'ai_low_risk_auto_enabled' => false,
        'ai_auto_execute' => false,
        'ai_max_surge_fee_amount' => 30,
        'ai_notification_cooldown_hours' => 4,
        'ai_notifications_require_approval' => false,
        'ai_notification_images_enabled' => true,
        'ai_image_monthly_budget_usd' => 10,
        'ai_image_async' => false,
        'ai_min_promotion_margin_percent' => 15,
        'ai_cart_coupon_threshold_minutes' => 60,
        'ai_cart_coupon_discount_percent' => 10,
        'ai_cart_coupon_expiry_hours' => 48,
        'ai_menu_price_max_change_percent' => 15,
        'ai_gig_autoprovision_enabled' => false,
        'ai_gig_autoprovision_horizon_hours' => 24,
        'ai_gig_autoprovision_max_slots_per_run' => 3,
        'ai_gig_autoprovision_min_forecast_orders' => 5,
        'openai_model' => 'gpt-4o-mini',
        'gemini_model' => 'gemini-1.5-flash',
        'openai_api_key' => null,
        'gemini_api_key' => null,
    ];

    public function all(): array
    {
        return Cache::remember('ai_settings_resolved', 60, function () {
            $settings = self::DEFAULTS;

            AiSetting::query()->get()->each(function (AiSetting $setting) use (&$settings) {
                $settings[$setting->key] = $setting->decodedValue();
            });

            return $settings;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $type = match (true) {
                is_bool(self::DEFAULTS[$key] ?? null) => 'boolean',
                is_int(self::DEFAULTS[$key] ?? null), is_float(self::DEFAULTS[$key] ?? null) => 'number',
                default => 'string',
            };

            AiSetting::setValue($key, $value, $type, str_contains($key, 'api_key'));
        }

        Cache::forget('ai_settings_resolved');
        Cache::forget('ai_settings');
    }

    public function health(): array
    {
        return [
            'enabled' => $this->bool('ai_enabled'),
            'kill_switch' => $this->bool('ai_kill_switch'),
            'simulation_mode' => $this->bool('ai_simulation_mode', true),
            'autonomy_mode' => (string) $this->get('ai_autonomy_mode', 'monitor'),
            'provider' => (string) $this->get('ai_provider', 'gemini'),
            'gemini_configured' => filled($this->get('gemini_api_key')) || filled(config('services.gemini.key')),
            'openai_configured' => filled($this->get('openai_api_key')) || filled(config('services.openai.key')),
        ];
    }
}
