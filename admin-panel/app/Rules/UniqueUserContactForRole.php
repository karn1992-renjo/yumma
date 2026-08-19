<?php

namespace App\Rules;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

class UniqueUserContactForRole implements ValidationRule
{
    public function __construct(
        private readonly string $contactType,
        private readonly ?string $role,
        private readonly ?int $ignoreUserId = null
    ) {
    }

    public static function email(?string $role, ?int $ignoreUserId = null): self
    {
        return new self('email', $role, $ignoreUserId);
    }

    public static function phone(?string $role, ?int $ignoreUserId = null): self
    {
        return new self('phone', $role, $ignoreUserId);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = $this->contactType === 'phone'
            ? self::phoneExists((string) $value, $this->role, $this->ignoreUserId)
            : self::emailExists((string) $value, $this->role, $this->ignoreUserId);

        if (! $exists) {
            return;
        }

        $fail($this->contactType === 'phone'
            ? 'This mobile number is already used for the selected role.'
            : 'This email address is already used for the selected role.');
    }

    public static function emailExists(?string $email, ?string $role, ?int $ignoreUserId = null): bool
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }

        return self::queryForRole($role)
            ->when($ignoreUserId, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();
    }

    public static function phoneExists(?string $phone, ?string $role, ?int $ignoreUserId = null): bool
    {
        $candidates = self::phoneLookupCandidates($phone);
        if (empty($candidates)) {
            return false;
        }

        return self::queryForRole($role)
            ->when($ignoreUserId, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
            ->whereIn('phone', $candidates)
            ->exists();
    }

    public static function findByEmailForRole(?string $email, ?string $role): ?User
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        return self::queryForRole($role)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->latest('id')
            ->first();
    }

    public static function findByPhoneForRole(?string $phone, ?string $role): ?User
    {
        $candidates = self::phoneLookupCandidates($phone);
        if (empty($candidates)) {
            return null;
        }

        return self::queryForRole($role)
            ->whereIn('phone', $candidates)
            ->latest('id')
            ->first();
    }

    public static function normalizeRole(?string $role): string
    {
        return match ($role) {
            'restaurant' => 'restaurant_owner',
            'driver' => 'delivery_partner',
            'restaurant_owner', 'restaurant_staff', 'delivery_partner', 'customer',
            'super_admin', 'admin', 'branch_owner', 'branch_manager', 'branch_staff' => $role,
            default => 'customer',
        };
    }

    public static function phoneLookupCandidates(?string $phone): array
    {
        $normalized = PhoneNumber::normalize(
            $phone,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );

        if ($normalized === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        $countryDigits = ltrim(
            PhoneNumber::normalizeCountryCode(AppSetting::getValue('default_mobile_country_code', '+91')),
            '+'
        );

        $candidates = [$normalized, $digits, '+' . $digits, ltrim($digits, '0')];

        if ($countryDigits !== '') {
            if (str_starts_with($digits, $countryDigits)) {
                $localDigits = substr($digits, strlen($countryDigits));
                if ($localDigits !== false && $localDigits !== '') {
                    $candidates[] = $localDigits;
                    $candidates[] = '0' . $localDigits;
                    $candidates[] = '+' . $countryDigits . $localDigits;
                }
            } else {
                $candidates[] = '+' . $countryDigits . $digits;
                $candidates[] = $countryDigits . $digits;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private static function queryForRole(?string $role): Builder
    {
        $role = self::normalizeRole($role);

        if ($role === 'restaurant_owner') {
            return User::query()->where(function (Builder $query) {
                $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery
                    ->whereIn('name', ['restaurant_owner', 'restaurant_staff']))
                    ->orWhereHas('restaurantStaff');
            });
        }

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', $role));
    }
}
