<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMfa
{
    public static function begin(Request $request, User $user, bool $remember = false)
    {
        $request->session()->forget(['admin_mfa', 'admin_security', 'admin_mfa_recovery_codes', 'login.id', 'login.remember']);
        $request->session()->regenerate();
        $request->session()->put('admin_mfa.pending', [
            'id' => $user->getKey(),
            'remember' => $remember,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'password_hash' => hash('sha256', $user->getAuthPassword()),
            'version' => (int) $user->admin_mfa_version,
        ]);

        return $request->expectsJson()
            ? response()->json(['two_factor' => true, 'redirect' => route('admin-mfa.show')])
            : redirect()->route('admin-mfa.show');
    }

    public static function verified(Request $request, User $user): bool
    {
        return (! $user->admin_mfa_required || $user->hasEnabledTwoFactorAuthentication())
            && (string) $request->session()->get('admin_mfa.verified_id') === (string) $user->getKey()
            && (int) $request->session()->get('admin_mfa.version', -1) === (int) $user->admin_mfa_version
            && hash_equals(hash('sha256', (string) $user->two_factor_secret), (string) $request->session()->get('admin_mfa.secret_hash'));
    }

    public static function stamp(Request $request, User $user): void
    {
        $request->session()->put([
            'admin_mfa.verified_id' => $user->getKey(),
            'admin_mfa.secret_hash' => hash('sha256', (string) $user->two_factor_secret),
            'admin_mfa.version' => (int) $user->admin_mfa_version,
        ]);
    }

    public static function pendingUser(Request $request): User
    {
        $pending = $request->session()->get('admin_mfa.pending', []);
        $user = isset($pending['id']) ? User::find($pending['id']) : null;
        if (! $user || ! $user->requiresAdminMfa()
            || ($pending['expires_at'] ?? 0) <= now()->timestamp
            || (int) ($pending['version'] ?? -1) !== (int) $user->admin_mfa_version
            || ! hash_equals(hash('sha256', $user->getAuthPassword()), (string) ($pending['password_hash'] ?? ''))) {
            $request->session()->forget(['admin_mfa', 'admin_security']);
            throw new HttpResponseException(redirect()->route('login')->withErrors(['email' => __('Your sign-in has expired. Please log in again.')]));
        }

        return $user;
    }

    public static function finishLogin(Request $request, User $user): void
    {
        $remember = (bool) $request->session()->get('admin_mfa.pending.remember');
        $request->session()->forget(['admin_mfa', 'admin_security']);
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        self::stamp($request, $user);
    }

    public static function maskPhone(string $phone): string
    {
        return str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);
    }
}
