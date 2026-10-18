# Admin MFA and mobile recovery

Applies to the `admin` and `super_admin` web accounts only. Each administrator manages their own security at **Settings > Security** (`/admin/settings/security`). Other roles and mobile/API login endpoints keep their existing behavior.

## Registration and mobile numbers

New MFA registrations first verify Mobile 1 by SMS, optionally verify Mobile 2, and then scan and confirm an authenticator QR. Existing enrolled admins pass their current authenticator/recovery-code challenge first, then verify at least one mobile before dashboard access. Existing authenticators are preserved.

Recovery numbers are separate from `users.phone`. Profile edits and ordinary user administration cannot set them. Each number is normalized with a country code and verified independently; duplicates within an account are rejected. Either verified number can be selected for recovery. An unverified number is never eligible.

Changing a number requires the current password and an authenticator/recovery code when MFA is enabled. When MFA is disabled, it requires an SMS code to an existing verified number first. In both cases, the new number must also be verified before replacing the old one. Removing a number requires password, the enabled MFA factor (if applicable), and mobile verification; at least one verified number must remain. Removing Mobile 1 promotes Mobile 2.

## Forgotten authenticator, enable and disable

After entering the correct login password, select **Forgot authenticator?**, choose a saved mobile, and verify its SMS code. A new QR is then displayed. Scan it and enter its authenticator code to complete replacement. The same flow is available as **Regenerate MFA QR** in Settings, with fresh password confirmation.

**Disable MFA** requires a signed-in session, the current password, an SMS code to either verified mobile, and explicit final confirmation. It clears the authenticator and recovery codes, retains verified mobiles, and allows subsequent password-only web logins.

**Enable MFA** requires the current password, mobile verification, and confirmation of the new authenticator. It remains disabled if setup is abandoned. Successful enrollment/replacement shows eight new single-use recovery codes.

SMS verification never grants dashboard access on its own during recovery. The previous authenticator remains valid until the replacement is confirmed. No password-only reset or reset on behalf of another administrator is provided. A lost phone can be worked around with the other number or an existing recovery code; recovery codes do not replace the SMS check required to disable MFA.

## Security behavior

- OTPs and confirmation grants expire after five minutes; password authorization expires after ten minutes, including across resends. Pending logins also expire after ten minutes.
- OTP sends have a 60-second cooldown per account/destination, five sends per hour per account and destination, and 20 per hour per IP. Verification allows five attempts per challenge; security POST requests additionally allow five per minute per account.
- Challenges are bound to the user, session, operation, destination, password hash, and account security version. Codes are hashed; pending QR secrets/payloads and confirmed Fortify secrets are encrypted. QR responses are not cached.
- Resending or selecting another mobile invalidates the earlier challenge. Database transactions and row locks prevent consuming the same grant twice. Security changes invalidate outstanding account challenges, old remember tokens, and other admin web sessions; version checks also apply while MFA is disabled.
- Legacy Fortify routes and Livewire mutation actions direct admins to Security settings instead of allowing mobile-verification bypasses. OTPs/factors are excluded from flashed validation input and security logs contain no secrets or full phone numbers.

## Deployment

The additive migration `2026_10_06_120000_add_admin_mfa_recovery.php` adds account MFA preference/version fields, two recovery-number slots with verification timestamps, and encrypted challenge storage. Existing users default to required MFA. Existing MFA secrets are not modified by the migration.

**Configure SMS before deploying mandatory recovery enrollment**, while existing administrators can still access Communication settings. Supported providers are Twilio, Exotel, and MSG91 in API mode. Firebase and MSG91 widget mode are not supported for this flow. Keep the application encryption key stable.

The read-only preflight checks configuration presence, never sends a message, and never prints credentials:

```sh
php artisan admin:mfa-check
```

For MSG91, configure Auth Key, OTP Template ID, and API mode. Admin OTP delivery explicitly supplies the challenge-specific six-digit `otp` to the SendOTP endpoint; it does not reuse the generic login method that lets MSG91 generate a different code. Confirm provider/template acceptance and actual delivery in staging. Twilio and Exotel reuse the configured sender credentials and `sms.otp` notification template; ensure template text matches the five-minute validity period.

Back up the application database and deploy in maintenance mode through the normal process. Apply the migration and refresh caches in each installation:

```sh
php artisan migrate --path=database/migrations/2026_10_06_120000_add_admin_mfa_recovery.php --force
php artisan optimize:clear
php artisan admin:mfa-check
```

Only reopen traffic after a staging/controlled smoke test confirms delivery, login, and recovery. Never mark existing unverified profile phones as verified to bypass onboarding. Expired challenge rows may be removed by normal data-retention maintenance; they cannot authorize actions after expiry.

Rollback requires restoring the previous application code alongside rolling back this migration. The old mandatory-MFA implementation will require accounts that disabled MFA to enroll again. Do not roll back the schema while the new code is serving requests.

## Verification

### Troubleshooting recovery-mobile registration

If submitting `/admin-mfa/security/start` produces a bare 403, check both the application log and the hosting/WAF error log at the request time. The application logs safe `Admin MFA security event` entries without OTPs, secrets, or full phone numbers. `phone_enrollment_factor_required` means the pending login is missing proof of the existing authenticator: sign in again and complete that check. `phone_enrollment_slot_already_verified` means an old form was submitted after the slot was verified: continue registration. These two cases now redirect with an explanation instead of returning 403. `security_context_rejected` identifies an account/session authorization rejection, and `phone_enrollment_requested` confirms the request passed the enrollment authorization checks.

`SQLSTATE[42S02]` naming `admin_mfa_challenges` means the new code was deployed without its additive migration. Confirm the migration file exists on the server, run the migration command above, and then clear application caches. The flow cannot send or verify recovery-mobile OTPs until this table exists.

If the page says the SMS could not be sent even though settings were saved, run `php artisan admin:mfa-check`. MSG91 requires an Auth Key and OTP Template ID. Admin MFA always uses the server-side **SendOTP API** so it can bind its own six-digit code to the security challenge. The global `msg91_otp_mode` may remain **Widget/SDK** for customer and mobile login; it does not change the admin MFA transport. Provider failures show a safe, actionable reason and write `Admin MFA SMS delivery failed` with provider, reason, HTTP status, and provider error code. The log never includes the destination, OTP, credential, or provider response body. HTTP 401/403 usually indicates credentials or account access; HTTP 400 commonly indicates the sender, destination, or template; a transport error points to DNS, TLS, firewall, or outbound HTTPS access.

When MSG91, Twilio, or Exotel delivery fails and Firebase is enabled with a Web API Key and Project ID, the challenge automatically switches to Firebase Phone Auth for the same selected number. The browser uses visible reCAPTCHA and Firebase sends its own verification code. The server accepts the result only after Firebase Identity Toolkit validates the signed ID token, the sign-in provider is `phone`, the verified number exactly matches the challenge destination, and the Firebase authentication time is fresh for that challenge. Add the production hostname (for example `yumma.in`) to Firebase Authentication's authorized domains and enable the Phone provider. Firebase fallback remains bound to the account, session, operation, password authorization, five-minute expiry, and five-attempt limit.

Deploy matching controllers, services, middleware, routes, and views together, then run `php artisan optimize:clear` and refresh PHP workers/opcache through the hosting panel if code remains stale. Start a fresh login. Do not disable CSRF, MFA authorization, or hosting security globally to work around a 403. Without a matching application event, inspect the hosting request log to determine whether the request reached Laravel.

```sh
php vendor/bin/phpunit tests/Feature/AdminMfaTest.php tests/Feature/AdminMfaRecoveryTest.php tests/Feature/AdminMfaSmsTest.php
```

These suites use SQLite in memory, array cache/session, and mocked SMS/HTTP. They do not load deployment credentials or send actual messages. Coverage includes both mobile slots, migration-backed enrollment, lifecycle controls, denial of legacy bypasses, expired/replayed grants, throttling, provider errors, and stale sessions. Security settings markup is rendered with a minimal test layout to avoid unrelated dashboard database dependencies.
