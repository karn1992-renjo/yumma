<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;

class RestaurantOnboardingSettings
{
    public const TRIGGER_APPROVED = 'restaurant_approved';
    public const TRIGGER_ACTIVATED = 'restaurant_activated';
    public const TRIGGER_FIRST_ORDER = 'first_successful_order';

    public function all(): array
    {
        return [
            'enabled' => $this->bool('driver_restaurant_onboarding_enabled', true),
            'incentive_amount' => $this->amount(),
            'incentive_trigger' => $this->trigger(),
            'owner_otp' => $this->ownerOtpMode(),
            'gps_required' => $this->bool('driver_restaurant_onboarding_gps_required', false),
            'gps_radius_meters' => $this->int('driver_restaurant_onboarding_gps_radius_meters', 200),
            'daily_limit' => $this->nullableInt('driver_restaurant_onboarding_daily_limit'),
            'monthly_limit' => $this->nullableInt('driver_restaurant_onboarding_monthly_limit'),
            'eligible_driver_ids' => $this->csvInts('driver_restaurant_onboarding_eligible_driver_ids'),
            'eligible_zone_ids' => $this->csvInts('driver_restaurant_onboarding_eligible_zone_ids'),
            'eligible_categories' => $this->csvStrings('driver_restaurant_onboarding_eligible_categories'),
        ];
    }

    public function update(array $data): void
    {
        $values = [
            'driver_restaurant_onboarding_enabled' => ! empty($data['enabled']) ? '1' : '0',
            'driver_restaurant_onboarding_incentive_amount' => (string) max(0, (float) ($data['incentive_amount'] ?? 0)),
            'driver_restaurant_onboarding_incentive_trigger' => $data['incentive_trigger'] ?? self::TRIGGER_ACTIVATED,
            'driver_restaurant_onboarding_owner_otp' => $data['owner_otp'] ?? 'required',
            'driver_restaurant_onboarding_gps_required' => ! empty($data['gps_required']) ? '1' : '0',
            'driver_restaurant_onboarding_gps_radius_meters' => (string) max(0, (int) ($data['gps_radius_meters'] ?? 200)),
            'driver_restaurant_onboarding_daily_limit' => $data['daily_limit'] ?? '',
            'driver_restaurant_onboarding_monthly_limit' => $data['monthly_limit'] ?? '',
            'driver_restaurant_onboarding_eligible_driver_ids' => $data['eligible_driver_ids'] ?? '',
            'driver_restaurant_onboarding_eligible_zone_ids' => $data['eligible_zone_ids'] ?? '',
            'driver_restaurant_onboarding_eligible_categories' => $data['eligible_categories'] ?? '',
        ];

        foreach ($values as $key => $value) {
            AppSetting::setValue($key, $value);
        }
    }

    public function amount(): float
    {
        return max(0, (float) AppSetting::getValue('driver_restaurant_onboarding_incentive_amount', 300));
    }

    public function trigger(): string
    {
        $trigger = (string) AppSetting::getValue('driver_restaurant_onboarding_incentive_trigger', self::TRIGGER_ACTIVATED);

        return in_array($trigger, [self::TRIGGER_APPROVED, self::TRIGGER_ACTIVATED, self::TRIGGER_FIRST_ORDER], true)
            ? $trigger
            : self::TRIGGER_ACTIVATED;
    }

    public function ownerOtpMode(): string
    {
        $mode = (string) AppSetting::getValue('driver_restaurant_onboarding_owner_otp', 'required');

        return in_array($mode, ['required', 'optional', 'disabled'], true) ? $mode : 'required';
    }

    public function driverIsEligible(User $driver): bool
    {
        if (! $this->bool('driver_restaurant_onboarding_enabled', true) || ! (bool) $driver->is_active) {
            return false;
        }

        $ids = $this->csvInts('driver_restaurant_onboarding_eligible_driver_ids');
        if ($ids !== [] && ! in_array((int) $driver->id, $ids, true)) {
            return false;
        }

        $zoneIds = $this->csvInts('driver_restaurant_onboarding_eligible_zone_ids');
        if ($zoneIds !== [] && ! in_array((int) $driver->delivery_area_id, $zoneIds, true)) {
            return false;
        }

        $categories = $this->csvStrings('driver_restaurant_onboarding_eligible_categories');
        if ($categories !== []) {
            $category = trim((string) data_get($driver->payout_provider_meta, 'driver_category', ''));
            if ($category === '' || ! in_array($category, $categories, true)) {
                return false;
            }
        }

        return true;
    }

    private function bool(string $key, bool $default): bool
    {
        $value = AppSetting::getValue($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function int(string $key, int $default): int
    {
        $value = AppSetting::getValue($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function nullableInt(string $key): ?int
    {
        $value = AppSetting::getValue($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function csvInts(string $key): array
    {
        return collect(explode(',', (string) AppSetting::getValue($key, '')))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function csvStrings(string $key): array
    {
        return collect(explode(',', (string) AppSetting::getValue($key, '')))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
