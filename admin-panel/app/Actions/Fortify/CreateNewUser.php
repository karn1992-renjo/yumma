<?php

namespace App\Actions\Fortify;

use App\Models\AppSetting;
use App\Models\User;
use App\Rules\UniqueUserContactForRole;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $normalizedPhone = PhoneNumber::normalize(
            $input['phone'] ?? null,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', UniqueUserContactForRole::email('customer')],
            'phone' => ['required', 'string', 'max:40'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->after(function ($validator) use ($input, $normalizedPhone) {
            if (preg_match('/[A-Za-z]/', (string) ($input['phone'] ?? ''))) {
                $validator->errors()->add('phone', 'Enter a valid mobile number.');
                return;
            }

            if ($normalizedPhone === '') {
                $validator->errors()->add('phone', 'Enter a valid mobile number.');
                return;
            }

            if (UniqueUserContactForRole::phoneExists($normalizedPhone, 'customer')) {
                $validator->errors()->add('phone', 'An account already exists with this mobile number for the customer role.');
            }
        })->validate();

        try {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $normalizedPhone,
                'password' => Hash::make($input['password']),
            ]);
        } catch (\Throwable $e) {
            if ($this->isDuplicateUserConstraint($e, 'phone')) {
                throw ValidationException::withMessages([
                    'phone' => 'An account already exists with this mobile number.',
                ]);
            }

            if ($this->isDuplicateUserConstraint($e, 'email')) {
                throw ValidationException::withMessages([
                    'email' => 'An account already exists with this email address.',
                ]);
            }

            throw $e;
        }

        $user->assignRole('customer');

        return $user;
    }

    protected function phoneLookupCandidates(string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = ltrim(
            PhoneNumber::normalizeCountryCode(AppSetting::getValue('default_mobile_country_code', '+91')),
            '+'
        );
        $candidates = [$phone, $digits, '+' . $digits, ltrim($digits, '0')];

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

    protected function isDuplicateUserConstraint(\Throwable $e, string $field): bool
    {
        $needle = match ($field) {
            'phone' => 'users_phone_unique',
            'email' => 'users_email_unique',
            default => '',
        };

        if ($needle === '') {
            return false;
        }

        do {
            $message = $e->getMessage();
            $code = (string) $e->getCode();

            if (
                str_contains($message, 'Duplicate entry')
                && str_contains($message, $needle)
            ) {
                return true;
            }

            if (
                in_array($code, ['23000', '23505'], true)
                && str_contains($message, $needle)
            ) {
                return true;
            }

            $e = $e->getPrevious();
        } while ($e);

        return false;
    }
}
