@extends('layouts.admin')

@section('title', 'Send Push Notification')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Send Push Notification</h1>
            <p class="text-muted mb-0">Broadcast a live push message to all users or selected app roles.</p>
        </div>
        <a href="{{ route('admin.push-notifications.index') }}" class="btn btn-outline-secondary rounded-3">
            <i class="fas fa-arrow-left me-2"></i> Back to History
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Notification Composer</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.push-notifications.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notification Title</label>
                        <input
                            type="text"
                            name="title"
                            class="form-control @error('title') is-invalid @enderror"
                            value="{{ old('title') }}"
                            maxlength="120"
                            placeholder="Example: New weekend offers are live"
                            required
                        >
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notification Message</label>
                        <textarea
                            name="body"
                            rows="4"
                            class="form-control @error('body') is-invalid @enderror"
                            maxlength="500"
                            placeholder="Write the push body users will see on their device."
                            required
                        >{{ old('body') }}</textarea>
                        @error('body')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Audience Type</label>
                            <select name="audience_type" id="audienceType" class="form-select @error('audience_type') is-invalid @enderror">
                                <option value="all" {{ old('audience_type', 'all') === 'all' ? 'selected' : '' }}>All App Users</option>
                                <option value="roles" {{ old('audience_type') === 'roles' ? 'selected' : '' }}>Role-wise</option>
                            </select>
                            @error('audience_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Deep Link</label>
                            <input
                                type="text"
                                name="deep_link"
                                class="form-control @error('deep_link') is-invalid @enderror"
                                value="{{ old('deep_link') }}"
                                placeholder="/orders, /wallet, foodflow://offers"
                            >
                            @error('deep_link')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-semibold">Notification Image URL</label>
                        <input
                            type="url"
                            name="image_url"
                            class="form-control @error('image_url') is-invalid @enderror"
                            value="{{ old('image_url') }}"
                            placeholder="https://example.com/promo-banner.jpg"
                        >
                        <small class="text-muted">Optional rich notification image. The URL is sent as `image_url` in the push payload.</small>
                        @error('image_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="roleSelector" class="mt-3" style="{{ old('audience_type') === 'roles' ? '' : 'display:none;' }}">
                        <label class="form-label fw-semibold">Select Roles</label>
                        <div class="row g-2">
                            @foreach($roleLabels as $role => $label)
                                <div class="col-md-6">
                                    <label class="border rounded-3 p-3 d-flex align-items-center gap-2 w-100">
                                        <input
                                            type="checkbox"
                                            name="audience_roles[]"
                                            value="{{ $role }}"
                                            class="form-check-input mt-0"
                                            {{ in_array($role, old('audience_roles', []), true) ? 'checked' : '' }}
                                        >
                                        <span>{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('audience_roles')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Custom Payload Data</h6>
                            <p class="text-muted mb-0 small">Optional key-value data for in-app navigation or action handling.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-3" id="addPayloadRow">
                            <i class="fas fa-plus me-1"></i> Add Field
                        </button>
                    </div>

                    <div id="payloadRows">
                        @php
                            $oldKeys = old('data_key', ['']);
                            $oldValues = old('data_value', ['']);
                        @endphp
                        @foreach($oldKeys as $index => $oldKey)
                            <div class="row g-2 payload-row mb-2">
                                <div class="col-md-5">
                                    <input type="text" name="data_key[]" class="form-control" value="{{ $oldKey }}" placeholder="key">
                                </div>
                                <div class="col-md-5">
                                    <input type="text" name="data_value[]" class="form-control" value="{{ $oldValues[$index] ?? '' }}" placeholder="value">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger w-100 remove-payload-row">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-3">
                            <i class="fas fa-paper-plane me-2"></i> Send Notification
                        </button>
                        <a href="{{ route('admin.push-notifications.index') }}" class="btn btn-outline-secondary rounded-3">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Best Practices</h5>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <div class="fw-semibold">Keep it short</div>
                    <div class="text-muted small">Use a strong headline and one clear action in the body.</div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold">Use role targeting</div>
                    <div class="text-muted small">Send restaurant operations alerts only to restaurant users and delivery updates only to drivers.</div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold">Attach a deep link</div>
                    <div class="text-muted small">Route users straight into orders, offers, bookings, wallet, or profile sections.</div>
                </div>
                <div class="alert alert-info rounded-3 mb-0">
                    Notifications are delivered only to active users with a registered FCM token on their device.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const audienceType = document.getElementById('audienceType');
    const roleSelector = document.getElementById('roleSelector');
    const payloadRows = document.getElementById('payloadRows');
    const addPayloadRow = document.getElementById('addPayloadRow');

    function toggleRoleSelector() {
        roleSelector.style.display = audienceType.value === 'roles' ? '' : 'none';
    }

    audienceType.addEventListener('change', toggleRoleSelector);
    toggleRoleSelector();

    addPayloadRow.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'row g-2 payload-row mb-2';
        row.innerHTML = `
            <div class="col-md-5">
                <input type="text" name="data_key[]" class="form-control" placeholder="key">
            </div>
            <div class="col-md-5">
                <input type="text" name="data_value[]" class="form-control" placeholder="value">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100 remove-payload-row">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        payloadRows.appendChild(row);
    });

    payloadRows.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-payload-row');
        if (!button) {
            return;
        }

        const rows = payloadRows.querySelectorAll('.payload-row');
        if (rows.length === 1) {
            rows[0].querySelectorAll('input').forEach((input) => input.value = '');
            return;
        }

        button.closest('.payload-row')?.remove();
    });
});
</script>
@endpush
