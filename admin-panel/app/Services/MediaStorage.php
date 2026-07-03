<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaStorage
{
    public static function configure(): void
    {
        if (AppSetting::getValue('media_storage_driver', 'local') !== 's3') {
            Config::set('filesystems.disks.public', [
                'driver' => 'local',
                'root' => public_path('storage'),
                'url' => rtrim((string) Config::get('app.url'), '/') . '/storage',
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ]);
            return;
        }

        Config::set('filesystems.disks.public', [
            'driver' => 's3',
            'key' => AppSetting::getValue('media_s3_key', Config::get('filesystems.disks.s3.key')),
            'secret' => AppSetting::getValue('media_s3_secret', Config::get('filesystems.disks.s3.secret')),
            'region' => AppSetting::getValue('media_s3_region', Config::get('filesystems.disks.s3.region')),
            'bucket' => AppSetting::getValue('media_s3_bucket', Config::get('filesystems.disks.s3.bucket')),
            'url' => AppSetting::getValue('media_s3_url', Config::get('filesystems.disks.s3.url')) ?: null,
            'endpoint' => AppSetting::getValue('media_s3_endpoint', Config::get('filesystems.disks.s3.endpoint')) ?: null,
            'use_path_style_endpoint' => filter_var(
                AppSetting::getValue('media_s3_path_style', Config::get('filesystems.disks.s3.use_path_style_endpoint', false)),
                FILTER_VALIDATE_BOOLEAN
            ),
            'visibility' => 'public',
            'options' => [
                'CacheControl' => 'public, max-age=31536000, immutable',
            ],
            'throw' => false,
        ]);
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        self::configure();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $versionedName = now()->format('YmdHis').'-'.Str::uuid().'.'.$extension;
        $path = trim($directory, '/').'/'.$versionedName;

        $storedPath = Storage::disk('public')->putFileAs(
            trim($directory, '/'),
            $file,
            $versionedName,
            [
                'visibility' => 'public',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]
        );

        if (! $storedPath) {
            throw new \RuntimeException('Unable to store uploaded media.');
        }

        return $storedPath;
    }

    public static function delete(?string $path): void
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return;
        }

        self::configure();
        Storage::disk('public')->delete(ltrim($path, '/'));
    }
}
