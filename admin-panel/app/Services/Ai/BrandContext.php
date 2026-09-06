<?php

namespace App\Services\Ai;

use App\Models\AppSetting;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One place to read the app's branding out of AppSetting for the AI image
 * pipeline: name, logo, primary/secondary colour, header branding style.
 *
 * Mirrors the keys App\Http\Controllers\Api\AuthController::branding() exposes
 * to the mobile apps, with the same fallbacks, so the generated notification
 * artwork stays visually consistent with the apps. Every field degrades
 * safely -- a half-configured branding section must never break notification
 * sending (the dev DB, for instance, only has app_name set).
 */
class BrandContext
{
    public const DEFAULT_PRIMARY = '#FF5A1F';

    public const DEFAULT_SECONDARY = '#2B2A33';

    public function __construct(
        public readonly string $appName,
        public readonly ?string $logoPath,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $headerBrandingType,
    ) {}

    public static function resolve(): self
    {
        return new self(
            appName: trim((string) AppSetting::getValue('app_name', 'Swado')) ?: 'Swado',
            logoPath: (function () {
                $raw = AppSetting::getValue('app_logo');

                return filled($raw) ? (string) $raw : null;
            })(),
            primaryColor: self::normalizeHex(AppSetting::getValue('primary_color')) ?? self::DEFAULT_PRIMARY,
            secondaryColor: self::normalizeHex(AppSetting::getValue('secondary_color')) ?? self::DEFAULT_SECONDARY,
            headerBrandingType: (string) (AppSetting::getValue('header_branding_type', 'text') ?: 'text'),
        );
    }

    public function hasLogo(): bool
    {
        return $this->logoPath !== null;
    }

    /**
     * The stored logo as raw image bytes, disk-aware (local AND S3), or null
     * when there is no logo or it cannot be read -- callers then composite
     * nothing rather than failing.
     */
    public function logoBytes(): ?string
    {
        if ($this->logoPath === null) {
            return null;
        }

        $raw = $this->logoPath;

        MediaStorage::configure();
        $disk = Storage::disk('public');

        if (Str::startsWith($raw, ['http://', 'https://'])) {
            if (Str::contains($raw, '/storage/')) {
                $raw = Str::after($raw, '/storage/');
            } else {
                try {
                    $bytes = @file_get_contents($raw, false, stream_context_create(['http' => ['timeout' => 4]]));

                    return $bytes ?: null;
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        $raw = ltrim($raw, '/');
        $raw = Str::startsWith($raw, 'storage/') ? Str::after($raw, 'storage/') : $raw;

        foreach ([$raw, 'branding/' . basename($raw)] as $candidate) {
            try {
                if ($disk->exists($candidate)) {
                    return $disk->get($candidate);
                }
            } catch (\Throwable) {
                // try next candidate
            }
        }

        $local = public_path($raw);

        return is_file($local) ? (file_get_contents($local) ?: null) : null;
    }

    /**
     * Short hash of the brand inputs that affect a generated image. Part of
     * the artwork cache key so re-branding invalidates cached campaign images.
     */
    public function version(): string
    {
        return substr(md5(implode('|', [
            $this->appName,
            (string) $this->logoPath,
            $this->primaryColor,
            $this->secondaryColor,
        ])), 0, 10);
    }

    public static function normalizeHex(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^#?[0-9a-fA-F]{6}$/', $value) ? '#' . strtoupper(ltrim($value, '#')) : null;
    }
}
