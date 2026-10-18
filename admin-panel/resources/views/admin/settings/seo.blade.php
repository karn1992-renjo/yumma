@extends('layouts.admin')

@section('title', 'SEO Settings')
@section('header', 'SEO Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-search"></i> Search & Social</span>
            <h1>SEO Settings</h1>
            <p>Control how the public site appears in Google search results and when shared on social media, plus the sitemap and robots.txt that search engines read.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Meta Tags &amp; Social Sharing</h2>
                <p class="settings-card-subtitle">Applied as the default on the homepage and every public page that doesn't set its own (restaurant pages already generate their own title, description and image from the restaurant's own data).</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.seo">

                <div class="settings-section-title">Meta Tags</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Title Suffix</label>
                        <input type="text" name="seo_meta_title_suffix" class="form-control" placeholder="e.g. | Swado" value="{{ $settings['seo_meta_title_suffix'] ?? '' }}">
                        <small class="text-muted">Appended to page titles on social share cards (Open Graph / Twitter). Page &lt;title&gt; tags are set per-page.</small>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Default Meta Keywords</label>
                        <input type="text" name="seo_meta_keywords" class="form-control" placeholder="food delivery, online food order, restaurants near me" value="{{ $settings['seo_meta_keywords'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Default Meta Description</label>
                        <textarea name="seo_meta_description" class="form-control" rows="2" maxlength="320" placeholder="Order food online from the best restaurants near you. Fast delivery, live order tracking, and easy payments.">{{ $settings['seo_meta_description'] ?? '' }}</textarea>
                        <small class="text-muted">Used for pages that don't generate their own description. Recommended: 150-160 characters.</small>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Default Social Share Image (Open Graph)</label>
                        <input type="file" name="seo_og_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                        @if(! empty($settings['seo_og_image']))
                            <small class="text-muted d-block mt-1">
                                Current:
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings['seo_og_image']) }}" target="_blank">view image</a>
                            </small>
                        @endif
                        <small class="text-muted">Shown when a page is shared on WhatsApp, Facebook, Twitter/X, etc. Recommended: 1200x630px.</small>
                    </div>
                </div>

                <div class="settings-section-title mt-4">Search Console &amp; Analytics</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Google Search Console Verification Code</label>
                        <input type="text" name="seo_google_site_verification" class="form-control" placeholder="e.g. AbCdEf123..." value="{{ $settings['seo_google_site_verification'] ?? '' }}">
                        <small class="text-muted">From the HTML tag verification method's "content" value — not the full tag.</small>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Google Analytics 4 Measurement ID</label>
                        <input type="text" name="seo_ga4_measurement_id" class="form-control" placeholder="G-XXXXXXXXXX" value="{{ $settings['seo_ga4_measurement_id'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Google Tag Manager Container ID</label>
                        <input type="text" name="seo_gtm_container_id" class="form-control" placeholder="GTM-XXXXXXX" value="{{ $settings['seo_gtm_container_id'] ?? '' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">robots.txt</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <label class="form-label">robots.txt Content</label>
                        <textarea name="seo_robots_txt" class="form-control" rows="8" style="font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px;">{{ $robotsContent }}</textarea>
                        <small class="text-muted">Served directly at <code>/robots.txt</code>. Saving this form overwrites that file.</small>
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save SEO Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="settings-card mt-4">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">XML Sitemap</h2>
                <p class="settings-card-subtitle">Lists every public page, restaurant, category and product page for search engines to crawl.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <p class="mb-3">
                <strong>Sitemap URL:</strong> <a href="{{ url('/sitemap.xml') }}" target="_blank">{{ url('/sitemap.xml') }}</a><br>
                <strong>Last generated:</strong>
                {{ $sitemapGeneratedAt ? \Illuminate\Support\Carbon::createFromTimestamp($sitemapGeneratedAt)->diffForHumans() : 'Never' }}
            </p>
            <form action="{{ route('admin.settings.seo.sitemap') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-sync me-2"></i>Regenerate Sitemap Now
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
