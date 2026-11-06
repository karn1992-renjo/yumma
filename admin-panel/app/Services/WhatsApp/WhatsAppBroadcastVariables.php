<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Support\Money;

/**
 * The per-customer values a broadcast template can use. In a template each {{1}}, {{2}}... is mapped to
 * either one of these {tokens} (filled in separately for every recipient) or to fixed text such as a
 * coupon code. A token can also be written inside fixed text: "Hi {name}" -> "Hi Asha".
 */
class WhatsAppBroadcastVariables
{
    /** @return array<string, array{label:string, description:string, example:string}> */
    public static function catalog(): array
    {
        return [
            '{name}' => ['label' => 'First name', 'description' => "Customer's first name (\"there\" if unknown)", 'example' => 'Asha'],
            '{full_name}' => ['label' => 'Full name', 'description' => "Customer's full name", 'example' => 'Asha Verma'],
            '{phone}' => ['label' => 'Mobile number', 'description' => "Customer's own WhatsApp number", 'example' => '+919876543210'],
            '{orders_count}' => ['label' => 'Orders placed', 'description' => 'How many orders this customer has placed', 'example' => '12'],
            '{last_order_restaurant}' => ['label' => 'Last restaurant', 'description' => 'Restaurant of their most recent order', 'example' => 'Spice Hub'],
            '{last_order_date}' => ['label' => 'Last order date', 'description' => 'Date of their most recent order', 'example' => '18 Oct 2026'],
            '{wallet_balance}' => ['label' => 'Wallet balance', 'description' => 'Current wallet balance with currency', 'example' => '₹250.00'],
            '{reward_points}' => ['label' => 'Reward points', 'description' => 'Current reward points balance', 'example' => '120'],
            '{gold_status}' => ['label' => 'Gold status', 'description' => '"active till <date>" or "not active"', 'example' => 'active till 12 Nov 2026'],
            '{app_name}' => ['label' => 'App name', 'description' => 'Your app / brand name', 'example' => 'Swado'],
            '{app_link}' => ['label' => 'App / website link', 'description' => 'Link to your website', 'example' => 'https://swado.online'],
        ];
    }

    public static function isToken(string $spec): bool
    {
        return array_key_exists($spec, self::catalog());
    }

    /** Fills every known {token} inside $spec for one recipient. */
    public static function fill(string $spec, string $phone, ?string $firstName): string
    {
        if (! str_contains($spec, '{')) {
            return $spec;
        }
        $user = null;
        $resolved = [];

        foreach (array_keys(self::catalog()) as $token) {
            if (! str_contains($spec, $token)) {
                continue;
            }
            $user ??= self::userFor($phone);
            $resolved[$token] = self::value($token, $phone, $firstName, $user);
        }

        return strtr($spec, $resolved);
    }

    /** Example values for the live preview in admin. */
    public static function sampleFill(string $spec): string
    {
        $map = [];
        foreach (self::catalog() as $token => $info) {
            $map[$token] = $info['example'];
        }

        return strtr($spec, $map);
    }

    private static function userFor(string $phone): ?User
    {
        $contact = WhatsAppContact::where('phone', $phone)->first();
        if ($contact?->user_id) {
            return User::find($contact->user_id);
        }

        return app(WhatsAppService::class)->accountForNumber($phone);
    }

    private static function value(string $token, string $phone, ?string $firstName, ?User $user): string
    {
        $source = trim((string) ($firstName ?: ($user?->name ?? '')));
        $first = $source === '' ? 'there' : explode(' ', $source)[0];

        return match ($token) {
            '{name}' => $first,
            '{full_name}' => trim((string) ($user?->name ?: $firstName)) ?: 'there',
            '{phone}' => $phone,
            '{orders_count}' => (string) ($user ? Order::where('customer_id', $user->id)->count() : 0),
            '{last_order_restaurant}' => (string) ($user ? (Order::with('restaurant')->where('customer_id', $user->id)->latest('id')->first()?->restaurant?->name ?? 'your favourite restaurant') : 'your favourite restaurant'),
            '{last_order_date}' => (string) ($user ? (Order::where('customer_id', $user->id)->latest('id')->first()?->created_at?->format('d M Y') ?? 'recently') : 'recently'),
            '{wallet_balance}' => $user ? Money::format($user->wallet?->balance ?? 0) : Money::format(0),
            '{reward_points}' => (string) ((int) ($user?->reward_points_balance ?? 0)),
            '{gold_status}' => self::goldStatus($user),
            '{app_name}' => (string) AppSetting::getValue('app_name', config('app.name')),
            '{app_link}' => url('/'),
            default => '',
        };
    }

    private static function goldStatus(?User $user): string
    {
        if (! $user) {
            return 'not active';
        }
        $sub = CustomerSubscription::live()->where('user_id', $user->id)->latest('id')->first();

        return $sub?->current_period_end ? 'active till ' . $sub->current_period_end->format('d M Y') : 'not active';
    }
}
