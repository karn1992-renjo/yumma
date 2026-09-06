@extends('layouts.admin')

@section('title', 'Edit Email Template')
@section('header', 'Edit Email Template')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>{{ $template->label }}</h1>
            <p class="text-muted small mb-0">{{ $template->key }}</p>
        </div>
        <a href="{{ route('admin.email-templates.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="table-card">
            <div class="card-header"><h5 class="mb-0">Email content</h5></div>
            <div class="p-4">
                <form action="{{ route('admin.email-templates.update', $template) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="title" id="tplSubject" class="form-control" maxlength="255"
                               value="{{ old('title', $template->title) }}" required>
                        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <label class="form-label mb-0">Body (HTML)</label>
                        <div>
                            <input type="file" id="tplImageInput" accept="image/*" class="d-none">
                            <button type="button" id="tplImageBtn" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-image me-1"></i>Insert image
                            </button>
                        </div>
                    </div>
                    <textarea name="body" id="tplBody" class="form-control font-monospace" rows="18" spellcheck="false" required style="font-size:12.5px;">{{ old('body', $template->body) }}</textarea>
                    <div class="text-muted small mt-1" id="tplImageStatus"></div>
                    @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

                    <div class="form-check my-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tplActive"
                               @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label" for="tplActive">
                            Active &mdash; when off, the built-in default email is used instead.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Template</button>
                </form>
            </div>
        </div>

        <div class="table-card mt-4">
            <div class="card-header"><h5 class="mb-0">Send test email</h5></div>
            <div class="p-4">
                <form action="{{ route('admin.email-templates.send-test', $template) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-8">
                        <label class="form-label">Send a sample (with placeholder data) to</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required
                               value="{{ auth()->user()->email ?? '' }}">
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-outline-primary w-100">Send test</button>
                    </div>
                    <div class="col-12 text-muted small">
                        Uses the currently saved template. If this fails, check
                        <a href="{{ route('admin.settings.communication') }}">mail (SMTP) settings</a>.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="table-card mb-4">
            <div class="card-header"><h5 class="mb-0">Placeholders</h5></div>
            <div class="p-4">
                @if(!empty($template->placeholders))
                    <p class="text-muted small">Click to copy. Substituted with real order values at send time.</p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($template->placeholders as $placeholder)
                            <code class="tpl-ph px-2 py-1 bg-light rounded" role="button">{{ '{' . '{' . $placeholder . '}' . '}' }}</code>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">No placeholders.</p>
                @endif
            </div>
        </div>

        <div class="table-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Live preview</h5>
                <span class="text-muted small">sample data</span>
            </div>
            <div class="p-3">
                <div class="text-muted small mb-1"><strong>Subject:</strong> <span id="previewSubject"></span></div>
                <iframe id="previewFrame" title="Email preview"
                        style="width:100%;height:640px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const sampleValues = @json($sample);
    const uploadUrl = @json(route('admin.email-templates.upload-image'));
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const subjectEl = document.getElementById('tplSubject');
    const bodyEl = document.getElementById('tplBody');
    const previewSubject = document.getElementById('previewSubject');
    const previewFrame = document.getElementById('previewFrame');
    const imageBtn = document.getElementById('tplImageBtn');
    const imageInput = document.getElementById('tplImageInput');
    const imageStatus = document.getElementById('tplImageStatus');

    function interpolate(text) {
        return String(text || '').replace(/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/g, function (match, key) {
            return sampleValues[key] !== undefined ? sampleValues[key] : match;
        });
    }

    let raf;
    function renderPreview() {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
            previewSubject.textContent = interpolate(subjectEl.value);
            previewFrame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"></head><body style="margin:0;">'
                + interpolate(bodyEl.value) + '</body></html>';
        });
    }

    [subjectEl, bodyEl].forEach(function (el) { el.addEventListener('input', renderPreview); });
    renderPreview();

    // Insert placeholder / image tag at the caret of the body textarea.
    function insertAtCaret(text) {
        const start = bodyEl.selectionStart ?? bodyEl.value.length;
        const end = bodyEl.selectionEnd ?? bodyEl.value.length;
        bodyEl.value = bodyEl.value.slice(0, start) + text + bodyEl.value.slice(end);
        const pos = start + text.length;
        bodyEl.setSelectionRange(pos, pos);
        bodyEl.focus();
        renderPreview();
    }

    document.querySelectorAll('.tpl-ph').forEach(function (chip) {
        chip.addEventListener('click', function () { insertAtCaret(chip.textContent); });
    });

    imageBtn.addEventListener('click', function () { imageInput.click(); });
    imageInput.addEventListener('change', function () {
        const file = imageInput.files && imageInput.files[0];
        if (!file) return;
        imageStatus.textContent = 'Uploading…';
        const data = new FormData();
        data.append('image', file);
        fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: data })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j.url) throw new Error(res.j.message || 'Upload failed');
                insertAtCaret('<img src="' + res.j.url + '" alt="" style="max-width:100%;height:auto;display:block;">');
                imageStatus.textContent = 'Inserted: ' + res.j.url;
            })
            .catch(function (err) { imageStatus.textContent = err.message; })
            .finally(function () { imageInput.value = ''; });
    });
})();
</script>
@endsection
