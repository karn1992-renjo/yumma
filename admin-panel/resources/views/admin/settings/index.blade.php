@extends('layouts.admin')

@section('title', 'Settings')
@section('header', 'System Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-sliders-h"></i> Platform Control</span>
            <h1>System Settings</h1>
            <p>Manage platform identity, contact details, media storage, payment routing, map services, and operational setup from one clean settings area.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-link-grid">
        <a href="{{ route('admin.settings.payment') }}" class="settings-link-card">
            <span class="settings-link-icon"><i class="fas fa-credit-card"></i></span>
            <span>
                <h3>Payment Settings</h3>
                <p>Gateway, currency, COD, and payout provider controls.</p>
            </span>
        </a>
        <a href="{{ route('admin.settings.rewards') }}" class="settings-link-card">
            <span class="settings-link-icon"><i class="fas fa-gift"></i></span>
            <span>
                <h3>Promotion & Reward Settings</h3>
                <p>Reward points value, minimum redeem, and scratch reward rules.</p>
            </span>
        </a>
        <a href="{{ route('admin.payout-settings.edit') }}" class="settings-link-card">
            <span class="settings-link-icon"><i class="fas fa-money-check-alt"></i></span>
            <span>
                <h3>Payout Gateway</h3>
                <p>Vendor payout schedule, credentials, and checks.</p>
            </span>
        </a>
        <a href="{{ route('admin.settings.branding') }}" class="settings-link-card">
            <span class="settings-link-icon"><i class="fas fa-palette"></i></span>
            <span>
                <h3>App Branding</h3>
                <p>Logo, colors, favicon, onboarding, and deep links.</p>
            </span>
        </a>
        <a href="{{ route('admin.settings.map') }}" class="settings-link-card">
            <span class="settings-link-icon"><i class="fas fa-map-marked-alt"></i></span>
            <span>
                <h3>Map & Location</h3>
                <p>Maps key, ETA settings, and delivery radius.</p>
            </span>
        </a>
        <a href="{{ route('admin.home-sections.index') }}" class="settings-link-card">
            <span class="settings-link-icon"><i class="fas fa-layer-group"></i></span>
            <span>
                <h3>Home Sections</h3>
                <p>Dynamic customer app and storefront sections.</p>
            </span>
        </a>
    </div>

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">General Platform Settings</h2>
                <p class="settings-card-subtitle">These values appear across admin, apps, notifications, and public support surfaces.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.index">

                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="{{ $settings['site_name'] ?? 'FoodFlow' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" value="{{ $settings['contact_email'] ?? 'admin@foodflow.com' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="contact_phone" class="form-control" value="{{ $settings['contact_phone'] ?? '+91 9876543210' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Currency Decimal Places</label>
                        <input type="number" name="currency_decimals" class="form-control" min="2" max="5" value="{{ max(2, min(5, (int) ($settings['currency_decimals'] ?? 2))) }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Site Description</label>
                        <textarea name="site_description" class="form-control" rows="3">{{ $settings['site_description'] ?? 'Food Delivery Platform' }}</textarea>
                    </div>
                </div>

                <div class="settings-section-title mt-4">Media Storage</div>
                <div class="settings-grid" id="media-storage-settings">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Storage Driver</label>
                        <select name="media_storage_driver" id="media-storage-driver" class="form-select">
                            <option value="local" @selected(($settings['media_storage_driver'] ?? 'local') === 'local')>Local public storage</option>
                            <option value="s3" @selected(($settings['media_storage_driver'] ?? 'local') === 's3')>Amazon S3 / compatible object storage</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-4 s3-field">
                        <label class="form-label">Access Key ID</label>
                        <input name="media_s3_key" class="form-control" value="{{ $settings['media_s3_key'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4 s3-field">
                        <label class="form-label">Secret Access Key</label>
                        <input type="password" name="media_s3_secret" class="form-control" placeholder="Leave blank to keep the saved secret">
                    </div>
                    <div class="settings-field settings-span-4 s3-field">
                        <label class="form-label">AWS Region</label>
                        <input name="media_s3_region" class="form-control" value="{{ $settings['media_s3_region'] ?? 'ap-south-1' }}">
                    </div>
                    <div class="settings-field settings-span-8 s3-field">
                        <label class="form-label">Bucket</label>
                        <input name="media_s3_bucket" class="form-control" value="{{ $settings['media_s3_bucket'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-6 s3-field">
                        <label class="form-label">Public/CDN URL (optional)</label>
                        <input type="url" name="media_s3_url" class="form-control" value="{{ $settings['media_s3_url'] ?? '' }}" placeholder="https://cdn.example.com">
                    </div>
                    <div class="settings-field settings-span-6 s3-field">
                        <label class="form-label">Custom Endpoint (optional)</label>
                        <input type="url" name="media_s3_endpoint" class="form-control" value="{{ $settings['media_s3_endpoint'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-12 s3-field">
                        <input type="hidden" name="media_s3_path_style" value="0">
                        <div class="form-check">
                            <input type="checkbox" name="media_s3_path_style" value="1" class="form-check-input" id="media-s3-path-style" @checked(($settings['media_s3_path_style'] ?? '0') == '1')>
                            <label class="form-check-label" for="media-s3-path-style">Use path-style endpoint</label>
                        </div>
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save General Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const driverSelect = document.getElementById('media-storage-driver');
    const fields = document.querySelectorAll('.s3-field');

    function toggleS3Fields() {
        const show = driverSelect && driverSelect.value === 's3';
        fields.forEach((field) => {
            field.style.display = show ? '' : 'none';
        });
    }

    if (driverSelect) {
        driverSelect.addEventListener('change', toggleS3Fields);
        toggleS3Fields();
    }
});
</script>
@endsection
