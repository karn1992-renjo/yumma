<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastTemplate;
use App\Services\WhatsApp\WhatsAppBroadcastVariables;
use App\Services\WhatsApp\WhatsAppTemplateRules;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;

/** Admin: shared WhatsApp inbox (agents reply to customers), broadcasts and delivery reports. */
class WhatsAppInboxController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp, private WhatsAppBroadcastService $broadcasts)
    {
    }

    // ---- Inbox -----------------------------------------------------------------------------------------

    public function inbox(Request $request)
    {
        $contacts = WhatsAppContact::query()
            ->whereHas('messages')
            ->with(['user:id,name', 'lastMessage'])
            ->when($request->query('filter') === 'waiting', fn ($q) => $q->where('bot_state', 'human'))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('phone', 'like', '%' . $request->search . '%')->orWhere('profile_name', 'like', '%' . $request->search . '%')))
            ->orderByDesc('last_inbound_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.whatsapp.inbox', [
            'contacts' => $contacts,
            'waiting' => WhatsAppContact::where('bot_state', 'human')->count(),
        ]);
    }

    public function thread(WhatsAppContact $contact)
    {
        return view('admin.whatsapp.thread', [
            'contact' => $contact->load('user:id,name,email'),
            'messages' => $contact->messages()->orderBy('id')->limit(300)->get(),
            'canText' => $contact->inServiceWindow() && ($this->whatsapp->providerFor($contact->phone)?->supportsFreeform() ?? false),
            'templates' => \App\Services\WhatsApp\WhatsAppTemplates::all(),
        ]);
    }

    public function reply(Request $request, WhatsAppContact $contact)
    {
        $data = $request->validate(['text' => ['nullable', 'string', 'max:3000'], 'template' => ['nullable', 'string', 'max:200']]);

        if (filled($data['template'] ?? null)) {
            $message = $this->whatsapp->sendRawTemplate($contact->phone, $data['template'], [], (string) AppSetting::getValue('whatsapp_template_language', 'en'));
        } elseif (filled($data['text'] ?? null)) {
            $message = $this->whatsapp->sendText($contact->phone, $data['text'], 'agent');
        } else {
            return back()->with('error', 'Type a message first.');
        }

        // An agent answering means the conversation is theirs until they hand it back.
        if ($message->status !== 'failed') {
            $contact->forceFill(['bot_state' => 'human', 'bot_context' => ['acked_at' => now()->timestamp, 'agent_id' => $request->user()->id]])->save();
        }

        return $message->status === 'failed'
            ? back()->with('error', 'Not sent: ' . $message->error)->withInput()
            : back()->with('success', 'Reply sent.');
    }

    public function handBack(WhatsAppContact $contact)
    {
        $contact->forceFill(['bot_state' => null, 'bot_context' => null])->save();

        return back()->with('success', 'Conversation handed back to the assistant.');
    }

    // ---- Broadcasts ---------------------------------------------------------------------------------------

    public function broadcasts()
    {
        return view('admin.whatsapp.broadcasts', [
            'broadcasts' => WhatsAppBroadcast::latest('id')->paginate(15),
            'audiences' => WhatsAppBroadcastService::AUDIENCES,
            'roleOptions' => WhatsAppBroadcastService::ROLES,
            'enabled' => $this->whatsapp->enabled(),
            'templates' => WhatsAppBroadcastTemplate::latest('id')->get(),
            'variableCatalog' => WhatsAppBroadcastVariables::catalog(),
            'provider' => $this->whatsapp->provider(),
            'minWordsPerVariable' => WhatsAppTemplateRules::MIN_WORDS_PER_VARIABLE,
        ]);
    }

    public function storeBroadcast(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'broadcast_template_id' => ['nullable', 'exists:whatsapp_broadcast_templates,id'],
            'template' => ['required_without:broadcast_template_id', 'nullable', 'string', 'max:200'],
            'params' => ['nullable', 'string', 'max:1000'],
            'header_image' => ['nullable', 'url', 'max:500'],
            'header_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'audience' => ['required', 'in:' . implode(',', array_keys(WhatsAppBroadcastService::AUDIENCES))],
            'audience_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:' . implode(',', array_keys(WhatsAppBroadcastService::ROLES))],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please confirm that these customers agreed to receive WhatsApp promotions.']);

        if (! $this->whatsapp->enabled() || ! $this->whatsapp->isConfigured()) {
            return back()->with('error', 'Turn on and configure WhatsApp first.')->withInput();
        }

        $saved = ! empty($data['broadcast_template_id']) ? WhatsAppBroadcastTemplate::find($data['broadcast_template_id']) : null;
        if ($saved && ! $saved->usable()) {
            return back()->with('error', "\"{$saved->name}\" is not approved yet. Paste its provider Template ID and mark it Approved first.")->withInput();
        }

        // An uploaded picture wins over a pasted link; WhatsApp needs a public JPG/PNG URL (max 5 MB).
        $headerImage = $request->hasFile('header_image_file')
            ? $this->publicUploadUrl($request->file('header_image_file'))
            : ($data['header_image'] ?? null);

        $broadcast = WhatsAppBroadcast::create([
            'title' => $data['title'], 'template' => $saved ? $saved->provider_template_id : $data['template'],
            'params' => $saved ? array_values($saved->variables ?? [])
                : array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['params'] ?? ''))), fn ($v) => $v !== '')),
            'header_image' => $headerImage,
            'audience' => $data['audience'], 'audience_days' => $data['audience_days'] ?? null, 'roles' => array_values($data['roles'] ?? []) ?: null, 'created_by' => $request->user()->id,
        ]);
        $count = $this->broadcasts->start($broadcast);

        if ($count === 0) {
            $broadcast->update(['status' => 'done', 'finished_at' => now()]);

            return back()->with('error', 'No customers matched that audience, nothing was sent.');
        }

        // First batch right away; the scheduler continues the rest every minute.
        $this->broadcasts->runBatch(50);

        return back()->with('success', "Broadcast started for {$count} customers.");
    }

    // ---- Broadcast templates -----------------------------------------------------------------------------

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:1100'],
            'language' => ['nullable', 'string', 'max:10'],
            'var_type' => ['nullable', 'array'],
            'var_text' => ['nullable', 'array'],
        ]);

        $check = WhatsAppTemplateRules::check($data['body']);
        if ($check['errors']) {
            return back()->withErrors(['body' => implode(' ', $check['errors'])])->withInput();
        }

        // What each {{n}} stands for: one of the {tokens}, or fixed text such as a coupon code.
        $specs = [];
        for ($n = 1; $n <= $check['variables']; $n++) {
            $type = (string) ($data['var_type'][$n] ?? '');
            $spec = WhatsAppBroadcastVariables::isToken($type) ? $type : trim((string) ($data['var_text'][$n] ?? ''));
            if ($spec === '') {
                return back()->withErrors(['body' => "Choose what {{" . $n . "}} should be (a customer detail or fixed text)."])->withInput();
            }
            $specs[$n] = $spec;
        }

        $slug = \Illuminate\Support\Str::of($data['name'])->lower()->slug('_')->limit(60, '')->toString() ?: 'broadcast';
        WhatsAppBroadcastTemplate::create([
            'name' => $data['name'], 'template_name' => $slug, 'body' => trim($data['body']), 'variables' => $specs,
            'language' => $data['language'] ?: (string) AppSetting::getValue('whatsapp_template_language', 'en'), 'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Template saved. Copy its text into ' . ($this->whatsapp->provider()?->label() ?? 'your provider') . ' for approval, then paste the approved Template ID here.');
    }

    public function updateTemplate(Request $request, WhatsAppBroadcastTemplate $template)
    {
        $data = $request->validate([
            'provider_template_id' => ['nullable', 'string', 'max:200'],
            'status' => ['required', 'in:draft,submitted,approved,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['status'] === 'approved' && blank($data['provider_template_id'] ?? null)) {
            return back()->with('error', 'Paste the Template ID from your provider before marking it Approved.');
        }
        $template->update($data);

        return back()->with('success', "Template \"{$template->name}\" updated.");
    }

    public function destroyTemplate(WhatsAppBroadcastTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Template deleted. Broadcasts already sent are not affected.');
    }

    public function cancelBroadcast(WhatsAppBroadcast $broadcast)
    {
        $this->broadcasts->cancel($broadcast);

        return back()->with('success', 'Broadcast cancelled. Messages already sent cannot be recalled.');
    }

    // ---- Reports ----------------------------------------------------------------------------------------------

    public function reports()
    {
        $since = now()->subDays(14)->startOfDay();
        $out = WhatsAppMessage::where('direction', 'out');

        return view('admin.whatsapp.reports', [
            'totals' => [
                'sent' => (clone $out)->where('status', '!=', 'failed')->count(),
                'delivered' => (clone $out)->whereIn('status', ['delivered', 'read'])->count(),
                'read' => (clone $out)->where('status', 'read')->count(),
                'failed' => (clone $out)->where('status', 'failed')->count(),
                'inbound' => WhatsAppMessage::where('direction', 'in')->count(),
                'contacts' => WhatsAppContact::count(),
                'optouts' => WhatsAppContact::where('opted_in', false)->count(),
            ],
            'byEvent' => (clone $out)->selectRaw("coalesce(event, 'other') as event, count(*) as total, sum(case when status in ('delivered','read') then 1 else 0 end) as delivered, sum(case when status = 'read' then 1 else 0 end) as read_count, sum(case when status = 'failed' then 1 else 0 end) as failed")
                ->groupBy('event')->orderByDesc('total')->get(),
            'daily' => WhatsAppMessage::where('created_at', '>=', $since)
                ->selectRaw("date(created_at) as day, sum(case when direction = 'out' then 1 else 0 end) as sent, sum(case when direction = 'in' then 1 else 0 end) as received")
                ->groupBy('day')->orderBy('day')->get(),
            'topErrors' => (clone $out)->where('status', 'failed')->whereNotNull('error')->selectRaw('error, count(*) as c')->groupBy('error')->orderByDesc('c')->limit(5)->get(),
        ]);
    }

    /** WhatsApp only accepts JPG/PNG headers from a public absolute URL, so the original file is kept (no WebP conversion). */
    private function publicUploadUrl(\Illuminate\Http\UploadedFile $file): string
    {
        \App\Services\MediaStorage::configure();
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = $disk->putFile('whatsapp', $file, 'public');
        $url = $disk->url($path);

        return preg_match('#^https?://#i', $url) ? $url : url($url);
    }
}
