<?php

namespace App\Services\WhatsApp\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * AiSensy. Its public API sends approved templates through "API campaigns"; there is no
 * free-form send endpoint, so the chat bot (Phase 3) replies with templates/campaigns only.
 */
class AiSensyProvider extends WhatsAppProvider
{
    public function key(): string
    {
        return 'aisensy';
    }

    public function label(): string
    {
        return 'AiSensy';
    }

    public function summary(): string
    {
        return 'Simple no-code platform. Notifications via API campaigns (template-only sending).';
    }

    public function supportsFreeform(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key (JWT)', 'type' => 'secret', 'required' => true, 'help' => 'AiSensy dashboard → Manage → API Key.'],
            ['key' => 'user_name', 'label' => 'Business / user name', 'type' => 'text', 'required' => true, 'help' => 'Shown to AiSensy as the sender name (your AiSensy account name).'],
        ];
    }

    public function setupSteps(): array
    {
        return [
            'Create an account at aisensy.com and connect your WhatsApp Business number (Meta embedded signup).',
            'Dashboard → Manage → API Key: copy the key into the form.',
            'Dashboard → Campaigns → create an "API Campaign" for every notification and choose an approved template for it. Copy each campaign NAME into the Templates section on this page.',
            'Template variables map in order ({{1}}, {{2}}…). Keep the same order as the sample shown in the Templates section.',
            'Dashboard → Manage → Webhooks: add the Callback URL shown on this page and select message and status events.',
            'Press "Save", then use "Send test message" (needs a campaign named in the test field).',
            'Note: AiSensy only sends templates through the API, so chat replies are limited to templates/campaigns.',
        ];
    }

    public function sendText(string $to, string $body, array $cfg): array
    {
        throw new \RuntimeException('AiSensy does not support free-form messages through its API. Use a template/campaign.');
    }

    public function sendTemplate(string $to, string $template, string $language, array $params, array $cfg): array
    {
        $response = Http::acceptJson()->timeout(20)->post('https://backend.aisensy.com/campaign/t1/api/v2', [
            'apiKey' => $cfg['api_key'] ?? '',
            'campaignName' => $template,
            'destination' => $this->digits($to),
            'userName' => $cfg['user_name'] ?? '',
            'templateParams' => array_map('strval', array_values($params)),
            'source' => 'swado-api',
        ]);
        if (! $response->successful()) {
            $this->fail('AiSensy', $response);
        }

        return ['id' => $response->json('submitted_message_id') ?? $response->json('id')];
    }

    public function parseWebhook(Request $request, array $cfg): array
    {
        $out = ['messages' => [], 'statuses' => []];
        $topic = (string) $request->input('topic', '');
        $data = $request->input('data', []);

        if (str_starts_with($topic, 'message.created') || $topic === 'message.received') {
            $m = $data['message'] ?? $data;
            if (($m['direction'] ?? 'IN') !== 'OUT') {
                $out['messages'][] = [
                    'id' => $m['id'] ?? $m['message_id'] ?? null,
                    'from' => '+' . preg_replace('/\D+/', '', (string) ($m['phone_number'] ?? $m['customer']['phone_number'] ?? '')),
                    'name' => $m['customer']['name'] ?? null,
                    'type' => $m['message_type'] ?? 'text',
                    'text' => (string) ($m['message_content']['text'] ?? $m['text'] ?? ''),
                    'payload' => $m['message_content']['button_payload'] ?? null,
                    'timestamp' => time(),
                ];
            }
        } elseif (str_starts_with($topic, 'message.status')) {
            $m = $data['message'] ?? $data;
            $status = strtolower((string) ($m['status'] ?? ''));
            $out['statuses'][] = [
                'id' => $m['id'] ?? $m['message_id'] ?? null,
                'status' => match ($status) {
                    'sent', 'submitted' => 'sent',
                    'delivered' => 'delivered',
                    'read' => 'read',
                    default => 'failed',
                },
                'error' => $m['failure_reason'] ?? null,
            ];
        }

        return $out;
    }
}
