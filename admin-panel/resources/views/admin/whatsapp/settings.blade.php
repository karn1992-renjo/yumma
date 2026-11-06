@extends('layouts.admin')

@section('title', 'WhatsApp')
@section('header', 'WhatsApp')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fab fa-whatsapp"></i> Messaging</span>
            <h1>WhatsApp</h1>
            <p>Send order, tracking and account updates on WhatsApp and let customers chat with you. Pick the provider you already use &mdash; the form below changes to match it.</p>
        </div>
        <div><a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>Message log</a></div>
    </div>

    @include('admin.settings._tabs')
    @include('admin.whatsapp._nav')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.whatsapp.save') }}">
        @csrf

        <div class="settings-card">
            <div class="settings-card-header"><div><h2 class="settings-card-title">1. Turn it on and choose a provider</h2></div></div>
            <div class="settings-card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="wa_enabled" name="whatsapp_enabled" value="1" @checked($enabled)>
                    <label class="form-check-label fw-semibold" for="wa_enabled">Enable WhatsApp messaging</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="wa_images" name="whatsapp_send_images" value="1" @checked($imagesEnabled)>
                    <label class="form-check-label fw-semibold" for="wa_images">Send restaurant and dish pictures in the chat <span class="text-muted fw-normal">(works with Meta, Twilio and Gupshup; other providers send text only)</span></label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="wa_bot" name="whatsapp_bot_enabled" value="1" @checked($botEnabled)>
                    <label class="form-check-label fw-semibold" for="wa_bot">Customer chat assistant <span class="text-muted fw-normal">(track orders, recent orders, account, offers, nearby restaurants, talk to team)</span></label>
                </div>
                <div class="row g-3">
                    @foreach($providers as $key => $provider)
                        <div class="col-md-6 col-xl">
                            <label class="d-block h-100 border rounded-3 p-3 wa-provider" style="cursor:pointer">
                                <input type="radio" class="form-check-input me-2" name="whatsapp_provider" value="{{ $key }}" @checked($selected === $key) onchange="waPick('{{ $key }}')">
                                <strong>{{ $provider->label() }}</strong>
                                <div class="small text-muted mt-1">{{ $provider->summary() }}</div>
                                @if($service->isConfigured($key))<span class="badge bg-success mt-2">configured</span>@endif
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3" style="max-width:520px">
                    <label class="form-label">Second number for ordering chats <span class="text-muted fw-normal">(optional)</span></label>
                    <select name="whatsapp_chat_provider" class="form-select" id="wa_chat" onchange="waPickChat(this.value)">
                        <option value="">None &mdash; use the provider above for everything</option>
                        @foreach($providers as $key => $provider)
                            <option value="{{ $key }}" @selected($chatSelected === $key)>{{ $provider->label() }}</option>
                        @endforeach
                    </select>
                    <div class="small text-muted">Keep notifications, broadcasts and templates on the provider above, and run customer ordering chats (catalog with cart, buttons) on a second WhatsApp number with its own provider &mdash; e.g. Gupshup above and Meta here. Customers are answered on the number they wrote to. Fill in this provider's connection below and add its webhook URL there.</div>
                </div>
                <div class="mt-3" style="max-width:220px">
                    <label class="form-label">Template language code</label>
                    <input name="whatsapp_template_language" class="form-control" value="{{ old('whatsapp_template_language', $language) }}" placeholder="en">
                    <div class="small text-muted">Use the language you approved templates in (en, en_US, hi…).</div>
                </div>
            </div>
        </div>

        @if(isset($providers['meta']))
        <div class="settings-card" id="catalogCard">
            <div class="settings-card-header"><div><h2 class="settings-card-title">In-chat catalog &amp; cart <span class="badge bg-info">Meta Cloud API</span></h2>
                <p class="settings-card-subtitle">Customers browse a restaurant's dishes inside WhatsApp with pictures, a +/&minus; quantity selector and a built-in cart, then send the cart and we take it to checkout. Works when <b>Meta</b> is the selected provider.</p></div></div>
            <div class="settings-card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="wa_catalog" name="whatsapp_catalog_enabled" value="1" @checked($catalogEnabled)>
                    <label class="form-check-label fw-semibold" for="wa_catalog">Show the catalog when a customer picks a restaurant</label>
                </div>
                <ol class="small ps-3 mb-3">
                    <li class="mb-1">In <b>Meta Commerce Manager</b> create a catalog (type "E-commerce") and connect it to your WhatsApp Business account (WhatsApp Manager → Catalog).</li>
                    <li class="mb-1">Copy the <b>Catalog ID</b> into the Meta form above, and make sure your access token includes the <code>catalog_management</code> permission. Save.</li>
                    <li class="mb-1">Press <b>Switch on cart</b> (shows the cart button and catalog icon), then <b>Sync menu now</b>. Menus are then kept up to date automatically every 15 minutes.</li>
                    <li>Dishes with size/variant choices, without a price or without any picture are not listed: those can still be ordered in the app.</li>
                </ol>
                @if($catalogLast)
                    <div class="small mb-2">Last sync {{ \Carbon\Carbon::parse($catalogLast['at'])->diffForHumans() }}: <b>{{ $catalogLast['created'] }}</b> added · <b>{{ $catalogLast['updated'] }}</b> updated · <b>{{ $catalogLast['removed'] }}</b> removed · <span class="{{ $catalogLast['failed'] ? 'text-danger' : '' }}">{{ $catalogLast['failed'] }} failed</span>
                        @if($catalogLast['error'])<div class="text-danger">{{ $catalogLast['error'] }}</div>@endif</div>
                @endif
                <div class="small text-muted mb-3">{{ number_format($catalogItems) }} dishes in the catalog @if($catalogFailed)· <span class="text-danger">{{ $catalogFailed }} need attention</span>@endif</div>
                <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="{{ route('admin.whatsapp.catalog.cart') }}" formmethod="POST">Switch on cart</button>
                <button type="submit" class="btn btn-sm btn-outline-primary" formaction="{{ route('admin.whatsapp.catalog.sync') }}" formmethod="POST">Sync menu now</button>
                <button type="submit" class="btn btn-sm btn-link" formaction="{{ route('admin.whatsapp.catalog.sync', ['force' => 1]) }}" formmethod="POST">Resend everything</button>
            </div>
        </div>
        @endif

        @foreach($providers as $key => $provider)
            <div class="wa-panel" data-provider="{{ $key }}" style="display:none">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="settings-card h-100">
                            <div class="settings-card-header"><div><h2 class="settings-card-title">2. {{ $provider->label() }} connection</h2></div></div>
                            <div class="settings-card-body">
                                @foreach($provider->fields() as $field)
                                    @php $saved = \App\Models\AppSetting::getValue("whatsapp_{$key}_{$field['key']}", ''); @endphp
                                    <div class="mb-3">
                                        <label class="form-label">{{ $field['label'] }} @if(!empty($field['required']))<span class="text-danger">*</span>@endif</label>
                                        <input type="{{ $field['type'] === 'secret' ? 'password' : 'text' }}" name="cfg[{{ $key }}][{{ $field['key'] }}]" autocomplete="off"
                                               class="form-control" placeholder="{{ $field['type'] === 'secret' && $saved ? '•••••••• saved (leave blank to keep)' : ($field['placeholder'] ?? '') }}"
                                               value="{{ $field['type'] === 'secret' ? '' : $saved }}">
                                        @if(!empty($field['help']))<div class="small text-muted">{{ $field['help'] }}</div>@endif
                                    </div>
                                @endforeach

                                <label class="form-label">Callback (webhook) URL</label>
                                <div class="input-group mb-1">
                                    <input type="text" readonly class="form-control" value="{{ $service->webhookUrl($provider) }}" id="wh_{{ $key }}">
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('wh_{{ $key }}').value);this.textContent='Copied'">Copy</button>
                                </div>
                                <div class="small text-muted">{{ $provider->signsWebhooks() ? 'Calls from the provider are verified by signature.' : 'This URL contains your secret key. Keep it private.' }} Paste it in {{ $provider->label() }} as described on the right. <b>Turn WhatsApp on and press Save first</b>, then add the URL in {{ $provider->label() }}.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="settings-card h-100">
                            <div class="settings-card-header"><div><h2 class="settings-card-title">How to set up {{ $provider->label() }}</h2></div></div>
                            <div class="settings-card-body">
                                <ol class="mb-0 ps-3">
                                    @foreach($provider->setupSteps() as $step)<li class="mb-2 small">{{ $step }}</li>@endforeach
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="mt-3"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save WhatsApp settings</button></div>
    </form>

    <form method="POST" action="{{ route('admin.whatsapp.test') }}" class="settings-card mt-4">
        @csrf
        <div class="settings-card-header"><div><h2 class="settings-card-title">3. Send a test message</h2>
            <p class="settings-card-subtitle">Save first. A plain text only reaches numbers that messaged you in the last 24 hours; to reach anyone else, enter an approved template identifier (Meta's built-in <code>hello_world</code> works for a new number).</p></div></div>
        <div class="settings-card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Your WhatsApp number</label><input name="phone" class="form-control" placeholder="9876543210" value="{{ old('phone') }}" required></div>
                <div class="col-md-4"><label class="form-label">Template identifier (optional)</label><input name="template" class="form-control" placeholder="hello_world" value="{{ old('template') }}"></div>
                <div class="col-md-4"><label class="form-label">Text (if no template)</label><input name="text" class="form-control" placeholder="Hello from {{ \App\Models\AppSetting::getValue('app_name', 'Swado') }}" value="{{ old('text') }}"></div>
            </div>
            <button class="btn btn-outline-primary mt-3"><i class="fab fa-whatsapp me-1"></i>Send test message</button>
        </div>
    </form>
</div>

<script>
    var waMain = @json($selected), waChat = @json($chatSelected);
    function waPickChat(key) { waChat = key; waPick(waMain); }
    function waPick(key) {
        waMain = key;
        document.querySelectorAll('.wa-panel').forEach(p => p.style.display = (p.dataset.provider === key || (waChat && p.dataset.provider === waChat)) ? '' : 'none');
        document.querySelectorAll('.wa-provider').forEach(l => l.style.borderColor = l.querySelector('input').value === key ? '#16a34a' : '');
    }
    waPick(@json($selected));
</script>
@endsection
