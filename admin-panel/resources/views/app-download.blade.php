@php
    use App\Services\MediaStorage;

    $settings = $settings ?? [];
    $siteName = trim((string) ($settings['site_name'] ?? ($settings['app_name'] ?? ''))) ?: config('app.name', 'App');
    $primary = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($settings['primary_color'] ?? '')) ? $settings['primary_color'] : '#F15A24';
    $secondary = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($settings['secondary_color'] ?? '')) ? $settings['secondary_color'] : '#111827';
    $logoUrl = MediaStorage::url($settings['app_logo'] ?? null);
    $tagline = trim((string) ($settings['onboarding_intro_subtitle'] ?? '')) ?: 'Order from your favourite places, delivered fast.';
    $isMobile = in_array($platform, ['android', 'ios'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Get the {{ $siteName }} {{ $appLabel }}</title>
    <meta name="description" content="Download the {{ $siteName }} {{ $appLabel }} for Android and iPhone.">
    <meta property="og:title" content="Get the {{ $siteName }} {{ $appLabel }}">
    <meta property="og:description" content="{{ $tagline }}">
    @if($logoUrl)
        <meta property="og:image" content="{{ $logoUrl }}">
        <link rel="icon" href="{{ $logoUrl }}">
    @endif
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
    <style>
        :root {
            --brand: {{ $primary }};
            --ink: {{ $secondary }};
            --primary: var(--brand);
            --app-ink: #111827;
            --app-muted: #6B7280;
            --app-border: #E5E7EB;
            --app-bg: #FAFAFA;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Nunito Sans', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(900px 480px at 12% -10%, color-mix(in srgb, var(--brand) 22%, transparent), transparent 60%),
                radial-gradient(760px 420px at 100% 110%, color-mix(in srgb, var(--brand) 14%, transparent), transparent 62%),
                #f6f7fb;
        }
        h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 26px;
            box-shadow: 0 30px 70px rgba(15, 23, 42, .12);
            padding: 34px 28px 28px;
            text-align: center;
        }
        .logo {
            width: 74px;
            height: 74px;
            object-fit: contain;
            border-radius: 20px;
            margin-bottom: 16px;
        }
        .logo-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 74px;
            height: 74px;
            border-radius: 20px;
            margin-bottom: 16px;
            background: var(--brand);
            color: #fff;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: .02em;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.6rem;
            line-height: 1.25;
            font-weight: 800;
        }
        .lede {
            margin: 0 auto 22px;
            max-width: 40ch;
            color: #4b5563;
            font-size: .98rem;
            line-height: 1.6;
        }
        .qr-wrap {
            display: inline-block;
            padding: 14px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .1);
            border-radius: 20px;
            margin-bottom: 18px;
        }
        .qr-wrap img { display: block; width: 210px; height: 210px; }
        .qr-hint {
            margin: 0 0 22px;
            font-size: .85rem;
            font-weight: 600;
            color: #6b7280;
        }
        .stores {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }
        .store {
            flex: 1 1 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 56px;
            padding: 12px 18px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: .95rem;
            background: var(--ink);
            color: #fff;
            border: 1px solid var(--ink);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .store:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(15, 23, 42, .18); }
        .store svg { width: 22px; height: 22px; flex: none; }
        .store small { display: block; font-size: .7rem; font-weight: 600; opacity: .75; }
        .store span { text-align: left; line-height: 1.2; }
        .website {
            display: inline-block;
            margin-top: 20px;
            color: var(--brand);
            font-weight: 700;
            font-size: .9rem;
            text-decoration: none;
        }
        .website:hover { text-decoration: underline; }
        .notice {
            margin: 0 0 18px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: .85rem;
            line-height: 1.5;
        }
        @media (max-width: 420px) {
            .card { padding: 26px 18px 22px; border-radius: 22px; }
            h1 { font-size: 1.35rem; }
            .store { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <main class="card">
        @if($logoUrl)
            <img class="logo" src="{{ $logoUrl }}" alt="{{ $siteName }}">
        @else
            <div class="logo-fallback">{{ strtoupper(mb_substr($siteName, 0, 1)) }}</div>
        @endif

        <h1>Get the {{ $siteName }} {{ $appLabel }}</h1>
        <p class="lede">{{ $tagline }}</p>

        @if($isMobile && ! $androidUrl && ! $iosUrl)
            <p class="notice">This app is not published to the stores yet. Please check back shortly.</p>
        @elseif($isMobile && $platform === 'android' && ! $androidUrl)
            <p class="notice">The Android build is not on the Play Store yet.</p>
        @elseif($isMobile && $platform === 'ios' && ! $iosUrl)
            <p class="notice">The iPhone build is not on the App Store yet.</p>
        @endif

        @unless($isMobile)
            <div class="qr-wrap">
                <img src="{{ $qrUrl }}" alt="QR code to download the {{ $siteName }} {{ $appLabel }}" width="210" height="210">
            </div>
            <p class="qr-hint">Scan this code with your phone camera to install the app.</p>
        @endunless

        <div class="stores">
            @if($androidUrl)
                <a class="store" href="{{ $androidUrl }}" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.6 20.6 12 12.2l2.6 2.6-8.3 6.7a1.5 1.5 0 0 1-2.7-.9Zm-.6-1.3V4.7c0-.2 0-.4.1-.6l8.1 8.1-8.2 7.1Zm14-4.4L14.4 12l2.6-2.9 3.5 2a1.5 1.5 0 0 1 0 2.6l-3.5 2.2ZM3.7 3.4a1.5 1.5 0 0 1 2.2-.6l8.4 4.8-2.4 2.7-8.2-6.9Z"/></svg>
                    <span><small>Get it on</small>Google Play</span>
                </a>
            @endif
            @if($iosUrl)
                <a class="store" href="{{ $iosUrl }}" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.4 12.7c0-2.2 1.8-3.3 1.9-3.4-1-1.5-2.6-1.7-3.2-1.7-1.4-.1-2.7.8-3.4.8-.7 0-1.8-.8-2.9-.8-1.5 0-2.9.9-3.7 2.2-1.6 2.7-.4 6.8 1.1 9 .8 1.1 1.6 2.3 2.8 2.2 1.1 0 1.5-.7 2.9-.7 1.3 0 1.7.7 2.9.7 1.2 0 2-1.1 2.7-2.2.8-1.2 1.2-2.4 1.2-2.5 0 0-2.3-.9-2.3-3.6ZM14.2 5.9c.6-.7 1-1.8.9-2.9-.9 0-2 .6-2.6 1.4-.6.6-1.1 1.7-.9 2.7 1 .1 2-.5 2.6-1.2Z"/></svg>
                    <span><small>Download on the</small>App Store</span>
                </a>
            @endif
        </div>

        @if($websiteUrl)
            <a class="website" href="{{ $websiteUrl }}">Continue to {{ $siteName }} on the web &rarr;</a>
        @endif
    </main>

    <script>
        // iPadOS 13+ reports a desktop Safari user agent, so the server sees a
        // Mac and serves this page. Touch support is the only reliable tell.
        (function () {
            var iosUrl = @json($iosUrl);
            if (!iosUrl) { return; }

            var ua = navigator.userAgent || '';
            var isIpadOnDesktopUa = /Macintosh/.test(ua)
                && typeof navigator.maxTouchPoints === 'number'
                && navigator.maxTouchPoints > 1;

            if (isIpadOnDesktopUa) {
                window.location.replace(iosUrl);
            }
        })();
    </script>
</body>
</html>
