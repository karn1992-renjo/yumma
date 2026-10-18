<?php

namespace App\Services;

use App\Exceptions\AdminMfaSmsException;
use App\Models\AppSetting;
use App\Models\NotificationTemplate;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class AdminMfaSms
{
    public function send(string $phone, string $code): void
    {
        $sms = app(SmsService::class);
        $provider = $sms->otpProvider();
        if (! in_array($provider, ['twilio', 'exotel', 'msg91'], true)) {
            throw new AdminMfaSmsException(
                'Admin recovery SMS requires Twilio, Exotel, or MSG91.',
                $provider ?: 'unconfigured',
                'unsupported_provider',
            );
        }

        if ($provider !== 'msg91') {
            $template = NotificationTemplate::where('key', 'sms.otp')->where('is_active', true)->value('body')
                ?: 'Your OTP code is {{otp}}. It is valid for 5 minutes.';
            $message = str_replace('{{otp}}', $code, $template);
            $this->sendMessage($provider, $phone, $message);

            return;
        }

        // The shared MSG91 login method generates its own OTP. Admin recovery
        // explicitly sends our challenge-specific code so another login OTP cannot authorize it.
        $key = trim((string) AppSetting::getValue('msg91_authkey', ''));
        $template = trim((string) AppSetting::getValue('msg91_otp_template_id', ''));
        if ($key === '' || $template === '') {
            throw new AdminMfaSmsException(
                'MSG91 Auth Key or OTP Template ID is missing.',
                'msg91',
                'configuration_incomplete',
            );
        }
        try {
            $response = Http::timeout(15)->withHeaders(['authkey' => $key])->acceptJson()
                ->post('https://control.msg91.com/api/v5/otp?' . http_build_query([
                    'template_id' => $template, 'mobile' => ltrim($phone, '+'),
                    'otp' => $code, 'otp_length' => 6, 'otp_expiry' => 5,
                ]), []);
        } catch (Throwable) {
            throw new AdminMfaSmsException(
                'MSG91 could not be reached. Check outbound HTTPS access and try again.',
                'msg91',
                'transport_error',
            );
        }
        if (! $response->successful() || $response->json('type') !== 'success') {
            throw $this->rejected('msg91', $response);
        }
    }

    private function sendMessage(string $provider, string $phone, string $message): void
    {
        // Do not use generic SMS error logging: provider bodies can echo OTPs and phone numbers.
        if ($provider === 'twilio') {
            $sid = $this->setting('twilio_account_sid');
            $key = $sid;
            $token = $this->setting('twilio_auth_token');
            $sender = $this->setting('twilio_phone_number');
            $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        } else {
            $sid = $this->setting('exotel_sid');
            $key = $this->setting('exotel_api_key');
            $token = $this->setting('exotel_api_token');
            $sender = $this->setting('exotel_sender_id');
            $host = $this->setting('exotel_subdomain');
            $url = 'https://' . $host . '/v1/Accounts/' . rawurlencode($sid) . '/Sms/send.json';
        }
        try {
            $response = Http::timeout(15)->withBasicAuth($key, $token)->asForm()
                ->post($url, ['From' => $sender, 'To' => $phone, 'Body' => $message]);
        } catch (Throwable) {
            throw new AdminMfaSmsException(
                ucfirst($provider) . ' could not be reached. Check outbound HTTPS access and try again.',
                $provider,
                'transport_error',
            );
        }
        if (! $response->successful()) {
            throw $this->rejected($provider, $response);
        }
    }

    private function setting(string $name): string
    {
        $value = trim((string) AppSetting::getValue($name, ''));
        if ($value === '') {
            throw new AdminMfaSmsException(
                'The selected SMS provider configuration is incomplete.',
                app(SmsService::class)->otpProvider() ?: 'unconfigured',
                'configuration_incomplete',
            );
        }

        return $value;
    }

    private function rejected(string $provider, Response $response): AdminMfaSmsException
    {
        $rawCode = $response->json('code')
            ?? $response->json('error.code')
            ?? $response->json('Error.Code')
            ?? $response->json('SMSMessage.ErrorCode');
        $providerCode = is_scalar($rawCode)
            ? preg_replace('/[^A-Za-z0-9_.-]/', '', substr((string) $rawCode, 0, 64))
            : null;
        $detail = 'HTTP ' . $response->status() . ($providerCode ? ', code ' . $providerCode : '');

        return new AdminMfaSmsException(
            ucfirst($provider) . ' rejected the SMS request (' . $detail . '). Check its credentials, sender, template approval, balance, and destination rules.',
            $provider,
            'provider_rejected',
            $response->status(),
            $providerCode ?: null,
        );
    }
}
