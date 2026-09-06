@extends('layouts.admin')

@section('title', 'Edit Notification Template')
@section('header', 'Edit Notification Template')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>{{ $template->label }}</h1>
            <p class="text-muted small mb-0">{{ $template->key }}</p>
        </div>
        <a href="{{ route('admin.notification-templates.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Templates
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0">Message</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.notification-templates.update', $template) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @if($template->title !== null)
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" maxlength="150"
                                   value="{{ old('title', $template->title) }}">
                            @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Body</label>
                        <textarea name="body" id="templateBody" class="form-control" rows="4" maxlength="2000" required>{{ old('body', $template->body) }}</textarea>
                        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                               @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label" for="isActive">
                            Active &mdash; when off, the app falls back to its built-in default message for this event.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Template</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Placeholders</h5>
            </div>
            <div class="p-4">
                @if(!empty($template->placeholders))
                    <p class="text-muted small">Use these inside the title/body. They're substituted with real values when the message is sent.</p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($template->placeholders as $placeholder)
                            <code class="px-2 py-1 bg-light rounded">{{ '{' . '{' . $placeholder . '}' . '}' }}</code>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">This template has no placeholders.</p>
                @endif
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0">Live Preview</h5>
            </div>
            <div class="p-4">
                <div class="text-muted small mb-2">Placeholders shown with sample values.</div>
                <div class="border rounded p-3 bg-light">
                    @if($template->title !== null)
                        <div class="fw-bold mb-1" id="previewTitle"></div>
                    @endif
                    <div id="previewBody" class="small"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const sampleValues = {
        order_number: '10234',
        restaurant_name: 'Tasty Bites',
        status: 'Confirmed',
        otp: '4821',
        customer_name: 'Riya Sharma',
        total: '349.00',
        app_name: 'Swado',
        status_message: 'Your order is 5 minutes away.',
        delivery_otp: '7391',
    };

    function interpolate(text) {
        return text.replace(/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/g, function (match, key) {
            return sampleValues[key] !== undefined ? sampleValues[key] : match;
        });
    }

    function renderPreview() {
        const titleField = document.querySelector('input[name="title"]');
        const bodyField = document.getElementById('templateBody');
        const previewTitle = document.getElementById('previewTitle');
        const previewBody = document.getElementById('previewBody');

        if (previewTitle && titleField) {
            previewTitle.textContent = interpolate(titleField.value || '');
        }
        if (previewBody && bodyField) {
            previewBody.textContent = interpolate(bodyField.value || '');
        }
    }

    document.querySelectorAll('input[name="title"], #templateBody').forEach(function (el) {
        el.addEventListener('input', renderPreview);
    });
    renderPreview();
})();
</script>
@endsection
