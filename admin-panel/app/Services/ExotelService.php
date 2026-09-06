<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Exotel API client -- no masking/business logic here (see
 * CallMaskingService for that). Every method throws RuntimeException on
 * failure rather than swallowing it, so callers decide fallback behavior.
 */
class ExotelService
{
    public function isConfigured(): bool
    {
        foreach (['exotel_sid', 'exotel_api_key', 'exotel_api_token', 'exotel_subdomain'] as $key) {
            if (trim((string) AppSetting::getValue($key, '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Bridges a call: Exotel rings $fromNumber first, and once answered,
     * dials & bridges to $toNumber. $callerId (an Exophone, or the real
     * number on an unmasked fallback) is shown on both legs' caller ID.
     *
     * @return array{sid: ?string, status: ?string}
     */
    public function connectCall(string $fromNumber, string $toNumber, string $callerId, array $meta = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Exotel is not configured in admin settings.');
        }

        $response = Http::withBasicAuth(
            AppSetting::getValue('exotel_api_key'),
            AppSetting::getValue('exotel_api_token')
        )->asForm()->post($this->accountUrl('Calls/connect.json'), array_filter([
            'From' => $fromNumber,
            'To' => $toNumber,
            'CallerId' => $callerId,
            'CallType' => 'trans',
            'StatusCallback' => $this->statusCallbackUrl(),
            'CustomField' => $meta ? json_encode($meta) : null,
        ]));

        if (! $response->successful()) {
            Log::warning('Exotel connect call failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Exotel connect call failed.');
        }

        $json = $response->json();

        return [
            'sid' => data_get($json, 'Call.Sid') ?? data_get($json, 'Call.Id'),
            'status' => data_get($json, 'Call.Status'),
        ];
    }

    /**
     * Places an outbound "alert" call to a single number. When the person
     * answers, Exotel runs the configured App flow ($flowUrl, from
     * exotel_order_alert_flow_url) which should speak the notification; if no
     * flow is set it bridges to exotel_order_alert_number instead. Used to nudge
     * a driver / restaurant that hasn't accepted an order in time.
     *
     * @return array{sid: ?string, status: ?string}
     */
    public function announceCall(string $toNumber, array $meta = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Exotel is not configured in admin settings.');
        }

        $flowUrl = trim((string) AppSetting::getValue('exotel_order_alert_flow_url', ''));
        $bridgeNumber = trim((string) AppSetting::getValue('exotel_order_alert_number', ''));
        $callerId = trim((string) AppSetting::getValue('exotel_order_alert_caller_id', ''))
            ?: trim((string) AppSetting::getValue('exotel_sender_id', ''));

        if ($flowUrl === '' && $bridgeNumber === '') {
            throw new \RuntimeException('No exotel_order_alert_flow_url or exotel_order_alert_number configured.');
        }

        $params = array_filter([
            'From' => $toNumber,
            'CallerId' => $callerId ?: null,
            'CallType' => 'trans',
            'TimeLimit' => 60,
            'StatusCallback' => $this->statusCallbackUrl(),
            'CustomField' => $meta ? json_encode($meta) : null,
        ]);

        if ($flowUrl !== '') {
            $params['Url'] = $flowUrl;
        } else {
            $params['To'] = $bridgeNumber;
        }

        $response = Http::withBasicAuth(
            AppSetting::getValue('exotel_api_key'),
            AppSetting::getValue('exotel_api_token')
        )->asForm()->timeout(15)->post($this->accountUrl('Calls/connect.json'), $params);

        if (! $response->successful()) {
            Log::warning('Exotel announce call failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Exotel announce call failed.');
        }

        $json = $response->json();

        return [
            'sid' => data_get($json, 'Call.Sid') ?? data_get($json, 'Call.Id'),
            'status' => data_get($json, 'Call.Status'),
        ];
    }

    public function sendSms(string $to, string $body): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Exotel is not configured in admin settings.');
        }

        $senderId = trim((string) AppSetting::getValue('exotel_sender_id', ''));
        if ($senderId === '') {
            throw new \RuntimeException('Exotel sender ID is missing.');
        }

        $response = Http::withBasicAuth(
            AppSetting::getValue('exotel_api_key'),
            AppSetting::getValue('exotel_api_token')
        )->asForm()->post($this->accountUrl('Sms/send.json'), [
            'From' => $senderId,
            'To' => $to,
            'Body' => $body,
        ]);

        if (! $response->successful()) {
            Log::warning('Exotel SMS send failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Exotel SMS send failed.');
        }
    }

    private function accountUrl(string $path): string
    {
        $subdomain = trim((string) AppSetting::getValue('exotel_subdomain', 'api.exotel.com'));
        $sid = AppSetting::getValue('exotel_sid');

        return "https://{$subdomain}/v1/Accounts/{$sid}/{$path}";
    }

    private function statusCallbackUrl(): string
    {
        $secret = trim((string) AppSetting::getValue('exotel_webhook_secret', ''));

        return route('webhooks.exotel.call-status') . ($secret !== '' ? "?token={$secret}" : '');
    }
}
