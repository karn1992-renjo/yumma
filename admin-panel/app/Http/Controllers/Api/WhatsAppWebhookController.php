<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Inbound messages + delivery receipts from the configured WhatsApp provider. */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    /** Providers that verify a webhook URL once (Meta) call GET. */
    public function verify(Request $request, string $provider, ?string $key = null)
    {
        $driver = $this->whatsapp->provider($provider);
        abort_unless($driver, 404);
        if ($early = $this->answerBeforeEnabled($driver, $request)) {
            return $early;
        }

        return $driver->handshake($request, $this->whatsapp->config($driver->key())) ?? response('OK', 200);
    }

    /**
     * Provider consoles (Gupshup, Interakt, ...) test the callback URL when you save it, usually before
     * WhatsApp has been switched on here. A URL carrying the right secret therefore gets a plain OK (and
     * is ignored); anything else, or a provider that is not the selected one, still looks like a missing page.
     */
    private function answerBeforeEnabled(\App\Services\WhatsApp\Providers\WhatsAppProvider $driver, Request $request)
    {
        if ($this->whatsapp->enabled() && collect($this->whatsapp->activeProviders())->contains(fn ($p) => $p->key() === $driver->key())) {
            return null;
        }
        $secret = (string) \App\Models\AppSetting::getValue('whatsapp_webhook_token', '');
        $given = (string) ($request->route('key') ?? $request->query('key'));
        if (! $driver->signsWebhooks() && $secret !== '' && hash_equals($secret, $given)) {
            return response()->json(['success' => true, 'ignored' => 'WhatsApp is not enabled yet']);
        }
        abort(404);
    }

    public function receive(Request $request, string $provider, ?string $key = null)
    {
        $driver = $this->whatsapp->provider($provider);
        abort_unless($driver, 404);
        if ($early = $this->answerBeforeEnabled($driver, $request)) {
            return $early;
        }

        if (! $this->whatsapp->authentic($driver, $request)) {
            Log::warning('WhatsApp webhook rejected: bad signature/key.', ['provider' => $provider, 'ip' => $request->ip()]);

            return response()->json(['success' => false], 403);
        }

        try {
            $counts = $this->whatsapp->handleWebhook($driver, $request);
        } catch (\Throwable $e) {
            // Never make the provider retry forever because of a bug on our side.
            Log::error('WhatsApp webhook processing failed.', ['provider' => $provider, 'error' => $e->getMessage()]);
            $counts = ['messages' => 0, 'statuses' => 0];
        }

        return response()->json(['success' => true] + $counts);
    }
}
