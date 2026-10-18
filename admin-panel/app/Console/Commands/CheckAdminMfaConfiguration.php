<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\AdminMfaFirebase;
use Illuminate\Console\Command;

class CheckAdminMfaConfiguration extends Command
{
    protected $signature = 'admin:mfa-check';

    protected $description = 'Check admin recovery SMS configuration without sending SMS or exposing credentials';

    public function handle(): int
    {
        $provider = strtolower(trim((string) AppSetting::getValue('otp_service_provider', '')));
        $firebaseAvailable = app(AdminMfaFirebase::class)->available();
        $required = match ($provider) {
            'twilio' => ['twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number'],
            'exotel' => ['exotel_sid', 'exotel_api_key', 'exotel_api_token', 'exotel_subdomain', 'exotel_sender_id'],
            'msg91' => ['msg91_authkey', 'msg91_otp_template_id'],
            default => [],
        };
        if (! $required) {
            if ($firebaseAvailable) {
                $this->warn('No server SMS provider is configured. Admin MFA will use Firebase Phone Auth.');
                $this->info('Firebase fallback configuration is present. No verification was sent.');

                return self::SUCCESS;
            }
            $this->error('Admin MFA requires Twilio, Exotel, MSG91, or configured Firebase Phone Auth.');

            return self::FAILURE;
        }
        $missing = array_filter($required, fn ($key) => trim((string) AppSetting::getValue($key, '')) === '');
        if ($missing) {
            if ($firebaseAvailable) {
                $this->warn('Missing primary SMS settings: ' . implode(', ', $missing));
                $this->info('Firebase fallback configuration is present. No verification was sent.');

                return self::SUCCESS;
            }
            $this->error('Missing SMS settings: ' . implode(', ', $missing));

            return self::FAILURE;
        }
        $this->info('Admin MFA SMS configuration is present (' . $provider . '). No SMS was sent.');
        if ($provider === 'msg91') {
            $this->line('Admin MFA uses the MSG91 SendOTP API independently of the customer/mobile Widget or API mode.');
        }
        $this->line($firebaseAvailable
            ? 'Firebase Phone Auth fallback is available.'
            : 'Firebase fallback is unavailable; enable Firebase and configure its Web API Key and Project ID if required.');
        $this->warn('This checks configuration only. Confirm real SMS delivery in staging before rollout.');

        return self::SUCCESS;
    }
}
