<?php

namespace App\Services\Ai;

use App\Models\AppSetting;

/**
 * The platform's configured currency (default INR/₹ in this deployment),
 * shared with every AI prompt so the model never defaults to writing "$"
 * for amounts that are actually in the platform's own currency.
 */
class AiCurrencyContext
{
    public static function resolve(): array
    {
        return [
            'symbol' => AppSetting::sanitizedCurrencySymbol(),
            'code' => strtoupper((string) (AppSetting::getValue('currency_code', 'INR') ?: 'INR')),
        ];
    }
}
