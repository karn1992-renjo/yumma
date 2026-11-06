<?php

namespace App\Services\WhatsApp\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Twilio WhatsApp (sandbox or approved sender). Templates are Twilio "Content Templates" (ContentSid). */
class TwilioProvider extends WhatsAppProvider
{
    public function key(): string
    {
        return 'twilio';
    }

    public function label(): string
    {
        return 'Twilio';
    }

    public function summary(): string
    {
        return 'Fast to start (sandbox in minutes). Pay Twilio per message plus Meta fees.';
    }

    public function fields(): array
    {
        return [
            ['key' => 'account_sid', 'label' => 'Account SID', 'type' => 'text', 'required' => true, 'help' => 'Twilio Console home page.'],
            ['key' => 'auth_token', 'label' => 'Auth token', 'type' => 'secret', 'required' => true, 'help' => 'Twilio Console home page. Also used to verify webhooks.'],
            ['key' => 'from_number', 'label' => 'WhatsApp sender number', 'type' => 'text', 'required' => true, 'placeholder' => '+14155238886', 'help' => 'Your approved WhatsApp sender, or the sandbox number while testing.'],
        ];
    }

    public function setupSteps(): array
    {
        return [
            'Create an account at twilio.com and open Console → Messaging → Try it out → Send a WhatsApp message to use the free sandbox first.',
            'Copy the Account SID and Auth token from the Console home page into the form.',
            'Sandbox: enter the sandbox number (+14155238886) as the sender and join the sandbox from your phone with the join code shown by Twilio.',
            'Production: Messaging → Senders → WhatsApp senders → register your business number (needs a Meta Business account).',
            'Messaging → Content Template Builder: create and submit your templates. Copy each template\'s Content SID (starts with HX) into the Templates section on this page.',
            'In the sender (or sandbox) settings set "When a message comes in" to the Callback URL shown on this page (method POST), and the Status callback URL to the same address.',
            'Press "Save", then use "Send test message".',
        ];
    }

    public function signsWebhooks(): bool
    {
        return true;
    }

    private function send(array $cfg, array $form): array
    {
        $response = Http::withBasicAuth($cfg['account_sid'] ?? '', $cfg['auth_token'] ?? '')->asForm()->timeout(20)
            ->post('https://api.twilio.com/2010-04-01/Accounts/' . ($cfg['account_sid'] ?? '') . '/Messages.json', $form + [
                'From' => 'whatsapp:+' . $this->digits($cfg['from_number'] ?? ''),
            ]);
        if (! $response->successful()) {
            $this->fail('Twilio', $response);
        }

        return ['id' => $response->json('sid')];
    }

    public function sendText(string $to, string $body, array $cfg): array
    {
        return $this->send($cfg, ['To' => 'whatsapp:+' . $this->digits($to), 'Body' => $body]);
    }

    public function supportsImages(): bool
    {
        return true;
    }

    public function sendImage(string $to, string $url, string $caption, array $cfg): array
    {
        return $this->send($cfg, ['To' => 'whatsapp:+' . $this->digits($to), 'Body' => mb_substr($caption, 0, 1000), 'MediaUrl' => $url]);
    }

    public function sendTemplate(string $to, string $template, string $language, array $params, array $cfg): array
    {
        $variables = [];
        foreach (array_values($params) as $i => $value) {
            $variables[(string) ($i + 1)] = (string) $value;
        }

        return $this->send($cfg, [
            'To' => 'whatsapp:+' . $this->digits($to),
            'ContentSid' => $template,                         // the Content SID (HX…)
            'ContentVariables' => json_encode((object) $variables),
        ]);
    }

    public function verifySignature(Request $request, array $cfg): bool
    {
        $token = (string) ($cfg['auth_token'] ?? '');
        $header = (string) $request->header('X-Twilio-Signature');
        if ($token === '' || $header === '') {
            return false;
        }
        $params = $request->post();
        ksort($params);
        $data = $request->fullUrl();
        $data = strtok($data, '?') . ($request->getQueryString() ? '?' . $request->getQueryString() : '');
        foreach ($params as $k => $v) {
            $data .= $k . (is_array($v) ? implode('', $v) : $v);
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $token, true)), $header);
    }

    public function parseWebhook(Request $request, array $cfg): array
    {
        $out = ['messages' => [], 'statuses' => []];
        $sid = $request->input('MessageSid') ?? $request->input('SmsMessageSid');

        // Status callback
        if ($request->filled('MessageStatus') && ! $request->filled('Body') && ! $request->filled('ButtonPayload')) {
            $status = strtolower((string) $request->input('MessageStatus'));
            $out['statuses'][] = [
                'id' => $sid,
                'status' => match ($status) {
                    'queued', 'accepted', 'sending', 'sent' => 'sent',
                    'delivered' => 'delivered',
                    'read' => 'read',
                    default => 'failed',
                },
                'error' => $request->input('ErrorMessage') ?? ($request->input('ErrorCode') ? 'Error ' . $request->input('ErrorCode') : null),
            ];

            return $out;
        }

        // Inbound message
        $from = preg_replace('/^whatsapp:/', '', (string) $request->input('From'));
        if ($from !== '') {
            $hasLocation = $request->filled('Latitude');
            $out['messages'][] = [
                'id' => $sid, 'from' => $from, 'name' => $request->input('ProfileName'),
                'type' => $hasLocation ? 'location' : ($request->filled('ButtonPayload') ? 'button' : 'text'),
                'text' => (string) ($request->input('ButtonText') ?? $request->input('Body') ?? ''),
                'payload' => $hasLocation ? json_encode(['latitude' => (float) $request->input('Latitude'), 'longitude' => (float) $request->input('Longitude')]) : $request->input('ButtonPayload'),
                'timestamp' => time(),
            ];
        }

        return $out;
    }
}
