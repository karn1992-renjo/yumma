@extends('layouts.admin')

@section('title', 'App Download QR')
@section('header', 'App Download QR')

@section('content')
@include('admin.settings._style')

<style>
    .qr-app-card {
        border: 1px solid rgba(148, 163, 184, .28);
        border-radius: 18px;
        padding: 20px;
        background: #fff;
    }
    .qr-app-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .qr-app-head h3 {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 800;
        color: #0f172a;
    }
    .qr-preview {
        display: flex;
        gap: 18px;
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 16px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px dashed rgba(148, 163, 184, .45);
    }
    .qr-preview-image {
        flex: none;
        width: 168px;
        height: 168px;
        padding: 10px;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, .3);
        border-radius: 14px;
    }
    .qr-preview-image img { width: 100%; height: 100%; display: block; }
    .qr-preview-body { flex: 1 1 260px; min-width: 240px; }
    .qr-url {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 12px;
    }
    .qr-url input {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .82rem;
    }
    .qr-route {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        font-size: .85rem;
        color: #334155;
        border-top: 1px solid rgba(148, 163, 184, .25);
    }
    .qr-route:first-of-type { border-top: 0; }
    .qr-route i { width: 18px; text-align: center; margin-top: 2px; }
    .qr-route code {
        font-size: .78rem;
        color: #475569;
        word-break: break-all;
    }
    .qr-flow {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .78rem;
        line-height: 1.65;
        color: #334155;
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 14px;
        padding: 16px 18px;
        margin: 0;
        overflow-x: auto;
    }
</style>

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-qrcode"></i> Install Links</span>
            <h1>App Download QR</h1>
            <p>One QR code per app that sends Android phones to the Play Store and iPhones to the App Store. Print it once &mdash; change the store links here whenever you like.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    @if(session('success'))
        <div class="alert alert-success rounded-4 border-0">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger rounded-4 border-0">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 border-0">
            <strong>There were some problems with your input.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">How it works</h2>
                <p class="settings-card-subtitle">The QR never contains a store link, so the same printed code keeps working after a store URL changes.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <pre class="qr-flow">  QR code on the flyer
           |
           v
  {{ $apps[\App\Services\AppDownloadService::DEFAULT_APP]['short_url'] }}
           |
   detect device from the request
     /           |          \
 Android        iPhone     Desktop
   |              |           |
 Play Store   App Store   {{ $desktopMode === 'redirect' ? 'website' : 'download page with the QR' }}</pre>
        </div>
    </div>

    <form action="{{ route('admin.settings.app-download.update') }}" method="POST">
        @csrf

        <div class="settings-card mt-4">
            <div class="settings-card-header">
                <div>
                    <h2 class="settings-card-title">Redirect Behaviour</h2>
                    <p class="settings-card-subtitle">Applies to every app QR below.</p>
                </div>
            </div>
            <div class="settings-card-body">
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Download Links Active</label>
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="form-check form-switch">
                                <input type="hidden" name="app_download_enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="appDownloadEnabled"
                                       name="app_download_enabled" value="1" @checked(old('app_download_enabled', $enabled ? '1' : '0') === '1')>
                                <label class="form-check-label fw-semibold" for="appDownloadEnabled">Serve /app links</label>
                            </div>
                            <div class="form-text mt-2">Turn off to return 404 on every download link and QR.</div>
                        </div>
                    </div>

                    <div class="settings-field settings-span-4">
                        <label class="form-label" for="appDownloadDesktopMode">Desktop &amp; Other Devices</label>
                        <select class="form-select" id="appDownloadDesktopMode" name="app_download_desktop_mode">
                            <option value="landing" @selected(old('app_download_desktop_mode', $desktopMode) === 'landing')>Show the download page (with QR to scan)</option>
                            <option value="redirect" @selected(old('app_download_desktop_mode', $desktopMode) === 'redirect')>Redirect straight to the website</option>
                        </select>
                        <div class="form-text">Windows, Mac and Linux visitors. Link-preview bots always get the page.</div>
                    </div>

                    <div class="settings-field settings-span-4">
                        <label class="form-label" for="appDownloadFallbackUrl">Website URL</label>
                        <input type="url" class="form-control" id="appDownloadFallbackUrl" name="app_download_fallback_url"
                               value="{{ old('app_download_fallback_url', $settings['app_download_fallback_url'] ?? '') }}"
                               placeholder="{{ rtrim(config('app.url'), '/') }}">
                        <div class="form-text">Leave blank to use {{ rtrim(config('app.url'), '/') }}.</div>
                    </div>

                    <div class="settings-field settings-span-4">
                        <label class="form-label" for="appDownloadQrColor">QR Colour</label>
                        <input type="color" class="form-control form-control-color" id="appDownloadQrColor"
                               name="app_download_qr_color" value="{{ old('app_download_qr_color', $qrColor) }}">
                        <div class="form-text">Keep it dark. Light codes fail to scan in poor light.</div>
                    </div>

                    <div class="settings-field settings-span-8">
                        <label class="form-label">Logo In The Centre</label>
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="form-check form-switch">
                                <input type="hidden" name="app_download_qr_logo" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="appDownloadQrLogo"
                                       name="app_download_qr_logo" value="1" @checked(old('app_download_qr_logo', $qrLogoEnabled ? '1' : '0') === '1')
                                       @disabled(! $pngAvailable)>
                                <label class="form-check-label fw-semibold" for="appDownloadQrLogo">Stamp the app logo into the PNG download</label>
                            </div>
                            <div class="form-text mt-2">
                                @if($pngAvailable)
                                    Uses the logo from Settings &rarr; Branding. Always test-scan a printed proof before a large run.
                                @else
                                    Needs the PHP GD extension, which is not enabled on this server. PNG downloads fall back to SVG.
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @foreach($apps as $key => $app)
            <div class="settings-card mt-4">
                <div class="settings-card-header">
                    <div>
                        <h2 class="settings-card-title">{{ $app['label'] }}</h2>
                        <p class="settings-card-subtitle">Enter the package name and App Store id. The store URLs are built for you.</p>
                    </div>
                    @if($app['configured'])
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill px-3 py-2">Ready</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-2">Not configured</span>
                    @endif
                </div>
                <div class="settings-card-body">
                    <div class="settings-grid">
                        <div class="settings-field settings-span-6">
                            <label class="form-label" for="{{ $key }}AndroidPackage">
                                <i class="fab fa-android text-success me-1"></i> Android Package Name
                            </label>
                            <input type="text" class="form-control" id="{{ $key }}AndroidPackage"
                                   name="{{ $key }}_android_package"
                                   value="{{ old($key.'_android_package', $app['android_package']) }}"
                                   placeholder="com.example.app">
                            <div class="form-text">
                                @if($app['android_package'] !== '')
                                    Play Store link: <code>https://play.google.com/store/apps/details?id={{ $app['android_package'] }}</code>
                                @else
                                    You can paste the full Play Store URL here &mdash; the package name is extracted.
                                @endif
                            </div>
                        </div>

                        <div class="settings-field settings-span-6">
                            <label class="form-label" for="{{ $key }}IosAppId">
                                <i class="fab fa-apple me-1"></i> Apple App Store ID
                            </label>
                            <input type="text" class="form-control" id="{{ $key }}IosAppId"
                                   name="{{ $key }}_ios_app_id"
                                   value="{{ old($key.'_ios_app_id', $app['ios_app_id']) }}"
                                   placeholder="1234567890">
                            <div class="form-text">
                                @if($app['ios_app_id'] !== '')
                                    App Store link: <code>https://apps.apple.com/app/id{{ $app['ios_app_id'] }}</code>
                                @else
                                    The numeric id from App Store Connect. A full apps.apple.com URL works too.
                                @endif
                            </div>
                        </div>

                        <div class="settings-field settings-span-6">
                            <label class="form-label" for="{{ $key }}AndroidStoreUrl">Android URL Override</label>
                            <input type="url" class="form-control" id="{{ $key }}AndroidStoreUrl"
                                   name="{{ $key }}_android_store_url"
                                   value="{{ old($key.'_android_store_url', $settings[$key.'_android_store_url'] ?? '') }}"
                                   placeholder="Optional">
                            <div class="form-text">Only for a non-Play store (Huawei AppGallery, a direct APK, ...).</div>
                        </div>

                        <div class="settings-field settings-span-6">
                            <label class="form-label" for="{{ $key }}IosStoreUrl">iOS URL Override</label>
                            <input type="url" class="form-control" id="{{ $key }}IosStoreUrl"
                                   name="{{ $key }}_ios_store_url"
                                   value="{{ old($key.'_ios_store_url', $settings[$key.'_ios_store_url'] ?? '') }}"
                                   placeholder="Optional">
                            <div class="form-text">Use for a TestFlight link or a region-specific listing.</div>
                        </div>

                        <div class="settings-field settings-span-12">
                            <div class="qr-preview">
                                <div class="qr-preview-image">
                                    <img src="{{ route('app.download.qr', ['app' => $key, 'size' => 320, 'v' => $cacheBuster]) }}"
                                         alt="{{ $app['label'] }} download QR code">
                                </div>
                                <div class="qr-preview-body">
                                    <div class="qr-url">
                                        <input type="text" class="form-control" readonly value="{{ $app['short_url'] }}"
                                               id="shortUrl-{{ $key }}">
                                        <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0"
                                                data-copy-target="shortUrl-{{ $key }}">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>

                                    <div class="qr-route">
                                        <i class="fab fa-android text-success"></i>
                                        <div>
                                            <strong>Android</strong><br>
                                            @if($app['android_url'])
                                                <code>{{ $app['android_url'] }}</code>
                                            @else
                                                <span class="text-danger">Not set &mdash; Android scans see the download page.</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="qr-route">
                                        <i class="fab fa-apple"></i>
                                        <div>
                                            <strong>iPhone / iPad</strong><br>
                                            @if($app['ios_url'])
                                                <code>{{ $app['ios_url'] }}</code>
                                            @else
                                                <span class="text-danger">Not set &mdash; iOS scans see the download page.</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="qr-route">
                                        <i class="fas fa-desktop text-secondary"></i>
                                        <div>
                                            <strong>Desktop</strong><br>
                                            <code>{{ $desktopMode === 'redirect' ? $fallbackUrl : 'Download page with this QR' }}</code>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('app.download.qr-png', ['app' => $key, 'size' => 1024, 'download' => 1, 'v' => $cacheBuster]) }}">
                                            <i class="fas fa-download"></i> PNG (1024px)
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('app.download.qr-png', ['app' => $key, 'size' => 2048, 'download' => 1, 'v' => $cacheBuster]) }}">
                                            <i class="fas fa-print"></i> PNG (2048px, print)
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="{{ route('app.download.qr', ['app' => $key, 'size' => 1024, 'download' => 1, 'v' => $cacheBuster]) }}">
                                            <i class="fas fa-vector-square"></i> SVG (vector)
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ $app['short_url'] }}" target="_blank" rel="noopener">
                                            <i class="fas fa-external-link-alt"></i> Test link
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="settings-card mt-4">
            <div class="settings-card-body">
                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save App Download Settings</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.getElementById(button.dataset.copyTarget);
            if (!field) { return; }

            field.select();
            field.setSelectionRange(0, 99999);

            var done = function () {
                var original = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied';
                setTimeout(function () { button.innerHTML = original; }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(field.value).then(done).catch(function () {
                    document.execCommand('copy');
                    done();
                });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    });
</script>
@endsection
