<?php

namespace App\Services\WhatsApp\Providers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

/** Official WhatsApp Business Cloud API from Meta (no middleman). */
class MetaCloudProvider extends WhatsAppProvider
{
    private const GRAPH = 'https://graph.facebook.com/v20.0';

    public function key(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta WhatsApp Cloud API';
    }

    public function summary(): string
    {
        return 'Official API straight from Meta. No platform fee, you pay Meta per conversation. Recommended.';
    }

    public function fields(): array
    {
        return [
            ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'type' => 'text', 'required' => true, 'help' => 'Meta App → WhatsApp → API Setup → "Phone number ID".'],
            ['key' => 'business_account_id', 'label' => 'WhatsApp Business Account ID', 'type' => 'text', 'required' => false, 'help' => 'Used for template management.'],
            ['key' => 'access_token', 'label' => 'Permanent access token', 'type' => 'secret', 'required' => true, 'help' => 'Create a System User in Business Settings and generate a never-expiring token with whatsapp_business_messaging permission.'],
            ['key' => 'app_secret', 'label' => 'App secret', 'type' => 'secret', 'required' => true, 'help' => 'Meta App → Settings → Basic. Used to verify that webhooks really come from Meta.'],
            ['key' => 'catalog_id', 'label' => 'Catalog ID (optional)', 'type' => 'text', 'required' => false, 'help' => 'Commerce Manager → your catalog → Settings. Needed only for the in-chat catalog with a native cart (see the Catalog card below). The token needs the catalog_management permission.'],
            ['key' => 'verify_token', 'label' => 'Webhook verify token', 'type' => 'text', 'required' => true, 'help' => 'Any word you choose. Paste the same word in Meta when saving the webhook.'],
        ];
    }

    public function setupSteps(): array
    {
        return [
            'Create a Meta Business account at business.facebook.com and verify your business (Business Settings → Security Center).',
            'Go to developers.facebook.com → Create App → type "Business" → add the "WhatsApp" product.',
            'WhatsApp → API Setup: add and verify your business phone number (it must not be in use on the normal WhatsApp app). Copy the Phone number ID.',
            'Business Settings → Users → System users → create a user, assign the app, generate a token with "whatsapp_business_messaging" and "whatsapp_business_management". Copy it here as the permanent token.',
            'Meta App → Settings → Basic: copy the App secret into the form above.',
            'WhatsApp → Configuration → Webhook: paste the Callback URL and Verify token shown on this page, then subscribe to the "messages" field.',
            'Create and submit message templates in WhatsApp Manager (the Templates section on this page lists the ones to create). Approval usually takes minutes to 2 days.',
            'Press "Save", then use "Send test message" to your own number.',
        ];
    }

    public function signsWebhooks(): bool
    {
        return true;
    }

    private function post(array $cfg, array $payload): array
    {
        $response = Http::withToken($cfg['access_token'] ?? '')->acceptJson()->timeout(20)
            ->post(self::GRAPH . '/' . ($cfg['phone_number_id'] ?? '') . '/messages', ['messaging_product' => 'whatsapp'] + $payload);
        if (! $response->successful()) {
            $this->fail('Meta', $response);
        }

        return ['id' => $response->json('messages.0.id')];
    }

    public function sendText(string $to, string $body, array $cfg): array
    {
        return $this->post($cfg, ['to' => $this->digits($to), 'type' => 'text', 'text' => ['preview_url' => true, 'body' => $body]]);
    }

    /** A button/list reply whose id is "say:xyz" counts as the customer typing "xyz"; others use the visible title. */
    private function interactiveText(array $interactive): string
    {
        $reply = $interactive['button_reply'] ?? $interactive['list_reply'] ?? [];
        $id = (string) ($reply['id'] ?? '');

        return str_starts_with($id, 'say:') ? substr($id, 4) : (string) ($reply['title'] ?? '');
    }

    public function supportsImages(): bool
    {
        return true;
    }

    public function supportsCatalog(): bool
    {
        return true;
    }

    public function supportsInteractive(): bool
    {
        return true;
    }

    public function sendInteractive(string $to, array $interactive, array $cfg): array
    {
        return $this->post($cfg, ['to' => $this->digits($to), 'type' => 'interactive', 'interactive' => $interactive]);
    }

    /** A catalog browsing message: a "View items" button that opens the products with a quantity selector and cart. */
    public function sendCatalogProducts(string $to, string $header, string $body, ?string $footer, array $sections, array $cfg): array
    {
        $interactive = [
            'type' => 'product_list',
            'header' => ['type' => 'text', 'text' => mb_substr($header, 0, 60)],
            'body' => ['text' => mb_substr($body, 0, 1024)],
            'action' => ['catalog_id' => $cfg['catalog_id'] ?? '', 'sections' => $sections],
        ];
        if ($footer) {
            $interactive['footer'] = ['text' => mb_substr($footer, 0, 60)];
        }

        return $this->post($cfg, ['to' => $this->digits($to), 'type' => 'interactive', 'interactive' => $interactive]);
    }

    /**
     * Creates / updates / deletes catalog items in one request (Meta Catalog Batch API).
     *
     * @param array<int, array{method: string, data: array<string, mixed>}> $requests
     * @param array<string, string> $cfg
     * @return array{handles: string[]}
     */
    public function syncCatalogItems(array $requests, array $cfg): array
    {
        $response = Http::withToken($cfg['access_token'] ?? '')->acceptJson()->timeout(60)
            ->asForm()->post(self::GRAPH . '/' . ($cfg['catalog_id'] ?? '') . '/items_batch', [
                'item_type' => 'PRODUCT_ITEM',
                'requests' => json_encode($requests),
            ]);
        if (! $response->successful()) {
            $this->fail('Meta catalog', $response);
        }

        return ['handles' => (array) ($response->json('handles') ?? [])];
    }

    /** Switches on the cart button and the catalog icon for this WhatsApp number. */
    public function enableCart(array $cfg): bool
    {
        $response = Http::withToken($cfg['access_token'] ?? '')->acceptJson()->timeout(20)
            ->post(self::GRAPH . '/' . ($cfg['phone_number_id'] ?? '') . '/whatsapp_commerce_settings', ['is_cart_enabled' => true, 'is_catalog_visible' => true]);
        if (! $response->successful()) {
            $this->fail('Meta', $response);
        }

        return (bool) ($response->json('success') ?? true);
    }

    public function sendImage(string $to, string $url, string $caption, array $cfg): array
    {
        return $this->post($cfg, ['to' => $this->digits($to), 'type' => 'image', 'image' => ['link' => $url, 'caption' => mb_substr($caption, 0, 1000)]]);
    }

    public function sendTemplate(string $to, string $template, string $language, array $params, array $cfg): array
    {
        $template = ['name' => $template, 'language' => ['code' => $language]];
        $components = [];
        if (! empty($cfg['header_image'])) {
            $components[] = ['type' => 'header', 'parameters' => [['type' => 'image', 'image' => ['link' => $cfg['header_image']]]]];
        }
        if ($params) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], array_values($params)),
            ];
        }
        if ($components) {
            $template['components'] = $components;
        }

        return $this->post($cfg, ['to' => $this->digits($to), 'type' => 'template', 'template' => $template]);
    }

    public function handshake(Request $request, array $cfg): ?Response
    {
        if ($request->query('hub_mode') === 'subscribe' && hash_equals((string) ($cfg['verify_token'] ?? ''), (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function verifySignature(Request $request, array $cfg): bool
    {
        $secret = (string) ($cfg['app_secret'] ?? '');
        $header = (string) $request->header('X-Hub-Signature-256');
        if ($secret === '' || $header === '') {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secret), $header);
    }

    public function parseWebhook(Request $request, array $cfg): array
    {
        $out = ['messages' => [], 'statuses' => []];
        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);

                foreach ($value['messages'] ?? [] as $m) {
                    $type = $m['type'] ?? 'text';
                    $text = match ($type) {
                        'order' => $m['order']['text'] ?? 'Order from catalog',
                        'text' => $m['text']['body'] ?? '',
                        'button' => $m['button']['text'] ?? '',
                        'interactive' => $this->interactiveText($m['interactive'] ?? []),
                        default => '',
                    };
                    $payload = match ($type) {
                        'order' => json_encode($m['order'] ?? []),
                        'button' => $m['button']['payload'] ?? null,
                        'interactive' => $m['interactive']['button_reply']['id'] ?? $m['interactive']['list_reply']['id'] ?? null,
                        'location' => json_encode($m['location'] ?? []),
                        default => null,
                    };
                    $out['messages'][] = [
                        'id' => $m['id'] ?? null, 'from' => '+' . ($m['from'] ?? ''), 'name' => $names[$m['from'] ?? ''] ?? null,
                        'type' => $type, 'text' => $text, 'payload' => $payload, 'timestamp' => $m['timestamp'] ?? null,
                    ];
                }
                foreach ($value['statuses'] ?? [] as $s) {
                    $out['statuses'][] = [
                        'id' => $s['id'] ?? null, 'status' => $s['status'] ?? '',
                        'error' => isset($s['errors'][0]) ? ($s['errors'][0]['title'] ?? $s['errors'][0]['message'] ?? 'failed') : null,
                    ];
                }
            }
        }

        return $out;
    }
}
