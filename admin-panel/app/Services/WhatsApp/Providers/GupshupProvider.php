<?php

namespace App\Services\WhatsApp\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Gupshup WhatsApp API (partner/BSP). Templates are addressed by their template ID. */
class GupshupProvider extends WhatsAppProvider
{
    public function key(): string
    {
        return 'gupshup';
    }

    public function label(): string
    {
        return 'Gupshup';
    }

    public function summary(): string
    {
        return 'Indian BSP, good for high volume and template management. Pay Gupshup per message.';
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true, 'help' => 'Gupshup Console → your app → API key.'],
            ['key' => 'app_name', 'label' => 'App name', 'type' => 'text', 'required' => true, 'help' => 'The exact name of your Gupshup WhatsApp app.'],
            ['key' => 'source_number', 'label' => 'Business WhatsApp number', 'type' => 'text', 'required' => true, 'placeholder' => '919876543210', 'help' => 'The number attached to the app, with country code.'],
        ];
    }

    public function setupSteps(): array
    {
        return [
            'In the Gupshup console create a WhatsApp app (Access API / Self Serve) and connect your business number. Finish the WhatsApp Business verification shown there.',
            'Copy three things into the form on the left: the API key (Console → your account → API key), the exact App name, and your WhatsApp number in full international form without + or spaces (for example 919876543210).',
            'Turn WhatsApp on above, choose Gupshup, and press "Save" BEFORE the next step, so the callback URL below is ready.',
            'Webhooks: Gupshup console → your app → Overview → Webhooks → Add Webhook. Paste the Callback URL shown on this page (it contains your secret key, keep it private). Payload format: choose "Gupshup format (v2)". "Meta format (v3)" also works. Tick the message events (enqueued, sent, delivered, read, failed) and incoming messages, then save. If Gupshup says "Invalid URL Passed", the server does not have the webhook route yet: see Troubleshooting below.',
            'Templates: open Templates in the Gupshup console and create each message as a TEXT template (no header image or buttons) in category Utility, using the exact wording shown in the Templates table below. Submit it for approval.',
            'When a template is approved, open it and copy its Template ID (a long UUID). Paste it in the "Gupshup identifier" column of the matching message in the Templates table below, then Save again.',
            'Use "Send test message" with the ID of an approved template. Free-form text only works inside 24 hours of the customer\'s last message, so test with a template first.',
            'If a send fails with "User is not Opted in", the customer must first be opted in on your Gupshup app (send them a first message from the Gupshup console, or have them message your number).',
            'Troubleshooting: opening the callback URL in a browser must show {"success":true,...} (not a 404 page). A 404 means the server is missing the WhatsApp webhook route in routes/api.php.',
        ];
    }

    private function headers(array $cfg): array
    {
        return ['apikey' => $cfg['api_key'] ?? '', 'Cache-Control' => 'no-cache'];
    }

    private function submit(string $url, array $cfg, array $form): array
    {
        $response = Http::withHeaders($this->headers($cfg))->asForm()->timeout(20)->post($url, $form + [
            'channel' => 'whatsapp',
            'source' => $this->digits($cfg['source_number'] ?? ''),
            'src.name' => $cfg['app_name'] ?? '',
        ]);
        if (! $response->successful() || strtolower((string) $response->json('status')) === 'error') {
            $this->fail('Gupshup', $response);
        }

        return ['id' => $response->json('messageId')];
    }

    public function sendText(string $to, string $body, array $cfg): array
    {
        return $this->submit('https://api.gupshup.io/wa/api/v1/msg', $cfg, [
            'destination' => $this->digits($to),
            'message' => json_encode(['type' => 'text', 'text' => $body]),
        ]);
    }

    public function supportsImages(): bool
    {
        return true;
    }

    public function sendImage(string $to, string $url, string $caption, array $cfg): array
    {
        return $this->submit('https://api.gupshup.io/wa/api/v1/msg', $cfg, [
            'destination' => $this->digits($to),
            'message' => json_encode(['type' => 'image', 'originalUrl' => $url, 'previewUrl' => $url, 'caption' => mb_substr($caption, 0, 1000)], JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function sendTemplate(string $to, string $template, string $language, array $params, array $cfg): array
    {
        $form = [
            'destination' => $this->digits($to),
            'template' => json_encode(['id' => $template, 'params' => array_map('strval', array_values($params))]),
        ];
        if (! empty($cfg['header_image'])) {
            // Templates approved with an IMAGE header need the picture with every send.
            $form['message'] = json_encode(['type' => 'image', 'image' => ['link' => $cfg['header_image']]], JSON_UNESCAPED_SLASHES);
        }

        return $this->submit('https://api.gupshup.io/wa/api/v1/template/msg', $cfg, $form);
    }

    public function parseWebhook(Request $request, array $cfg): array
    {
        // "Meta format (v3)" webhooks arrive in WhatsApp Cloud API shape.
        if ($request->has('entry')) {
            return $this->parseMetaFormat($request, $cfg);
        }

        $out = ['messages' => [], 'statuses' => []];
        $type = $request->input('type');
        $p = $request->input('payload', []);

        if ($type === 'message') {
            $inner = $p['payload'] ?? [];
            $kind = $p['type'] ?? 'text';
            $out['messages'][] = [
                'id' => $p['id'] ?? null,
                'from' => '+' . ($p['source'] ?? ($p['sender']['phone'] ?? '')),
                'name' => $p['sender']['name'] ?? null,
                'type' => $kind,
                'text' => (string) ($inner['text'] ?? $inner['title'] ?? ''),
                'payload' => $inner['postbackText'] ?? ($kind === 'location' ? json_encode($inner) : ($inner['id'] ?? null)),
                'timestamp' => isset($request['timestamp']) ? (int) ($request['timestamp'] / 1000) : time(),
            ];
        } elseif ($type === 'message-event') {
            $event = strtolower((string) ($p['type'] ?? ''));
            $out['statuses'][] = [
                'id' => $p['gsId'] ?? $p['id'] ?? null,
                'status' => match ($event) {
                    'enqueued', 'sent' => 'sent',
                    'delivered' => 'delivered',
                    'read' => 'read',
                    default => 'failed',
                },
                'error' => $event === 'failed' ? ($p['payload']['reason'] ?? 'failed') : null,
                'alt_id' => $p['id'] ?? null,
            ];
        }

        return $out;
    }

    /** Gupshup's v3 payload: the same layout as Meta's Cloud API, plus Gupshup's own message id on statuses. */
    private function parseMetaFormat(Request $request, array $cfg): array
    {
        $parsed = (new MetaCloudProvider)->parseWebhook($request, $cfg);
        $parsed['statuses'] = [];
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $st) {
                    $status = strtolower((string) ($st['status'] ?? ''));
                    $parsed['statuses'][] = [
                        // We store the id Gupshup returned when sending; v3 repeats it as gs_id next to the WhatsApp id.
                        'id' => $st['gs_id'] ?? $st['id'] ?? null,
                        'alt_id' => $st['id'] ?? ($st['meta_msg_id'] ?? null),
                        'status' => match ($status) {
                            'sent', 'enqueued' => 'sent',
                            'delivered' => 'delivered',
                            'read' => 'read',
                            default => 'failed',
                        },
                        'error' => isset($st['errors'][0]) ? ($st['errors'][0]['title'] ?? $st['errors'][0]['message'] ?? 'failed') : null,
                    ];
                }
            }
        }

        return $parsed;
    }
}
