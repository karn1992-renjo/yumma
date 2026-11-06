<?php

namespace App\Services\WhatsApp\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Interakt (Jio Haptik) public message API. */
class InteraktProvider extends WhatsAppProvider
{
    public function key(): string
    {
        return 'interakt';
    }

    public function label(): string
    {
        return 'Interakt';
    }

    public function summary(): string
    {
        return 'Shared inbox + automations, popular with Indian D2C brands.';
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true, 'help' => 'Interakt → Settings → Developer Settings → API key (Base64 string).'],
            ['key' => 'default_country_code', 'label' => 'Default country code', 'type' => 'text', 'required' => false, 'placeholder' => '+91', 'help' => 'Used when a number has no country code.'],
        ];
    }

    public function setupSteps(): array
    {
        return [
            'Create an account at interakt.ai and connect your WhatsApp Business number (Meta embedded signup).',
            'Settings → Developer Settings: copy the API key into the form.',
            'Templates: create and submit each message template, wait for approval, then copy the exact template NAME into the Templates section on this page.',
            'Settings → Developer Settings → Webhook: add the Callback URL shown on this page and enable message received + delivery status events.',
            'Press "Save", then use "Send test message".',
        ];
    }

    private function send(array $cfg, string $to, array $body): array
    {
        $digits = $this->digits($to);
        $cc = $this->digits($cfg['default_country_code'] ?? '+91') ?: '91';
        // $to is E.164 (+CCNNNN…): split the country code off using the configured default when it matches.
        $country = str_starts_with($digits, $cc) ? $cc : substr($digits, 0, max(1, strlen($digits) - 10));
        $local = substr($digits, strlen($country));

        $response = Http::withHeaders(['Authorization' => 'Basic ' . ($cfg['api_key'] ?? '')])->acceptJson()->timeout(20)
            ->post('https://api.interakt.ai/v1/public/message/', [
                'countryCode' => '+' . $country, 'phoneNumber' => $local,
            ] + $body);
        if (! $response->successful() || $response->json('result') === false) {
            $this->fail('Interakt', $response);
        }

        return ['id' => $response->json('id')];
    }

    public function sendText(string $to, string $body, array $cfg): array
    {
        return $this->send($cfg, $to, ['type' => 'Text', 'data' => ['message' => $body]]);
    }

    public function sendTemplate(string $to, string $template, string $language, array $params, array $cfg): array
    {
        return $this->send($cfg, $to, [
            'type' => 'Template',
            'template' => ['name' => $template, 'languageCode' => $language, 'bodyValues' => array_map('strval', array_values($params))],
        ]);
    }

    public function parseWebhook(Request $request, array $cfg): array
    {
        $out = ['messages' => [], 'statuses' => []];
        $type = (string) $request->input('type', '');
        $data = $request->input('data', []);
        $message = $data['message'] ?? [];

        if ($type === 'message_received') {
            $phone = ($data['customer']['country_code'] ?? '+91') . ($data['customer']['phone_number'] ?? '');
            $kind = strtolower((string) ($message['message_content_type'] ?? 'text'));
            $out['messages'][] = [
                'id' => $message['id'] ?? null,
                'from' => '+' . preg_replace('/\D+/', '', $phone),
                'name' => $data['customer']['traits']['name'] ?? null,
                'type' => $kind,
                'text' => (string) ($message['message'] ?? ''),
                'payload' => $message['button_payload'] ?? null,
                'timestamp' => time(),
            ];
        } elseif (str_starts_with($type, 'message_api_')) {
            $status = substr($type, strlen('message_api_'));
            $out['statuses'][] = [
                'id' => $message['id'] ?? $data['id'] ?? null,
                'status' => match ($status) {
                    'sent' => 'sent',
                    'delivered' => 'delivered',
                    'read' => 'read',
                    default => 'failed',
                },
                'error' => $message['channel_failure_reason'] ?? null,
            ];
        }

        return $out;
    }
}
