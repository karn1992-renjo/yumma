<?php

namespace App\Http\Controllers;

use App\Services\AdminMfaFirebase;
use App\Services\AdminMfaSecurity;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AdminSecurityController extends Controller
{
    public function index(Request $request, AdminMfaSecurity $security)
    {
        $user = $security->context($request);

        return response()->view('admin.settings.security', compact('user'))->header('Cache-Control', 'no-store, private');
    }

    public function start(Request $request, AdminMfaSecurity $security)
    {
        $security->start($request);

        return redirect()->route('admin-mfa.security.show');
    }

    public function show(Request $request, AdminMfaSecurity $security)
    {
        $user = $security->context($request);
        try {
            $challenge = $security->challenge($request, $user);
        } catch (ValidationException $e) {
            return redirect()->route($request->user('web') ? 'admin.security.index' : 'admin-mfa.show')->withErrors($e->errors());
        }
        $qrCode = $secret = $firebaseConfig = null;
        if (($challenge->payload['verification_transport'] ?? null) === 'firebase') {
            $firebaseConfig = app(AdminMfaFirebase::class)->clientConfig();
        }
        if ($challenge->verified_at && isset($challenge->payload['secret'])) {
            $displayUser = clone $user;
            $displayUser->two_factor_secret = $challenge->payload['secret'];
            $qrCode = $displayUser->twoFactorQrCodeSvg();
            $secret = Fortify::currentEncrypter()->decrypt($displayUser->two_factor_secret);
        }

        return response()->view('auth.admin-security-challenge', compact('user', 'challenge', 'qrCode', 'secret', 'firebaseConfig'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function verify(Request $request, AdminMfaSecurity $security)
    {
        $result = $security->verifyOtp($request);
        if ($result === 'complete') {
            return redirect()->route($request->user('web') ? 'admin.security.index' : 'admin-mfa.show')->with('status', 'Recovery mobile updated successfully.');
        }

        return redirect()->route('admin-mfa.security.show');
    }

    public function firebase(Request $request, AdminMfaSecurity $security)
    {
        $result = $security->verifyFirebase($request);
        if ($result === 'complete') {
            return redirect()->route($request->user('web') ? 'admin.security.index' : 'admin-mfa.show')->with('status', 'Recovery mobile verified with Firebase.');
        }

        return redirect()->route('admin-mfa.security.show');
    }

    public function resend(Request $request, AdminMfaSecurity $security)
    {
        $user = $security->context($request);
        $challenge = $security->challenge($request, $user);
        abort_if($challenge->verified_at, 403);
        $security->send($request, $user, $challenge->purpose, $challenge->payload['phone'], $challenge->payload);

        return redirect()->route('admin-mfa.security.show')->with('status', 'A new code has been sent.');
    }

    public function confirm(Request $request, AdminMfaSecurity $security)
    {
        $codes = $security->confirm($request);
        if ($codes) {
            $request->session()->flash('admin_mfa_recovery_codes', $codes);

            return redirect()->route('admin-mfa.recovery');
        }

        return redirect()->route('admin.security.index')->with('status', 'MFA is disabled. Your next login will use your password.');
    }

    public function cancel(Request $request, AdminMfaSecurity $security)
    {
        $security->context($request);
        $security->cancel($request);

        return redirect()->route($request->user('web') ? 'admin.security.index' : 'admin-mfa.show');
    }
}
