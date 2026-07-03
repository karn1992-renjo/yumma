@extends('layouts.admin')

@section('title', 'App Branding')
@section('header', 'App Branding')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>App Branding</h1>
            <p>Update the platform branding settings.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Branding Settings</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.branding.post') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">App Name</label>
                        <input type="text" name="app_name" class="form-control" value="{{ $settings['app_name'] ?? 'FoodFlow' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">App Logo</label>
                        @if(isset($settings['app_logo']))
                            <div class="mb-2">
                                <img src="{{ Storage::disk('public')->url($settings['app_logo']) }}" height="50" alt="Logo">
                            </div>
                        @endif
                        <input type="file" name="app_logo" class="form-control" accept="image/*">
                        <small class="text-muted">Recommended size: 200x200px, PNG format</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Header Branding Type</label>
                        <select name="header_branding_type" class="form-select">
                            <option value="text" {{ (isset($settings['header_branding_type']) && $settings['header_branding_type'] === 'text') ? 'selected' : '' }}>Text only</option>
                            <option value="logo" {{ (isset($settings['header_branding_type']) && $settings['header_branding_type'] === 'logo') ? 'selected' : '' }}>Logo only</option>
                            <option value="logo_text" {{ (isset($settings['header_branding_type']) && $settings['header_branding_type'] === 'logo_text') ? 'selected' : '' }}>Logo + Text</option>
                        </select>
                        <small class="text-muted">Choose how the header should display on the storefront.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">App Icon</label>
                        @if(isset($settings['app_icon']))
                            <div class="mb-2">
                                <img src="{{ Storage::disk('public')->url($settings['app_icon']) }}" height="40" alt="Icon">
                            </div>
                        @endif
                        <input type="file" name="app_icon" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Favicon</label>
                        @if(!empty($settings['app_favicon']))
                            <div class="mb-2">
                                <img src="{{ Storage::disk('public')->url($settings['app_favicon']) }}" height="32" width="32" style="object-fit: contain;" alt="Favicon">
                            </div>
                        @endif
                        <input type="file" name="app_favicon" class="form-control" accept="image/x-icon,image/png,image/jpeg,image/webp,image/svg+xml">
                        <small class="text-muted">Used in browser tabs. Recommended size: 32x32px or 48x48px.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Frontend Background Image</label>
                        @if(!empty($settings['frontend_background_image']))
                            <div class="mb-2">
                                <img src="{{ Storage::disk('public')->url($settings['frontend_background_image']) }}" height="96" class="rounded-3 border" style="width: 180px; object-fit: cover;" alt="Frontend background">
                            </div>
                        @endif
                        <input type="file" name="frontend_background_image" class="form-control" accept="image/png,image/jpeg,image/jpg,image/webp">
                        <small class="text-muted">Used as the soft animated storefront background and hero image. Recommended: 1920x1080 JPG/WebP.</small>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Primary Color</label>
                            <input type="color" name="primary_color" class="form-control form-control-color" value="{{ $settings['primary_color'] ?? '#8B5CF6' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Secondary Color</label>
                            <input type="color" name="secondary_color" class="form-control form-control-color" value="{{ $settings['secondary_color'] ?? '#A78BFA' }}">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="fw-bold mb-3">Restaurant App Colors</h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Primary</label>
                                        <input type="color" name="restaurant_primary_color" class="form-control form-control-color" value="{{ $settings['restaurant_primary_color'] ?? ($settings['primary_color'] ?? '#0A9443') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Secondary</label>
                                        <input type="color" name="restaurant_secondary_color" class="form-control form-control-color" value="{{ $settings['restaurant_secondary_color'] ?? ($settings['secondary_color'] ?? '#0C7038') }}">
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">Used by the restaurant owner mobile app.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="fw-bold mb-3">Driver App Colors</h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Primary</label>
                                        <input type="color" name="driver_primary_color" class="form-control form-control-color" value="{{ $settings['driver_primary_color'] ?? ($settings['primary_color'] ?? '#0A9443') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Secondary</label>
                                        <input type="color" name="driver_secondary_color" class="form-control form-control-color" value="{{ $settings['driver_secondary_color'] ?? ($settings['secondary_color'] ?? '#0C7038') }}">
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">Used by the driver mobile app.</small>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded-3 p-3 mb-3">
                        <h6 class="fw-bold mb-3">Customer App Links</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Play Store URL</label>
                                <input type="url" name="customer_play_store_url" class="form-control" value="{{ $settings['customer_play_store_url'] ?? '' }}" placeholder="https://play.google.com/store/apps/details?id=...">
                                <small class="text-muted">Used after successful feedback rating.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Deep Link Scheme</label>
                                <input type="text" name="customer_deeplink_scheme" class="form-control" value="{{ $settings['customer_deeplink_scheme'] ?? 'foodflow' }}" placeholder="foodflow">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Web Deep Link Base URL</label>
                                <input type="url" name="customer_deeplink_base_url" class="form-control" value="{{ $settings['customer_deeplink_base_url'] ?? '' }}" placeholder="https://foodflow.in">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Order Deep Link Template</label>
                                <input type="text" name="customer_order_deeplink_template" class="form-control" value="{{ $settings['customer_order_deeplink_template'] ?? 'foodflow://orders/{order_id}' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Restaurant Deep Link Template</label>
                                <input type="text" name="customer_restaurant_deeplink_template" class="form-control" value="{{ $settings['customer_restaurant_deeplink_template'] ?? 'foodflow://restaurants/{restaurant_id}' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Wallet Deep Link Template</label>
                                <input type="text" name="customer_wallet_deeplink_template" class="form-control" value="{{ $settings['customer_wallet_deeplink_template'] ?? 'foodflow://wallet' }}">
                            </div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">Onboarding Content</h6>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Intro Title</label>
                        <input type="text" name="onboarding_intro_title" class="form-control" value="{{ $settings['onboarding_intro_title'] ?? ($settings['app_name'] ?? 'FoodFlow') }}">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Intro Subtitle</label>
                        <textarea name="onboarding_intro_subtitle" class="form-control" rows="2">{{ $settings['onboarding_intro_subtitle'] ?? 'Food, groceries and everyday cravings delivered fast.' }}</textarea>
                    </div>

                    @for ($i = 1; $i <= 3; $i++)
                        <div class="border rounded-3 p-3 mb-4">
                            <h6 class="fw-bold mb-3">Slide {{ $i }}</h6>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Title</label>
                                <input type="text" name="onboarding_slide_{{ $i }}_title" class="form-control" value="{{ $settings["onboarding_slide_{$i}_title"] ?? '' }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="onboarding_slide_{{ $i }}_description" class="form-control" rows="2">{{ $settings["onboarding_slide_{$i}_description"] ?? '' }}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-semibold">Image</label>
                                @if(!empty($settings["onboarding_slide_{$i}_image"]))
                                    <div class="mb-2">
                                        <img src="{{ Storage::disk('public')->url($settings["onboarding_slide_{$i}_image"]) }}" height="72" alt="Slide {{ $i }} image">
                                    </div>
                                @endif
                                <input type="file" name="onboarding_slide_{{ $i }}_image" class="form-control" accept="image/*">
                            </div>
                        </div>
                    @endfor
                    <button type="submit" class="btn btn-primary">Save Branding</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
