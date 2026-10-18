<?php

namespace App\Services;

use App\Models\AppSetting;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One printed QR code per app, resolved to the right store at scan time.
 *
 * The QR encodes a short URL on this domain (/app, /app/restaurant, ...).
 * The request that follows is sniffed for the device platform and bounced to
 * the Play Store or the App Store, so the printed code never has to change
 * when a store link does -- only the settings behind it.
 */
class AppDownloadService
{
    /** Apps that have their own store listing + QR. */
    public const APPS = [
        'customer' => 'Customer App',
        'restaurant' => 'Restaurant Partner App',
        'driver' => 'Delivery Partner App',
    ];

    public const DEFAULT_APP = 'customer';

    public const PLATFORM_ANDROID = 'android';

    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_OTHER = 'other';

    public const PLATFORM_BOT = 'bot';

    public function apps(): array
    {
        return self::APPS;
    }

    public function normalizeApp(?string $app): string
    {
        $app = strtolower(trim((string) $app));

        return array_key_exists($app, self::APPS) ? $app : self::DEFAULT_APP;
    }

    public function label(string $app): string
    {
        return self::APPS[$this->normalizeApp($app)];
    }

    public function enabled(): bool
    {
        return filter_var(
            AppSetting::getValue('app_download_enabled', '1'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /** 'landing' shows the scan-with-your-phone page, 'redirect' sends desktops to the website. */
    public function desktopMode(): string
    {
        return AppSetting::getValue('app_download_desktop_mode', 'landing') === 'redirect'
            ? 'redirect'
            : 'landing';
    }

    public function fallbackUrl(): string
    {
        $configured = trim((string) AppSetting::getValue('app_download_fallback_url', ''));

        return $configured !== '' ? $configured : rtrim((string) config('app.url'), '/');
    }

    public function qrColor(): string
    {
        $color = trim((string) AppSetting::getValue('app_download_qr_color', '#000000'));

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#000000';
    }

    public function qrLogoEnabled(): bool
    {
        return filter_var(
            AppSetting::getValue('app_download_qr_logo', '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /* ---------------------------------------------------------------- links */

    public function androidPackage(string $app): string
    {
        return self::normalizeAndroidPackage(
            AppSetting::getValue($this->normalizeApp($app) . '_android_package', '')
        );
    }

    public function iosAppId(string $app): string
    {
        return self::normalizeIosAppId(
            AppSetting::getValue($this->normalizeApp($app) . '_ios_app_id', '')
        );
    }

    /**
     * Explicit override wins, then the package name, then the release-update
     * URL already configured under Settings -> Branding, then the legacy
     * customer_play_store_url key.
     */
    public function androidUrl(string $app): ?string
    {
        $app = $this->normalizeApp($app);

        $override = trim((string) AppSetting::getValue($app . '_android_store_url', ''));
        if ($override !== '') {
            return $override;
        }

        $package = $this->androidPackage($app);
        if ($package !== '') {
            return 'https://play.google.com/store/apps/details?id=' . $package;
        }

        $legacy = trim((string) AppSetting::getValue($app . '_android_update_url', ''));
        if ($legacy !== '') {
            return $legacy;
        }

        if ($app === 'customer') {
            $legacyCustomer = trim((string) AppSetting::getValue('customer_play_store_url', ''));
            if ($legacyCustomer !== '') {
                return $legacyCustomer;
            }
        }

        return null;
    }

    public function iosUrl(string $app): ?string
    {
        $app = $this->normalizeApp($app);

        $override = trim((string) AppSetting::getValue($app . '_ios_store_url', ''));
        if ($override !== '') {
            return $override;
        }

        $appId = $this->iosAppId($app);
        if ($appId !== '') {
            return 'https://apps.apple.com/app/id' . $appId;
        }

        $legacy = trim((string) AppSetting::getValue($app . '_ios_update_url', ''));

        return $legacy !== '' ? $legacy : null;
    }

    /** The URL that actually goes into the printed QR code. */
    public function shortUrl(string $app): string
    {
        $app = $this->normalizeApp($app);

        return $app === self::DEFAULT_APP
            ? route('app.download')
            : route('app.download.for', ['app' => $app]);
    }

    public function isConfigured(string $app): bool
    {
        return $this->androidUrl($app) !== null || $this->iosUrl($app) !== null;
    }

    public function summary(string $app): array
    {
        $app = $this->normalizeApp($app);

        return [
            'app' => $app,
            'label' => self::APPS[$app],
            'android_package' => $this->androidPackage($app),
            'ios_app_id' => $this->iosAppId($app),
            'android_url' => $this->androidUrl($app),
            'ios_url' => $this->iosUrl($app),
            'short_url' => $this->shortUrl($app),
            'configured' => $this->isConfigured($app),
        ];
    }

    /* ------------------------------------------------------------ detection */

    /**
     * Link-preview crawlers must never be bounced to a store: WhatsApp,
     * Facebook and Slack all fetch the URL when it is pasted, and a 302 to
     * the Play Store makes the preview card show Google's page, not ours.
     */
    public function detectPlatform(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return self::PLATFORM_OTHER;
        }

        if (preg_match('/(bot|crawler|spider|facebookexternalhit|whatsapp|slackbot|telegrambot|twitterbot|discordbot|linkedinbot|skypeuripreview|embedly|pinterest|preview)/i', $ua)) {
            return self::PLATFORM_BOT;
        }

        if (preg_match('/windows phone/i', $ua)) {
            return self::PLATFORM_OTHER;
        }

        if (preg_match('/(iphone|ipod|ipad)/i', $ua)) {
            return self::PLATFORM_IOS;
        }

        if (preg_match('/android/i', $ua)) {
            return self::PLATFORM_ANDROID;
        }

        return self::PLATFORM_OTHER;
    }

    public function storeUrlForPlatform(string $app, string $platform): ?string
    {
        return match ($platform) {
            self::PLATFORM_ANDROID => $this->androidUrl($app),
            self::PLATFORM_IOS => $this->iosUrl($app),
            default => null,
        };
    }

    /* ------------------------------------------------------------------ qr */

    public function qrSvg(string $text, int $size = 320): string
    {
        $size = max(120, min(2000, $size));
        $color = self::hexToRgb($this->qrColor());

        return Cache::remember(
            'app_download_qr_svg:' . md5($text . '|' . $size . '|' . $this->qrColor()),
            3600,
            function () use ($text, $size, $color) {
                $renderer = new ImageRenderer(
                    new RendererStyle($size, 2, null, null, Fill::uniformColor(new Rgb(255, 255, 255), $color)),
                    new SvgImageBackEnd()
                );

                return (new Writer($renderer))->writeString($text, 'UTF-8', ErrorCorrectionLevel::H());
            }
        );
    }

    /**
     * PNG needs GD. Returns null when the extension is missing so callers can
     * fall back to the SVG instead of throwing on a download click.
     */
    public function qrPng(string $text, int $size = 1024, ?bool $withLogo = null): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $size = max(200, min(3000, $size));
        $withLogo = $withLogo ?? $this->qrLogoEnabled();
        $color = self::hexToRgb($this->qrColor());

        try {
            $renderer = new GDLibRenderer(
                $size,
                2,
                'png',
                9,
                Fill::uniformColor(new Rgb(255, 255, 255), $color)
            );

            $png = (new Writer($renderer))->writeString($text, 'UTF-8', ErrorCorrectionLevel::H());
        } catch (\Throwable $e) {
            return null;
        }

        return $withLogo ? ($this->stampLogo($png, $size) ?? $png) : $png;
    }

    /**
     * Drops the platform logo into the middle of the code. Error correction is
     * fixed at H (~30%), so a badge covering ~20% of the width still scans.
     */
    private function stampLogo(string $png, int $size): ?string
    {
        $logoPath = trim((string) AppSetting::getValue('app_logo', ''));
        if ($logoPath === '') {
            return null;
        }

        $canvas = null;
        $logo = null;

        try {
            $contents = $this->readLogoContents($logoPath);
            if ($contents === null) {
                return null;
            }

            $canvas = @imagecreatefromstring($png);
            $logo = @imagecreatefromstring($contents);
            if ($canvas === false || $logo === false) {
                return null;
            }

            $badge = (int) round($size * 0.22);
            $offset = (int) round(($size - $badge) / 2);

            // White plate behind the logo so the modules underneath do not
            // bleed through a transparent PNG.
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, $offset, $offset, $offset + $badge, $offset + $badge, $white);

            $inner = (int) round($badge * 0.82);
            $innerOffset = (int) round(($size - $inner) / 2);

            imagealphablending($canvas, true);
            imagecopyresampled(
                $canvas,
                $logo,
                $innerOffset,
                $innerOffset,
                0,
                0,
                $inner,
                $inner,
                imagesx($logo),
                imagesy($logo)
            );

            ob_start();
            imagepng($canvas, null, 9);
            $out = (string) ob_get_clean();

            return $out !== '' ? $out : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            if ($canvas instanceof \GdImage) {
                imagedestroy($canvas);
            }
            if ($logo instanceof \GdImage) {
                imagedestroy($logo);
            }
        }
    }

    private function readLogoContents(string $logoPath): ?string
    {
        if (Str::startsWith($logoPath, ['http://', 'https://'])) {
            $contents = @file_get_contents($logoPath);

            return is_string($contents) && $contents !== '' ? $contents : null;
        }

        $candidates = [
            public_path('storage/' . ltrim($logoPath, '/')),
            storage_path('app/public/' . ltrim($logoPath, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $contents = @file_get_contents($candidate);
                if (is_string($contents) && $contents !== '') {
                    return $contents;
                }
            }
        }

        $url = MediaStorage::url($logoPath);
        if ($url === null) {
            return null;
        }

        $contents = @file_get_contents($url);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }

    /* ---------------------------------------------------------- normalizing */

    /**
     * Admins paste the whole Play Store URL as often as the package name, so
     * accept either and keep the package.
     */
    public static function normalizeAndroidPackage(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[?&]id=([^&#\s]+)/i', $value, $matches)) {
            $value = urldecode($matches[1]);
        }

        $value = trim($value);

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)+$/', $value) ? $value : '';
    }

    /** Accepts 1234567890, id1234567890 or a full apps.apple.com URL. */
    public static function normalizeIosAppId(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#/id(\d{5,})#i', $value, $matches)) {
            return $matches[1];
        }

        $digits = preg_replace('/\D+/', '', $value);

        return is_string($digits) && strlen($digits) >= 5 ? $digits : '';
    }

    private static function hexToRgb(string $hex): Rgb
    {
        $hex = ltrim($hex, '#');

        return new Rgb(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2))
        );
    }
}
