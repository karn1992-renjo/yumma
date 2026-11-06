<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\GoldSubscriptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The customer-facing WhatsApp assistant. Deliberately plain text with numbered options so it
 * works identically on every provider (Meta, Twilio, Gupshup, Interakt); AiSensy can only send
 * templates, so the bot stays silent there.
 *
 * It only ever shows a customer their OWN data (matched by the verified WhatsApp number) and
 * never takes payment inside the chat: ordering hands over to the secure app/web checkout.
 */
class WhatsAppBot
{
    public function __construct(private WhatsAppService $whatsapp, private WhatsAppActions $actions, private WhatsAppOrdering $ordering, private WhatsAppLogin $login)
    {
    }

    public function enabled(): bool
    {
        return $this->whatsapp->enabled()
            && filter_var(AppSetting::getValue('whatsapp_bot_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(WhatsAppContact $contact, WhatsAppMessage $message, array $raw = []): void
    {
        if (! $this->enabled() || ! ($this->whatsapp->providerFor($contact->phone)?->supportsFreeform())) {
            return;
        }

        $text = trim((string) $message->body);
        $word = Str::lower($text);

        // Compliance first: STOP / START are answered with a confirmation, nothing else.
        if (in_array($word, WhatsAppService::STOP_WORDS, true)) {
            $this->say($contact, 'You have been unsubscribed from WhatsApp messages. Reply START any time to turn them back on.');

            return;
        }
        if (in_array($word, WhatsAppService::START_WORDS, true)) {
            $this->say($contact, "Welcome back! 🎉 Reply MENU to see what I can do.");

            return;
        }
        if (! $contact->opted_in) {
            return;
        }

        $isMenu = in_array($word, ['hi', 'hello', 'hey', 'menu', 'start over', 'home', '0', 'help', 'namaste', 'hii'], true);

        // A person from the team has taken over: stay quiet except to hand back on MENU.
        if ($contact->bot_state === 'human' && ! $isMenu) {
            if (! $contact->bot_context || ($contact->bot_context['acked_at'] ?? 0) < now()->subMinutes(30)->timestamp) {
                $this->say($contact, 'Our team has your message and will reply here shortly. Reply MENU to go back to the assistant.');
                $this->setState($contact, 'human', ['acked_at' => now()->timestamp]);
            }

            return;
        }

        // A cart sent from the WhatsApp catalog.
        if (($message->type ?? '') === 'order' && $this->ordering->receiveCatalogOrder($contact, $message)) {
            return;
        }

        // Log in / sign up with an OTP.
        if ($this->login->consumes($contact, $text, $word)) {
            return;
        }

        // Restaurant owners and drivers (matched by their verified WhatsApp number) get their own assistant.
        if (in_array($word, ['customer', 'customer menu', 'order for myself'], true)) {
            $this->setState($contact, null, ['mode' => 'customer']);
            $this->mainMenu($contact);

            return;
        }
        if (in_array($word, ['partner', 'partner menu', 'business'], true)) {
            $this->setState($contact, null, ['mode' => null]);
            $this->mainMenu($contact);

            return;
        }
        $role = $this->partnerRole($contact);
        if ($role && ! $isMenu) {
            $this->partnerReply($contact, $role, $word, $text);

            return;
        }

        $location = $this->locationFrom($message);
        if ($location && $this->ordering->acceptLocation($contact, $location['lat'], $location['lng'])) {
            return;
        }
        if ($location) {
            $this->nearbyRestaurants($contact, $location['lat'], $location['lng']);

            return;
        }

        if ($isMenu) {
            $this->mainMenu($contact);

            return;
        }

        // Ordering food: menu browsing, cart, address, payment, confirmation.
        if ($this->ordering->consumes($contact, $text, $word)) {
            return;
        }

        // Quick-reply buttons / list picks arrive as a payload; typing the same words works too.
        $choice = $this->choiceFrom($message, $word);

        // "Reply YES to cancel" step.
        if ($contact->bot_state === 'confirm_cancel') {
            $this->confirmCancel($contact, $word);

            return;
        }

        // A restaurant chosen from the nearby list.
        if ($contact->bot_state === 'pick_restaurant' && ctype_digit($word)) {
            $this->restaurantDetails($contact, (int) $word);

            return;
        }
        // An order chosen from "my orders".
        if ($contact->bot_state === 'pick_order' && ctype_digit($word)) {
            $ids = $contact->bot_context['order_ids'] ?? [];
            $order = isset($ids[(int) $word - 1]) ? $this->ordersFor($contact)->whereKey($ids[(int) $word - 1])->first() : null;
            $order ? $this->orderStatus($contact, $order) : $this->say($contact, 'Please reply with a number from the list, or MENU.');

            return;
        }

        // An order number typed anywhere in the message.
        if (preg_match('/\b(ORD[A-Z0-9]{6,})\b/i', $text, $m)) {
            $order = $this->ordersFor($contact)->where('order_number', strtoupper($m[1]))->first();
            if ($order && str_starts_with($word, 'cancel')) {
                $this->askCancel($contact, $order);

                return;
            }
            $order ? $this->orderStatus($contact, $order) : $this->say($contact, "I couldn't find order {$m[1]} for this WhatsApp number. Reply MENU for options.");

            return;
        }

        match ($choice) {
            'track' => $this->trackLatest($contact),
            'rider' => $this->whereIsRider($contact),
            'cancel' => $this->askCancel($contact),
            'orders' => $this->myOrders($contact),
            'food' => $this->askLocation($contact),
            'account' => $this->account($contact),
            'offers' => $this->offers($contact),
            'support' => $this->handToHuman($contact),
            default => $this->say($contact, "Sorry, I didn't get that. 🙏\nReply MENU to see what I can do, or 6 to talk to our team."),
        };
    }

    // ---- Choices ------------------------------------------------------------------------------------------

    private function choiceFrom(WhatsAppMessage $message, string $word): ?string
    {
        $payload = Str::lower((string) ($message->payload['payload'] ?? ''));
        $word = $payload !== '' ? $payload : $word;

        return match (true) {
            in_array($word, ['cancel', 'cancel order', 'cancel my order'], true) => 'cancel',
            in_array($word, ['rider', 'where is my rider', 'where is rider', 'map', 'live map', 'live location', 'rider location'], true) => 'rider',
            in_array($word, ['1', 'track', 'track order', 'track my order', 'where is my order', 'status'], true) => 'track',
            in_array($word, ['2', 'orders', 'my orders', 'order history'], true) => 'orders',
            in_array($word, ['3', 'order', 'order food', 'food', 'restaurants', 'nearby'], true) => 'food',
            in_array($word, ['4', 'account', 'my account', 'wallet', 'gold', 'balance'], true) => 'account',
            in_array($word, ['5', 'offers', 'coupons', 'deals', 'discount'], true) => 'offers',
            in_array($word, ['6', 'support', 'agent', 'human', 'talk to team', 'talk to support', 'complaint', 'refund'], true) => 'support',
            default => null,
        };
    }

    private function mainMenu(WhatsAppContact $contact): void
    {
        $this->setState($contact, null);
        $app = AppSetting::getValue('app_name', config('app.name', 'Swado'));
        if ($role = $this->partnerRole($contact)) {
            $this->partnerMenu($contact, $role, $app);

            return;
        }
        $name = $contact->user?->name ? ' ' . trim(explode(' ', $contact->user->name)[0]) : ($contact->profile_name ? ' ' . trim(explode(' ', $contact->profile_name)[0]) : '');

        $this->say($contact, "Hi{$name}! 👋 Welcome to {$app}.\nHow can I help? Reply with a number:\n\n"
            . "1️⃣ Track my order\n2️⃣ My recent orders\n3️⃣ Order food\n4️⃣ My account (wallet & Gold)\n5️⃣ Offers\n6️⃣ Talk to our team\n"
            . ($this->partnerRoleIgnoringMode($contact) ? "7️⃣ Back to my business menu (reply PARTNER)\n" : '')
            . (($cartLine = $this->ordering->cartSummaryLine($contact)) ? "\n{$cartLine}\n" : '')
            . "\nReply MENU any time to come back here.");
    }

    // ---- Orders ----------------------------------------------------------------------------------------------

    /** Orders that belong to this WhatsApp number: the linked account, or the number given at checkout. */
    private function ordersFor(WhatsAppContact $contact)
    {
        $last10 = substr(preg_replace('/\D+/', '', $contact->phone), -10);

        return Order::with(['restaurant', 'driver'])->where(function ($q) use ($contact, $last10) {
            if ($contact->user_id) {
                $q->orWhere('customer_id', $contact->user_id);
            }
            $q->orWhere('customer_phone', 'like', '%' . $last10);
        })->latest('id');
    }

    private function trackLatest(WhatsAppContact $contact): void
    {
        $order = $this->ordersFor($contact)->whereNotIn('status', ['delivered', 'cancelled', 'rejected'])->first()
            ?? $this->ordersFor($contact)->first();

        $order
            ? $this->orderStatus($contact, $order)
            : $this->say($contact, "I couldn't find any orders for this number yet. Reply 3 to order food, or MENU.");
    }

    /** Live map link plus a plain Google Maps pin of the rider's last known position. */
    private function whereIsRider(WhatsAppContact $contact): void
    {
        $order = $this->ordersFor($contact)->whereNotIn('status', ['delivered', 'cancelled', 'rejected'])->first();
        if (! $order) {
            $this->say($contact, "You don't have an order on the way right now. Reply 2 to see your recent orders.");

            return;
        }
        $lines = ['🛵 Order #' . $order->order_number . ' · ' . $this->label($order->status)];
        if (! $order->driver) {
            $lines[] = 'A rider has not been assigned yet. We will message you as soon as one is.';
        } else {
            $cached = \Illuminate\Support\Facades\Cache::get("driver_location_{$order->driver_id}", []);
            $lat = is_array($cached) ? ($cached['lat'] ?? null) : null;
            $lng = is_array($cached) ? ($cached['lng'] ?? null) : null;
            $lines[] = 'Rider: ' . trim(explode(' ', (string) $order->driver->name)[0]);
            $lines[] = is_numeric($lat) && is_numeric($lng)
                ? '📍 Rider is here now: ' . \App\Support\PublicTracking::mapsLink((float) $lat, (float) $lng)
                : 'Rider location is not available yet.';
        }
        $lines[] = '🗺️ Live map: ' . \App\Support\PublicTracking::url($order);
        $this->say($contact, implode("\n", $lines));
    }

    /** Starts the "cancel my order" confirmation. Only the account holder's own order, and only inside the allowed window. */
    private function askCancel(WhatsAppContact $contact, ?Order $order = null): void
    {
        $user = $contact->user_id ? User::find($contact->user_id) : null;
        if (! $user) {
            $this->say($contact, "I can only cancel orders for a registered account. Please cancel in the app, or reply 6 to talk to our team.");

            return;
        }
        $order ??= Order::with('restaurant')->where('customer_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup'])->latest('id')->first();

        if (! $order || (int) $order->customer_id !== (int) $user->id) {
            $this->say($contact, "You have no order that can be cancelled right now. Reply 2 to see your orders, or 6 to talk to our team.");

            return;
        }
        if (! in_array($order->status, ['pending', 'confirmed', 'preparing', 'ready_for_pickup'], true)
            || ! \App\Models\OrderCancellationLimit::isWithinWindow($order, 'customer', 15)) {
            $this->say($contact, "Order #{$order->order_number} can't be cancelled any more (" . $this->label($order->status) . "). Reply 6 and our team will help.");

            return;
        }

        $this->setState($contact, 'confirm_cancel', ['order_id' => $order->id]);
        $this->say($contact, "Cancel order #{$order->order_number} from " . ($order->restaurant->name ?? 'the restaurant') . ' (' . $this->money($order->total) . ")?\n"
            . ($order->payment_status === 'success' ? "You paid online, so a refund follows our refund policy.\n" : '')
            . "\nReply YES to cancel it, or NO to keep it.");
    }

    private function confirmCancel(WhatsAppContact $contact, string $word): void
    {
        $orderId = $contact->bot_context['order_id'] ?? null;
        $this->setState($contact, null);

        if (! in_array($word, ['yes', 'y', 'confirm', 'yes cancel', 'cancel it'], true)) {
            $this->say($contact, 'Okay, your order is not cancelled. Reply MENU for options.');

            return;
        }
        $user = User::find($contact->user_id);
        $order = $orderId && $user ? Order::where('customer_id', $user->id)->find($orderId) : null;
        if (! $order) {
            $this->say($contact, "I couldn't find that order. Reply MENU for options.");

            return;
        }
        $result = $this->actions->cancelOrder($user, $order, 'Cancelled by customer via WhatsApp');
        $this->say($contact, ($result['success'] ? '✅ ' : '⚠️ ') . $result['message'] . "\n\nReply MENU for options.");
    }

    private function myOrders(WhatsAppContact $contact): void
    {
        $orders = $this->ordersFor($contact)->limit(5)->get();
        if ($orders->isEmpty()) {
            $this->say($contact, "You haven't placed an order with this number yet. Reply 3 to order food!");

            return;
        }
        $lines = $orders->values()->map(fn (Order $o, $i) => ($i + 1) . '. #' . $o->order_number . ' · ' . ($o->restaurant->name ?? 'Restaurant')
            . ' · ' . $this->money($o->total) . ' · ' . $this->label($o->status))->implode("\n");
        $this->setState($contact, 'pick_order', ['order_ids' => $orders->pluck('id')->all()]);
        $this->say($contact, "Your recent orders:\n\n{$lines}\n\nReply with a number to see its details, or MENU.");
    }

    private function orderStatus(WhatsAppContact $contact, Order $order): void
    {
        $this->setState($contact, null);
        $lines = ["🧾 Order #{$order->order_number}", 'From: ' . ($order->restaurant->name ?? 'Restaurant'), 'Status: ' . $this->label($order->status), 'Total: ' . $this->money($order->total)];

        if ($order->driver && in_array($order->status, ['picked_up', 'on_the_way', 'reached_pickup', 'ready_for_pickup'], true)) {
            $lines[] = '🛵 Rider: ' . $order->driver->name . ($order->driver->phone ? ' (' . $order->driver->phone . ')' : '');
        }
        if (in_array($order->status, ['picked_up', 'on_the_way'], true) && $order->delivery_otp) {
            $lines[] = '🔐 Delivery OTP: ' . $order->delivery_otp . ' (tell it to the rider only)';
        }
        if (! in_array($order->status, ['delivered', 'cancelled'], true)) {
            $lines[] = '📍 Live map: ' . \App\Support\PublicTracking::url($order);
            if ($order->driver) {
                $lines[] = 'Reply RIDER for the rider\'s location.';
            }
            if (in_array($order->status, ['pending', 'confirmed', 'preparing', 'ready_for_pickup'], true)) {
                $lines[] = 'Reply CANCEL to cancel this order (only allowed for a short time after placing it).';
            }
        }
        if ($order->status === 'cancelled' && $order->cancellation_reason) {
            $lines[] = 'Reason: ' . $order->cancellation_reason;
        }
        $lines[] = "\nNeed help with this order? Reply 6. Reply MENU for more.";
        $this->say($contact, implode("\n", $lines));
    }

    // ---- Account -------------------------------------------------------------------------------------------------

    private function account(WhatsAppContact $contact): void
    {
        $user = $contact->user_id ? User::find($contact->user_id) : null;
        if (! $user) {
            $this->login->offer($contact, "I can't find an account for this WhatsApp number.");

            return;
        }

        $wallet = $user->wallet?->balance ?? 0;
        $gold = app(GoldSubscriptionService::class)->activeSubscriptionFor($user->id);
        $lines = [
            '👤 ' . $user->name,
            '💰 Wallet balance: ' . $this->money($wallet),
            '⭐ Reward points: ' . (int) $user->reward_points_balance,
            $gold ? '🥇 Gold: active till ' . $gold->current_period_end?->format('d M Y') : '🥇 Gold: not active. Join in the app for free delivery on every order.',
        ];
        $this->say($contact, implode("\n", $lines) . "\n\nReply MENU for more.");
    }

    private function offers(WhatsAppContact $contact): void
    {
        $now = now();
        $codes = PromoCode::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $now))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->whereNull('assigned_to')
            ->latest('id')->limit(5)->get();

        if ($codes->isEmpty()) {
            $this->say($contact, 'No public offers right now. Check the app for personalised rewards! Reply MENU for more.');

            return;
        }
        $lines = $codes->map(function (PromoCode $c) {
            $value = str_contains((string) $c->discount_type, 'percent') ? rtrim(rtrim((string) $c->discount_value, '0'), '.') . '% off' : $this->money($c->discount_value) . ' off';
            $min = (float) $c->min_order_amount > 0 ? ' on orders above ' . $this->money($c->min_order_amount) : '';

            return "🎟️ {$c->code}: {$value}{$min}";
        })->implode("\n");
        $this->say($contact, "Current offers:\n\n{$lines}\n\nApply the code at checkout. Reply MENU for more.");
    }

    // ---- Discovery ----------------------------------------------------------------------------------------------

    private function askLocation(WhatsAppContact $contact): void
    {
        $this->setState($contact, 'await_location');
        $this->say($contact, "Let's find food near you 🍽️\nTap the 📎 (attach) icon → Location → Send your current location.");
    }

    /** @return array{lat:float,lng:float}|null */
    private function locationFrom(WhatsAppMessage $message): ?array
    {
        if (($message->type ?? '') !== 'location') {
            return null;
        }
        $data = json_decode((string) ($message->payload['payload'] ?? ''), true) ?: [];
        $lat = $data['latitude'] ?? $data['lat'] ?? null;
        $lng = $data['longitude'] ?? $data['lng'] ?? null;

        return is_numeric($lat) && is_numeric($lng) ? ['lat' => (float) $lat, 'lng' => (float) $lng] : null;
    }

    private function nearbyRestaurants(WhatsAppContact $contact, float $lat, float $lng): void
    {
        $nearby = Restaurant::customerVisible()->where('is_open', true)->where('is_verified', true)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->limit(3000)->get(['id', 'name', 'slug', 'latitude', 'longitude', 'delivery_radius', 'rating', 'delivery_time', 'cuisine'])
            ->map(function (Restaurant $r) use ($lat, $lng) {
                $r->distance_km = $this->distanceKm($lat, $lng, (float) $r->latitude, (float) $r->longitude);

                return $r;
            })
            ->filter(fn (Restaurant $r) => $r->distance_km <= max(1.0, (float) ($r->delivery_radius ?: 5)))
            ->sortBy('distance_km')->take(8)->values();

        if ($nearby->isEmpty()) {
            $this->setState($contact, null);
            $this->say($contact, "Sorry, no open restaurants deliver to that location right now. 😔\nTry again later, or reply MENU.");

            return;
        }

        $this->setState($contact, 'pick_restaurant', ['restaurant_ids' => $nearby->pluck('id')->all(), 'loc' => ['lat' => $lat, 'lng' => $lng]]);
        $lines = $nearby->map(fn (Restaurant $r, $i) => ($i + 1) . '. ' . $r->name . ' · ' . number_format($r->distance_km, 1) . ' km · ⭐ ' . number_format((float) $r->rating, 1)
            . ' · ~' . (int) $r->delivery_time . ' min')->implode("\n");
        $this->say($contact, "Open near you:\n\n{$lines}\n\nReply with a number to see the menu, or MENU.");
    }

    private function restaurantDetails(WhatsAppContact $contact, int $number): void
    {
        $ids = $contact->bot_context['restaurant_ids'] ?? [];
        $restaurant = isset($ids[$number - 1]) ? Restaurant::find($ids[$number - 1]) : null;
        if (! $restaurant) {
            $this->say($contact, 'Please reply with a number from the list, or MENU.');

            return;
        }

        $this->ordering->startMenu($contact, $restaurant);
    }

    // ---- Support -----------------------------------------------------------------------------------------------------

    private function handToHuman(WhatsAppContact $contact): void
    {
        $this->setState($contact, 'human', ['acked_at' => now()->timestamp]);
        $this->say($contact, "Okay, I'm connecting you to our team. 🙋 Please type your question here; an agent will reply on this chat. Reply MENU to go back to the assistant.");
    }

    // ---- Restaurant owners & drivers ------------------------------------------------------------------------------

    /** 'restaurant' | 'driver' | null. Null while the person chose customer mode. */
    private function partnerRole(WhatsAppContact $contact): ?string
    {
        if (($contact->bot_context['mode'] ?? null) === 'customer') {
            return null;
        }

        return $this->partnerRoleIgnoringMode($contact);
    }

    private function partnerRoleIgnoringMode(WhatsAppContact $contact): ?string
    {
        $user = $contact->user_id ? User::find($contact->user_id) : null;
        if (! $user) {
            return null;
        }
        if (Restaurant::where('owner_id', $user->id)->exists()) {
            return 'restaurant';
        }

        return $user->hasRole('delivery_partner') ? 'driver' : null;
    }

    private function partnerMenu(WhatsAppContact $contact, string $role, string $app): void
    {
        $name = $contact->user?->name ? ' ' . trim(explode(' ', $contact->user->name)[0]) : '';
        if ($role === 'restaurant') {
            $this->say($contact, "Hi{$name}! 👋 {$app} restaurant assistant.\nReply with a number:\n\n"
                . "1️⃣ Orders needing action\n2️⃣ Today's summary\n3️⃣ Open / close my restaurant\n4️⃣ Recent orders\n5️⃣ My payouts\n6️⃣ Talk to our team\n7️⃣ Order food as a customer (reply CUSTOMER)\n\nReply MENU any time.");

            return;
        }
        $this->say($contact, "Hi{$name}! 🛵 {$app} delivery assistant.\nReply with a number:\n\n"
            . "1️⃣ My current delivery\n2️⃣ Today's earnings\n3️⃣ Recent deliveries\n4️⃣ Cash in hand (COD)\n5️⃣ Online / offline (reply ONLINE or OFFLINE)\n6️⃣ Talk to our team\n7️⃣ Order food as a customer (reply CUSTOMER)\n\nReply MENU any time.");
    }

    private function partnerReply(WhatsAppContact $contact, string $role, string $word, string $text): void
    {
        $user = User::find($contact->user_id);
        $restaurantIds = $role === 'restaurant' ? Restaurant::where('owner_id', $user->id)->pluck('id')->all() : [];

        // ACCEPT / REJECT must win over the plain order-number lookup below.
        if ($role === 'restaurant' && preg_match('/^(accept|reject)\b\s*(ORD[A-Z0-9]+)?\s*(.*)$/i', $text, $am)) {
            $this->restaurantDecision($contact, $user, $restaurantIds, strtolower($am[1]), strtoupper($am[2] ?? ''), trim($am[3] ?? ''));

            return;
        }

        // An order number: only orders of THEIR restaurant / assigned to THEM.
        if (preg_match('/\b(ORD[A-Z0-9]{6,})\b/i', $text, $m)) {
            $q = Order::with(['restaurant', 'driver', 'customer'])->where('order_number', strtoupper($m[1]));
            $role === 'restaurant' ? $q->whereIn('restaurant_id', $restaurantIds) : $q->where('driver_id', $user->id);
            $order = $q->first();
            $order ? $this->say($contact, $this->partnerOrderCard($order, $role)) : $this->say($contact, "I couldn't find order {$m[1]} on your account. Reply MENU.");

            return;
        }

        if ($role === 'restaurant') {
            if (preg_match('/^(accept|reject)\b\s*(ORD[A-Z0-9]+)?\s*(.*)$/i', $text, $am)) {
                $this->restaurantDecision($contact, $user, $restaurantIds, strtolower($am[1]), strtoupper($am[2] ?? ''), trim($am[3] ?? ''));

                return;
            }
            $this->restaurantReply($contact, $user, $restaurantIds, $word);

            return;
        }
        $this->driverReply($contact, $user, $word);
    }

    private function restaurantReply(WhatsAppContact $contact, User $user, array $ids, string $word): void
    {
        $restaurants = Restaurant::whereIn('id', $ids)->get();
        $choice = match (true) {
            in_array($word, ['1', 'orders', 'pending', 'new orders', 'orders needing action'], true) => 'pending',
            in_array($word, ['2', 'today', 'summary', 'sales'], true) => 'summary',
            in_array($word, ['3', 'status'], true) => 'status',
            in_array($word, ['open', 'open restaurant', 'go online'], true) => 'open',
            in_array($word, ['close', 'close restaurant', 'go offline'], true) => 'close',
            in_array($word, ['4', 'recent', 'recent orders', 'history'], true) => 'recent',
            in_array($word, ['5', 'payout', 'payouts', 'settlement'], true) => 'payouts',
            in_array($word, ['6', 'support', 'agent', 'human', 'help'], true) => 'support',
            default => null,
        };

        switch ($choice) {
            case 'pending':
                $orders = Order::with('restaurant')->whereIn('restaurant_id', $ids)->whereIn('status', ['pending', 'confirmed', 'preparing'])->latest('id')->limit(8)->get();
                if ($orders->isEmpty()) {
                    $this->say($contact, 'No orders need action right now. ✅ Reply MENU for more.');

                    return;
                }
                $lines = $orders->map(fn (Order $o) => '#' . $o->order_number . ' · ' . $this->money($o->total) . ' · ' . $this->label($o->status)
                    . ($restaurants->count() > 1 ? ' · ' . $o->restaurant?->name : ''))->implode("\n");
                $this->say($contact, "Orders needing action:\n\n{$lines}\n\nReply ACCEPT <order no> [minutes] to accept, or REJECT <order no> <reason> to reject. (Mark ready in the app.) Reply an order number for details.");

                return;
            case 'summary':
                $today = Order::whereIn('restaurant_id', $ids)->whereDate('created_at', today());
                $delivered = (clone $today)->where('status', 'delivered');
                $this->say($contact, "📊 Today\nOrders: " . (clone $today)->count() . "\nDelivered: " . (clone $delivered)->count() . ' · Sales: ' . $this->money((clone $delivered)->sum('total'))
                    . "\nCancelled: " . (clone $today)->where('status', 'cancelled')->count() . "\nIn progress: " . (clone $today)->whereNotIn('status', ['delivered', 'cancelled'])->count() . "\n\nReply MENU for more.");

                return;
            case 'status':
                $this->say($contact, $restaurants->map(fn (Restaurant $r) => ($r->is_open ? '🟢 ' : '🔴 ') . $r->name . ' is ' . ($r->is_open ? 'OPEN' : 'CLOSED'))->implode("\n")
                    . "\n\nReply OPEN or CLOSE to change it.");

                return;
            case 'open':
            case 'close':
                $open = $choice === 'open';
                \App\Services\WhatsApp\WhatsAppNotifier::$quietStatusChange = true;
                try {
                    $restaurants->each(fn (Restaurant $r) => $r->update(['is_open' => $open]));
                } finally {
                    \App\Services\WhatsApp\WhatsAppNotifier::$quietStatusChange = false;
                }
                $this->say($contact, ($open ? '🟢 Now OPEN and taking orders: ' : '🔴 Now CLOSED, no new orders: ') . $restaurants->pluck('name')->implode(', ') . "\nReply " . ($open ? 'CLOSE' : 'OPEN') . ' to change it back.');

                return;
            case 'recent':
                $orders = Order::whereIn('restaurant_id', $ids)->latest('id')->limit(5)->get();
                $this->say($contact, $orders->isEmpty() ? 'No orders yet. Reply MENU.' : "Recent orders:\n\n" . $orders->map(fn (Order $o) => '#' . $o->order_number . ' · ' . $this->money($o->total) . ' · ' . $this->label($o->status))->implode("\n") . "\n\nReply an order number for details.");

                return;
            case 'payouts':
                $payouts = \App\Models\Payout::whereIn('restaurant_id', $ids)->latest('id')->limit(4)->get();
                $this->say($contact, $payouts->isEmpty() ? 'No payouts yet. Reply MENU.' : "Latest payouts:\n\n" . $payouts->map(fn ($p) => $this->money($p->net_amount ?: $p->amount) . ' · ' . $this->label($p->status) . ' · ' . ($p->processed_at ?? $p->created_at)->format('d M'))->implode("\n") . "\n\nReply MENU for more.");

                return;
            case 'support':
                $this->handToHuman($contact);

                return;
        }
        $this->say($contact, "Sorry, I didn't get that. 🙏 Reply MENU for options, or 6 to talk to our team.");
    }

    /** ACCEPT / REJECT from WhatsApp, only for orders of this owner's restaurants. */
    private function restaurantDecision(WhatsAppContact $contact, User $owner, array $restaurantIds, string $verb, string $orderNumber, string $rest): void
    {
        $pending = Order::whereIn('restaurant_id', $restaurantIds)->where('status', 'pending');
        if ($orderNumber !== '') {
            $order = Order::whereIn('restaurant_id', $restaurantIds)->where('order_number', $orderNumber)->first();
            if (! $order) {
                $this->say($contact, "I couldn't find order {$orderNumber} on your restaurant. Reply 1 to see orders needing action.");

                return;
            }
        } else {
            $open = (clone $pending)->latest('id')->limit(2)->get();
            if ($open->isEmpty()) {
                $this->say($contact, 'No orders are waiting for you. ✅ Reply MENU for more.');

                return;
            }
            if ($open->count() > 1) {
                $this->say($contact, "You have several pending orders. Please include the order number, e.g. " . strtoupper($verb) . ' ' . $open->first()->order_number . ($verb === 'reject' ? ' out of stock' : ' 20') . '. Reply 1 to see them.');

                return;
            }
            $order = $open->first();
        }

        if ($verb === 'accept') {
            $minutes = preg_match('/^\d{1,3}/', $rest, $mm) ? (int) $mm[0] : null;
            $result = $this->actions->acceptOrder($owner, $order, $minutes);
            $this->say($contact, $result['success']
                ? "✅ Order #{$order->order_number} accepted" . ($minutes ? " ({$minutes} min to prepare)" : '') . ". A rider is being arranged. Mark it ready in the app when done."
                : '⚠️ ' . $result['message']);

            return;
        }

        if ($rest === '') {
            $this->say($contact, "Please add a reason so the customer knows why. Example:\nREJECT {$order->order_number} out of stock");

            return;
        }
        $result = $this->actions->rejectOrder($owner, $order, $rest);
        $this->say($contact, $result['success'] ? "❌ Order #{$order->order_number} rejected. The customer is told and refunded if they paid." : '⚠️ ' . $result['message']);
    }

    private function driverReply(WhatsAppContact $contact, User $user, string $word): void
    {
        $choice = match (true) {
            in_array($word, ['1', 'current', 'delivery', 'my delivery', 'current delivery', 'order'], true) => 'current',
            in_array($word, ['2', 'earnings', 'today', 'income'], true) => 'earnings',
            in_array($word, ['3', 'recent', 'recent deliveries', 'history'], true) => 'recent',
            in_array($word, ['4', 'cash', 'cod', 'cash in hand', 'cod cash'], true) => 'cash',
            in_array($word, ['5', 'status'], true) => 'status',
            in_array($word, ['online', 'go online'], true) => 'online',
            in_array($word, ['offline', 'go offline'], true) => 'offline',
            in_array($word, ['6', 'support', 'agent', 'human', 'help'], true) => 'support',
            default => null,
        };

        switch ($choice) {
            case 'cash':
                $svc = app(\App\Services\AutoAssignDriverService::class);
                $this->say($contact, "💵 Cash in hand (COD not yet deposited): " . $this->money($svc->driverCodCashInHand($user))
                    . "\nLimit: " . $this->money(\App\Services\AutoAssignDriverService::codCashLimit())
                    . "\nDeposit it in the app to keep receiving orders. Reply MENU for more.");

                return;
            case 'status':
                $online = (bool) (\Illuminate\Support\Facades\Cache::get("driver_status_{$user->id}")['is_online'] ?? false);
                $this->say($contact, ($online ? '🟢 You are ONLINE.' : '🔴 You are OFFLINE.') . " Reply " . ($online ? 'OFFLINE' : 'ONLINE') . ' to change it.');

                return;
            case 'online':
            case 'offline':
                $online = $choice === 'online';
                \App\Services\WhatsApp\WhatsAppNotifier::$quietStatusChange = true;
                try {
                    $result = $this->actions->setDriverOnline($user, $online);
                } finally {
                    \App\Services\WhatsApp\WhatsAppNotifier::$quietStatusChange = false;
                }
                $this->say($contact, ($result['success'] ? ($online ? '🟢 You are now ONLINE. Keep the app open so we can see your live location and send you orders.' : '🔴 You are now OFFLINE.') : '⚠️ ' . $result['message']));

                return;
            case 'current':
                $order = Order::with(['restaurant', 'customer'])->where('driver_id', $user->id)
                    ->whereNotIn('status', ['delivered', 'cancelled', 'rejected'])->latest('id')->first();
                $this->say($contact, $order ? $this->partnerOrderCard($order, 'driver') : 'You have no active delivery right now. 🛵 Reply MENU for more.');

                return;
            case 'earnings':
                $done = Order::where('driver_id', $user->id)->where('status', 'delivered')->whereDate('delivered_at', today());
                $this->say($contact, "💰 Today\nDeliveries: " . (clone $done)->count() . "\nEarnings: " . $this->money((clone $done)->sum('driver_earning')) . "\n\nReply MENU for more.");

                return;
            case 'recent':
                $orders = Order::with('restaurant')->where('driver_id', $user->id)->where('status', 'delivered')->latest('id')->limit(5)->get();
                $this->say($contact, $orders->isEmpty() ? 'No deliveries yet. Reply MENU.' : "Recent deliveries:\n\n" . $orders->map(fn (Order $o) => '#' . $o->order_number . ' · ' . ($o->restaurant->name ?? '') . ' · ' . $this->money($o->driver_earning))->implode("\n") . "\n\nReply MENU for more.");

                return;
            case 'support':
                $this->handToHuman($contact);

                return;
        }
        $this->say($contact, "Sorry, I didn't get that. 🙏 Reply MENU for options, or 6 to talk to our team.");
    }

    /** Order details for the restaurant / the driver. The delivery OTP is never shown here. */
    private function partnerOrderCard(Order $order, string $role): string
    {
        $lines = ["🧾 Order #{$order->order_number}", 'Status: ' . $this->label($order->status), 'Total: ' . $this->money($order->total)];
        if ($role === 'restaurant') {
            $lines[] = 'Items: ' . collect($order->items ?? [])->map(fn ($i) => ($i['quantity'] ?? 1) . ' × ' . ($i['name'] ?? 'item'))->implode(', ');
            if ($order->driver) {
                $lines[] = '🛵 Rider: ' . $order->driver->name . ($order->driver->phone ? ' (' . $order->driver->phone . ')' : '');
            }
        } else {
            $lines[] = '🍴 Pickup: ' . ($order->restaurant->name ?? '') . ($order->restaurant?->address ? ', ' . $order->restaurant->address : '');
            $lines[] = '📍 Drop: ' . ($order->delivery_address ?: data_get($order->customer_address, 'address', ''));
            $lines[] = '👤 Customer: ' . ($order->customer_name ?: $order->customer?->name) . ($order->customer_phone ? ' (' . $order->customer_phone . ')' : '');
            $lines[] = $order->payment_method === 'cod' ? '💵 Collect cash: ' . $this->money($order->total) : '✅ Already paid online';
        }

        return implode("\n", $lines) . "\n\nReply MENU for more.";
    }

    // ---- Plumbing -------------------------------------------------------------------------------------------------------

    private function say(WhatsAppContact $contact, string $text): void
    {
        try {
            $this->whatsapp->sendText($contact->phone, $text, 'bot');
        } catch (\Throwable $e) {
            Log::warning('WhatsApp bot reply failed.', ['phone' => $contact->phone, 'error' => $e->getMessage()]);
        }
    }

    private function setState(WhatsAppContact $contact, ?string $state, array $context = []): void
    {
        // "customer mode", the shared delivery location and the shopping cart survive between steps.
        foreach (['mode', 'cart', 'loc'] as $keep) {
            if (! array_key_exists($keep, $context) && ($old = $contact->bot_context[$keep] ?? null)) {
                $context[$keep] = $old;
            }
        }
        $context = array_filter($context, fn ($v, $k) => ! (in_array($k, ['mode', 'cart', 'loc'], true) && $v === null), ARRAY_FILTER_USE_BOTH);
        $contact->forceFill(['bot_state' => $state, 'bot_context' => $context ?: null])->save();
    }

    private function money($amount): string
    {
        return AppSetting::sanitizedCurrencySymbol() . number_format((float) $amount, AppSetting::currencyDecimals());
    }

    private function label(?string $status): string
    {
        return ucwords(str_replace('_', ' ', (string) $status));
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
