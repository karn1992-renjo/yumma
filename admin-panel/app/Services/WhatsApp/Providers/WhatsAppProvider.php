<?php

namespace App\Services\WhatsApp\Providers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One WhatsApp sending channel (Meta Cloud API, Twilio, Gupshup, AiSensy, Interakt).
 *
 * Providers never touch the database or the opt-in rules: WhatsAppService does that. A
 * provider only (1) describes its settings form, (2) sends, (3) understands its own webhook.
 *
 * Normalised webhook output of parseWebhook():
 *   ['messages' => [['id','from','name','type','text','payload','timestamp']],
 *    'statuses' => [['id','status'(sent|delivered|read|failed),'error']]]
 */
abstract class WhatsAppProvider
{
    abstract public function key(): string;

    abstract public function label(): string;

    /** One line shown on the provider picker. */
    abstract public function summary(): string;

    /**
     * Dynamic settings form. Each field: key, label, type (text|secret|select), required,
     * help, placeholder, options (for select), default.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function fields(): array;

    /** Plain-language setup steps shown next to the form. @return string[] */
    abstract public function setupSteps(): array;

    /** Whether free-form text (inside the 24h window) can be sent through this provider's API. */
    public function supportsFreeform(): bool
    {
        return true;
    }

    /** Whether this provider can show a WhatsApp product catalog (multi-product message) with a native cart. */
    public function supportsCatalog(): bool
    {
        return false;
    }

    /**
     * @param array<int, array{title: string, product_items: array<int, array{product_retailer_id: string}>}> $sections
     * @param array<string, string> $cfg
     * @return array{id: ?string}
     */
    public function sendCatalogProducts(string $to, string $header, string $body, ?string $footer, array $sections, array $cfg): array
    {
        throw new \RuntimeException($this->label() . ' cannot send a product catalog through its API.');
    }

    /** Whether this provider can send tappable buttons, lists and a "send my location" request. */
    public function supportsInteractive(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $interactive the provider's interactive payload (button, list or location request)
     * @param array<string, string> $cfg
     * @return array{id: ?string}
     */
    public function sendInteractive(string $to, array $interactive, array $cfg): array
    {
        throw new \RuntimeException($this->label() . ' cannot send interactive messages.');
    }

    /** Whether this provider can send a picture with a caption inside the 24h window. */
    public function supportsImages(): bool
    {
        return false;
    }

    /**
     * @param array<string, string> $cfg
     * @return array{id: ?string}
     */
    public function sendImage(string $to, string $url, string $caption, array $cfg): array
    {
        throw new \RuntimeException($this->label() . ' cannot send pictures through its API.');
    }

    /** True when the webhook is authenticated by a signature header; otherwise a ?key= token is used. */
    public function signsWebhooks(): bool
    {
        return false;
    }

    /** @param array<string, string> $cfg @return array{id: ?string} */
    abstract public function sendText(string $to, string $body, array $cfg): array;

    /**
     * @param string[] $params ordered body variables ({{1}}, {{2}}…)
     * @param array<string, string> $cfg
     * @return array{id: ?string}
     */
    abstract public function sendTemplate(string $to, string $template, string $language, array $params, array $cfg): array;

    /** @param array<string, string> $cfg @return array{messages: array, statuses: array} */
    abstract public function parseWebhook(Request $request, array $cfg): array;

    /** Signature check for providers that sign (others return true; the controller checks the token). */
    public function verifySignature(Request $request, array $cfg): bool
    {
        return true;
    }

    /** Answer to the provider's one-time webhook verification request, if it has one. */
    public function handshake(Request $request, array $cfg): ?Response
    {
        return null;
    }

    /** Digits only, with country code (no +). */
    protected function digits(string $e164): string
    {
        return preg_replace('/\D+/', '', $e164) ?? '';
    }

    protected function fail(string $provider, \Illuminate\Http\Client\Response $response): never
    {
        $detail = $response->json('error.message') ?? $response->json('message') ?? $response->json('detail') ?? $response->body();
        throw new \RuntimeException("{$provider} rejected the message (HTTP {$response->status()}): " . mb_substr(is_string($detail) ? $detail : json_encode($detail), 0, 300));
    }
}
