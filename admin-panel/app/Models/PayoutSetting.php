<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AppSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class PayoutSetting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'gateway',
        'is_active',
        'auto_generate_enabled',
        'auto_process_enabled',
        'schedule_frequency',
        'schedule_day',
        'minimum_payout_amount',
        'credentials',
        'webhook_config',
        'options',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_generate_enabled' => 'boolean',
        'auto_process_enabled' => 'boolean',
        'minimum_payout_amount' => 'float',
        'options' => 'array',
    ];

    public static function activeGateway(): string
    {
        return static::where('is_active', true)->value('gateway')
            ?: AppSetting::getValue('payout_gateway_provider', 'razorpay');
    }

    public function getCredentialsAttribute($value): array
    {
        return $this->decryptArrayAttribute('credentials', $value);
    }

    public function setCredentialsAttribute($value): void
    {
        $this->attributes['credentials'] = $this->encryptArrayAttribute($value);
    }

    public function getWebhookConfigAttribute($value): array
    {
        return $this->decryptArrayAttribute('webhook_config', $value);
    }

    public function setWebhookConfigAttribute($value): void
    {
        $this->attributes['webhook_config'] = $this->encryptArrayAttribute($value);
    }

    private function encryptArrayAttribute($value): string
    {
        return Crypt::encryptString(json_encode($value ?: []));
    }

    private function decryptArrayAttribute(string $attribute, $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        try {
            $value = Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            Log::warning('Unable to decrypt payout setting attribute.', [
                'payout_setting_id' => $this->getKey(),
                'gateway' => $this->attributes['gateway'] ?? null,
                'attribute' => $attribute,
            ]);

            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
