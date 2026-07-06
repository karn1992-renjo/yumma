@extends('layouts.admin')

@section('title', 'Settings')
@section('header', 'System Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>General Settings</h1>
            <p>Configure the core platform settings.</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="{{ route('admin.settings.payment') }}" class="card h-100 text-decoration-none text-reset shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Payment Settings</h5>
                <p class="card-text text-muted">Configure payment and payout gateway providers for customer, restaurant, and driver apps.</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.payout-settings.edit') }}" class="card h-100 text-decoration-none text-reset shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Payout Gateway</h5>
                <p class="card-text text-muted">Manage active payout gateway, schedule, and credentials from one place.</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.settings.branding') }}" class="card h-100 text-decoration-none text-reset shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Branding</h5>
                <p class="card-text text-muted">Upload application logo and icon, and set primary colors.</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.settings.map') }}" class="card h-100 text-decoration-none text-reset shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Map Settings</h5>
                <p class="card-text text-muted">Edit map provider keys and location settings used by the apps.</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.home-sections.index') }}" class="card h-100 text-decoration-none text-reset shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Home Sections</h5>
                <p class="card-text text-muted">Manage homepage section order, curated restaurant rails, cuisine grids, and banner carousels.</p>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">General Settings</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin.settings.index">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="{{ $settings['site_name'] ?? 'FoodFlow' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Site Description</label>
                        <textarea name="site_description" class="form-control" rows="3">{{ $settings['site_description'] ?? 'Food Delivery Platform' }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" value="{{ $settings['contact_email'] ?? 'admin@foodflow.com' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contact Phone</label>
                        <input type="text" name="contact_phone" class="form-control" value="{{ $settings['contact_phone'] ?? '+91 9876543210' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Currency Decimals</label>
                        <input type="number" name="currency_decimals" class="form-control" min="2" max="5" value="{{ max(2, min(5, (int) ($settings['currency_decimals'] ?? 2))) }}">
                        <div class="form-text">Number of decimal places to display after the currency symbol. Allowed range: 2 to 5.</div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold" id="media-storage-settings" style="scroll-margin-top: 100px;">Media Storage</h5>
                    <p class="text-muted">All new uploads and generated media URLs use the selected storage provider.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Storage Provider</label>
                        <select name="media_storage_driver" id="media-storage-driver" class="form-select">
                            <option value="local" @selected(($settings['media_storage_driver'] ?? 'local') === 'local')>Local server storage</option>
                            <option value="s3" @selected(($settings['media_storage_driver'] ?? 'local') === 's3')>Amazon S3</option>
                        </select>
                    </div>
                    <div id="s3-storage-settings" class="row g-3">
                        <div class="col-md-6"><label class="form-label">Access Key ID</label><input name="media_s3_key" class="form-control" value="{{ $settings['media_s3_key'] ?? '' }}"></div>
                        <div class="col-md-6"><label class="form-label">Secret Access Key</label><input type="password" name="media_s3_secret" class="form-control" placeholder="Leave blank to keep the saved secret"></div>
                        <div class="col-md-4"><label class="form-label">AWS Region</label><input name="media_s3_region" class="form-control" value="{{ $settings['media_s3_region'] ?? 'ap-south-1' }}"></div>
                        <div class="col-md-8"><label class="form-label">Bucket</label><input name="media_s3_bucket" class="form-control" value="{{ $settings['media_s3_bucket'] ?? '' }}"></div>
                        <div class="col-md-6"><label class="form-label">Public/CDN URL (optional)</label><input type="url" name="media_s3_url" class="form-control" value="{{ $settings['media_s3_url'] ?? '' }}" placeholder="https://cdn.example.com"></div>
                        <div class="col-md-6"><label class="form-label">Custom endpoint (optional)</label><input type="url" name="media_s3_endpoint" class="form-control" value="{{ $settings['media_s3_endpoint'] ?? '' }}"></div>
                        <div class="col-12"><input type="hidden" name="media_s3_path_style" value="0"><div class="form-check"><input type="checkbox" name="media_s3_path_style" value="1" class="form-check-input" id="media-s3-path-style" @checked(($settings['media_s3_path_style'] ?? '0') == '1')><label class="form-check-label" for="media-s3-path-style">Use path-style endpoint</label></div></div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save General Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const driver = document.getElementById('media-storage-driver');
    const fields = document.getElementById('s3-storage-settings');
    const refresh = () => fields.style.display = driver.value === 's3' ? '' : 'none';
    driver.addEventListener('change', refresh);
    refresh();
});
</script>
@endsection
