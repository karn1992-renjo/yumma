<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminMfaSecurity;
use App\Support\AdminMfa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

class AdminMfaController extends Controller
{
    public function show(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $user = AdminMfa::pendingUser($request);
        if ((! $user->adminRecoveryPhones() && (! $user->hasEnabledTwoFactorAuthentication() || $request->session()->get('admin_mfa.pending.factor_passed') || ! $user->admin_mfa_required))
            || $request->session()->get('admin_mfa.pending.factor_passed') || $request->session()->get('admin_mfa.pending.mobile_enrollment')) {
            return response()->view('auth.admin-mobile-enrollment', ['user' => $user])
                ->header('Cache-Control', 'no-store, private');
        }
        $setup = ! $user->hasEnabledTwoFactorAuthentication();
        $qrCode = $secret = null;

        if ($setup) {
            // Keep unconfirmed enrollment in this session, never overwrite another session's setup.
            if (! $request->session()->has('admin_mfa.setup_secret')) {
                $request->session()->put('admin_mfa.setup_secret', Fortify::currentEncrypter()->encrypt($provider->generateSecretKey(32)));
            }

            $user->two_factor_secret = $request->session()->get('admin_mfa.setup_secret');
            $qrCode = $user->twoFactorQrCodeSvg();
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        }

        return response()->view('auth.admin-mfa', compact('setup', 'qrCode', 'secret'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function verify(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $pendingUser = AdminMfa::pendingUser($request);
        abort_unless($pendingUser->admin_mfa_required, 403);
        $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'regex:/^[0-9]{6}$/'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:100'],
        ]);

        [$user, $recoveryCodes] = DB::transaction(function () use ($request, $provider, $pendingUser) {
            $user = User::whereKey($pendingUser->getKey())->lockForUpdate()->firstOrFail();
            abort_unless((int) $user->admin_mfa_version === (int) $pendingUser->admin_mfa_version, 409);
            $setup = ! $user->hasEnabledTwoFactorAuthentication();
            abort_if($setup && ! $user->adminRecoveryPhones(), 403, 'Verify a recovery mobile first.');
            $secret = $setup ? $request->session()->get('admin_mfa.setup_secret') : $user->two_factor_secret;
            $valid = false;

            if (! $setup && $request->filled('recovery_code')) {
                foreach ($user->recoveryCodes() as $recoveryCode) {
                    if (hash_equals($recoveryCode, (string) $request->input('recovery_code'))) {
                        $user->replaceRecoveryCode($recoveryCode);
                        $valid = true;
                        break;
                    }
                }
            } elseif ($secret && $request->filled('code')) {
                $valid = $provider->verify(Fortify::currentEncrypter()->decrypt($secret), $request->input('code'));
            }

            if (! $valid) {
                throw ValidationException::withMessages(['code' => __('The authentication code is invalid or has expired.')]);
            }

            $recoveryCodes = [];
            if ($setup) {
                $recoveryCodes = collect(range(1, 8))->map(fn () => RecoveryCode::generate())->all();
                $user->forceFill([
                    'two_factor_secret' => $secret,
                    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($recoveryCodes)),
                    'two_factor_confirmed_at' => now(),
                ])->save();
                app(AdminMfaSecurity::class)->rotateVersion($user);
                app(AdminMfaSecurity::class)->audit($user, 'mfa_enrolled', $request);
            }

            return [$user, $recoveryCodes];
        });

        if (! $user->adminRecoveryPhones()) {
            $request->session()->put('admin_mfa.pending.factor_passed', true);

            return redirect()->route('admin-mfa.show');
        }
        AdminMfa::finishLogin($request, $user);

        if ($recoveryCodes) {
            $request->session()->flash('admin_mfa_recovery_codes', $recoveryCodes);

            return redirect()->route('admin-mfa.recovery');
        }

        return redirect()->route('admin.dashboard');
    }

    public function recovery(Request $request)
    {
        abort_unless($request->user()->requiresAdminMfa() && AdminMfa::verified($request, $request->user()), 403);
        $recoveryCodes = $request->session()->get('admin_mfa_recovery_codes');

        if (! $recoveryCodes) {
            return redirect()->route('admin.dashboard');
        }

        return response()->view('auth.admin-mfa', ['recoveryCodes' => $recoveryCodes])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function cancel(Request $request)
    {
        app(AdminMfaSecurity::class)->cancel($request);
        $request->session()->forget(['admin_mfa.pending', 'admin_mfa.setup_secret', 'admin_security']);
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function continueLogin(Request $request)
    {
        $user = AdminMfa::pendingUser($request);
        abort_unless($user->adminRecoveryPhones(), 403);
        $request->session()->forget('admin_mfa.pending.mobile_enrollment');
        if (! $user->admin_mfa_required || ($user->hasEnabledTwoFactorAuthentication() && $request->session()->get('admin_mfa.pending.factor_passed'))) {
            AdminMfa::finishLogin($request, $user);

            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('admin-mfa.show');
    }
}
