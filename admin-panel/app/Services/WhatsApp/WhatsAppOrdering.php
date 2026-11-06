<?php

namespace App\Services\WhatsApp;

use App\Models\Address;
use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppCatalogItem;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\MediaStorage;
use App\Support\GatewayRegistry;
use App\Support\PublicPayment;
use App\Support\PublicTracking;
use Illuminate\Support\Facades\Log;

/**
 * Ordering food inside WhatsApp: browse a menu, build a cart, give an address, pick a payment
 * method, confirm, pay and track. Plain text with numbered choices so it works on every provider.
 *
 * Pricing and order creation are NOT done here: they go through the app's own checkout code
 * (WhatsAppActions::priceOrder / placeOrder), so fees, taxes, coupons, Gold benefits, minimum
 * order, restaurant hours and payment rules are exactly what the customer gets in the app.
 * Money is never taken in the chat: online payments open a secure payment page.
 *
 * State lives on the contact: bot_state = order_menu | order_address | order_coupon |
 * order_payment | order_confirm, and bot_context['cart'] holds the cart (kept until placed or cleared).
 */
class WhatsAppOrdering
{
    private const STATES = ['order_menu', 'order_address', 'order_coupon', 'order_payment', 'order_confirm'];
    private const PAGE = 8;
    private const MAX_QTY = 20;
    private const CART_TTL_HOURS = 6;

    public function __construct(private WhatsAppService $whatsapp, private WhatsAppActions $actions, private WhatsAppLogin $login, private WhatsAppCatalogSync $catalog)
    {
    }

    // ---- Entry points used by the assistant ------------------------------------------------------------

    public function hasCart(WhatsAppContact $contact): bool
    {
        $cart = $this->cart($contact);

        return $cart !== null && ! empty($cart['items']);
    }

    public function cartSummaryLine(WhatsAppContact $contact): ?string
    {
        $cart = $this->cart($contact);
        if (! $cart || empty($cart['items'])) {
            return null;
        }

        return '🛒 You have ' . $this->count($cart) . ' item(s) from ' . $cart['restaurant_name'] . ' in your cart. Reply CART to see it or CHECKOUT to order.';
    }

    /** A restaurant was picked from the nearby list: start (or restart) its menu. */
    public function startMenu(WhatsAppContact $contact, Restaurant $restaurant): void
    {
        $existing = $this->cart($contact);
        // Switching restaurant starts a fresh cart: one order is always from one restaurant.
        $cart = ($existing && (int) $existing['restaurant_id'] === (int) $restaurant->id) ? $existing : [
            'restaurant_id' => $restaurant->id, 'restaurant_name' => $restaurant->name, 'items' => [],
            'page' => 1, 'search' => null, 'listed' => [], 'coupon' => null, 'payment' => null, 'address' => null,
            'address_id' => null, 'lat' => $contact->bot_context['loc']['lat'] ?? null, 'lng' => $contact->bot_context['loc']['lng'] ?? null,
        ];
        $cart['page'] = 1;
        $cart['search'] = null;
        $this->save($contact, 'order_menu', $cart);

        $photo = MediaStorage::url($restaurant->cover_image ?: ($restaurant->banner_image ?: $restaurant->logo_image));
        if ($photo) {
            $cuisine = is_array($restaurant->cuisine) ? implode(', ', array_slice($restaurant->cuisine, 0, 3)) : (string) $restaurant->cuisine;
            $this->say($contact, '🍴 ' . $restaurant->name . ($restaurant->rating ? ' · ⭐ ' . number_format((float) $restaurant->rating, 1) : '') . ($cuisine ? " · {$cuisine}" : '')
                . ($restaurant->delivery_time ? ' · ~' . (int) $restaurant->delivery_time . ' min' : ''), $photo);
        }
        // The native WhatsApp catalog (pictures, +/- quantity, cart) when it is set up; the text menu otherwise.
        if ($this->sendCatalog($contact, $restaurant)) {
            return;
        }
        $this->showMenu($contact, $cart);
    }

    /** Sends the restaurant's dishes as catalog messages (30 per message). False when the catalog can't be used. */
    private function sendCatalog(WhatsAppContact $contact, Restaurant $restaurant): bool
    {
        if (! $this->whatsapp->catalogEnabled($contact->phone)) {
            return false;
        }
        $items = $this->catalog->itemsFor($restaurant->id);
        if ($items->isEmpty()) {
            return false;
        }

        $messages = 0;
        foreach ($items->chunk(30)->take(3) as $n => $chunk) {
            $sections = $chunk->groupBy(fn (MenuItem $i) => mb_substr($i->category?->name ?: 'Menu', 0, 24))->take(10)
                ->map(fn ($group, $title) => ['title' => (string) $title, 'product_items' => $group->map(fn (MenuItem $i) => ['product_retailer_id' => WhatsAppCatalogItem::retailerId($i->id)])->values()->all()])
                ->values()->all();
            $result = $this->whatsapp->sendCatalog(
                $contact->phone, mb_substr($restaurant->name, 0, 60) . ($n > 0 ? ' (more)' : ''),
                $n === 0 ? "Tap *View items*, choose your dishes and set the quantity, then send your cart. 🛒 I'll take it from there." : 'More dishes from this restaurant. Add them to the same cart.',
                'Prices include your restaurant offers', $sections, 'bot'
            );
            if ($result->status === 'failed') {
                if ($messages === 0) {
                    return false;                          // could not show the catalog at all: use the text menu
                }
                break;
            }
            $messages++;
        }
        $total = $items->count();
        $this->say($contact, ($total > 90 ? 'Showing the first 90 of ' . $total . ' dishes. ' : '') . 'Prefer typing? Reply TEXT MENU for the numbered menu, or SEARCH <word>.');

        return true;
    }

    /**
     * The customer sent a cart from the WhatsApp catalog. The catalog's prices are ignored: every
     * dish and price comes from our own menu, and the app's checkout prices the order as usual.
     */
    public function receiveCatalogOrder(WhatsAppContact $contact, WhatsAppMessage $message): bool
    {
        $order = json_decode((string) ($message->payload['payload'] ?? ''), true) ?: [];
        $items = [];
        $restaurants = [];
        $skipped = 0;
        foreach ($order['product_items'] ?? [] as $line) {
            $id = WhatsAppCatalogItem::menuItemIdFrom((string) ($line['product_retailer_id'] ?? ''));
            $dish = $id ? MenuItem::with('restaurant')->find($id) : null;
            if (! $dish || ! $dish->is_available || ! $dish->is_scheduled_available || $this->needsOptions($dish)) {
                $skipped++;

                continue;
            }
            $qty = max(1, min(self::MAX_QTY, (int) ($line['quantity'] ?? 1)));
            $restaurants[$dish->restaurant_id] = $dish->restaurant;
            if (isset($items[$dish->id])) {
                $items[$dish->id]['qty'] = min(self::MAX_QTY, $items[$dish->id]['qty'] + $qty);
            } else {
                $items[$dish->id] = ['id' => $dish->id, 'name' => $dish->name, 'price' => $dish->getFinalPriceAttribute(), 'qty' => $qty];
            }
        }

        if (! $items) {
            $this->say($contact, "I couldn't use that cart: those dishes are not available right now. Reply 3 to order food again.");

            return true;
        }
        if (count($restaurants) > 1) {
            $this->say($contact, 'Your cart has dishes from more than one restaurant. I can order from one restaurant at a time: please send a cart with dishes from a single restaurant.');

            return true;
        }

        $restaurant = array_values($restaurants)[0];
        $previous = $this->cart($contact);
        $cart = [
            'restaurant_id' => $restaurant->id, 'restaurant_name' => $restaurant->name, 'items' => array_values($items),
            'page' => 1, 'search' => null, 'listed' => [], 'coupon' => null, 'payment' => null, 'address' => null, 'address_id' => null,
            'lat' => $previous['lat'] ?? ($contact->bot_context['loc']['lat'] ?? null), 'lng' => $previous['lng'] ?? ($contact->bot_context['loc']['lng'] ?? null),
            'note' => trim((string) ($order['text'] ?? '')) ?: null,
        ];
        $this->save($contact, 'order_menu', $cart);

        $lines = collect($cart['items'])->map(fn ($l) => $l['qty'] . ' × ' . $l['name'] . ' – ' . $this->money($l['price'] * $l['qty']))->implode("\n");
        $this->say($contact, "🛒 Got your cart from {$restaurant->name}:\n\n{$lines}\n\nItems total: " . $this->money($this->itemsTotal($cart))
            . ($skipped ? "\n(Skipped {$skipped} dish(es) that are not available right now.)" : ''));

        return $this->checkout($contact, $cart) || true;
    }

    /**
     * The customer shares their CURRENT location while ordering: it becomes the delivery pin. Works at
     * every step (even after choosing a saved address, or at the final confirmation) and re-prices.
     * Returns true when the pin was used for the order.
     */
    public function acceptLocation(WhatsAppContact $contact, float $lat, float $lng): bool
    {
        $cart = $this->cart($contact);
        if (! $cart || ! in_array($contact->bot_state, self::STATES, true)) {
            return false;
        }

        $cart['lat'] = $lat;
        $cart['lng'] = $lng;
        $cart['address_id'] = null;           // a saved address would override the pin, so the typed text is used with the pin
        $cart['suggested'] = $this->reverseGeocode($lat, $lng);
        $maps = 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
        $state = $contact->bot_state;
        $this->save($contact, $state, $cart);

        $found = $cart['suggested'] ? "\nThis looks like: {$cart['suggested']}" : '';
        switch ($state) {
            case 'order_address':
                if (! empty($cart['address'])) {
                    $this->say($contact, "📍 Delivery location updated: {$maps}");
                    $this->afterAddress($contact, $cart);
                } else {
                    $this->say($contact, "📍 Got your current location: {$maps}{$found}\n" . ($cart['suggested'] ? 'Reply OK to use that address, or type your full address (house or flat number, building, landmark).' : 'Now please type your full delivery address (house or flat number, building, landmark).'));
                }
                break;
            case 'order_coupon':
                $this->say($contact, "📍 Delivery location updated: {$maps}\n🎟️ Type a coupon code, or reply SKIP.");
                break;
            case 'order_payment':
                $this->say($contact, "📍 Delivery location updated: {$maps}");
                $this->askPayment($contact, $cart);
                break;
            case 'order_confirm':
                $this->say($contact, "📍 Delivery location updated: {$maps}\nLet me check the bill for the new location…");
                $this->showConfirm($contact, $cart);
                break;
            default:   // browsing the menu: remember it for checkout
                $this->say($contact, "📍 Saved as your delivery location: {$maps}\nKeep adding items, or reply CHECKOUT.");
        }

        return true;
    }

    /** A readable address for a pin, from the Google Maps key in admin. Best effort: null when unavailable. */
    private function reverseGeocode(float $lat, float $lng): ?string
    {
        $key = AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', ''));
        if (! $key) {
            return null;
        }
        $cacheKey = 'wa_geocode:' . round($lat, 4) . ',' . round($lng, 4);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addDay(), function () use ($lat, $lng, $key) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', ['latlng' => "{$lat},{$lng}", 'key' => $key]);

                return $response->successful() ? ($response->json('results.0.formatted_address') ?: null) : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Handles a message when the customer is inside the ordering flow, or uses a cart command.
     * Returns true when the message was dealt with.
     */
    public function consumes(WhatsAppContact $contact, string $text, string $word): bool
    {
        $inFlow = in_array($contact->bot_state, self::STATES, true);
        $cart = $this->cart($contact);

        // Cart commands work from anywhere while a cart exists.
        if (! $inFlow) {
            if (! $cart || empty($cart['items'])) {
                return false;
            }
            return match ($word) {
                'cart', 'view cart', 'my cart' => $this->showCart($contact, $cart) || true,
                'checkout', 'check out' => $this->checkout($contact, $cart) || true,
                'clear', 'clear cart', 'empty cart' => $this->clear($contact) || true,
                default => false,
            };
        }
        if (! $cart) {
            $this->save($contact, null, null);

            return false;
        }

        // Common commands in every step of the flow.
        switch ($word) {
            case 'cart':
            case 'view cart':
            case 'my cart':
                $this->showCart($contact, $cart);

                return true;
            case 'clear':
            case 'clear cart':
            case 'empty cart':
                $this->clear($contact);

                return true;
            case 'cancel':
            case 'cancel order':
            case 'stop ordering':
                $this->save($contact, null, $cart);
                $this->say($contact, 'Okay, I stopped checkout. Your cart is saved: reply CART to see it, CHECKOUT to continue, or CLEAR to empty it. Reply MENU for other options.');

                return true;
            case 'menu items':
            case 'browse':
            case 'add more':
            case 'back to menu':
                $this->save($contact, 'order_menu', $cart);
                $this->showMenu($contact, $cart);

                return true;
        }

        return match ($contact->bot_state) {
            'order_menu' => $this->menuInput($contact, $cart, $text, $word),
            'order_address' => $this->addressInput($contact, $cart, $text, $word),
            'order_coupon' => $this->couponInput($contact, $cart, $text, $word),
            'order_payment' => $this->paymentInput($contact, $cart, $word),
            'order_confirm' => $this->confirmInput($contact, $cart, $word),
            default => false,
        };
    }

    // ---- Menu -----------------------------------------------------------------------------------------------

    private function menuQuery(array $cart)
    {
        return MenuItem::where('restaurant_id', $cart['restaurant_id'])->where('is_available', true)
            ->where(fn ($q) => $q->whereNull('approval_status')->orWhere('approval_status', 'approved'))
            ->when($cart['search'], fn ($q, $s) => $q->where('name', 'like', '%' . $s . '%'))
            ->orderByDesc('is_bestseller')->orderByDesc('total_orders')->orderBy('name');
    }

    private function showMenu(WhatsAppContact $contact, array $cart): void
    {
        $query = $this->menuQuery($cart);
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / self::PAGE));
        $page = max(1, min($pages, (int) $cart['page']));
        $items = (clone $query)->forPage($page, self::PAGE)->get()->filter(fn (MenuItem $i) => $i->is_scheduled_available)->values();

        if ($items->isEmpty()) {
            $this->say($contact, $cart['search']
                ? "I couldn't find \"{$cart['search']}\" on this menu. Try another word, or reply BROWSE to see the full menu."
                : "This restaurant has nothing available to order right now. Reply MENU for other options.");

            return;
        }

        $cart['page'] = $page;
        $cart['listed'] = $items->pluck('id')->all();
        $this->save($contact, 'order_menu', $cart);

        $lines = $items->map(function (MenuItem $i, $n) {
            $diet = ($i->food_type ?: ($i->is_veg ? 'veg' : 'non_veg')) === 'veg' ? '🟢' : '🔴';
            $options = $this->needsOptions($i) ? ' ⚙️ (choose options in the app)' : '';

            return ($n + 1) . '. ' . $diet . ' ' . $i->name . ' – ' . $this->money($i->getFinalPriceAttribute()) . $options;
        })->implode("\n");

        $this->say($contact, "🍴 {$cart['restaurant_name']}" . ($cart['search'] ? " · \"{$cart['search']}\"" : '') . " (page {$page}/{$pages})\n\n{$lines}\n\n"
            . "To add: reply the item number, or number and quantity, e.g. 2 or 2x3. PHOTO <number> shows a picture.\n"
            . ($page < $pages ? 'MORE = next page · ' : '') . ($page > 1 ? 'BACK = previous · ' : '') . "SEARCH <word> · CART · CHECKOUT");
    }

    private function menuInput(WhatsAppContact $contact, array $cart, string $text, string $word): bool
    {
        if (in_array($word, ['text menu', 'menu text', 'numbered menu', 'list menu'], true)) {
            $this->showMenu($contact, $cart);

            return true;
        }
        if (in_array($word, ['more', 'next', 'next page'], true)) {
            $cart['page']++;
            $this->showMenu($contact, $cart);

            return true;
        }
        if (in_array($word, ['back', 'prev', 'previous'], true)) {
            $cart['page'] = max(1, $cart['page'] - 1);
            $this->showMenu($contact, $cart);

            return true;
        }
        if (preg_match('/^(?:search|find)\s+(.{2,40})$/i', $text, $m)) {
            $cart['search'] = trim($m[1]);
            $cart['page'] = 1;
            $this->showMenu($contact, $cart);

            return true;
        }
        if (preg_match('/^(?:photo|pic|picture|image)\s+(\d{1,2})$/i', $word, $m)) {
            return $this->showPhoto($contact, $cart, (int) $m[1]) || true;
        }
        if (in_array($word, ['checkout', 'check out', 'done', 'order', 'place order'], true)) {
            return $this->checkout($contact, $cart) || true;
        }
        if (preg_match('/^remove\s+(\d+)$/i', $word, $m)) {
            return $this->remove($contact, $cart, (int) $m[1]) || true;
        }
        // "2", "2x3", "2 x 3", "add 2", "add 2 3"
        if (preg_match('/^(?:add\s+)?(\d{1,2})(?:\s*[x×*]\s*|\s+)?(\d{1,2})?$/i', $word, $m)) {
            return $this->addItem($contact, $cart, (int) $m[1], isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1) || true;
        }

        $this->say($contact, "I didn't get that. Reply an item number to add it (e.g. 2 or 2x3), MORE for the next page, SEARCH <word>, CART, or CHECKOUT.");

        return true;
    }

    /** "PHOTO 3": the dish picture with its price and description. */
    private function showPhoto(WhatsAppContact $contact, array $cart, int $number): bool
    {
        $id = $cart['listed'][$number - 1] ?? null;
        $item = $id ? MenuItem::find($id) : null;
        if (! $item) {
            $this->say($contact, 'That number is not on this page. Reply a number from the list above.');

            return true;
        }
        $diet = ($item->food_type ?: ($item->is_veg ? 'veg' : 'non_veg')) === 'veg' ? '🟢 Veg' : '🔴 Non-veg';
        $caption = "{$item->name} · " . $this->money($item->getFinalPriceAttribute()) . " · {$diet}"
            . ($item->description ? "\n" . mb_substr(trim((string) $item->description), 0, 350) : '')
            . "\n\nReply {$number} to add it" . ($this->needsOptions($item) ? ' (this one has options: order it in the app)' : '') . '.';
        $this->say($contact, $caption, $item->image_url);

        return true;
    }

    private function needsOptions(MenuItem $item): bool
    {
        return ! empty($item->variants);
    }

    private function addItem(WhatsAppContact $contact, array $cart, int $number, int $qty): bool
    {
        $id = $cart['listed'][$number - 1] ?? null;
        $item = $id ? MenuItem::find($id) : null;
        if (! $item) {
            $this->say($contact, 'That number is not on this page. Reply a number from the list above, or MORE for more items.');

            return true;
        }
        if ($this->needsOptions($item)) {
            $this->say($contact, "{$item->name} has size or variant options that can only be chosen in the app: " . route('restaurant.show', Restaurant::find($cart['restaurant_id'])->slug) . "\nPlease pick another item, or reply CHECKOUT.");

            return true;
        }
        $qty = max(1, min(self::MAX_QTY, $qty));

        $found = false;
        foreach ($cart['items'] as &$line) {
            if ((int) $line['id'] === (int) $item->id) {
                $line['qty'] = min(self::MAX_QTY, $line['qty'] + $qty);
                $found = true;
            }
        }
        unset($line);
        if (! $found) {
            $cart['items'][] = ['id' => $item->id, 'name' => $item->name, 'price' => $item->getFinalPriceAttribute(), 'qty' => $qty];
        }
        $this->save($contact, 'order_menu', $cart);

        $this->say($contact, "✅ Added {$qty} × {$item->name}.\n🛒 " . $this->count($cart) . ' item(s) · ' . $this->money($this->itemsTotal($cart))
            . " (before delivery & taxes)\n\nMORE for more items, CART to review, or CHECKOUT to order.", $item->image_url);

        return true;
    }

    private function remove(WhatsAppContact $contact, array $cart, int $number): bool
    {
        if (! isset($cart['items'][$number - 1])) {
            $this->say($contact, 'There is no item with that number in your cart. Reply CART to see it.');

            return true;
        }
        $gone = $cart['items'][$number - 1]['name'];
        array_splice($cart['items'], $number - 1, 1);
        $this->save($contact, $contact->bot_state, $cart);
        $this->say($contact, "Removed {$gone}.");
        $this->showCart($contact, $cart);

        return true;
    }

    // ---- Cart ---------------------------------------------------------------------------------------------------

    private function showCart(WhatsAppContact $contact, array $cart): bool
    {
        if (empty($cart['items'])) {
            $this->say($contact, 'Your cart is empty. Pick a restaurant (reply 3) and add some items.');

            return true;
        }
        $lines = collect($cart['items'])->map(fn ($l, $n) => ($n + 1) . '. ' . $l['qty'] . ' × ' . $l['name'] . ' – ' . $this->money($l['price'] * $l['qty']))->implode("\n");
        $this->say($contact, "🛒 Your cart · {$cart['restaurant_name']}\n\n{$lines}\n\nItems total: " . $this->money($this->itemsTotal($cart))
            . "\n\nREMOVE <number> · MORE to add items · CHECKOUT to order · CLEAR to empty");

        return true;
    }

    private function clear(WhatsAppContact $contact): bool
    {
        $this->save($contact, null, null);
        $this->say($contact, 'Your cart is empty now. Reply 3 to order food, or MENU for other options.');

        return true;
    }

    // ---- Checkout ----------------------------------------------------------------------------------------------------

    private function checkout(WhatsAppContact $contact, array $cart): bool
    {
        if (empty($cart['items'])) {
            $this->say($contact, 'Your cart is empty. Reply the number of an item to add it first.');

            return true;
        }
        $user = $this->user($contact);
        if (! $user) {
            $this->login->offer($contact, 'To place your order I need your account. Your cart is saved.');

            return true;
        }

        $saved = Address::where('user_id', $user->id)->orderByDesc('is_default')->latest('id')->limit(10)->get();
        $cart['saved_addresses'] = $saved->pluck('id')->all();
        $this->save($contact, 'order_address', $cart);
        $this->askAddress($contact, $cart, $saved->isNotEmpty());

        return true;
    }

    /** "Where should we deliver?" with three taps: detect my location, saved address, type an address. */
    private function askAddress(WhatsAppContact $contact, array $cart, bool $hasSaved): void
    {
        $pin = ! empty($cart['lat']) ? "\nI'll use the location pin you shared earlier unless you send a new one." : '';
        $fallback = "📍 Where should we deliver?\n\nReply:\n*DETECT* – share my current location (📎 → Location)\n"
            . ($hasSaved ? "*SAVED* – pick one of my saved addresses\n" : '') . "*TYPE* – type a new address{$pin}";
        $buttons = [['type' => 'reply', 'reply' => ['id' => 'say:detect', 'title' => '📍 Detect my location']]];
        if ($hasSaved) {
            $buttons[] = ['type' => 'reply', 'reply' => ['id' => 'say:saved', 'title' => '🏠 Saved address']];
        }
        $buttons[] = ['type' => 'reply', 'reply' => ['id' => 'say:type', 'title' => '✍️ Type address']];

        $this->whatsapp->sendInteractive($contact->phone, [
            'type' => 'button',
            'body' => ['text' => "📍 Where should we deliver?{$pin}"],
            'action' => ['buttons' => $buttons],
        ], $fallback, 'bot');
    }

    /** Asks the phone to share its location with one tap (WhatsApp's own "Send location" button). */
    private function requestLocation(WhatsAppContact $contact): void
    {
        $this->whatsapp->sendInteractive($contact->phone, [
            'type' => 'location_request_message',
            'body' => ['text' => '📍 Tap the button to share your current location. The rider will use it as the delivery pin.'],
            'action' => ['name' => 'send_location'],
        ], '📍 Share your current location: tap the 📎 (attach) icon → Location → Send your current location.', 'bot');
    }

    /** The customer's saved addresses as a tappable list (numbered text when lists are unavailable). */
    private function showSavedAddresses(WhatsAppContact $contact, array $cart): void
    {
        $ids = $cart['saved_addresses'] ?? [];
        $saved = Address::whereIn('id', $ids)->get()->sortBy(fn ($a) => array_search($a->id, $ids))->values();
        if ($saved->isEmpty()) {
            $this->say($contact, 'You have no saved addresses yet. Reply DETECT to share your location, or type your full address.');

            return;
        }
        $lines = $saved->map(fn (Address $a, $n) => ($n + 1) . '. ' . trim($a->name . ' · ' . $a->address . ', ' . $a->city, ' ,·'))->implode("\n");
        $rows = $saved->map(fn (Address $a, $n) => [
            'id' => 'say:' . ($n + 1),
            'title' => mb_substr(trim($a->name ?: 'Address ' . ($n + 1)), 0, 24),
            'description' => mb_substr(trim($a->address . ', ' . $a->city, ' ,'), 0, 72),
        ])->all();

        $this->whatsapp->sendInteractive($contact->phone, [
            'type' => 'list',
            'body' => ['text' => '🏠 Choose a saved address for delivery.'],
            'action' => ['button' => 'Choose address', 'sections' => [['title' => 'Saved addresses', 'rows' => $rows]]],
        ], "🏠 Your saved addresses:\n\n{$lines}\n\nReply 1" . ($saved->count() > 1 ? '-' . $saved->count() : '') . ' to use one.', 'bot');
    }

    private function addressInput(WhatsAppContact $contact, array $cart, string $text, string $word): bool
    {
        // "OK" accepts the address read from the shared location pin.
        if (in_array($word, ['ok', 'okay', 'yes', 'same', 'use it', 'use this'], true) && ! empty($cart['suggested'])) {
            $cart['address'] = mb_substr($cart['suggested'], 0, 300);
            $cart['address_id'] = null;
            $this->save($contact, 'order_address', $cart);
            $this->afterAddress($contact, $cart);

            return true;
        }

        if ($word === 'detect' || $word === 'detect my location') {
            $this->requestLocation($contact);

            return true;
        }
        if ($word === 'saved' || $word === 'saved address') {
            $this->showSavedAddresses($contact, $cart);

            return true;
        }
        if ($word === 'type' || $word === 'type address') {
            $this->say($contact, '✍️ Please type your full delivery address (house or flat number, building, area, landmark).');

            return true;
        }

        // a saved address
        if (preg_match('/^\d{1,2}$/', $word) && isset($cart['saved_addresses'][(int) $word - 1])) {
            $address = Address::find($cart['saved_addresses'][(int) $word - 1]);
            if ($address) {
                $cart['address_id'] = $address->id;
                $cart['address'] = trim($address->address . ', ' . $address->city);
                if ($address->latitude && $address->longitude) {
                    $cart['lat'] = (float) $address->latitude;
                    $cart['lng'] = (float) $address->longitude;
                }
                $this->save($contact, 'order_address', $cart);
                if (empty($cart['lat'])) {
                    $this->say($contact, '📍 Please share your location pin too (📎 → Location) so the rider can find you.');

                    return true;
                }
                $this->afterAddress($contact, $cart);

                return true;
            }
        }

        if (mb_strlen($text) < 8) {
            $this->say($contact, 'Please type the full address (house or flat number, building, area, landmark), at least a few words.');

            return true;
        }
        $cart['address'] = mb_substr($text, 0, 300);
        $cart['address_id'] = null;
        $this->save($contact, 'order_address', $cart);

        if (empty($cart['lat'])) {
            $this->say($contact, '📍 Thanks. Now please share your location pin (📎 → Location) so the rider can find you.');

            return true;
        }
        $this->afterAddress($contact, $cart);

        return true;
    }

    private function afterAddress(WhatsAppContact $contact, array $cart): void
    {
        $this->save($contact, 'order_coupon', $cart);
        $this->say($contact, "🎟️ Do you have a coupon code? Type it now, or reply SKIP.");
    }

    private function couponInput(WhatsAppContact $contact, array $cart, string $text, string $word): bool
    {
        if (in_array($word, ['skip', 'no', 'none', 'n'], true)) {
            $cart['coupon'] = null;
        } else {
            $cart['coupon'] = strtoupper(trim($text));
            $check = $this->price($contact, $cart);
            if (! $check['success']) {
                $this->say($contact, '⚠️ ' . $check['message'] . "\nType another code, or reply SKIP.");

                return true;
            }
        }
        $this->save($contact, 'order_payment', $cart);
        $this->askPayment($contact, $cart);

        return true;
    }

    // ---- Payment choice, confirm, place ----------------------------------------------------------------------------

    /** @return array<int, array{key:string,label:string}> */
    private function paymentOptions(WhatsAppContact $contact, array $cart): array
    {
        $options = [];
        if (filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            $options[] = ['key' => 'cod', 'label' => 'Cash on delivery'];
        }
        if (GatewayRegistry::customerPaymentsEnabled() && GatewayRegistry::availableCustomerPaymentGateway()) {
            $options[] = ['key' => 'online', 'label' => 'Pay online (UPI, cards, netbanking)'];
        }
        $user = $this->user($contact);
        $balance = (float) ($user?->wallet?->balance ?? 0);
        $price = $this->price($contact, $cart);
        if ($balance > 0 && $price['success'] && $balance >= (float) ($price['data']['total'] ?? PHP_INT_MAX)) {
            $options[] = ['key' => 'wallet', 'label' => 'Wallet (balance ' . $this->money($balance) . ')'];
        }

        return $options;
    }

    private function askPayment(WhatsAppContact $contact, array $cart): void
    {
        $options = $this->paymentOptions($contact, $cart);
        if (! $options) {
            $this->say($contact, "Sorry, no payment method is available right now. Please try again later or order in the app.");

            return;
        }
        $cart['payment_options'] = array_column($options, 'key');
        $this->save($contact, 'order_payment', $cart);
        $lines = collect($options)->map(fn ($o, $n) => ($n + 1) . '. ' . $o['label'])->implode("\n");
        $this->say($contact, "💳 How would you like to pay?\n\n{$lines}\n\nReply with the number.");
    }

    private function paymentInput(WhatsAppContact $contact, array $cart, string $word): bool
    {
        $keys = $cart['payment_options'] ?? [];
        $choice = null;
        if (ctype_digit($word) && isset($keys[(int) $word - 1])) {
            $choice = $keys[(int) $word - 1];
        } elseif (in_array($word, ['cod', 'cash', 'cash on delivery'], true) && in_array('cod', $keys, true)) {
            $choice = 'cod';
        } elseif (in_array($word, ['online', 'upi', 'card', 'pay online'], true) && in_array('online', $keys, true)) {
            $choice = 'online';
        } elseif ($word === 'wallet' && in_array('wallet', $keys, true)) {
            $choice = 'wallet';
        }
        if (! $choice) {
            $this->say($contact, 'Please reply with the number of a payment option from the list.');

            return true;
        }

        $cart['payment'] = $choice;
        $this->save($contact, 'order_confirm', $cart);
        $this->showConfirm($contact, $cart);

        return true;
    }

    private function showConfirm(WhatsAppContact $contact, array $cart): void
    {
        $price = $this->price($contact, $cart);
        if (! $price['success']) {
            $this->say($contact, '⚠️ ' . $price['message'] . "\nReply CART to change your items, or CANCEL.");

            return;
        }
        $d = $price['data'];
        if (isset($d['meets_minimum_order']) && $d['meets_minimum_order'] === false) {
            $this->say($contact, '⚠️ The minimum order here is ' . $this->money($d['minimum_order_amount'] ?? 0) . '. Add ' . $this->money($d['minimum_order_shortfall'] ?? 0) . " more. Reply MORE to add items.");
            $this->save($contact, 'order_menu', $cart);

            return;
        }

        $lines = collect($cart['items'])->map(fn ($l) => $l['qty'] . ' × ' . $l['name'])->implode(', ');
        $bill = ['Items' => $d['subtotal'] ?? null, 'Delivery' => $d['payable_delivery_fee'] ?? ($d['delivery_fee'] ?? null), 'Platform fee' => $d['platform_fee'] ?? null,
            'Taxes' => $d['tax'] ?? null, 'Discount' => isset($d['discount']) && $d['discount'] > 0 ? -1 * (float) $d['discount'] : null, 'Tip' => $d['tip'] ?? null];
        $billLines = collect($bill)->filter(fn ($v) => $v !== null && (float) $v != 0.0)->map(fn ($v, $k) => $k . ': ' . ((float) $v < 0 ? '−' . $this->money(abs((float) $v)) : $this->money($v)))->implode("\n");
        $method = ['cod' => 'Cash on delivery', 'online' => 'Pay online', 'wallet' => 'Wallet'][$cart['payment']] ?? $cart['payment'];

        $this->say($contact, "🧾 Please confirm your order\n\n{$cart['restaurant_name']}\n{$lines}\n\n{$billLines}\n*Total: " . $this->money($d['total'] ?? 0) . "*\n\n📍 {$cart['address']}" . (! empty($cart['lat']) ? "\n🗺️ Delivery pin: https://www.google.com/maps?q={$cart['lat']},{$cart['lng']} (send a new location any time to change it)" : '') . "\n💳 {$method}"
            . ($cart['coupon'] ? "\n🎟️ Coupon {$cart['coupon']}" : '') . "\n\nReply YES to place the order, or NO to stop.");
    }

    private function confirmInput(WhatsAppContact $contact, array $cart, string $word): bool
    {
        if (in_array($word, ['no', 'n', 'stop'], true)) {
            $this->save($contact, null, $cart);
            $this->say($contact, 'No problem, nothing was ordered. Your cart is saved: reply CART or CHECKOUT any time, or CLEAR to empty it.');

            return true;
        }
        if (! in_array($word, ['yes', 'y', 'confirm', 'place order', 'ok', 'okay'], true)) {
            $this->say($contact, 'Reply YES to place the order or NO to stop.');

            return true;
        }

        $user = $this->user($contact);
        if (! $user) {
            $this->say($contact, 'I could not find your account. Please order in the app.');

            return true;
        }

        $method = match ($cart['payment']) {
            'online' => GatewayRegistry::availableCustomerPaymentGateway() ?: 'razorpay',
            default => $cart['payment'],
        };
        $payload = [
            'restaurant_id' => $cart['restaurant_id'],
            'items' => collect($cart['items'])->map(fn ($l) => ['id' => $l['id'], 'quantity' => $l['qty']])->all(),
            'order_type' => 'delivery',
            'delivery_address' => $cart['address'],
            'delivery_lat' => $cart['lat'], 'delivery_lng' => $cart['lng'],
            'payment_method' => $method,
            'coupon_code' => $cart['coupon'],
            'customer_name' => $user->name,
            'customer_phone' => $user->phone ?: $contact->phone,
            'special_instructions' => 'Ordered through WhatsApp' . (! empty($cart['note']) ? '. Note: ' . mb_substr($cart['note'], 0, 300) : ''),
        ];
        if (! empty($cart['address_id'])) {
            $payload['delivery_address_id'] = $cart['address_id'];
        }

        // Online payment follows the app's pay-first checkout: nothing is created until the payment succeeds.
        if ($cart['payment'] === 'online') {
            $quote = $this->price($contact, $cart);
            $link = PublicPayment::createSession(
                $user, array_filter($payload, fn ($v) => $v !== null), (float) ($quote['data']['total'] ?? 0), (string) $cart['restaurant_name'], $contact->phone
            );
            $this->save($contact, null, $cart);   // the cart stays until the payment completes
            $this->say($contact, "💳 Almost there! Please pay " . $this->money($quote['data']['total'] ?? 0) . " securely to place your order (link valid for " . PublicPayment::MINUTES_VALID . " minutes):\n{$link}\n\n"
                . "Your order is placed the moment the payment succeeds, and I'll confirm it here. Your cart stays saved if you need to come back.");

            return true;
        }

        // The chat itself confirms a COD / wallet order, so the "order placed" template is held back.
        WhatsAppNotifier::$quietOrderPlaced = true;
        try {
            $result = $this->actions->placeOrder($user, array_filter($payload, fn ($v) => $v !== null));
        } finally {
            WhatsAppNotifier::$quietOrderPlaced = false;
        }

        if (! $result['success']) {
            $this->save($contact, 'order_confirm', $cart);
            $this->say($contact, '⚠️ ' . $result['message'] . "\nReply YES to try again, CART to change items, or CANCEL.");

            return true;
        }

        $orderData = $result['data']['order'] ?? [];
        $order = Order::find($orderData['id'] ?? null) ?: Order::where('order_number', $result['data']['order_number'] ?? '')->first();
        $this->save($contact, null, null);   // the order exists: the cart is done

        if (! $order) {
            $this->say($contact, '✅ Your order was placed. Reply 1 to track it.');

            return true;
        }

        $this->say($contact, "✅ Order placed! #{$order->order_number} · " . $this->money($order->total) . "\n{$cart['restaurant_name']} has been notified."
            . "\n\n📍 Track live: " . PublicTracking::url($order) . "\nReply RIDER for your rider's location, or CANCEL to cancel (only for a short time).");

        return true;
    }

    // ---- Helpers -------------------------------------------------------------------------------------------------------

    /** @return array{success: bool, message: string, data: array} */
    private function price(WhatsAppContact $contact, array $cart): array
    {
        $user = $this->user($contact);
        if (! $user) {
            return ['success' => false, 'message' => 'No account found for this number.', 'data' => []];
        }

        return $this->actions->priceOrder($user, array_filter([
            'restaurant_id' => $cart['restaurant_id'],
            'items' => collect($cart['items'])->map(fn ($l) => ['id' => $l['id'], 'quantity' => $l['qty']])->all(),
            'order_type' => 'delivery',
            'delivery_address_id' => $cart['address_id'] ?? null,
            'delivery_lat' => $cart['lat'] ?? null, 'delivery_lng' => $cart['lng'] ?? null,
            'coupon_code' => $cart['coupon'] ?? null,
        ], fn ($v) => $v !== null));
    }

    private function user(WhatsAppContact $contact): ?User
    {
        return $contact->user_id ? User::find($contact->user_id) : null;
    }

    private function cart(WhatsAppContact $contact): ?array
    {
        $cart = $contact->bot_context['cart'] ?? null;
        if (! is_array($cart)) {
            return null;
        }
        // An abandoned cart expires, so old prices and availability never get ordered by surprise.
        if (($cart['updated'] ?? 0) < now()->subHours(self::CART_TTL_HOURS)->timestamp) {
            return null;
        }

        return $cart;
    }

    /** Saves the state and the cart (null clears it) without touching the other conversation data. */
    private function save(WhatsAppContact $contact, ?string $state, ?array $cart): void
    {
        $context = $contact->bot_context ?? [];
        if ($cart === null) {
            unset($context['cart']);
        } else {
            $cart['updated'] = now()->timestamp;
            $context['cart'] = $cart;
        }
        $contact->forceFill(['bot_state' => $state, 'bot_context' => $context ?: null])->save();
    }

    private function count(array $cart): int
    {
        return (int) collect($cart['items'])->sum('qty');
    }

    private function itemsTotal(array $cart): float
    {
        return (float) collect($cart['items'])->sum(fn ($l) => $l['price'] * $l['qty']);
    }

    private function money($amount): string
    {
        return AppSetting::sanitizedCurrencySymbol() . number_format((float) $amount, AppSetting::currencyDecimals());
    }

    private function say(WhatsAppContact $contact, string $text, ?string $image = null): void
    {
        try {
            $image
                ? $this->whatsapp->sendImage($contact->phone, $image, $text, 'bot')
                : $this->whatsapp->sendText($contact->phone, $text, 'bot');
        } catch (\Throwable $e) {
            Log::warning('WhatsApp ordering reply failed.', ['phone' => $contact->phone, 'error' => $e->getMessage()]);
        }
    }
}
