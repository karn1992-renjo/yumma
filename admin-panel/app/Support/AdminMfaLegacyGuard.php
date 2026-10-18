<?php

namespace App\Support;

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AdminMfaLegacyGuard
{
    public static function check($user): void
    {
        abort_if($user->requiresAdminMfa(), 403, 'Manage admin MFA from Settings > Security.');
    }

    public static function register($app): void
    {
        // Bind the underlying actions too: Livewire calls them without visiting Fortify routes.
        $app->bind(EnableTwoFactorAuthentication::class, fn ($app) => new class($app->make(TwoFactorAuthenticationProvider::class)) extends EnableTwoFactorAuthentication
        {
            public function __invoke($user, $force = false)
            {
                AdminMfaLegacyGuard::check($user);
                parent::__invoke($user, $force);
            }
        });
        $app->bind(ConfirmTwoFactorAuthentication::class, fn ($app) => new class($app->make(TwoFactorAuthenticationProvider::class)) extends ConfirmTwoFactorAuthentication
        {
            public function __invoke($user, $code)
            {
                AdminMfaLegacyGuard::check($user);
                parent::__invoke($user, $code);
            }
        });
        $app->bind(DisableTwoFactorAuthentication::class, fn () => new class() extends DisableTwoFactorAuthentication
        {
            public function __invoke($user)
            {
                AdminMfaLegacyGuard::check($user);
                parent::__invoke($user);
            }
        });
        $app->bind(GenerateNewRecoveryCodes::class, fn () => new class() extends GenerateNewRecoveryCodes
        {
            public function __invoke($user)
            {
                AdminMfaLegacyGuard::check($user);
                parent::__invoke($user);
            }
        });
    }
}
