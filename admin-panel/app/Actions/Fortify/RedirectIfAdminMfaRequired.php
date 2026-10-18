<?php

namespace App\Actions\Fortify;

use App\Support\AdminMfa;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;

class RedirectIfAdminMfaRequired extends RedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        // Never expose enrollment credentials before validating the password.
        $user = $this->validateCredentials($request);
        $request->session()->forget(['admin_mfa', 'admin_mfa_recovery_codes']);

        if ($user->requiresAdminMfa() && ($user->admin_mfa_required || ! $user->adminRecoveryPhones())) {
            return AdminMfa::begin($request, $user, $request->boolean('remember'));
        }

        if ($user->requiresAdminMfa()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
