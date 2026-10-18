<?php

namespace App\Services;

use App\Exceptions\AdminMfaSmsException;
use App\Models\AdminMfaChallenge;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\AdminMfa;
use App\Support\PhoneNumber;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

class AdminMfaSecurity
{
    public function context(Request $request): User
    {
        if ($user = $request->user('web')) {
            if (! $user->requiresAdminMfa() || ! AdminMfa::verified($request, $user)) {
                $this->audit($user, 'security_context_rejected', $request);
                abort(403, 'Please sign in with an administrator account and complete its verification.');
            }

            return $user;
        }

        return AdminMfa::pendingUser($request);
    }

    public function start(Request $request): AdminMfaChallenge
    {
        $user = $this->context($request);
        $data = $request->validate([
            'action' => ['required', 'in:phone,remove,reset,enable,disable'],
            'slot' => ['nullable', 'integer', 'in:1,2'],
            'recovery_slot' => ['nullable', 'integer', 'in:1,2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'max:1024'],
            'factor' => ['nullable', 'string', 'max:100'],
        ]);
        $signedIn = (bool) $request->user('web');
        if ($signedIn && ! Hash::check((string) ($data['password'] ?? ''), $user->getAuthPassword())) {
            $this->fail('Enter your current password.', 'password');
        }
        $action = $data['action'];
        $phones = $user->adminRecoveryPhones();
        $slot = (int) ($data['slot'] ?? 1);
        $destination = $phones[(int) ($data['recovery_slot'] ?? 1)] ?? null;
        $payload = ['slot' => $slot, 'authorized_at' => now()->timestamp];

        if (in_array($action, ['enable', 'disable', 'remove'], true)) {
            abort_unless($signedIn, 403);
        }
        if ($action === 'reset') {
            abort_unless($user->admin_mfa_required && $user->hasEnabledTwoFactorAuthentication(), 403);
        }
        if ($action === 'enable') {
            abort_if($user->admin_mfa_required, 403);
        }
        if ($action === 'disable') {
            abort_unless($user->admin_mfa_required && $user->hasEnabledTwoFactorAuthentication(), 403);
        }

        if (in_array($action, ['phone', 'remove'], true)) {
            if (! $signedIn) {
                // An enrolled admin cannot change the recovery destination using only a password.
                if ($user->hasEnabledTwoFactorAuthentication() && ! $request->session()->get('admin_mfa.pending.factor_passed')) {
                    $this->audit($user, 'phone_enrollment_factor_required', $request);
                    $this->cancel($request);
                    $request->session()->forget('admin_mfa');
                    throw new HttpResponseException(redirect()->route('login')->withErrors([
                        'email' => 'Please sign in again and verify your existing authenticator or recovery code before registering a recovery mobile.',
                    ]));
                }
                if (isset($phones[$slot])) {
                    $this->audit($user, 'phone_enrollment_slot_already_verified', $request);
                    $request->session()->put('admin_mfa.pending.mobile_enrollment', true);
                    throw new HttpResponseException(redirect()->route('admin-mfa.show')->withErrors([
                        'phone' => 'This mobile slot is already verified. Continue registration, or change it later in Settings > Security.',
                    ]));
                }
            } elseif ($user->admin_mfa_required) {
                $valid = DB::transaction(function () use ($user, $data) {
                    $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                    return $this->factor($locked, (string) ($data['factor'] ?? ''));
                });
                if (! $valid) {
                    $this->fail('Enter a valid authenticator or recovery code.', 'factor');
                }
            }

            if ($action === 'remove') {
                if (count($phones) < 2 || ! isset($phones[$slot])) {
                    $this->fail('Keep at least one verified recovery mobile.');
                }
            } else {
                $this->audit($user, 'phone_enrollment_requested', $request);
                $rawPhone = (string) ($data['phone'] ?? '');
                $phone = PhoneNumber::normalize($rawPhone, AppSetting::getValue('default_mobile_country_code', '+91'));
                if (preg_match('/[^0-9+()\s-]/', $rawPhone) || ! preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
                    $this->fail('Enter a valid mobile number including its country code.', 'phone');
                }
                if (in_array($phone, $phones, true)) {
                    $this->fail('Use a different mobile number for each slot.', 'phone');
                }
                if ($slot === 2 && ! isset($phones[1])) {
                    $this->fail('Verify Mobile 1 first.', 'phone');
                }
                $payload['new_phone'] = $phone;
                if ($signedIn && ! $user->admin_mfa_required && $phones) {
                    $action = 'phone_authorize';
                } else {
                    $destination = $phone;
                }
            }
        }

        if (! $destination) {
            $this->fail('Choose a verified recovery mobile.');
        }

        return $this->send($request, $user, $action, $destination, $payload);
    }

    public function send(Request $request, User $user, string $purpose, string $phone, array $payload): AdminMfaChallenge
    {
        if (($payload['authorized_at'] ?? 0) + 600 <= now()->timestamp) {
            $this->fail('Password confirmation has expired. Start again.');
        }
        $binding = $request->session()->get('admin_security.binding');
        if (! $binding) {
            $binding = Str::random(64);
            $request->session()->put('admin_security.binding', $binding);
        }
        // Reserve all limits while holding the account lock, including across sessions.
        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($request, $user, $purpose, $phone, $payload, $binding, $code) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->admin_mfa_version === (int) $user->admin_mfa_version
                && hash_equals($locked->getAuthPassword(), $user->getAuthPassword()), 409);
            $keys = [
                ['admin-mfa-send:account:' . $user->id, 5, 3600],
                ['admin-mfa-send:phone:' . hash('sha256', $phone), 5, 3600],
                ['admin-mfa-send:ip:' . $request->ip(), 20, 3600],
                ['admin-mfa-send:cooldown:' . $user->id . ':' . hash('sha256', $phone), 1, 60],
            ];
            foreach ($keys as [$key, $max, $seconds]) {
                if (RateLimiter::tooManyAttempts($key, $max)) {
                    $this->fail('Please wait ' . RateLimiter::availableIn($key) . ' seconds before sending another code.');
                }
            }
            foreach ($keys as [$key, $max, $seconds]) {
                RateLimiter::hit($key, $seconds);
            }
            AdminMfaChallenge::where('user_id', $user->id)->where('session_hash', hash('sha256', $binding))
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $challenge = new AdminMfaChallenge();
            $challenge->forceFill([
                'id' => (string) Str::uuid(), 'user_id' => $user->id,
                'session_hash' => hash('sha256', $binding), 'security_version' => (int) $user->admin_mfa_version,
                'password_hash' => hash('sha256', $user->getAuthPassword()), 'purpose' => $purpose,
                'payload' => array_merge($payload, ['phone' => $phone]), 'otp_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(5),
            ])->save();

            return $challenge;
        });
        $request->session()->put('admin_security.challenge', $challenge->id);
        try {
            app(AdminMfaSms::class)->send($phone, $code);
        } catch (\Throwable $e) {
            $firebase = app(AdminMfaFirebase::class);
            if ($e instanceof AdminMfaSmsException && $firebase->available()) {
                $payload = $challenge->payload;
                $payload['verification_transport'] = 'firebase';
                $challenge->forceFill(['payload' => $payload])->save();
                Log::warning('Admin MFA SMS failed; Firebase fallback started', [
                    'user_id' => $user->id,
                    ...$e->context(),
                    'exception' => class_basename($e),
                ]);
                $this->audit($user, 'firebase_fallback_started', $request);

                return $challenge;
            }
            $challenge->forceFill(['consumed_at' => now()])->save();
            $this->audit($user, 'sms_failed', $request);
            if ($e instanceof AdminMfaSmsException) {
                Log::warning('Admin MFA SMS delivery failed', array_merge(
                    ['user_id' => $user->id, 'exception' => class_basename($e)],
                    $e->context(),
                ));
                $this->fail($e->getMessage());
            }
            Log::warning('Admin MFA SMS delivery failed', [
                'user_id' => $user->id,
                'reason' => 'unexpected_error',
                'exception' => class_basename($e),
            ]);
            $this->fail('SMS could not be sent. Check the configured SMS provider and try again.');
        }
        $this->audit($user, 'otp_sent', $request);

        return $challenge;
    }

    public function challenge(Request $request, User $user, bool $lock = false): AdminMfaChallenge
    {
        $query = AdminMfaChallenge::whereKey($request->session()->get('admin_security.challenge'));
        $challenge = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $challenge || $challenge->user_id !== $user->id || $challenge->consumed_at
            || $challenge->expires_at->isPast() || ($challenge->attempts >= 5 && ! $challenge->verified_at)
            || ($challenge->payload['authorized_at'] ?? 0) + 600 <= now()->timestamp
            || (int) $challenge->security_version !== (int) $user->admin_mfa_version
            || ! hash_equals($challenge->password_hash, hash('sha256', $user->getAuthPassword()))
            || ! hash_equals($challenge->session_hash, hash('sha256', (string) $request->session()->get('admin_security.binding')))) {
            $this->fail('This verification has expired. Start again.');
        }

        return $challenge;
    }

    public function verifyOtp(Request $request): string
    {
        $user = $this->context($request);
        $request->validate(['otp' => ['required', 'regex:/^[0-9]{6}$/']]);
        $result = DB::transaction(function () use ($request, &$user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $challenge = $this->challenge($request, $user, true);
            if ($challenge->verified_at) {
                $this->fail('This code has already been used.');
            }
            if (($challenge->payload['verification_transport'] ?? 'sms') === 'firebase') {
                $this->fail('Complete Firebase phone verification on this page.', 'otp');
            }
            $challenge->attempts++;
            if (! Hash::check($request->input('otp'), $challenge->otp_hash)) {
                $challenge->save();

                return ['invalid']; // Commit failed attempts rather than rolling them back.
            }

            return $this->completeVerifiedChallenge($user, $challenge);
        });
        if ($result[0] === 'invalid') {
            $this->audit($user, 'otp_failed', $request);
            $this->fail('The SMS code is invalid or has expired.', 'otp');
        }
        if ($result[0] === 'next_phone') {
            $this->send($request, $user, 'phone', $result[1]['new_phone'], $result[1]);

            return 'confirm';
        }
        if ($result[0] === 'complete') {
            $this->syncSession($request, $user);
            $this->audit($user, $result[1] === 'phone' ? 'phone_verified' : 'phone_removed', $request);
        }

        return $result[0];
    }

    public function verifyFirebase(Request $request): string
    {
        $user = $this->context($request);
        $request->validate(['firebase_id_token' => ['required', 'string', 'max:10000']]);
        $challenge = $this->challenge($request, $user);
        if ($challenge->verified_at || ($challenge->payload['verification_transport'] ?? null) !== 'firebase') {
            $this->fail('Firebase verification is not active for this challenge.', 'firebase');
        }

        try {
            $identity = app(AdminMfaFirebase::class)->verify((string) $request->input('firebase_id_token'));
        } catch (ValidationException $e) {
            $this->recordFailedAttempt($request, $user);
            throw $e;
        }

        $result = DB::transaction(function () use ($request, &$user, $identity) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $challenge = $this->challenge($request, $user, true);
            if ($challenge->verified_at || ($challenge->payload['verification_transport'] ?? null) !== 'firebase') {
                $this->fail('Firebase verification is not active for this challenge.', 'firebase');
            }
            $challenge->attempts++;
            $expectedPhone = (string) $challenge->payload['phone'];
            $verifiedPhone = PhoneNumber::normalize(
                (string) $identity['phone'],
                AppSetting::getValue('default_mobile_country_code', '+91'),
            );
            $freshAfter = $challenge->created_at->copy()->subMinute()->timestamp;
            if (! hash_equals($expectedPhone, $verifiedPhone)
                || (int) $identity['auth_time'] < $freshAfter
                || (int) $identity['auth_time'] > now()->addMinute()->timestamp) {
                $challenge->save();

                return ['invalid'];
            }

            return $this->completeVerifiedChallenge($user, $challenge);
        });
        if ($result[0] === 'invalid') {
            $this->audit($user, 'firebase_verification_failed', $request);
            $this->fail('Firebase verification must match the selected mobile and be completed during this request.', 'firebase');
        }
        $this->audit($user, 'firebase_phone_verified', $request);
        if ($result[0] === 'next_phone') {
            $this->send($request, $user, 'phone', $result[1]['new_phone'], $result[1]);

            return 'confirm';
        }
        if ($result[0] === 'complete') {
            $this->syncSession($request, $user);
            $this->audit($user, $result[1] === 'phone' ? 'phone_verified' : 'phone_removed', $request);
        }

        return $result[0];
    }

    private function completeVerifiedChallenge(User $user, AdminMfaChallenge $challenge): array
    {
        $challenge->verified_at = now();
        if (in_array($challenge->purpose, ['phone', 'remove'], true)) {
            $payload = $challenge->payload;
            $slot = $payload['slot'];
            if ($challenge->purpose === 'phone') {
                $user->forceFill(["admin_mfa_phone_{$slot}" => $payload['new_phone'], "admin_mfa_phone_{$slot}_verified_at" => now()]);
            } else {
                if (count($user->adminRecoveryPhones()) < 2) {
                    $this->fail('Keep at least one verified recovery mobile.');
                }
                if ($slot === 1) {
                    $user->forceFill(['admin_mfa_phone_1' => $user->admin_mfa_phone_2, 'admin_mfa_phone_1_verified_at' => $user->admin_mfa_phone_2_verified_at]);
                }
                $user->forceFill(['admin_mfa_phone_2' => null, 'admin_mfa_phone_2_verified_at' => null]);
            }
            $this->rotateVersion($user);

            return ['complete', $challenge->purpose];
        }
        if ($challenge->purpose === 'phone_authorize') {
            $challenge->consumed_at = now();
            $challenge->save();

            return ['next_phone', $challenge->payload];
        }
        $payload = $challenge->payload;
        if (in_array($challenge->purpose, ['reset', 'enable'], true)) {
            $payload['secret'] = Fortify::currentEncrypter()->encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey(32));
        }
        $challenge->forceFill(['payload' => $payload, 'expires_at' => now()->addMinutes(5)])->save();

        return ['confirm'];
    }

    private function recordFailedAttempt(Request $request, User $user): void
    {
        DB::transaction(function () use ($request, $user) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $challenge = $this->challenge($request, $lockedUser, true);
            $challenge->increment('attempts');
        });
    }

    public function confirm(Request $request): array
    {
        $user = $this->context($request);
        $request->validate(['confirm' => ['required', 'accepted'], 'code' => ['nullable', 'regex:/^[0-9]{6}$/']]);
        $codes = DB::transaction(function () use ($request, &$user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $challenge = $this->challenge($request, $user, true);
            abort_unless($challenge->verified_at && in_array($challenge->purpose, ['reset', 'enable', 'disable'], true), 403);
            if ($challenge->purpose === 'disable') {
                abort_unless($request->user('web'), 403);
                $user->forceFill(['admin_mfa_required' => false, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]);
                $codes = [];
            } else {
                $secret = $challenge->payload['secret'];
                if (! app(TwoFactorAuthenticationProvider::class)->verify(Fortify::currentEncrypter()->decrypt($secret), (string) $request->input('code'))) {
                    $this->fail('Enter a valid code from the new authenticator.', 'code');
                }
                $codes = collect(range(1, 8))->map(fn () => RecoveryCode::generate())->all();
                $user->forceFill(['admin_mfa_required' => true, 'two_factor_secret' => $secret,
                    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($codes)), 'two_factor_confirmed_at' => now()]);
            }
            $this->rotateVersion($user);
            $this->audit($user, 'mfa_' . $challenge->purpose, $request);

            return $codes;
        });
        if ($request->user('web')) {
            $request->session()->regenerate();
            $this->syncSession($request, $user);
        } else {
            AdminMfa::finishLogin($request, $user);
        }

        return $codes;
    }

    public function factor(User $user, string $code): bool
    {
        if (! $user->hasEnabledTwoFactorAuthentication() || $code === '') {
            return false;
        }
        if (preg_match('/^[0-9]{6}$/', $code)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code);
        }
        foreach ($user->recoveryCodes() as $recovery) {
            if (hash_equals($recovery, $code)) {
                $user->replaceRecoveryCode($recovery);

                return true;
            }
        }

        return false;
    }

    public function rotateVersion(User $user): void
    {
        $user->forceFill(['admin_mfa_version' => (int) $user->admin_mfa_version + 1, 'remember_token' => Str::random(60)])->save();
        AdminMfaChallenge::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
    }

    public function syncSession(Request $request, User $user): void
    {
        $request->session()->forget('admin_security');
        if ($request->user('web')) {
            auth('web')->setUser($user);
            AdminMfa::stamp($request, $user);
        } else {
            $request->session()->put('admin_mfa.pending.version', (int) $user->admin_mfa_version);
            $request->session()->put('admin_mfa.pending.mobile_enrollment', true);
        }
    }

    public function cancel(Request $request): void
    {
        $challengeId = $request->session()->get('admin_security.challenge');
        $binding = $request->session()->get('admin_security.binding');
        if ($challengeId && $binding) {
            AdminMfaChallenge::whereKey($challengeId)
                ->where('user_id', $request->user('web')?->id ?? $request->session()->get('admin_mfa.pending.id'))
                ->where('session_hash', hash('sha256', $binding))
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);
        }
        $request->session()->forget('admin_security');
    }

    public function audit(User $user, string $event, Request $request): void
    {
        Log::info('Admin MFA security event', ['user_id' => $user->id, 'event' => $event, 'ip' => $request->ip()]);
    }

    private function fail(string $message, string $field = 'security'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
