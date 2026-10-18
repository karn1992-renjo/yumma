<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\UserReferral;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReferralController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $isDriver = $user->hasRole('delivery_partner');
        $code = $user->referral_code ?: '';
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $referrals = UserReferral::query()
            ->with(['referredUser:id,name,phone,created_at'])
            ->where('referrer_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();
        $appLogoUrl = $this->appLogoUrl();

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $code,
                'share_link' => $this->shareLink($code, $appLogoUrl, $isDriver),
                'app_logo_url' => $appLogoUrl,
                'currency_symbol' => $currencySymbol,
                'stats' => [
                    'registered' => $referrals->count(),
                    'pending' => $referrals->whereIn('status', ['registered', 'qualified'])->count(),
                    'credited' => $referrals->where('status', 'credited')->count(),
                    'amount_earned' => round((float) $referrals->where('status', 'credited')->sum('amount'), 2),
                    'points_earned' => (int) $referrals->where('status', 'credited')->sum('points'),
                ],
                'how_it_works' => $isDriver
                    ? [
                        'Share your referral code or invite link.',
                        'Your friend signs up as a driver with this code.',
                        'Referral bonus is credited to both of you after your friend completes their first delivery.',
                    ]
                    : [
                        'Share your referral code or invite link.',
                        'Your friend signs up as a customer with this code.',
                        'Referral bonus is credited after the friend places an eligible order and a referral bonus promotion matches.',
                    ],
                'referrals' => $referrals->map(fn (UserReferral $referral) => [
                    'id' => $referral->id,
                    'name' => $referral->referredUser?->name ?: ($isDriver ? 'Driver' : 'Customer'),
                    'phone' => $referral->referredUser?->phone,
                    'status' => $referral->status,
                    'bonus_type' => $referral->bonus_type,
                    'amount' => $referral->amount,
                    'points' => $referral->points,
                    'registered_at' => $referral->created_at,
                    'qualified_at' => $referral->qualified_at,
                    'credited_at' => $referral->credited_at,
                ])->values(),
            ],
        ]);
    }

    private function shareLink(string $code, ?string $appLogoUrl, bool $isDriver = false): string
    {
        $prefix = $isDriver ? 'driver_' : '';
        $longLink = $this->appsFlyerLongLink($code, $appLogoUrl, $isDriver);
        $token = trim((string) config("services.appsflyer.{$prefix}onelink_api_token"));
        $oneLinkId = trim((string) config("services.appsflyer.{$prefix}onelink_id"));

        if ($code === '' || $token === '' || $oneLinkId === '') {
            return $longLink;
        }

        $cacheKey = 'referral_appsflyer_short_link:' . ($isDriver ? 'driver:' : 'customer:') . sha1($code . '|' . ($appLogoUrl ?? ''));
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $shortLink = $this->createAppsFlyerShortLink($code, $appLogoUrl, $isDriver);
        if ($shortLink !== null) {
            Cache::put($cacheKey, $shortLink, now()->addDays(30));
            return $shortLink;
        }

        return $longLink;
    }

    private function appsFlyerLongLink(string $code, ?string $appLogoUrl, bool $isDriver = false): string
    {
        $prefix = $isDriver ? 'driver_' : '';
        $domain = preg_replace('#^https?://#', '', trim((string) config("services.appsflyer.{$prefix}onelink_domain")));
        $domain = rtrim((string) $domain, '/');
        if ($domain === '') {
            $domain = 'Swado.onelink.me';
        }

        $path = trim((string) config("services.appsflyer.{$prefix}onelink_path"), '/');
        if ($path === '') {
            $oneLinkId = trim((string) config("services.appsflyer.{$prefix}onelink_id"));
            $path = trim($oneLinkId . '/referral', '/');
        }

        $params = $this->appsFlyerLinkData($code, $appLogoUrl, $isDriver);

        return 'https://' . $domain . '/' . $path . '?' . http_build_query($params);
    }

    private function appsFlyerLinkData(string $code, ?string $appLogoUrl, bool $isDriver = false): array
    {
        $webLink = $this->webReferralLink($code, $isDriver);
        $description = $isDriver
            ? 'Use referral code ' . $code . ' to join as a delivery partner and start earning.'
            : 'Use referral code ' . $code . ' for a fast, convenient food ordering experience.';
        $params = [
            'pid' => 'referral',
            'c' => 'referral_share',
            'deep_link_value' => 'referral',
            'screen' => 'referral',
            'type' => 'referral',
            'deep_link_sub1' => $code,
            'af_sub1' => $code,
            'referral_code' => $code,
            'link' => $webLink,
            'af_web_dp' => $webLink,
            'af_og_title' => 'Join ' . AppSetting::getValue('app_name', config('app.name', 'Swado')),
            'af_og_description' => $description,
        ];

        if ($appLogoUrl !== null && str_starts_with($appLogoUrl, 'https://')) {
            $params['af_og_image'] = $appLogoUrl;
        }

        return $params;
    }

    private function createAppsFlyerShortLink(string $code, ?string $appLogoUrl, bool $isDriver = false): ?string
    {
        $prefix = $isDriver ? 'driver_' : '';
        try {
            $response = Http::withToken((string) config("services.appsflyer.{$prefix}onelink_api_token"))
                ->acceptJson()
                ->timeout(5)
                ->post(
                    'https://onelink.appsflyer.com/api/v2.0/shortlinks/' . urlencode((string) config("services.appsflyer.{$prefix}onelink_id")),
                    [
                        'ttl' => (string) config('services.appsflyer.short_link_ttl', '30d'),
                        'data' => $this->appsFlyerLinkData($code, $appLogoUrl, $isDriver),
                    ]
                );

            if (! $response->successful()) {
                Log::warning('AppsFlyer short referral link creation failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $payload = $response->json();
            foreach (['shortlink', 'short_link', 'shortlink_url', 'url', 'link'] as $key) {
                $value = is_array($payload) ? ($payload[$key] ?? null) : null;
                if (is_string($value) && str_starts_with($value, 'https://')) {
                    return $value;
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('AppsFlyer short referral link exception.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    private function webReferralLink(string $code, bool $isDriver = false): string
    {
        $base = $isDriver
            ? (AppSetting::getValue('driver_deeplink_base_url') ?: AppSetting::getValue('customer_deeplink_base_url') ?: config('app.url'))
            : (AppSetting::getValue('customer_deeplink_base_url') ?: AppSetting::getValue('customer_app_url') ?: config('app.url'));

        return rtrim((string) $base, '/') . '/referral?code=' . urlencode($code) . ($isDriver ? '&role=driver' : '');
    }

    private function appLogoUrl(): ?string
    {
        $logo = MediaStorage::url(AppSetting::getValue('app_logo'));
        return is_string($logo) && str_starts_with($logo, 'https://') ? $logo : null;
    }
}
