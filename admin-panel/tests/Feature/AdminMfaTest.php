<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Tests\Support\AdminMfaTestCase;

class AdminMfaTest extends AdminMfaTestCase
{
    public function test_admin_password_login_requires_qr_registration_without_authenticating(): void
    {
        $user = $this->user();
        $this->passwordLogin($user)->assertRedirect(route('admin-mfa.show'));
        $this->assertGuest();
        $this->get('/admin-mfa')->assertOk()->assertSee('Set up your authenticator')->assertSee('<svg', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $secret = session('admin_mfa.setup_secret');
        $this->get('/admin-mfa')->assertOk();
        $this->assertSame($secret, session('admin_mfa.setup_secret'));
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/_mfa-test/private')->assertRedirect(route('login'));
    }

    public function test_registration_requires_valid_code_then_saves_encrypted_secret_and_recovery_codes(): void
    {
        $user = $this->user('super_admin');
        $this->passwordLogin($user);
        $this->get('/admin-mfa');
        $secret = session('admin_mfa.setup_secret');
        $this->post('/admin-mfa', ['code' => $this->code($secret)])->assertRedirect(route('admin-mfa.recovery'));
        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertNull(session('admin_mfa.pending'));
        $this->assertNull(session('admin_mfa.setup_secret'));
        $this->get('/admin-mfa/recovery')->assertOk()->assertSee('Save your recovery codes')->assertSee($user->recoveryCodes()[0]);
        $this->get('/_mfa-test/private')->assertOk();
    }

    public function test_invalid_setup_code_does_not_enable_mfa_or_authenticate(): void
    {
        $user = $this->user();
        $this->passwordLogin($user);
        $this->get('/admin-mfa');
        $code = $this->code(session('admin_mfa.setup_secret')) === '000000' ? '111111' : '000000';
        $this->post('/admin-mfa', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_registered_admin_is_challenged_without_showing_qr_again(): void
    {
        $user = $this->user('admin', true);
        $this->passwordLogin($user)->assertRedirect(route('admin-mfa.show'));
        $this->assertGuest();
        $this->get('/admin-mfa')->assertOk()->assertSee('Verify your admin login')->assertDontSee('<svg', false)
            ->assertDontSee(Fortify::currentEncrypter()->decrypt($user->two_factor_secret));
        $this->post('/admin-mfa', ['code' => $this->code($user->two_factor_secret)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/_mfa-test/private')->assertOk();
    }

    public function test_invalid_password_cannot_get_enrollment_secret(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->get('/admin-mfa')->assertRedirect(route('login'));
        $this->assertNull(session('admin_mfa.setup_secret'));
        $this->assertGuest();
    }

    public function test_non_admin_logins_do_not_require_mfa(): void
    {
        foreach (['customer', 'restaurant_owner', 'restaurant_staff', 'delivery_partner', 'branch_owner', 'theatre_owner', 'support'] as $role) {
            $user = $this->user($role);
            $this->passwordLogin($user)->assertRedirect();
            $this->assertAuthenticatedAs($user);
            $this->assertNull(session('admin_mfa.pending'));
            $this->get('/_mfa-test/private')->assertOk();
            Auth::logout();
        }
    }

    public function test_existing_admin_session_cannot_bypass_mfa(): void
    {
        $user = $this->user('admin', true);
        $this->actingAs($user)->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('admin_mfa.pending.id'));
    }

    public function test_expired_challenge_requires_password_again(): void
    {
        $user = $this->user();
        $this->passwordLogin($user);
        $this->get('/admin-mfa');
        $this->travel(11)->minutes();
        $this->post('/admin-mfa', ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertNull(session('admin_mfa.setup_secret'));
        $this->assertGuest();
    }

    public function test_password_change_invalidates_pending_challenge(): void
    {
        $user = $this->user();
        $this->passwordLogin($user);
        $user->forceFill(['password' => 'new-password'])->save();
        $this->get('/admin-mfa')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_recovery_code_is_usable_only_once(): void
    {
        $user = $this->user('admin', true);
        $recovery = $user->recoveryCodes()[0];
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['recovery_code' => $recovery])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes());
        $this->post('/logout');
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['recovery_code' => $recovery])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_cannot_confirm_first_time_enrollment(): void
    {
        $user = $this->user();
        (new EnableTwoFactorAuthentication(app(TwoFactorAuthenticationProvider::class)))($user);
        $this->passwordLogin($user);
        $this->get('/admin-mfa');
        $this->post('/admin-mfa', ['recovery_code' => $user->recoveryCodes()[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_verification_attempts_are_rate_limited(): void
    {
        $this->passwordLogin($this->user());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin-mfa', ['code' => '123456'])->assertSessionHasErrors('code');
        }
        $this->post('/admin-mfa', ['code' => '123456'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_cancel_clears_pending_enrollment(): void
    {
        $this->passwordLogin($this->user());
        $this->get('/admin-mfa');
        $this->post('/admin-mfa/cancel')->assertRedirect(route('login'));
        $this->get('/admin-mfa')->assertRedirect(route('login'));
        $this->assertNull(session('admin_mfa.setup_secret'));
    }

    public function test_logout_clears_verification_and_next_login_requires_mfa(): void
    {
        $user = $this->user('admin', true);
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['code' => $this->code($user->two_factor_secret)]);
        $this->post('/logout')->assertRedirect('/');
        $this->assertNull(session('admin_mfa.verified_id'));
        $this->assertGuest();
        $this->passwordLogin($user)->assertRedirect(route('admin-mfa.show'));
        $this->assertGuest();
    }

    public function test_json_admin_login_reports_mfa_required(): void
    {
        $user = $this->user();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJson(['two_factor' => true, 'redirect' => route('admin-mfa.show')]);
        $this->assertGuest();
    }

    public function test_authenticator_code_cannot_be_replayed_after_logout(): void
    {
        $user = $this->user('admin', true);
        $code = $this->code($user->two_factor_secret);
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_another_sessions_completed_enrollment_cannot_be_overwritten(): void
    {
        $user = $this->user();
        $this->passwordLogin($user);
        $this->get('/admin-mfa');
        $pendingSecret = session('admin_mfa.setup_secret');
        (new EnableTwoFactorAuthentication(app(TwoFactorAuthenticationProvider::class)))($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        // Use the pending session's old code, avoiding a possible six-digit collision.
        $this->assertNotSame($pendingSecret, $user->two_factor_secret);
        $code = $this->code($pendingSecret);
        if ($code === $this->code($user->two_factor_secret)) {
            $this->markTestSkipped('Independent authenticator codes coincided in this time step.');
        }
        $this->post('/admin-mfa', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame($user->two_factor_secret, $user->fresh()->two_factor_secret);
        $this->assertGuest();
    }

    public function test_mfa_verification_cannot_be_reused_for_another_admin(): void
    {
        $first = $this->user('admin', true);
        $second = $this->user('super_admin', true);
        $this->passwordLogin($first);
        $this->post('/admin-mfa', ['code' => $this->code($first->two_factor_secret)]);
        $this->actingAs($second)->get('/_mfa-test/private')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('admin_mfa.pending.id'));
    }

    public function test_non_admin_cannot_access_admin_recovery_codes(): void
    {
        $this->actingAs($this->user('customer'))->get('/admin-mfa/recovery')->assertForbidden();
    }
}
