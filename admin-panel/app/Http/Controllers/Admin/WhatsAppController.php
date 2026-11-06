<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\WhatsApp\WhatsAppTemplates;
use Illuminate\Http\Request;

/** Settings -> WhatsApp: pick a provider, fill its (dynamic) form, map templates, test, view the log. */
class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public function settings()
    {
        return view('admin.whatsapp.settings', [
            'service' => $this->whatsapp,
            'providers' => $this->whatsapp->providers(),
            'selected' => (string) AppSetting::getValue('whatsapp_provider', 'meta'),
            'chatSelected' => (string) AppSetting::getValue('whatsapp_chat_provider', ''),
            'enabled' => filter_var(AppSetting::getValue('whatsapp_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'templates' => WhatsAppTemplates::all(),
            'language' => AppSetting::getValue('whatsapp_template_language', 'en'),
            'catalogEnabled' => filter_var(AppSetting::getValue('whatsapp_catalog_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'catalogLast' => json_decode((string) AppSetting::getValue('whatsapp_catalog_last_sync', ''), true) ?: null,
            'catalogItems' => \App\Models\WhatsAppCatalogItem::where('status', 'synced')->count(),
            'catalogFailed' => \App\Models\WhatsAppCatalogItem::where('status', 'failed')->count(),
            'imagesEnabled' => filter_var(AppSetting::getValue('whatsapp_send_images', '1'), FILTER_VALIDATE_BOOLEAN),
            'botEnabled' => filter_var(AppSetting::getValue('whatsapp_bot_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function save(Request $request)
    {
        $providers = $this->whatsapp->providers();
        $data = $request->validate([
            'whatsapp_provider' => ['required', 'in:' . implode(',', array_keys($providers))],
            'whatsapp_template_language' => ['nullable', 'string', 'max:10'],
            'whatsapp_chat_provider' => ['nullable', 'in:' . implode(',', array_keys($providers))],
        ]);

        AppSetting::setValue('whatsapp_enabled', $request->boolean('whatsapp_enabled') ? '1' : '0');
        AppSetting::setValue('whatsapp_provider', $data['whatsapp_provider']);
        AppSetting::setValue('whatsapp_chat_provider', ($data['whatsapp_chat_provider'] ?? '') === $data['whatsapp_provider'] ? '' : (string) ($data['whatsapp_chat_provider'] ?? ''));
        AppSetting::setValue('whatsapp_bot_enabled', $request->boolean('whatsapp_bot_enabled') ? '1' : '0');
        AppSetting::setValue('whatsapp_send_images', $request->boolean('whatsapp_send_images') ? '1' : '0');
        AppSetting::setValue('whatsapp_catalog_enabled', $request->boolean('whatsapp_catalog_enabled') ? '1' : '0');
        AppSetting::setValue('whatsapp_template_language', ($data['whatsapp_template_language'] ?? '') ?: 'en');

        foreach ($providers as $key => $provider) {
            foreach ($provider->fields() as $field) {
                $input = $request->input("cfg.{$key}.{$field['key']}");
                if ($input === null) {
                    continue;
                }
                $input = trim((string) $input);
                // A blank secret box means "keep what is saved".
                if ($field['type'] === 'secret' && $input === '') {
                    continue;
                }
                AppSetting::setValue("whatsapp_{$key}_{$field['key']}", $input);
            }
            foreach (array_keys(WhatsAppTemplates::all()) as $templateKey) {
                $value = $request->input("tpl.{$key}.{$templateKey}");
                if ($value !== null) {
                    AppSetting::setValue("whatsapp_tpl_{$key}_{$templateKey}", trim((string) $value));
                }
            }
        }
        // The per-event switches are only changed when the form carries them.
        if ($request->has('events')) {
            foreach (array_keys(WhatsAppTemplates::all()) as $templateKey) {
                AppSetting::setValue("whatsapp_event_{$templateKey}", $request->boolean("events.{$templateKey}") ? '1' : '0');
            }
        }
        $this->whatsapp->webhookToken();   // make sure one exists

        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function catalogSync(Request $request)
    {
        $sync = app(\App\Services\WhatsApp\WhatsAppCatalogSync::class);
        if (! $sync->ready()) {
            return back()->with('error', 'Turn the catalog on, choose Meta and save a Catalog ID first.');
        }
        @set_time_limit(180);
        $r = $sync->run((bool) $request->boolean('force'));

        return $r['failed'] > 0
            ? back()->with('error', "Synced with problems: {$r['created']} added, {$r['updated']} updated, {$r['removed']} removed, {$r['failed']} failed. " . ($r['error'] ?? ''))
            : back()->with('success', "Catalog synced: {$r['created']} added, {$r['updated']} updated, {$r['removed']} removed ({$r['total']} dishes on sale).");
    }

    public function catalogCart()
    {
        $provider = $this->whatsapp->catalogProvider() ?? $this->whatsapp->provider('meta');
        if (! $provider instanceof \App\Services\WhatsApp\Providers\MetaCloudProvider || ! $this->whatsapp->isConfigured($provider->key())) {
            return back()->with('error', 'Fill in the Meta connection details and save first.');
        }
        try {
            $provider->enableCart($this->whatsapp->config($provider->key()));
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not switch on the cart: ' . $e->getMessage());
        }

        return back()->with('success', 'The cart button and catalog icon are now on for your WhatsApp number.');
    }

    public function test(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'template' => ['nullable', 'string', 'max:200'],
            'text' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->whatsapp->enabled() || ! $this->whatsapp->isConfigured()) {
            return back()->with('error', 'Turn WhatsApp on, choose a provider and fill in its required fields first.');
        }

        $message = filled($data['template'] ?? null)
            ? $this->whatsapp->sendRawTemplate($data['phone'], $data['template'], [], (string) AppSetting::getValue('whatsapp_template_language', 'en'))
            : $this->whatsapp->sendText($data['phone'], $data['text'] ?: 'Test message from ' . AppSetting::getValue('app_name', config('app.name')), 'test');

        return $message->status === 'failed'
            ? back()->with('error', 'Test failed: ' . $message->error)->withInput()
            : back()->with('success', 'Test message sent to ' . $message->phone . '. Check the log for delivery status.');
    }

    public function messages(Request $request)
    {
        $messages = WhatsAppMessage::query()
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->direction))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('phone', 'like', '%' . $request->search . '%')->orWhere('body', 'like', '%' . $request->search . '%')))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.whatsapp.messages', [
            'messages' => $messages,
            'stats' => [
                'sent' => WhatsAppMessage::where('direction', 'out')->count(),
                'delivered' => WhatsAppMessage::where('direction', 'out')->whereIn('status', ['delivered', 'read'])->count(),
                'failed' => WhatsAppMessage::where('direction', 'out')->where('status', 'failed')->count(),
                'inbound' => WhatsAppMessage::where('direction', 'in')->count(),
                'optouts' => WhatsAppContact::where('opted_in', false)->count(),
            ],
        ]);
    }
}
