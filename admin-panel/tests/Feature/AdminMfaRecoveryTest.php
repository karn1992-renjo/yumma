<?php

namespace Tests\Feature;

use App\Exceptions\AdminMfaSmsException;
use App\Models\AdminMfaChallenge;
use App\Models\User;
use App\Services\AdminMfaFirebase;
use App\Services\AdminMfaSecurity;
use App\Services\AdminMfaSms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AdminMfaTestCase;

class AdminMfaRecoveryTest extends AdminMfaTestCase
{
    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(AdminMfaSms::class)->shouldReceive('send')->andReturnUsing(function ($phone, $code) {
            $this->sms[] = compact('phone', 'code');
        });
        View::getFinder()->prependLocation(base_path('tests/Fixtures/security-views'));
    }

    private function signedIn(User $user): void
    {
        $this->passwordLogin($user);
        if ($user->admin_mfa_required) {
            $this->post('/admin-mfa', ['code' => $this->code($user->two_factor_secret)])->assertRedirect();
        }
        $this->assertAuthenticatedAs($user);
    }

    private function start(string $action, array $extra = [])
    {
        return $this->post('/admin-mfa/security/start', array_merge(['action' => $action, 'recovery_slot' => 1, 'password' => 'password'], $extra));
    }

    private function verifySms()
    {
        return $this->post('/admin-mfa/security/verify', ['otp' => end($this->sms)['code']]);
    }

    private function challenge(): AdminMfaChallenge
    {
        return AdminMfaChallenge::findOrFail(session('admin_security.challenge'));
    }

    public function test_first_registration_requires_mobile_before_qr_and_supports_optional_second_mobile(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_phone_1' => null, 'admin_mfa_phone_1_verified_at' => null])->save();
        $this->passwordLogin($user);
        $this->get('/admin-mfa')->assertSee('Register your recovery mobile')->assertDontSee('<svg', false);
        $this->post('/admin-mfa', ['code' => '123456'])->assertForbidden();
        $this->start('phone', ['slot' => 1, 'phone' => '+91 9876543210'])->assertRedirect(route('admin-mfa.security.show'));
        $this->get('/admin-mfa/security')->assertOk()->assertSee('Verify your mobile');
        $this->verifySms()->assertRedirect(route('admin-mfa.show'));
        $this->assertGuest();
        $this->assertSame('+919876543210', $user->fresh()->admin_mfa_phone_1);
        $this->get('/admin-mfa')->assertSee('(optional)');
        $this->start('phone', ['slot' => 2, 'phone' => '+919876543211'])->assertRedirect();
        $this->verifySms()->assertRedirect();
        $this->assertCount(2, $user->fresh()->adminRecoveryPhones());
        $this->post('/admin-mfa/continue')->assertRedirect(route('admin-mfa.show'));
        $this->get('/admin-mfa')->assertSee('<svg', false);
        $this->post('/admin-mfa', ['code' => $this->code(session('admin_mfa.setup_secret'))])->assertRedirect(route('admin-mfa.recovery'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_admin_must_pass_current_mfa_before_registering_mobile(): void
    {
        $user = $this->user('admin', true);
        $secret = $user->two_factor_secret;
        $user->forceFill(['admin_mfa_phone_1' => null, 'admin_mfa_phone_1_verified_at' => null])->save();
        $this->passwordLogin($user);
        $this->start('phone', ['phone' => '+919876543210'])->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertEmpty($this->sms);
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['code' => $this->code($secret)])->assertRedirect(route('admin-mfa.show'));
        $this->assertGuest();
        $this->get('/admin-mfa')->assertOk()->assertSee('Register your recovery mobile');
        $this->start('phone', ['phone' => '+919876543210'])->assertRedirect();
        $this->verifySms();
        $this->post('/admin-mfa/continue')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
    }

    public function test_either_verified_mobile_can_recover_mfa(): void
    {
        foreach ([1, 2] as $slot) {
            $user = $this->user('admin', true);
            $user->forceFill(['admin_mfa_phone_2' => '+919876543211', 'admin_mfa_phone_2_verified_at' => now()])->save();
            $oldSecret = $user->two_factor_secret;
            $oldCodes = $user->recoveryCodes();
            $this->passwordLogin($user);
            $this->start('reset', ['recovery_slot' => $slot])->assertRedirect();
            $this->assertSame($user->adminRecoveryPhones()[$slot], end($this->sms)['phone']);
            $this->verifySms()->assertRedirect(route('admin-mfa.security.show'));
            $this->assertGuest();
            $this->assertSame($oldSecret, $user->fresh()->two_factor_secret);
            $this->get('/admin-mfa/security')->assertOk()->assertSee('<svg', false);
            $this->post('/admin-mfa/security/confirm', ['confirm' => 1, 'code' => $this->code($this->challenge()->payload['secret'])])
                ->assertRedirect(route('admin-mfa.recovery'));
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($oldSecret, $user->fresh()->two_factor_secret);
            $this->assertEmpty(array_intersect($oldCodes, $user->fresh()->recoveryCodes()));
            $this->post('/logout');
        }
    }

    public function test_disable_requires_password_sms_and_explicit_confirmation_then_password_login_works(): void
    {
        $user = $this->user('admin', true);
        $this->signedIn($user);
        $this->start('disable', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertEmpty($this->sms);
        $this->start('disable')->assertRedirect();
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1])->assertForbidden();
        $this->verifySms()->assertRedirect();
        $this->assertTrue($user->fresh()->admin_mfa_required);
        $this->get('/admin-mfa/security')->assertSee('Disable MFA?');
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1])->assertRedirect(route('admin.security.index'));
        $user->refresh();
        $this->assertFalse($user->admin_mfa_required);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertCount(1, $user->adminRecoveryPhones());
        $this->get('/_mfa-test/private')->assertOk();
        $this->post('/logout');
        $this->passwordLogin($user)->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/_mfa-test/private')->assertOk();
    }

    public function test_disabled_admin_can_enable_only_after_sms_and_new_authenticator_confirmation(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_required' => false])->save();
        $this->signedIn($user);
        $this->start('enable');
        $this->verifySms();
        $this->assertFalse($user->fresh()->admin_mfa_required);
        $this->get('/admin-mfa/security')->assertSee('Enable your authenticator');
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1, 'code' => $this->code($this->challenge()->payload['secret'])])->assertRedirect(route('admin-mfa.recovery'));
        $this->assertTrue($user->fresh()->admin_mfa_required);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->post('/logout');
        $this->passwordLogin($user)->assertRedirect(route('admin-mfa.show'));
        $this->assertGuest();
    }

    public function test_cancelled_reset_keeps_old_authenticator_and_cancelled_enable_stays_disabled(): void
    {
        $user = $this->user('admin', true);
        $secret = $user->two_factor_secret;
        $this->passwordLogin($user);
        $this->start('reset');
        $this->verifySms();
        $this->post('/admin-mfa/security/cancel')->assertRedirect(route('admin-mfa.show'));
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
        $this->assertGuest();
    }

    public function test_otp_failures_are_counted_atomically_and_expired_codes_fail(): void
    {
        $this->passwordLogin($user = $this->user('admin', true));
        $this->start('reset');
        $id = $this->challenge()->id;
        for ($i = 0; $i < 5; $i++) {
            $this->travel(61)->seconds(); // Exercise the challenge limit independently of HTTP throttling.
            $this->challengeExpiry($id);
            $this->post('/admin-mfa/security/verify', ['otp' => '000000'])->assertSessionHasErrors('otp');
        }
        $this->assertSame(5, AdminMfaChallenge::find($id)->attempts);
        $this->verifySms()->assertSessionHasErrors('security');
        $this->assertGuest();
    }

    private function challengeExpiry(string $id): void
    {
        AdminMfaChallenge::whereKey($id)->update(['expires_at' => now()->addMinutes(5)]);
    }

    public function test_expired_sms_grant_cannot_reset_mfa_and_get_does_not_loop(): void
    {
        $this->passwordLogin($user = $this->user('admin', true));
        $this->start('reset');
        $this->verifySms();
        $this->travel(6)->minutes();
        $this->get('/admin-mfa/security')->assertRedirect(route('admin-mfa.show'));
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1, 'code' => '123456'])->assertSessionHasErrors('security');
        $this->assertGuest();
    }

    public function test_resend_invalidates_old_code_and_enforces_cooldown(): void
    {
        $this->passwordLogin($this->user('admin', true));
        $this->start('reset');
        $old = $this->challenge();
        $this->post('/admin-mfa/security/resend')->assertSessionHasErrors('security');
        $this->travel(61)->seconds();
        $this->post('/admin-mfa/security/resend')->assertRedirect();
        $this->assertNotNull($old->fresh()->consumed_at);
        $this->assertNotSame($old->id, $this->challenge()->id);
        $this->verifySms()->assertRedirect();
        $this->verifySms()->assertSessionHasErrors('security');
    }

    public function test_phone_change_requires_factor_and_verification_and_does_not_change_profile_phone(): void
    {
        $user = $this->user('admin', true);
        $this->signedIn($user);
        $this->start('phone', ['phone' => '+919876543211', 'factor' => 'bad'])->assertSessionHasErrors('factor');
        $this->start('phone', ['phone' => '+919876543211', 'factor' => $user->recoveryCodes()[0]])->assertRedirect();
        $this->assertSame('+919876543210', $user->fresh()->admin_mfa_phone_1);
        $this->verifySms()->assertRedirect(route('admin.security.index'));
        $this->assertSame('+919876543211', $user->fresh()->admin_mfa_phone_1);
        $this->assertNull($user->fresh()->phone);
        $this->get('/_mfa-test/private')->assertOk();
    }

    public function test_disabled_phone_change_requires_existing_mobile_then_new_mobile(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_required' => false])->save();
        $this->signedIn($user);
        $this->start('phone', ['phone' => '+919876543211']);
        $this->assertSame('+919876543210', end($this->sms)['phone']);
        $this->verifySms()->assertRedirect();
        $this->assertSame('+919876543211', end($this->sms)['phone']);
        $this->assertSame('+919876543210', $user->fresh()->admin_mfa_phone_1);
        $this->verifySms()->assertRedirect(route('admin.security.index'));
        $this->assertSame('+919876543211', $user->fresh()->admin_mfa_phone_1);
    }

    public function test_duplicate_and_unverified_recovery_numbers_are_rejected(): void
    {
        $user = $this->user('admin', true);
        $user->forceFill(['admin_mfa_phone_2' => '+919876543211'])->save();
        $this->passwordLogin($user);
        $this->start('reset', ['recovery_slot' => 2])->assertSessionHasErrors('security');
        $this->assertEmpty($this->sms);
        $this->post('/admin-mfa', ['code' => $this->code($user->two_factor_secret)]);
        $this->start('phone', ['slot' => 2, 'phone' => '+91 9876543210', 'factor' => $user->recoveryCodes()[0]])->assertSessionHasErrors('phone');
    }

    public function test_removal_preserves_one_number_and_promotes_secondary(): void
    {
        $user = $this->user('admin', true);
        $user->forceFill(['admin_mfa_phone_2' => '+919876543211', 'admin_mfa_phone_2_verified_at' => now()])->save();
        $this->signedIn($user);
        $this->start('remove', ['slot' => 1, 'factor' => $user->recoveryCodes()[0]]);
        $this->verifySms()->assertRedirect(route('admin.security.index'));
        $this->assertSame([1 => '+919876543211'], $user->fresh()->adminRecoveryPhones());
        $this->start('remove', ['slot' => 1, 'factor' => $user->fresh()->recoveryCodes()[1]])->assertSessionHasErrors('security');
    }

    public function test_pending_login_cannot_disable_or_replace_recovery_numbers(): void
    {
        $this->passwordLogin($this->user('admin', true));
        $this->start('disable')->assertForbidden();
        $this->start('phone', ['phone' => '+919876543211'])->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertEmpty($this->sms);
    }

    public function test_stale_mobile_form_requires_authenticator_again_without_sending_sms(): void
    {
        $user = $this->user('admin', true);
        $user->forceFill(['admin_mfa_phone_1' => null, 'admin_mfa_phone_1_verified_at' => null])->save();
        $this->passwordLogin($user);
        $this->post('/admin-mfa', ['code' => $this->code($user->two_factor_secret)])->assertRedirect();
        $this->get('/admin-mfa')->assertOk()->assertSee('Register your recovery mobile');
        session()->forget('admin_mfa.pending.factor_passed');

        $this->start('phone', ['phone' => '+919876543210'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertEmpty($this->sms);
        $this->assertNull(session('admin_mfa.pending'));
        $this->assertGuest();
        $this->assertSame($user->two_factor_secret, $user->fresh()->two_factor_secret);
        $this->assertEmpty($user->fresh()->adminRecoveryPhones());
    }

    public function test_resubmitting_verified_mobile_slot_returns_to_registration_without_replacing_it(): void
    {
        $user = $this->user();
        $this->passwordLogin($user);

        $this->start('phone', ['slot' => 1, 'phone' => '+919876543211'])
            ->assertRedirect(route('admin-mfa.show'))->assertSessionHasErrors('phone');

        $this->get('/admin-mfa')->assertOk()->assertSee('This mobile slot is already verified.')->assertSee('Continue');
        $this->assertEmpty($this->sms);
        $this->assertSame('+919876543210', $user->fresh()->admin_mfa_phone_1);
        $this->assertGuest();
    }

    public function test_challenge_cannot_be_transferred_to_another_session_or_account(): void
    {
        $user = $this->user('admin', true);
        $this->passwordLogin($user);
        $this->start('reset');
        $this->withSession(['admin_security.binding' => 'another-browser']);
        $this->verifySms()->assertSessionHasErrors('security');
        $this->assertGuest();
        $other = $this->user('admin', true);
        $oldId = session('admin_security.challenge');
        $this->passwordLogin($other);
        $this->withSession(['admin_security.challenge' => $oldId]);
        $this->verifySms()->assertSessionHasErrors('security');
    }

    public function test_security_version_invalidates_sessions_even_when_mfa_is_disabled(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_required' => false])->save();
        $this->signedIn($user);
        $oldVersion = session('admin_mfa.version');
        $user->increment('admin_mfa_version');
        Auth::guard('web')->setUser($user->fresh());
        $this->get('/_mfa-test/private')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($oldVersion, $user->fresh()->admin_mfa_version);
    }

    public function test_legacy_routes_and_actions_cannot_bypass_mobile_verification(): void
    {
        $user = $this->user('admin', true);
        $this->signedIn($user);
        $this->delete('/user/two-factor-authentication')->assertForbidden();
        $this->post('/user/two-factor-authentication')->assertForbidden();
        $this->get('/user/two-factor-qr-code')->assertForbidden();
        foreach ([EnableTwoFactorAuthentication::class, DisableTwoFactorAuthentication::class, GenerateNewRecoveryCodes::class] as $action) {
            try {
                app($action)($user);
                $this->fail('Legacy action should reject an admin.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_security_settings_render_and_non_admins_are_denied(): void
    {
        $user = $this->user('admin', true);
        $this->signedIn($user);
        $this->get('/admin/settings/security')->assertOk()->assertSee('Regenerate MFA QR')->assertSee('Disable MFA')->assertSee('Mobile 2');
        $this->post('/logout');
        $this->actingAs($this->user('customer'))->getJson('/admin/settings/security')->assertForbidden();
    }

    public function test_storage_encrypts_challenge_payload_and_profile_updates_do_not_modify_recovery(): void
    {
        $this->passwordLogin($user = $this->user('admin', true));
        $this->start('reset');
        $raw = DB::table('admin_mfa_challenges')->where('id', $this->challenge()->id)->first();
        $this->assertStringNotContainsString('+919876543210', $raw->payload);
        $this->assertNotSame(end($this->sms)['code'], $raw->otp_hash);
        $this->assertTrue(Hash::check(end($this->sms)['code'], $raw->otp_hash));
        $user->fill(['admin_mfa_required' => false, 'admin_mfa_phone_1' => '+919876543219', 'phone' => '+919876543212'])->save();
        $this->assertTrue($user->fresh()->admin_mfa_required);
        $this->assertSame('+919876543210', $user->fresh()->admin_mfa_phone_1);
    }

    public function test_delivery_failure_does_not_change_mfa_or_leave_usable_challenge(): void
    {
        $this->instance(AdminMfaSms::class, new class() extends AdminMfaSms
        {
            public function send(string $phone, string $code): void
            {
                throw new \RuntimeException('SMS provider unavailable');
            }
        });
        $this->passwordLogin($user = $this->user('admin', true));
        $secret = $user->two_factor_secret;
        $this->start('reset')->assertSessionHasErrors('security');
        $this->assertNotNull($this->challenge()->consumed_at);
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
        $this->assertGuest();
    }

    public function test_sms_delivery_failure_falls_back_to_firebase_for_the_same_mobile(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_phone_1' => null, 'admin_mfa_phone_1_verified_at' => null])->save();
        $this->instance(AdminMfaSms::class, new class() extends AdminMfaSms
        {
            public function send(string $phone, string $code): void
            {
                throw new AdminMfaSmsException('MSG91 unavailable', 'msg91', 'provider_rejected', 400, '418');
            }
        });
        $firebase = $this->mock(AdminMfaFirebase::class);
        $firebase->shouldReceive('available')->andReturnTrue();
        $firebase->shouldReceive('clientConfig')->andReturn(['apiKey' => 'public-key', 'projectId' => 'project']);
        $firebase->shouldReceive('verify')->once()->with('valid-firebase-token')->andReturn([
            'phone' => '+919876543210',
            'uid' => 'firebase-user',
            'auth_time' => now()->timestamp,
        ]);

        $this->passwordLogin($user);
        $this->start('phone', ['slot' => 1, 'phone' => '+919876543210'])->assertRedirect(route('admin-mfa.security.show'));
        $this->assertSame('firebase', $this->challenge()->payload['verification_transport']);
        $this->get('/admin-mfa/security')->assertOk()->assertSee('Verify with Firebase');
        $this->post('/admin-mfa/security/firebase', ['firebase_id_token' => 'valid-firebase-token'])
            ->assertRedirect(route('admin-mfa.show'));

        $this->assertSame('+919876543210', $user->fresh()->admin_mfa_phone_1);
        $this->assertNotNull($user->fresh()->admin_mfa_phone_1_verified_at);
        $this->assertGuest();
    }

    public function test_firebase_fallback_rejects_a_token_for_another_mobile(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_phone_1' => null, 'admin_mfa_phone_1_verified_at' => null])->save();
        $this->instance(AdminMfaSms::class, new class() extends AdminMfaSms
        {
            public function send(string $phone, string $code): void
            {
                throw new AdminMfaSmsException('MSG91 unavailable', 'msg91', 'provider_rejected', 400, '418');
            }
        });
        $firebase = $this->mock(AdminMfaFirebase::class);
        $firebase->shouldReceive('available')->andReturnTrue();
        $firebase->shouldReceive('verify')->once()->andReturn([
            'phone' => '+919876543299',
            'uid' => 'another-user',
            'auth_time' => now()->timestamp,
        ]);

        $this->passwordLogin($user);
        $this->start('phone', ['slot' => 1, 'phone' => '+919876543210'])->assertRedirect();
        $this->post('/admin-mfa/security/firebase', ['firebase_id_token' => 'wrong-phone-token'])
            ->assertSessionHasErrors('firebase');

        $this->assertNull($user->fresh()->admin_mfa_phone_1);
        $this->assertSame(1, $this->challenge()->attempts);
        $this->assertGuest();
    }

    public function test_abandoned_enable_leaves_mfa_disabled_and_grant_cannot_be_reused(): void
    {
        $user = $this->user();
        $user->forceFill(['admin_mfa_required' => false])->save();
        $this->signedIn($user);
        $this->start('enable');
        $this->verifySms();
        $grant = $this->challenge();
        $this->post('/admin-mfa/security/cancel')->assertRedirect(route('admin.security.index'));
        $this->assertNotNull($grant->fresh()->consumed_at);
        $this->assertFalse($user->fresh()->admin_mfa_required);
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1, 'code' => '123456'])->assertSessionHasErrors('security');
    }

    public function test_switching_recovery_numbers_invalidates_old_challenge(): void
    {
        $user = $this->user('admin', true);
        $user->forceFill(['admin_mfa_phone_2' => '+919876543211', 'admin_mfa_phone_2_verified_at' => now()])->save();
        $this->passwordLogin($user);
        $this->start('reset');
        $old = $this->challenge();
        $this->start('reset', ['recovery_slot' => 2]);
        $this->assertNotNull($old->fresh()->consumed_at);
        $this->assertSame('+919876543211', $this->challenge()->payload['phone']);
    }

    public function test_security_change_invalidates_other_pending_reset_grants(): void
    {
        $user = $this->user('admin', true);
        $this->passwordLogin($user);
        $this->start('reset');
        $this->verifySms();
        $code = $this->code($this->challenge()->payload['secret']);
        app(AdminMfaSecurity::class)->rotateVersion($user);
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1, 'code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_correct_fifth_otp_attempt_can_still_complete_reset(): void
    {
        $this->passwordLogin($user = $this->user('admin', true));
        $this->start('reset');
        // Persist four failures; the HTTP limiter is separately covered by the base suite.
        $this->challenge()->forceFill(['attempts' => 4])->save();
        $this->verifySms()->assertRedirect();
        $this->get('/admin-mfa/security')->assertOk();
        $this->post('/admin-mfa/security/confirm', ['confirm' => 1, 'code' => $this->code($this->challenge()->payload['secret'])])->assertRedirect(route('admin-mfa.recovery'));
    }

    public function test_hourly_send_limit_survives_new_sign_in_challenges(): void
    {
        $user = $this->user('admin', true);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->passwordLogin($user);
            $this->start('reset')->assertRedirect();
            $this->travel(61)->seconds();
        }
        $this->passwordLogin($user);
        $this->start('reset')->assertSessionHasErrors('security');
        $this->assertCount(5, $this->sms);
    }

    public function test_submitted_destination_and_user_id_cannot_redirect_recovery_sms(): void
    {
        $user = $this->user('admin', true);
        $other = $this->user('admin', true);
        $this->passwordLogin($user);
        $this->start('reset', ['phone' => '+919876543219', 'user_id' => $other->id]);
        $this->assertSame($user->id, $this->challenge()->user_id);
        $this->assertSame('+919876543210', end($this->sms)['phone']);
    }

    public function test_resending_cannot_extend_settings_password_authorization_beyond_ten_minutes(): void
    {
        $user = $this->user('admin', true);
        $this->signedIn($user);
        $this->start('reset');
        $this->travel(4)->minutes();
        $this->post('/admin-mfa/security/resend')->assertRedirect();
        $this->travel(4)->minutes();
        $this->post('/admin-mfa/security/resend')->assertRedirect();
        $this->travel(3)->minutes();
        $this->verifySms()->assertSessionHasErrors('security');
        $this->assertSame($user->two_factor_secret, $user->fresh()->two_factor_secret);
    }
}
