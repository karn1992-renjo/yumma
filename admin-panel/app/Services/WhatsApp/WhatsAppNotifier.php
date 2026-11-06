<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

/**
 * Turns business events (order placed, rider assigned, delivered, refund…) into WhatsApp
 * template messages. Every method is safe to call from model hooks: it does nothing unless
 * WhatsApp is enabled and that message type is switched on, never throws, never sends the
 * same event for the same record twice, and runs after the HTTP response.
 */
class WhatsAppNotifier
{
    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    // ---- Orders --------------------------------------------------------------------------------------

    public function orderCreated(Order $order): void
    {
        if (! $this->whatsapp->enabled()) {
            return;
        }
        // Online orders count as "placed" only once paid (see orderUpdated); COD is placed right away.
        if ($order->payment_method === 'cod' || $order->payment_status === 'success') {
            $this->orderPlaced($order);
        }
    }

    public function orderUpdated(Order $order): void
    {
        if (! $this->whatsapp->enabled()) {
            return;
        }

        if ($order->wasChanged('payment_status') && $order->payment_status === 'success') {
            $this->orderPlaced($order);
        }

        if ($order->wasChanged('driver_id') && $order->driver_id) {
            $this->riderAssigned($order);
        }

        if ($order->wasChanged('status')) {
            match ((string) $order->status) {
                'confirmed', 'accepted' => $this->orderAccepted($order),
                'picked_up', 'on_the_way' => $this->outForDelivery($order),
                'delivered' => $this->delivered($order),
                'cancelled' => $this->cancelled($order),
                default => null,
            };
        }
    }

    private function orderPlaced(Order $order): void
    {
        $order->loadMissing('restaurant');
        if (! self::$quietOrderPlaced) {
            $this->toCustomer($order, 'order_placed', [
                $this->name($order), $this->number($order), (string) optional($order->restaurant)->name,
            ]);
        }

        foreach ($this->restaurantPhones($order->restaurant) as $phone) {
            $this->send($phone, 'restaurant_new_order', [
                $this->number($order), (string) $this->itemCount($order), $this->money($order->total),
            ], $order);
        }
    }

    private function orderAccepted(Order $order): void
    {
        $order->loadMissing('restaurant');
        $this->toCustomer($order, 'order_accepted', [$this->name($order), (string) optional($order->restaurant)->name, $this->number($order)]);
    }

    private function riderAssigned(Order $order): void
    {
        $order->loadMissing('driver', 'restaurant');
        $driver = $order->driver;
        if (! $driver) {
            return;
        }
        $this->toCustomer($order, 'order_rider_assigned', [
            trim(explode(' ', (string) $driver->name)[0]) ?: 'Your rider', $this->number($order), $this->trackLink($order),
        ]);
        foreach ($this->restaurantPhones($order->restaurant) as $phone) {
            $this->send($phone, 'restaurant_rider_assigned', [(string) $driver->name, $this->number($order), (string) ($driver->phone ?: 'the app')], $order);
        }
        if ($driver->phone) {
            $this->send($driver->phone, 'driver_new_request', [
                (string) optional($order->restaurant)->name,
                (string) (data_get($order->customer_address, 'area') ?? data_get($order->customer_address, 'city') ?? 'customer location'),
                $this->money($order->driver_earning ?? 0),
            ], $order);
        }
    }

    private function outForDelivery(Order $order): void
    {
        $this->toCustomer($order, 'order_out_for_delivery', [$this->number($order), (string) ($order->delivery_otp ?? '—'), $this->trackLink($order)]);
    }

    private function delivered(Order $order): void
    {
        $this->toCustomer($order, 'order_delivered', [$this->number($order), $this->name($order)]);
    }

    private function cancelled(Order $order): void
    {
        $reason = (string) ($order->cancellation_reason ?: 'Cancelled');
        $this->toCustomer($order, 'order_cancelled', [$this->number($order), $reason]);

        $order->loadMissing('restaurant', 'driver');
        $by = trim((string) ($order->cancelled_by_role ?: 'the customer')) ?: 'the customer';
        foreach ($this->restaurantPhones($order->restaurant) as $phone) {
            $this->send($phone, 'restaurant_order_cancelled', [$this->number($order), $by, $reason], $order);
        }
        if ($order->driver?->phone) {
            $this->send($order->driver->phone, 'driver_order_cancelled', [$this->number($order), $reason], $order);
        }
    }

    public function refundIssued(Order $order, float $amount): void
    {
        $this->toCustomer($order, 'refund_issued', [
            $this->money($amount), $this->number($order), $order->payment_method === 'wallet' ? 'wallet' : 'original payment method',
        ]);
    }

    // ---- Money / account events (customer, restaurant, driver) ------------------------------------------------

    /** A payout to a restaurant or a driver has been completed. */
    public function payoutCompleted(\App\Models\Payout $payout): void
    {
        if (! $this->whatsapp->enabled()) {
            return;
        }
        $amount = $this->money($payout->net_amount ?: $payout->amount);
        $ref = (string) ($payout->transaction_id ?: $payout->gateway_reference_id ?: ('#' . $payout->id));

        if ($payout->restaurant_id && ($restaurant = \App\Models\Restaurant::with('owner')->find($payout->restaurant_id))) {
            foreach ($this->restaurantPhones($restaurant) as $phone) {
                $this->send($phone, 'payout_paid', [$amount, 'bank account', $ref], $payout);
            }
        } elseif ($payout->driver_id && ($driver = \App\Models\User::find($payout->driver_id)) && $driver->phone) {
            $this->send($driver->phone, 'payout_paid', [$amount, 'bank account', $ref], $payout);
        }
    }

    public function walletCredited(\App\Models\WalletTransaction $tx): void
    {
        if (! $this->whatsapp->enabled() || $tx->type !== 'credit') {
            return;
        }
        // Refunds to the wallet already send their own "refund issued" message.
        if (preg_match('/refund/i', (string) $tx->description . ' ' . (string) $tx->reference_type)) {
            return;
        }
        $user = \App\Models\User::find($tx->user_id);
        // Customer wallets only: drivers/partners get earnings credited on every delivery, which would be spam.
        if ($user && $user->hasAnyRole(['delivery_partner', 'restaurant_owner', 'franchise_owner', 'franchise_manager', 'super_admin', 'admin'])) {
            return;
        }
        if ($user?->phone) {
            $this->send($user->phone, 'wallet_credit', [$this->money($tx->amount), $this->money($tx->balance_after)], $tx);
        }
    }

    public function paymentFailed(\App\Models\PaymentAttempt $attempt): void
    {
        if (! $this->whatsapp->enabled() || ! $attempt->order_id) {
            return;
        }
        $order = Order::with('customer')->find($attempt->order_id);
        // An order that was paid another way (or already placed) needs no "payment failed" nudge.
        if (! $order || $order->payment_status === 'success' || in_array($order->status, ['cancelled', 'delivered'], true)) {
            return;
        }
        $this->toCustomer($order, 'payment_failed', [$this->money($attempt->amount ?: $order->total), $this->number($order), $this->trackLink($order)], $attempt);
    }

    public function restaurantApproved(\App\Models\Restaurant $restaurant): void
    {
        foreach ($this->restaurantPhones($restaurant) as $phone) {
            $this->send($phone, 'restaurant_approved', [(string) $restaurant->name, (string) AppSetting::getValue('app_name', config('app.name'))], $restaurant);
        }
    }

    // ---- Going online / offline ------------------------------------------------------------------------------------

    /** Set while the WhatsApp assistant itself flips a status, so the person is not told twice. */
    public static bool $quietStatusChange = false;

    /** Set while the assistant places an order itself: the chat already confirms it, so no template message to the customer. */
    public static bool $quietOrderPlaced = false;

    public function restaurantStatusChanged(\App\Models\Restaurant $restaurant): void
    {
        if (! $this->whatsapp->enabled() || self::$quietStatusChange) {
            return;
        }
        $open = (bool) $restaurant->is_open;
        foreach ($this->restaurantPhones($restaurant) as $phone) {
            $this->send($phone, 'restaurant_status_changed', [
                (string) $restaurant->name, $open ? 'OPEN' : 'CLOSED', (string) AppSetting::getValue('app_name', config('app.name')),
            ], $restaurant, 2);
        }
    }

    /** @param string|null $reason why it happened when the system did it (e.g. no booked gig) */
    public function driverStatusChanged(\App\Models\User $driver, bool $online, ?string $reason = null): void
    {
        if (! $this->whatsapp->enabled() || self::$quietStatusChange || ! $driver->phone) {
            return;
        }
        $this->send($driver->phone, 'driver_status_changed', [
            trim(explode(' ', (string) $driver->name)[0]) ?: 'there', $online ? 'ONLINE' : 'OFFLINE',
            (string) AppSetting::getValue('app_name', config('app.name')),
        ], $driver, 2);
    }

    // ---- Scheduled reminders ---------------------------------------------------------------------------------------

    /** Gold ending within 3 days and franchise plans renewing within 7 days. @return int messages queued */
    public function sendReminders(): int
    {
        if (! $this->whatsapp->enabled()) {
            return 0;
        }
        $count = 0;

        \App\Models\CustomerSubscription::with('user')->whereIn('status', ['active', 'past_due'])
            ->whereBetween('current_period_end', [now(), now()->addDays(3)])->get()
            ->each(function ($sub) use (&$count) {
                if ($sub->user?->phone) {
                    $this->send($sub->user->phone, 'gold_expiring', [
                        trim(explode(' ', (string) $sub->user->name)[0]) ?: 'there', (string) $sub->current_period_end->format('d M Y'), url('/'),
                    ], $sub);
                    $count++;
                }
            });

        // Installs without the franchise module simply skip this part.
        if (class_exists(\App\Support\FranchiseProgram::class) && class_exists(\App\Models\Franchise::class) && \App\Support\FranchiseProgram::enabled()) {
            \App\Models\Franchise::with('plan')->where('status', 'active')->whereBetween('plan_expires_at', [now(), now()->addDays(7)])->get()
                ->each(function ($f) use (&$count) {
                    if ($f->owner_phone) {
                        $this->send($f->owner_phone, 'franchise_renewal', [
                            (string) $f->owner_name, (string) $f->plan_expires_at->format('d M Y'), url('/franchise-panel/plan'),
                        ], $f);
                        $count++;
                    }
                });
        }

        return $count;
    }

    // ---- Account / franchise ---------------------------------------------------------------------------------

    public function goldWelcome(\App\Models\CustomerSubscription $subscription): void
    {
        $user = $subscription->user;
        if ($user?->phone) {
            $this->send($user->phone, 'gold_welcome', [
                trim(explode(' ', (string) $user->name)[0]) ?: 'there',
                (string) $subscription->current_period_end?->format('d M Y'),
            ], $subscription);
        }
    }

    public function franchiseCredentials(\App\Models\Franchise $franchise): void
    {
        if ($franchise->owner_phone) {
            $this->send($franchise->owner_phone, 'franchise_credentials', [
                (string) $franchise->owner_name, (string) $franchise->code, url('/login'),
            ], $franchise);
        }
    }

    // ---- Plumbing ---------------------------------------------------------------------------------------------

    private function toCustomer(Order $order, string $template, array $params, ?\Illuminate\Database\Eloquent\Model $related = null): void
    {
        $order->loadMissing('customer');
        if ($order->customer && $order->customer->notify_order_updates === false) {
            return;
        }
        $phone = trim((string) ($order->customer_phone ?: $order->customer?->phone));
        if ($phone !== '') {
            $this->send($phone, $template, $params, $related ?? $order);
        }
    }

    /** The restaurant's own number and its owner's number (once each). @return string[] */
    private function restaurantPhones(?\App\Models\Restaurant $restaurant): array
    {
        if (! $restaurant) {
            return [];
        }
        $restaurant->loadMissing('owner');
        $seen = [];
        $phones = [];
        foreach ([$restaurant->phone, $restaurant->owner?->phone] as $phone) {
            $key = substr(preg_replace('/\D+/', '', (string) $phone), -10);
            if ($key !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $phones[] = (string) $phone;
            }
        }

        return $phones;
    }

    private function send(string $phone, string $template, array $params, \Illuminate\Database\Eloquent\Model $related, ?int $withinMinutes = null): void
    {
        if (! $this->whatsapp->eventEnabled($template)) {
            return;
        }
        // One message per event per record -- model hooks can fire more than once.
        if (WhatsAppMessage::where('event', $template)->where('related_type', $related->getMorphClass())->where('related_id', $related->getKey())
            ->where('phone', 'like', '%' . substr(preg_replace('/\D+/', '', $phone), -10))->where('status', '!=', 'failed')
            ->when($withinMinutes, fn ($q) => $q->where('created_at', '>=', now()->subMinutes($withinMinutes))->where('body', $this->previewText($template, $params)))->exists()) {
            return;
        }

        $work = function () use ($phone, $template, $params, $related) {
            try {
                $this->whatsapp->sendTemplate($phone, $template, $params, $template, $related);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp notification failed.', ['template' => $template, 'error' => $e->getMessage()]);
            }
        };

        app()->runningInConsole() ? $work() : app()->terminating($work);
    }

    /** Same text the message log stores, so identical repeats (not different states) can be recognised. */
    private function previewText(string $template, array $params): ?string
    {
        $body = \App\Services\WhatsApp\WhatsAppTemplates::all()[$template]['body'] ?? null;
        foreach (array_values($params) as $i => $value) {
            $body = $body === null ? null : str_replace('{{' . ($i + 1) . '}}', (string) $value, $body);
        }

        return $body;
    }

    private function name(Order $order): string
    {
        return trim(explode(' ', (string) ($order->customer_name ?: $order->customer?->name))[0]) ?: 'there';
    }

    private function number(Order $order): string
    {
        return (string) ($order->order_number ?: $order->id);
    }

    private function money($amount): string
    {
        return AppSetting::sanitizedCurrencySymbol() . number_format((float) $amount, AppSetting::currencyDecimals());
    }

    private function itemCount(Order $order): int
    {
        return collect($order->items ?? [])->sum(fn ($i) => (int) ($i['quantity'] ?? 1)) ?: count($order->items ?? []);
    }

    private function trackLink(Order $order): string
    {
        return \App\Support\PublicTracking::url($order);
    }
}
