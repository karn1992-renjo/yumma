@extends('layouts.admin')

@section('title', 'WhatsApp Broadcasts')
@section('header', 'WhatsApp Broadcasts')

@section('content')
@include('admin.settings._style')
@php
    $approved = $templates->filter->usable();
@endphp
<div class="settings-shell">
    <div class="settings-hero">
        <div><span class="settings-eyebrow"><i class="fab fa-whatsapp"></i> Messaging</span><h1>Broadcasts</h1><p>Write a template once, get it approved, then send it to a group of customers. Messages go out in small batches every minute.</p></div>
    </div>
    @include('admin.whatsapp._nav')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @unless($enabled)<div class="alert alert-warning">WhatsApp is not enabled yet. Turn it on in Settings first.</div>@endunless

    {{-- 1. Variables --}}
    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-1">1. Variables you can use</h5>
        <p class="text-muted small mb-3">In the template text, write <code>@{{1}}</code>, <code>@{{2}}</code>… where a value should go. For each one you choose which customer detail fills it in. Every customer gets <b>their own</b> value. You can also use a fixed text, such as a coupon code.</p>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Variable</th><th>What it is</th><th>Example</th></tr></thead>
            <tbody>
            @foreach($variableCatalog as $token => $info)
                <tr><td><code>{{ $token }}</code></td><td>{{ $info['label'] }} <span class="text-muted small">&middot; {{ $info['description'] }}</span></td><td>{{ $info['example'] }}</td></tr>
            @endforeach
            <tr><td><span class="badge bg-secondary">Fixed text</span></td><td>Any text you type, the same for everyone <span class="text-muted small">&middot; e.g. a coupon code <code>WEEKEND50</code> or <code>Saturday</code></span></td><td>WEEKEND50</td></tr>
            </tbody></table></div>
        <p class="small text-muted mt-2 mb-0">You can also put a token inside fixed text, for example <code>Hi {name}</code>. Customers we have no details for get a polite default (for example "there").</p>
    </div></div>

    {{-- 2. Create template --}}
    <form method="POST" action="{{ route('admin.whatsapp.templates.store') }}" class="card mb-4" id="tplForm">@csrf
        <div class="card-body">
            <h5 class="mb-3">2. Create a broadcast template</h5>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Template name</label><input name="name" class="form-control" value="{{ old('name') }}" required maxlength="80" placeholder="Weekend offer"></div>
                <div class="col-md-2"><label class="form-label">Language</label><input name="language" class="form-control" value="{{ old('language', \App\Models\AppSetting::getValue('whatsapp_template_language', 'en')) }}" maxlength="10"></div>
                <div class="col-12">
                    <label class="form-label">Message text</label>
                    <textarea name="body" id="tplBody" rows="5" class="form-control" required maxlength="1100" placeholder="Hi @{{1}}, this weekend only, enjoy a special offer from us! Use code @{{2}} at checkout to get a discount on your next order. Order from your favourite restaurants in just a few taps in the app, and thank you for being a valued customer.">{{ old('body') }}</textarea>
                    <div class="small text-muted mt-1">Rules WhatsApp checks: variables numbered in order, text can't start or end with a variable, and enough fixed words around each variable (about 10 or more per variable).</div>
                </div>
            </div>
            <div id="tplVars" class="mt-3"></div>
            <div id="tplChecks" class="mt-3"></div>
            <div class="mt-3"><label class="form-label small text-muted mb-1">Preview with sample values</label><div id="tplPreview" class="border rounded-3 p-3 small" style="background:#f6f7f9;white-space:pre-wrap;min-height:48px"></div></div>
            <button class="btn btn-success mt-3" id="tplSave"><i class="fas fa-floppy-disk me-1"></i>Save template</button>
        </div>
    </form>

    {{-- 3. Send --}}
    <form method="POST" action="{{ route('admin.whatsapp.broadcasts.store') }}" enctype="multipart/form-data" class="card mb-4">@csrf
        <div class="card-body">
            <h5 class="mb-3">3. New broadcast</h5>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Campaign name</label><input name="title" class="form-control" value="{{ old('title') }}" required placeholder="Weekend offer"></div>
                <div class="col-md-8"><label class="form-label">Template</label>
                    <select name="broadcast_template_id" class="form-select" id="bcTemplate">
                        <option value="">Use a template ID I type below</option>
                        @foreach($approved as $t)<option value="{{ $t->id }}" @selected(old('broadcast_template_id') == $t->id)>{{ $t->name }} (approved)</option>@endforeach
                    </select>
                    @if($approved->isEmpty())<div class="small text-muted mt-1">No approved templates yet. Create one above, get it approved and it will appear here.</div>@endif
                </div>
                <div class="col-md-6 bc-manual"><label class="form-label">Template identifier</label><input name="template" class="form-control" value="{{ old('template') }}" placeholder="the exact approved template name / ID"></div>
                <div class="col-md-6 bc-manual"><label class="form-label">Variables (one per line, in order)</label><textarea name="params" rows="3" class="form-control" placeholder="{name}&#10;WEEKEND50">{{ old('params') }}</textarea><div class="small text-muted">Any variable above, e.g. <code>{name}</code>, or fixed text.</div></div>
                <div class="col-md-6"><label class="form-label">Header image URL <span class="text-muted fw-normal">(only if the template was approved with an image header)</span></label><input name="header_image_file" type="file" accept="image/png,image/jpeg" class="form-control">
                    <input name="header_image" type="url" class="form-control mt-2" value="{{ old('header_image') }}" placeholder="…or paste an image link (https://…)">
                    <div class="small text-muted">Upload a JPG/PNG (max 5 MB). Without it, a template with an image header fails with "Format mismatch, expected IMAGE".</div></div>
                <div class="col-12"><label class="form-label">Limit to roles <span class="text-muted fw-normal">(optional, works with every audience; leave all unticked for no limit)</span></label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($roleOptions as $roleKey => $roleLabel)
                            <label class="form-check"><input type="checkbox" class="form-check-input" name="roles[]" value="{{ $roleKey }}" @checked(in_array($roleKey, (array) old('roles', [])))> <span class="form-check-label">{{ $roleLabel }}</span></label>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-1">With roles ticked, a person only receives the message if their account has one of those roles. People who never registered (chat contacts without an account) are skipped.</div></div>
                <div class="col-md-6"><label class="form-label">Audience</label>
                    <select name="audience" class="form-select">@foreach($audiences as $key => $label)<option value="{{ $key }}" @selected(old('audience') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Days (for recent customers)</label><input name="audience_days" type="number" min="1" max="365" class="form-control" value="{{ old('audience_days', 30) }}"></div>
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required>
                <label class="form-check-label" for="consent">I confirm these customers agreed to receive WhatsApp promotions. Anyone who replied STOP is skipped automatically.</label>
            </div>
            <button class="btn btn-success mt-3" onclick="return confirm('Start this broadcast now?')"><i class="fas fa-paper-plane me-1"></i>Start broadcast</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Campaign</th><th>Audience</th><th>Progress</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($broadcasts as $b)
                    <tr>
                        <td><strong>{{ $b->title }}</strong><div class="small text-muted">{{ $b->template }} · {{ $b->created_at->format('d M H:i') }}</div></td>
                        <td class="small">{{ $audiences[$b->audience] ?? $b->audience }}</td>
                        <td class="small">{{ $b->sent }} sent · {{ $b->failed }} failed · {{ $b->total }} total</td>
                        <td><span class="badge bg-{{ ['done' => 'success', 'sending' => 'info', 'cancelled' => 'secondary', 'draft' => 'secondary'][$b->status] ?? 'secondary' }}">{{ $b->status }}</span></td>
                        <td class="text-end">@if($b->status === 'sending')<form method="POST" action="{{ route('admin.whatsapp.broadcasts.cancel', $b) }}" onsubmit="return confirm('Stop sending the remaining messages?')">@csrf<button class="btn btn-sm btn-outline-danger">Cancel</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No broadcasts yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $broadcasts->links() }}</div>
    </div>
</div>

<script>
const CATALOG = @json($variableCatalog);
const MIN_RATIO = {{ $minWordsPerVariable }};
const OLD_TYPES = @json(old('var_type', []));
const OLD_TEXT = @json(old('var_text', []));
</script>
@verbatim
<script>
const body = document.getElementById('tplBody');
const holder = document.getElementById('tplVars');
const checks = document.getElementById('tplChecks');
const preview = document.getElementById('tplPreview');
const saveBtn = document.getElementById('tplSave');
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function found(text) { return [...text.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1]); }

function analyse(text) {
    const t = text.trim(), vars = found(t), n = vars.length, errors = [], warnings = [];
    if (!t) return { errors: ['Write the message text.'], warnings, n, ratio: null };
    if (n && JSON.stringify(vars) !== JSON.stringify(Array.from({ length: n }, (_, i) => i + 1))) errors.push('Variables must be numbered in order: {{1}}, {{2}}, {{3}}… each used once, in the order they appear.');
    if (/^\{\{\s*\d+\s*\}\}/.test(t)) errors.push('The text cannot start with a variable. Begin with a few words, for example "Hi {{1}}".');
    if (/\{\{\s*\d+\s*\}\}[\s.!?,:;…)'"]*$/.test(t)) errors.push('The text cannot end with a variable. Finish with a sentence.');
    if (/\{\{\s*\d+\s*\}\}\s*\{\{\s*\d+\s*\}\}/.test(t)) errors.push('Two variables cannot sit next to each other. Put words between them.');
    if (t.length > 1024) errors.push('The text is longer than 1024 characters.');
    let ratio = null;
    if (n) {
        const words = (t.replace(/\{\{\s*\d+\s*\}\}/g, ' ').match(/[\p{L}\p{N}']+/gu) || []).length;
        ratio = Math.round(words / n * 10) / 10;
        if (ratio < MIN_RATIO) errors.push(`Too many variables for the length: ${n} variable(s) but only ${words} other words. Add more fixed text or remove variables (aim for at least 10 words per variable).`);
        else if (ratio < 10) warnings.push('Close to the limit. Adding a sentence or two makes approval more likely.');
        if (n > 5) warnings.push('Many variables. Three or fewer is the safest.');
    }
    return { errors, warnings, n, ratio };
}

function rows(n) {
    const keep = {};
    holder.querySelectorAll('[data-n]').forEach(r => { keep[r.dataset.n] = { t: r.querySelector('select').value, x: r.querySelector('input').value }; });
    holder.innerHTML = n ? '<label class="form-label">What each variable should be</label>' : '';
    for (let i = 1; i <= n; i++) {
        const k = keep[i] || { t: OLD_TYPES[i] ?? '{name}', x: OLD_TEXT[i] ?? '' };
        const opts = Object.entries(CATALOG).map(([tok, c]) => `<option value="${esc(tok)}" ${k.t === tok ? 'selected' : ''}>${esc(c.label)} ${esc(tok)}</option>`).join('');
        holder.insertAdjacentHTML('beforeend', `<div class="row g-2 mb-2 align-items-center" data-n="${i}">
            <div class="col-auto"><code>{{${i}}}</code></div>
            <div class="col-md-5"><select name="var_type[${i}]" class="form-select form-select-sm">${opts}<option value="fixed" ${k.t === 'fixed' ? 'selected' : ''}>Fixed text…</option></select></div>
            <div class="col-md-5"><input name="var_text[${i}]" class="form-control form-control-sm" placeholder="Fixed text, e.g. WEEKEND50" value="${esc(k.x)}" style="${k.t === 'fixed' ? '' : 'display:none'}"></div></div>`);
    }
    holder.querySelectorAll('select').forEach(s => s.addEventListener('change', () => { s.closest('[data-n]').querySelector('input').style.display = s.value === 'fixed' ? '' : 'none'; render(false); }));
    holder.querySelectorAll('input').forEach(i => i.addEventListener('input', () => render(false)));
}

function render(rebuild = true) {
    const a = analyse(body.value);
    if (rebuild) rows(a.n);
    checks.innerHTML = [
        ...a.errors.map(e => `<div class="text-danger small">✖ ${esc(e)}</div>`),
        ...a.warnings.map(w => `<div class="text-warning small">⚠ ${esc(w)}</div>`),
        (!a.errors.length && body.value.trim()) ? `<div class="text-success small">✔ Looks good${a.ratio ? ` · ${a.ratio} words per variable` : ''}. Ready to save and submit for approval.</div>` : '',
    ].join('');
    let text = body.value;
    holder.querySelectorAll('[data-n]').forEach(r => {
        const sel = r.querySelector('select').value, fixed = r.querySelector('input').value;
        let sample = sel === 'fixed' ? (fixed || '…') : CATALOG[sel].example;
        Object.entries(CATALOG).forEach(([tok, c]) => { sample = sample.split(tok).join(c.example); });
        text = text.replace(new RegExp(`\\{\\{\\s*${r.dataset.n}\\s*\\}\\}`, 'g'), sample);
    });
    preview.textContent = text || '—';
    saveBtn.disabled = a.errors.length > 0;
}
body.addEventListener('input', () => render(true));
render(true);

const sel = document.getElementById('bcTemplate');
function toggleManual() { document.querySelectorAll('.bc-manual').forEach(el => el.style.display = sel.value ? 'none' : ''); }
sel.addEventListener('change', toggleManual); toggleManual();
</script>
@endverbatim
@endsection
