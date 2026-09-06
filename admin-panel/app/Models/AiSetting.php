<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class AiSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'is_secret', 'description'];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('ai_settings', 600, function () {
            return self::query()->get()->keyBy('key');
        });

        $setting = $settings->get($key);
        if (! $setting) {
            return $default;
        }

        return $setting->decodedValue();
    }

    public static function setValue(
        string $key,
        mixed $value,
        string $type = 'string',
        bool $secret = false,
        ?string $description = null
    ): void {
        self::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $secret && filled($value) ? Crypt::encryptString((string) $value) : self::encodeValue($value, $type),
                'type' => $type,
                'is_secret' => $secret,
                'description' => $description,
            ]
        );

        Cache::forget('ai_settings');
    }

    public function decodedValue(): mixed
    {
        if ($this->is_secret) {
            if (! filled($this->value)) {
                return null;
            }

            try {
                return Crypt::decryptString($this->value);
            } catch (\Throwable) {
                return null;
            }
        }

        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'decimal', 'number' => (float) $this->value,
            'json' => json_decode((string) $this->value, true) ?: [],
            default => $this->value,
        };
    }

    private static function encodeValue(mixed $value, string $type): string
    {
        if ($type === 'json') {
            return json_encode($value ?: [], JSON_THROW_ON_ERROR);
        }

        return $value === null ? '' : (string) $value;
    }
}

