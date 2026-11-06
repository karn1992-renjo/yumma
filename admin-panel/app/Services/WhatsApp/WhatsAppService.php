<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppMessageReceived;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\Providers\AiSensyProvider;
use App\Services\WhatsApp\Providers\GupshupProvider;
use App\Services\WhatsApp\Providers\InteraktProvider;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\Providers\TwilioProvider;
use App\Services\WhatsApp\Providers\WhatsAppProvider;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Everything the rest of the app does with WhatsApp goes through here: it applies the
 * on/off switch, opt-in/out and the 24-hour window, picks the configured provider, and logs
 * every message. Sending never throws: failures are recorded on the message row.
 */
class WhatsAppService
{
    public const STOP_WORDS = ['stop', 'unsubscribe', 'cancel notifications', 'opt out', 'optout'];
    public const START_WORDS = ['start', 'subscribe', 'unstop', 'opt in', 'optin'];

    /** @return array<string, WhatsAppProvider> */
    public function providers(): array
    {
        return collect([new MetaCloudProvider, new TwilioProvider, new GupshupProvider, new AiSensyProvider, new InteraktProvider])
            ->keyBy(fn (WhatsAppProvider $p) => $p->key())->all();
    }

    public function provider(?string $key = null): ?WhatsAppProvider
    {
        $key ??= (string) AppSetting::getValue('whatsapp_provider', '');

        return $this->providers()[$key] ?? null;
    }

    /**
     * Optional second provider (a second WhatsApp number) used for the interactive customer chats:
     * catalog, cart, buttons. The main provider keeps sending templates and notifications.
     */
    public function chatProvider(): ?WhatsAppProvider
    {
        $key = trim((string) AppSetting::getValue('whatsapp_chat_provider', ''));
        if ($key === '' || $key === (string) AppSetting::getValue('whatsapp_provider', '')) {
            return null;
        }
        $provider = $this->provider($key);

        return $provider && $this->isConfigured($key) ? $provider : null;
    }

    /** Both providers that may receive webhooks and send, main first. */
    public function activeProviders(): array
    {
        return array_values(array_filter([$this->provider(), $this->chatProvider()]));
    }

    /**
     * The provider to use for this phone. Templates always go through the main provider; free-form
     * messages go back through the number the customer last wrote to (when it is one of ours).
     */
    public function providerFor(?string $phone, string $type = 'text'): ?WhatsAppProvider
    {
        $main = $this->provider();
        if ($type === 'template' || ! $phone) {
            return $main;
        }
        try {
            $channel = WhatsAppContact::where('phone', $this->normalize($phone))->value('channel');
        } catch (\Throwable) {
            return $main;
        }
        if ($channel && $main && $channel !== $main->key()) {
            $chat = $this->chatProvider();
            if ($chat && $chat->key() === $channel) {
                return $chat;
            }
        }

        return $main;
    }

    public function enabled(): bool
    {
        return filter_var(AppSetting::getValue('whatsapp_enabled', '0'), FILTER_VALIDATE_BOOLEAN) && $this->provider() !== null;
    }

    /** True when this kind of automatic message is switched on (default on once WhatsApp is enabled). */
    public function eventEnabled(string $event): bool
    {
        return $this->enabled() && filter_var(AppSetting::getValue("whatsapp_event_{$event}", '1'), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, string> */
    public function config(?string $providerKey = null): array
    {
        $provider = $this->provider($providerKey);
        if (! $provider) {
            return [];
        }
        $cfg = [];
        foreach ($provider->fields() as $field) {
            $cfg[$field['key']] = (string) AppSetting::getValue("whatsapp_{$provider->key()}_{$field['key']}", '');
        }

        return $cfg;
    }

    public function isConfigured(?string $providerKey = null): bool
    {
        $provider = $this->provider($providerKey);
        if (! $provider) {
            return false;
        }
        $cfg = $this->config($provider->key());
        foreach ($provider->fields() as $field) {
            if (! empty($field['required']) && trim($cfg[$field['key']] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    /** Secret that authenticates webhooks from providers that do not sign them. */
    public function webhookToken(): string
    {
        $token = (string) AppSetting::getValue('whatsapp_webhook_token', '');
        if ($token === '') {
            $token = Str::random(32);
            AppSetting::setValue('whatsapp_webhook_token', $token);
        }

        return $token;
    }

    public function webhookUrl(WhatsAppProvider $provider): string
    {
        $url = url('/api/webhooks/whatsapp/' . $provider->key());

        return $provider->signsWebhooks() ? $url : $url . '/' . $this->webhookToken();
    }

    public function normalize(string $phone): string
    {
        return PhoneNumber::normalize($phone, AppSetting::getValue('default_mobile_country_code', '+91'));
    }

    public function templateIdentifier(string $providerKey, string $templateKey): string
    {
        $custom = trim((string) AppSetting::getValue("whatsapp_tpl_{$providerKey}_{$templateKey}", ''));

        return $custom !== '' ? $custom : $templateKey;
    }

    /**
     * The account whose mobile number is this WhatsApp number, however it was typed when registering
     * (+91 98765 43210, 98765-43210, 919876543210 ...). Matches on the last 10 digits; if a person has
     * several accounts on the same number (e.g. customer and driver) the customer account wins.
     */
    public function accountForNumber(string $phone): ?User
    {
        $last10 = substr(preg_replace('/\D+/', '', $phone), -10);
        if (strlen($last10) < 10) {
            return null;
        }
        $matches = User::where('phone', 'like', '%' . substr($last10, -4) . '%')->limit(100)->get()
            ->filter(fn (User $u) => substr(preg_replace('/\D+/', '', (string) $u->phone), -10) === $last10);

        return $matches->first(fn (User $u) => $u->hasRole('customer')) ?? $matches->first();
    }

    public function contactFor(string $phone, ?int $userId = null, ?string $name = null): WhatsAppContact
    {
        $phone = $this->normalize($phone);
        $contact = WhatsAppContact::firstOrCreate(['phone' => $phone], ['opted_in' => true]);
        $dirty = false;
        if ($userId && ! $contact->user_id) {
            $contact->user_id = $userId;
            $dirty = true;
        } elseif (! $contact->user_id) {
            // A number that already has an account needs no login: the number is verified by WhatsApp itself.
            if ($match = $this->accountForNumber($phone)) {
                $contact->user_id = $match->id;
                $dirty = true;
            }
        }
        if ($name && ! $contact->profile_name) {
            $contact->profile_name = $name;
            $dirty = true;
        }
        if ($dirty) {
            $contact->save();
        }

        return $contact;
    }

    // ---- Sending -----------------------------------------------------------------------------

    /** Free-form text. Only inside the customer's 24h window, and not through template-only providers. */
    public function sendText(string $phone, string $body, ?string $event = null, ?\Illuminate\Database\Eloquent\Model $related = null): WhatsAppMessage
    {
        return $this->dispatch($phone, 'text', null, $body, [], 'en', $event, $related);
    }

    /** The provider that holds the product catalog: an active one that can show catalogs and has a Catalog ID. */
    public function catalogProvider(?string $phone = null): ?WhatsAppProvider
    {
        if (! $this->enabled() || ! filter_var(AppSetting::getValue('whatsapp_catalog_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }
        $candidates = $phone ? array_filter([$this->providerFor($phone)]) : $this->activeProviders();
        foreach ($candidates as $provider) {
            if ($provider->supportsCatalog() && trim((string) AppSetting::getValue("whatsapp_{$provider->key()}_catalog_id", '')) !== '') {
                return $provider;
            }
        }

        return null;
    }

    /** The in-chat catalog is switched on and usable (for this customer's number, when given). */
    public function catalogEnabled(?string $phone = null): bool
    {
        return $this->catalogProvider($phone) !== null;
    }

    /**
     * Shows part of the catalog (up to 30 dishes in sections) with a "View items" button.
     *
     * @param array<int, array{title: string, product_items: array<int, array{product_retailer_id: string}>}> $sections
     */
    public function sendCatalog(string $phone, string $header, string $body, ?string $footer, array $sections, ?string $event = null): WhatsAppMessage
    {
        return $this->dispatch($phone, 'catalog', null, $body, ['header' => $header, 'footer' => $footer, 'sections' => $sections], 'en', $event, null);
    }

    /**
     * Tappable buttons / list / location request. $fallback is the plain-text version: it is what gets
     * sent (and logged) when the provider has no interactive messages or the send fails.
     *
     * @param array<string, mixed> $interactive
     */
    public function sendInteractive(string $phone, array $interactive, string $fallback, ?string $event = null): WhatsAppMessage
    {
        $provider = $this->providerFor($phone);
        if (! $provider || ! $provider->supportsInteractive()) {
            return $this->sendText($phone, $fallback, $event);
        }

        $message = $this->dispatch($phone, 'interactive', null, $fallback, ['interactive' => $interactive], 'en', $event, null);
        if ($message->status === 'failed' && ! preg_match('/24-hour|opted out|not enabled|Invalid phone/i', (string) $message->error)) {
            return $this->sendText($phone, $fallback, $event);
        }

        return $message;
    }

    public function imagesEnabled(): bool
    {
        return filter_var(AppSetting::getValue('whatsapp_send_images', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * A picture with a caption (restaurant or dish photo). Same rules as free text. When the provider
     * cannot send pictures, the picture is switched off, or sending it fails, the caption goes out as
     * plain text instead, so a conversation never stalls on a photo.
     */
    public function sendImage(string $phone, ?string $url, string $caption, ?string $event = null): WhatsAppMessage
    {
        $provider = $this->providerFor($phone);
        if (blank($url) || ! $provider || ! $provider->supportsImages() || ! $this->imagesEnabled()) {
            return $this->sendText($phone, $caption, $event);
        }

        $message = $this->dispatch($phone, 'image', null, $caption, ['url' => $url], 'en', $event, null);
        if ($message->status === 'failed' && ! preg_match('/24-hour|opted out|not enabled|Invalid phone/i', (string) $message->error)) {
            return $this->sendText($phone, $caption, $event);
        }

        return $message;
    }

    /** An approved template, addressed by its catalog key (see WhatsAppTemplates) -> provider identifier. */
    public function sendTemplate(string $phone, string $templateKey, array $params, ?string $event = null, ?\Illuminate\Database\Eloquent\Model $related = null): WhatsAppMessage
    {
        $provider = $this->provider();
        $identifier = $provider ? $this->templateIdentifier($provider->key(), $templateKey) : $templateKey;
        $language = (string) AppSetting::getValue('whatsapp_template_language', 'en');

        return $this->dispatch($phone, 'template', $identifier, $this->previewBody($templateKey, $params), $params, $language, $event ?? $templateKey, $related);
    }

    /** Same, but with the provider's template identifier given directly (used by the test button). */
    public function sendRawTemplate(string $phone, string $identifier, array $params = [], string $language = 'en', ?string $headerImage = null): WhatsAppMessage
    {
        return $this->dispatch($phone, 'template', $identifier, null, $params, $language, 'test', null, $headerImage);
    }

    private function previewBody(string $templateKey, array $params): ?string
    {
        $tpl = WhatsAppTemplates::all()[$templateKey]['body'] ?? null;
        if (! $tpl) {
            return null;
        }
        foreach (array_values($params) as $i => $value) {
            $tpl = str_replace('{{' . ($i + 1) . '}}', (string) $value, $tpl);
        }

        return $tpl;
    }

    private function dispatch(string $phone, string $type, ?string $template, ?string $body, array $params, string $language, ?string $event, $related, ?string $headerImage = null): WhatsAppMessage
    {
        $provider = $this->providerFor($phone, $type);
        $providerKey = $provider?->key() ?? (string) AppSetting::getValue('whatsapp_provider', 'meta');
        $message = new WhatsAppMessage([
            'direction' => 'out', 'provider' => $providerKey, 'phone' => $phone, 'type' => $type,
            'template' => $template, 'body' => $body, 'payload' => $params ? ['params' => array_values($params)] : null,
            'status' => 'queued', 'event' => $event,
        ]);
        if ($related) {
            $message->related_type = $related->getMorphClass();
            $message->related_id = $related->getKey();
        }

        if (! $this->enabled() || ! $provider || ! $this->isConfigured($providerKey)) {
            return $this->finishFailed($message, 'WhatsApp is not enabled or not fully configured.', false);
        }

        try {
            $phone = $this->normalize($phone);
            $message->phone = $phone;
            $contact = $this->contactFor($phone);
            $message->contact_id = $contact->id;
        } catch (\Throwable) {
            return $this->finishFailed($message, 'Invalid phone number.');
        }

        if (! $contact->opted_in) {
            return $this->finishFailed($message, 'Customer has opted out of WhatsApp messages.');
        }

        if (in_array($type, ['text', 'image', 'catalog', 'interactive'], true)) {
            if (! $provider->supportsFreeform()) {
                return $this->finishFailed($message, "{$provider->label()} can only send templates, not free-form text.");
            }
            if (! $contact->inServiceWindow()) {
                return $this->finishFailed($message, 'Outside the 24-hour window: WhatsApp only allows approved templates now.');
            }
        }

        try {
            $cfg = $this->config($providerKey);
            if ($headerImage) {
                $cfg['header_image'] = $headerImage;     // templates approved with an image header
            }
            $result = match ($type) {
                'text' => $provider->sendText($phone, (string) $body, $cfg),
                'image' => $provider->sendImage($phone, (string) ($params['url'] ?? ''), (string) $body, $cfg),
                'interactive' => $provider->sendInteractive($phone, (array) ($params['interactive'] ?? []), $cfg),
                'catalog' => $provider->sendCatalogProducts($phone, (string) ($params['header'] ?? ''), (string) $body, $params['footer'] ?? null, $params['sections'] ?? [], $cfg),
                default => $provider->sendTemplate($phone, (string) $template, $language, $params, $cfg),
            };
            $message->provider_message_id = $result['id'] ?? null;
            $message->status = 'sent';
            $message->save();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed.', ['provider' => $providerKey, 'phone' => $phone, 'error' => $e->getMessage()]);

            return $this->finishFailed($message, $e->getMessage());
        }

        return $message;
    }

    private function finishFailed(WhatsAppMessage $message, string $error, bool $save = true): WhatsAppMessage
    {
        $message->status = 'failed';
        $message->error = mb_substr($error, 0, 500);
        if ($save) {
            $message->save();
        }

        return $message;
    }

    // ---- Inbound webhook ----------------------------------------------------------------------

    /** Whether this request really comes from the provider (signature, or our secret ?key= token). */
    public function authentic(WhatsAppProvider $provider, Request $request): bool
    {
        if ($provider->signsWebhooks()) {
            return $provider->verifySignature($request, $this->config($provider->key()));
        }

        // The secret travels in the URL path (always preserved) or, for older setups, as ?key=.
        return hash_equals($this->webhookToken(), (string) ($request->route('key') ?? $request->query('key')));
    }

    /** @return array{messages:int, statuses:int} */
    public function handleWebhook(WhatsAppProvider $provider, Request $request): array
    {
        $parsed = $provider->parseWebhook($request, $this->config($provider->key()));
        $counts = ['messages' => 0, 'statuses' => 0];

        foreach ($parsed['messages'] as $in) {
            if (empty($in['from'])) {
                continue;
            }
            // The same webhook can be delivered more than once.
            if (! empty($in['id']) && WhatsAppMessage::where('direction', 'in')->where('provider_message_id', $in['id'])->exists()) {
                continue;
            }

            $contact = $this->contactFor($in['from'], null, $in['name'] ?? null);
            $contact->last_inbound_at = now();
            $contact->channel = $provider->key();
            $text = trim((string) ($in['text'] ?? ''));
            $word = Str::lower($text);

            if (in_array($word, self::STOP_WORDS, true)) {
                $contact->opted_in = false;
                $contact->opted_out_at = now();
            } elseif (in_array($word, self::START_WORDS, true)) {
                $contact->opted_in = true;
                $contact->opted_out_at = null;
            }
            $contact->save();

            $message = WhatsAppMessage::create([
                'contact_id' => $contact->id, 'direction' => 'in', 'provider' => $provider->key(), 'phone' => $contact->phone,
                'type' => $in['type'] ?? 'text', 'body' => $text, 'payload' => ['payload' => $in['payload'] ?? null],
                'provider_message_id' => $in['id'] ?? null, 'status' => 'received',
            ]);
            $counts['messages']++;

            try {
                event(new WhatsAppMessageReceived($contact, $message, $in));
            } catch (\Throwable $e) {
                Log::warning('WhatsApp inbound listener failed.', ['error' => $e->getMessage()]);
            }

            // The assistant replies after the webhook has been acknowledged to the provider.
            $reply = function () use ($contact, $message, $in) {
                try {
                    app(WhatsAppBot::class)->handle($contact->fresh(), $message, $in);
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp bot failed.', ['error' => $e->getMessage()]);
                }
            };
            app()->runningInConsole() ? $reply() : app()->terminating($reply);
        }

        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        foreach ($parsed['statuses'] as $st) {
            $ids = array_filter([$st['id'] ?? null, $st['alt_id'] ?? null]);
            if (! $ids) {
                continue;
            }
            $message = WhatsAppMessage::where('direction', 'out')->whereIn('provider_message_id', $ids)->first();
            if (! $message) {
                continue;
            }
            $new = $st['status'] ?? '';
            // Statuses can arrive out of order: never move a message backwards (read -> delivered).
            if (($rank[$new] ?? -1) > ($rank[$message->status] ?? 0) || $new === 'failed') {
                $message->status = $new;
                $message->error = $new === 'failed' ? mb_substr((string) ($st['error'] ?? 'failed'), 0, 500) : $message->error;
                $message->save();
            }
            $counts['statuses']++;
        }

        return $counts;
    }
}
