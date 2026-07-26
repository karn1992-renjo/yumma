<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendOtp(string $phone, string $otp, ?string $appSignature = null): string
    {
        $provider = $this->otpProvider();
        $template = AppSetting::getValue('message_template_otp', 'Your OTP code is {{otp}}. It is valid for 10 minutes.');
        $message = $this->withSmsRetrieverSignature(
            $this->renderTemplate($template, ['otp' => $otp]),
            $appSignature
        );

        return match ($provider) {
            'twilio' => $this->sendTwilio($phone, $message, 'OTP'),
            'msg91' => $this->sendMsg91Otp($phone, $otp),
            'firebase' => throw new \RuntimeException('Firebase OTP is not supported for this API login flow. Configure Twilio or MSG91 in admin settings.'),
            default => throw new \RuntimeException('OTP service provider is not configured in admin settings.'),
        };
    }

    public function verifyOtp(string $phone, string $otp): bool
    {
        if ($this->otpProvider() !== 'msg91') {
            return false;
        }

        $authKey = trim((string) AppSetting::getValue('msg91_authkey', ''));
        if ($authKey === '') {
            throw new \RuntimeException('MSG91 auth key is missing.');
        }

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'authkey' => $authKey,
        ])->get('https://control.msg91.com/api/v5/otp/verify', [
            'otp' => $otp,
            'mobile' => $this->msg91Mobile($phone),
        ]);

        if (! $response->successful()) {
            Log::warning('MSG91 OTP verify failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $json = $response->json();
        $type = strtolower((string) data_get($json, 'type', ''));
        $message = strtolower((string) data_get($json, 'message', ''));

        return $type === 'success'
            || str_contains($message, 'verified success')
            || str_contains($message, 'number_verified_successfully');
    }


    public function msg91OtpMode(): string
    {
        return strtolower(trim((string) AppSetting::getValue('msg91_otp_mode', 'widget'))) === 'api'
            ? 'api'
            : 'widget';
    }

    public function usesMsg91WidgetOtp(): bool
    {
        return $this->otpProvider() === 'msg91' && $this->msg91OtpMode() === 'widget';
    }

    public function publicMsg91WidgetConfig(): array
    {
        return [
            'widget_id' => trim((string) AppSetting::getValue('msg91_widget_id', '')),
            'token_auth' => trim((string) AppSetting::getValue('msg91_widget_token', '')),
            'otp_length' => 4,
        ];
    }

    public function assertMsg91WidgetConfigured(): void
    {
        $config = $this->publicMsg91WidgetConfig();
        $authKey = trim((string) AppSetting::getValue('msg91_authkey', ''));

        if ($authKey === '' || $config['widget_id'] === '' || $config['token_auth'] === '') {
            throw new \RuntimeException('MSG91 widget settings are incomplete. Add Auth Key, Widget ID and Widget Token.');
        }
    }

    public function verifyMsg91WidgetAccessToken(string $accessToken, string $phone): array
    {
        $authKey = trim((string) AppSetting::getValue('msg91_authkey', ''));
        $accessToken = trim($accessToken);

        if ($authKey === '') {
            throw new \RuntimeException('MSG91 auth key is missing for widget verification.');
        }

        if ($accessToken === '') {
            throw new \RuntimeException('MSG91 widget access token is missing from the app verification response.');
        }

        $payload = [
            'authkey' => $authKey,
            'access-token' => $accessToken,
        ];
        $verifyUrl = 'https://control.msg91.com/api/v5/widget/verifyAccessToken?' . http_build_query($payload);

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'authkey' => $authKey,
            'access-token' => $accessToken,
        ])->asForm()->post($verifyUrl, $payload);

        $json = $response->json();
        if (! $response->successful() || $this->msg91ResponseFailed($json) || ! $this->msg91WidgetTokenVerified($json)) {
            Log::warning('MSG91 widget access token verify failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'phone' => $this->msg91Mobile($phone),
            ]);

            throw new \RuntimeException($this->msg91ErrorMessage($json) ?: 'MSG91 widget token verification failed.');
        }

        $identifier = $this->msg91WidgetIdentifier($json);
        if ($identifier !== null && $this->msg91Mobile($identifier) !== $this->msg91Mobile($phone)) {
            Log::warning('MSG91 widget verified identifier mismatch.', [
                'expected' => $this->msg91Mobile($phone),
                'actual' => $this->msg91Mobile($identifier),
            ]);

            throw new \RuntimeException('The verified MSG91 phone number does not match this login request.');
        }

        return is_array($json) ? $json : [];
    }
    public function sendOrderConfirmation(Order $order): bool
    {
        $template = AppSetting::getValue(
            'message_template_order_confirmation',
            'Your order has been confirmed. Order number: {{order_number}}.'
        );

        return $this->sendOrderMessage($order, $template, 'msg91_order_confirmation_template_id', 'order confirmation');
    }

    public function sendDeliveryUpdate(Order $order, ?string $message = null): bool
    {
        $template = AppSetting::getValue(
            'message_template_delivery_update',
            'Your order is on the way. Order number: {{order_number}}.'
        );

        return $this->sendOrderMessage(
            $order,
            $template,
            'msg91_delivery_update_template_id',
            'delivery update',
            ['status_message' => $message ?: '']
        );
    }

    public function sendDeliveryOtp(Order $order): bool
    {
        $template = AppSetting::getValue(
            'message_template_delivery_update',
            'Your delivery OTP for order {{order_number}} is {{delivery_otp}}.'
        );

        return $this->sendOrderMessage($order, $template, 'msg91_delivery_update_template_id', 'delivery OTP');
    }

    public function otpProvider(): string
    {
        $provider = strtolower(trim((string) AppSetting::getValue('otp_service_provider', '')));

        return in_array($provider, ['firebase', 'twilio', 'msg91'], true) ? $provider : '';
    }

    public function messageProvider(): string
    {
        $provider = strtolower(trim((string) AppSetting::getValue('message_service', '')));

        return in_array($provider, ['twilio', 'firebase', 'msg91'], true) ? $provider : '';
    }

    private function sendOrderMessage(
        Order $order,
        string $template,
        string $msg91TemplateSetting,
        string $purpose,
        array $extraVariables = []
    ): bool
    {
        $provider = $this->messageProvider();

        if ($provider === '' || $provider === 'firebase') {
            return false;
        }

        $phone = trim((string) ($order->customer_phone ?: $order->customer?->phone));
        if ($phone === '') {
            return false;
        }

        $variables = array_merge($this->orderVariables($order), $extraVariables);
        $message = $this->renderTemplate($template, $variables);

        try {
            if ($provider === 'twilio') {
                $this->sendTwilio($phone, $message, $purpose);

                return true;
            }

            if ($provider === 'msg91') {
                $templateId = trim((string) AppSetting::getValue($msg91TemplateSetting, ''));
                if ($templateId === '') {
                    throw new \RuntimeException("MSG91 {$purpose} template ID is missing.");
                }

                $this->sendMsg91Flow($phone, $templateId, $variables);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning("Customer {$purpose} SMS failed.", [
                'provider' => $provider,
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function sendTwilio(string $phone, string $message, string $purpose): string
    {
        $sid = AppSetting::getValue('twilio_account_sid');
        $token = AppSetting::getValue('twilio_auth_token');
        $from = AppSetting::getValue('twilio_phone_number');

        if (! $sid || ! $token || ! $from) {
            throw new \RuntimeException("Twilio {$purpose} settings are incomplete.");
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $phone,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            Log::warning("Twilio {$purpose} send failed.", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("Twilio {$purpose} send failed.");
        }

        return 'twilio';
    }

    private function sendMsg91Otp(string $phone, string $otp): string
    {
        $authKey = trim((string) AppSetting::getValue('msg91_authkey', ''));
        $templateId = trim((string) AppSetting::getValue('msg91_otp_template_id', ''));

        if ($authKey === '' || $templateId === '') {
            throw new \RuntimeException('MSG91 OTP settings are incomplete.');
        }

        $url = 'https://control.msg91.com/api/v5/otp?' . http_build_query([
            'template_id' => $templateId,
            'mobile' => $this->msg91Mobile($phone),
            'authkey' => $authKey,
            'otp_expiry' => 10,
            'otp_length' => 4,
        ]);

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post($url, [
            'otp_expiry' => 10,
            'otp_length' => 4,
        ]);

        if (! $response->successful() || $this->msg91ResponseFailed($response->json())) {
            Log::warning('MSG91 OTP send failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($this->msg91ErrorMessage($response->json()) ?: 'MSG91 OTP send failed.');
        }

        return 'msg91';
    }

    private function sendMsg91Flow(string $phone, string $templateId, array $variables): void
    {
        $authKey = trim((string) AppSetting::getValue('msg91_authkey', ''));

        if ($authKey === '') {
            throw new \RuntimeException('MSG91 auth key is missing.');
        }

        $recipient = array_merge(
            ['mobiles' => $this->msg91Mobile($phone)],
            $this->msg91Variables($variables)
        );

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'authkey' => $authKey,
            'content-type' => 'application/json',
        ])->post('https://control.msg91.com/api/v5/flow', [
            'template_id' => $templateId,
            'short_url' => '0',
            'realTimeResponse' => '1',
            'recipients' => [$recipient],
        ]);

        if (! $response->successful() || $this->msg91ResponseFailed($response->json())) {
            Log::warning('MSG91 SMS send failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException($this->msg91ErrorMessage($response->json()) ?: 'MSG91 SMS send failed.');
        }
    }

    private function orderVariables(Order $order): array
    {
        $order->loadMissing(['restaurant', 'customer']);
        $status = ucwords(str_replace('_', ' ', (string) $order->status));

        return [
            'order_number' => (string) ($order->order_number ?: $order->id),
            'order_id' => (string) $order->id,
            'status' => $status,
            'restaurant_name' => (string) optional($order->restaurant)->name,
            'customer_name' => (string) ($order->customer_name ?: $order->customer?->name),
            'total' => number_format((float) $order->total, AppSetting::currencyDecimals(), '.', ''),
            'delivery_otp' => (string) ($order->delivery_otp ?? ''),
            'app_name' => AppSetting::getValue('app_name', config('app.name', 'Swaad')),
        ];
    }

    private function renderTemplate(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    private function msg91Variables(array $variables): array
    {
        $mapped = [
            'VAR1' => $variables['order_number'] ?? $variables['otp'] ?? '',
            'VAR2' => $variables['status'] ?? '',
            'VAR3' => $variables['restaurant_name'] ?? '',
            'VAR4' => $variables['total'] ?? '',
            'VAR5' => $variables['delivery_otp'] ?? '',
        ];

        foreach ($variables as $key => $value) {
            $mapped[$key] = (string) $value;
            $mapped[strtoupper($key)] = (string) $value;
        }

        return array_filter($mapped, fn ($value) => $value !== '');
    }

    private function msg91Mobile(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function msg91ResponseFailed(mixed $json): bool
    {
        if (! is_array($json)) {
            return false;
        }

        return strtolower((string) ($json['type'] ?? '')) === 'error';
    }

    private function msg91ErrorMessage(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $message = trim((string) ($json['message'] ?? $json['error'] ?? ''));

        return $message !== '' ? $message : null;
    }


    private function msg91WidgetTokenVerified(mixed $json): bool
    {
        if (! is_array($json)) {
            return false;
        }

        $type = strtolower((string) data_get($json, 'type', ''));
        $message = strtolower((string) data_get($json, 'message', ''));
        $status = strtolower((string) data_get($json, 'status', ''));
        $verified = data_get($json, 'data.verified', data_get($json, 'verified'));

        return $type === 'success'
            || $status === 'success'
            || $verified === true
            || $verified === 1
            || $verified === '1'
            || str_contains($message, 'success')
            || str_contains($message, 'verified');
    }

    private function msg91WidgetIdentifier(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        foreach (['identifier', 'mobile', 'phone', 'data.identifier', 'data.mobile', 'data.phone', 'data.user.identifier'] as $key) {
            $value = trim((string) data_get($json, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
    private function withSmsRetrieverSignature(string $message, ?string $appSignature): string
    {
        $signature = preg_replace('/[^A-Za-z0-9+\/]/', '', (string) $appSignature);
        if ($signature === '' || strlen($signature) < 8 || strlen($signature) > 16) {
            return $message;
        }

        $normalizedMessage = trim($message);
        if (! str_starts_with($normalizedMessage, '<#>')) {
            $normalizedMessage = '<#> ' . $normalizedMessage;
        }

        return rtrim($normalizedMessage) . "\n" . $signature;
    }
}





