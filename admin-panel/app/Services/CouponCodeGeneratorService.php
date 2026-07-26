<?php

namespace App\Services;

use App\Models\PromotionCouponCode;
use Illuminate\Support\Str;

class CouponCodeGeneratorService
{
    public function generate(array $options = []): array
    {
        $count = max(1, min(10000, (int) ($options['count'] ?? 1)));
        $length = max(4, min(32, (int) ($options['length'] ?? 8)));
        $prefix = strtoupper((string) ($options['prefix'] ?? ''));
        $suffix = strtoupper((string) ($options['suffix'] ?? ''));
        $sequential = (bool) ($options['sequential'] ?? false);
        $codes = [];

        for ($i = 1; $i <= $count; $i++) {
            do {
                $body = $sequential
                    ? str_pad((string) $i, $length, '0', STR_PAD_LEFT)
                    : strtoupper(Str::random($length));
                $code = $prefix . $body . $suffix;
            } while (PromotionCouponCode::where('code', $code)->exists() || in_array($code, $codes, true));

            $codes[] = $code;
        }

        return $codes;
    }
}
