<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DeliveryChargeSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PaymentAttempt;
use App\Models\Restaurant;
use App\Models\TaxSetting;
use App\Models\Theatre;
use App\Models\TheatreScreen;
use App\Models\TheatreShow;
use App\Models\Transaction;
use App\Services\OrderReleaseService;
use App\Services\Tax\TaxCalculationService;
use App\Services\WalletService;
use App\Support\TheatreSeatToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * swaad-only: the customer-facing API for movie-theatre in-seat food
 * ordering. Deliberately a separate, additive controller from
 * Api\OrderController rather than a modification of it -- that controller
 * is revenue-critical and every existing order still assumes exactly one
 * restaurant per order; theatre checkout can span several stalls under one
 * payment, so it gets its own endpoint and its own (simpler, no promotions
 * engine yet) pricing path instead of forcing that assumption to bend.
 *
 * See the movie-theatre-in-seat-ordering project memory / plan for the full
 * design. This is Phase 3: the customer ordering flow + the Order Group
 * checkout engine.
 */
class TheatreController extends Controller
{
    /**
     * Theatres near a point, each with a computed distance and whether the
     * point is inside THAT theatre's own geofence -- a pure UX signal for
     * the app to show "you're at X" or a pick list. Never trusted for the
     * actual order-placement gate; see checkout() for the real check.
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $theatres = Theatre::where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (Theatre $theatre) use ($validated) {
                $distance = $this->haversineMeters(
                    (float) $validated['lat'],
                    (float) $validated['lng'],
                    (float) $theatre->latitude,
                    (float) $theatre->longitude
                );

                return [
                    'id' => $theatre->id,
                    'name' => $theatre->name,
                    'code' => $theatre->code,
                    'address' => $theatre->address,
                    'city' => $theatre->city,
                    'logo' => $theatre->logo,
                    'distance_meters' => round($distance, 1),
                    'within_geofence' => $distance <= $theatre->geofence_radius_meters,
                    'aisle_handoff_only' => (bool) $theatre->aisle_handoff_only,
                    'tags' => is_array($theatre->tags) ? $theatre->tags : (json_decode($theatre->tags ?? '[]', true) ?: []),
                ];
            })
            ->sortBy('distance_meters')
            ->take(20)
            ->values();

        return response()->json(['success' => true, 'data' => $theatres]);
    }

    /**
     * One theatre by id, with no location required. The nearby() listing
     * cannot serve the QR-scan flow: a scanned sticker already names its
     * theatre, and the customer's phone may well be refusing location
     * inside a cinema -- which is exactly the case the QR exists for.
     */
    public function show(Theatre $theatre)
    {
        abort_unless($theatre->status === 'active', 404);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $theatre->id,
                'name' => $theatre->name,
                'code' => $theatre->code,
                'address' => $theatre->address,
                'city' => $theatre->city,
                'logo' => $theatre->logo,
                'distance_meters' => null,
                'within_geofence' => true,
                'aisle_handoff_only' => (bool) $theatre->aisle_handoff_only,
                'tags' => is_array($theatre->tags) ? $theatre->tags : (json_decode($theatre->tags ?? '[]', true) ?: []),
            ],
        ]);
    }

    public function screens(Theatre $theatre)
    {
        abort_unless($theatre->status === 'active', 404);

        $screens = $theatre->screens()
            ->where('is_active', true)
            ->whereNotNull('seat_map_config')
            ->orderBy('name')
            ->get(['id', 'theatre_id', 'name', 'seat_map_config']);

        return response()->json(['success' => true, 'data' => $screens]);
    }

    /**
     * What is playing on this screen, for the timing choice at checkout.
     * Only screenings that are still accepting orders are returned -- a
     * show the customer can no longer order for is noise on a phone held
     * in a dark auditorium.
     */
    public function shows(TheatreScreen $screen)
    {
        abort_unless($screen->is_active, 404);

        $shows = $screen->shows()
            ->live()
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (TheatreShow $show) => $show->isAcceptingOrders())
            ->map(fn (TheatreShow $show) => $this->showPayload($show))
            ->values();

        return response()->json(['success' => true, 'data' => $shows]);
    }

    private function showPayload(TheatreShow $show): array
    {
        return [
            'id' => $show->id,
            'movie_title' => $show->movie_title,
            'language' => $show->language,
            'starts_at' => $show->starts_at?->toIso8601String(),
            'interval_at' => $show->interval_at?->toIso8601String(),
            'ends_at' => $show->ends_at?->toIso8601String(),
            'interval_available' => $show->canDeliverAtInterval(TheatreShow::RELEASE_LEAD_MINUTES),
            'order_cutoff_at' => $show->orderCutoffAt()->toIso8601String(),
        ];
    }

    public function restaurants(Theatre $theatre)
    {
        abort_unless($theatre->status === 'active', 404);

        $restaurants = $theatre->restaurants()
            ->wherePivot('is_active', true)
            ->where('restaurants.is_open', true)
            ->get(['restaurants.id', 'restaurants.name', 'restaurants.logo_image', 'restaurants.cuisine', 'restaurants.rating', 'restaurants.is_open']);

        return response()->json(['success' => true, 'data' => $restaurants]);
    }

    /**
     * Creates a real gateway payment session (Razorpay order / Cashfree
     * order / Stripe PaymentIntent) for the SERVER-COMPUTED group total of
     * a not-yet-placed theatre cart, so the app can run the real gateway
     * SDK before calling checkout() with the resulting proof. Additive to
     * checkout() -- reuses the exact same validation/pricing helpers so the
     * amount charged here is always identical to what checkout() will
     * re-verify, and re-prices from scratch rather than trusting a client-
     * sent total (the cart may be stale by the time the customer pays).
     */
    public function paymentSession(Request $request)
    {
        $validator = Validator::make($request->all(), $this->checkoutValidationRules(requirePaymentProof: false));
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if (! in_array($request->payment_method, ['razorpay', 'stripe', 'cashfree'], true)) {
            return response()->json(['success' => false, 'message' => 'Choose Razorpay, Stripe, or Cashfree for an online payment session.'], 422);
        }

        try {
            [$theatre, $screen] = $this->resolveTheatreAndScreen($request);
            $this->resolveShowAndTiming($request, $screen);
            [, $groupTotal] = $this->priceStalls($theatre, $request->stalls);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        try {
            $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
            $session = app(PaymentController::class)->createCheckoutPaymentSessionForOrder(
                $request,
                $request->payment_method,
                $groupTotal,
                $currency
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Could not start payment.'], 502);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($session, [
                'gateway' => $request->payment_method,
                'amount' => $groupTotal,
            ]),
        ]);
    }

    /**
     * The Order Group checkout engine. Validates everything (presence,
     * seat, every stall, every item) before charging anything, takes ONE
     * payment for the combined total, then -- inside one DB transaction --
     * creates a real `orders` row per stall, each pointing at the same
     * OrderGroup. Each stall's restaurant only ever sees its own order row;
     * nothing here couples them together beyond that shared group id, which
     * is also what keeps a later partial cancellation/refund of just one
     * stall's order naturally isolated -- there is no code path that acts
     * on "the whole group" once orders exist, only ever on one Order row.
     */
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), $this->checkoutValidationRules(requirePaymentProof: false));

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            [$theatre, $screen] = $this->resolveTheatreAndScreen($request);
            [$show, $timing, $scheduledFor] = $this->resolveShowAndTiming($request, $screen);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($request->payment_method === 'cod'
            && ! filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['success' => false, 'message' => 'Cash on Delivery is currently unavailable. Please choose an online payment method or Wallet.'], 422);
        }

        // Price every stall server-side BEFORE touching the database --
        // never trust client-sent prices, and never charge for a stall that
        // turns out not to be valid. Re-priced from scratch even for a
        // gateway payment (whose session was already priced identically by
        // paymentSession() above) rather than trusting a client-sent total.
        try {
            [$priced, $groupTotal] = $this->priceStalls($theatre, $request->stalls);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($groupTotal <= 0) {
            return response()->json(['success' => false, 'message' => 'Nothing to order.'], 422);
        }

        $user = $request->user();

        DB::beginTransaction();
        try {
            $orderGroup = OrderGroup::create([
                'group_number' => $this->generateGroupNumber(),
                'user_id' => $user->id,
                'theatre_id' => $theatre->id,
                'screen_id' => $screen->id,
                'show_id' => $show?->id,
                'seat_label' => $request->seat_label,
                'delivery_timing' => $timing,
                'scheduled_for' => $scheduledFor,
                'total_amount' => $groupTotal,
                'payment_status' => 'pending',
            ]);

            $seatDescription = "{$theatre->name} — {$screen->name} — Seat {$request->seat_label}";
            $orders = [];

            foreach ($priced as $stall) {
                $orders[] = Order::create([
                    'order_number' => $this->generateOrderNumber(),
                    'customer_id' => $user->id,
                    'restaurant_id' => $stall['restaurant']->id,
                    'theatre_id' => $theatre->id,
                    'screen_id' => $screen->id,
                    'show_id' => $show?->id,
                    'seat_label' => $request->seat_label,
                    'delivery_timing' => $timing,
                    'scheduled_for' => $scheduledFor,
                    'order_group_id' => $orderGroup->id,
                    'order_type' => 'theatre_delivery',
                    'items' => $stall['lines'],
                    'subtotal' => $stall['subtotal'],
                    'delivery_fee' => 0,
                    'platform_fee' => $stall['platform_fee'],
                    'tax' => $stall['tax'],
                    'tax_breakdown' => $stall['tax_breakdown'],
                    'total' => $stall['total'],
                    'payment_method' => $request->payment_method,
                    'payment_status' => 'pending',
                    'customer_name' => $user->name,
                    'customer_phone' => $user->phone,
                    'customer_address' => $seatDescription,
                    'delivery_address' => $seatDescription,
                    'status' => 'pending',
                ]);
            }

            // Take payment for the COMBINED total, once -- not per stall.
            $verifiedPaymentId = null;
            if ($request->payment_method === 'wallet') {
                $debited = app(WalletService::class)->debitInstant(
                    $user,
                    $groupTotal,
                    'order_group',
                    $orderGroup->id,
                    "Theatre order {$orderGroup->group_number}"
                );
                if (! $debited) {
                    throw new \RuntimeException('Insufficient wallet balance.');
                }
                $verifiedPaymentId = 'wallet';
            } elseif (in_array($request->payment_method, ['razorpay', 'stripe', 'cashfree'], true)) {
                $verifiedPaymentId = app(PaymentController::class)
                    ->verifyCheckoutPaymentForOrder($request, $request->payment_method, $groupTotal);

                Transaction::firstOrCreate(
                    ['order_id' => $orders[0]->id, 'transaction_id' => $verifiedPaymentId],
                    [
                        'user_id' => $user->id,
                        'amount' => $groupTotal,
                        'type' => 'payment',
                        'status' => 'success',
                        'payment_method' => $request->payment_method,
                    ]
                );
                PaymentAttempt::create([
                    'order_id' => $orders[0]->id,
                    'gateway' => $request->payment_method,
                    'source' => PaymentAttempt::SOURCE_CUSTOMER_APP,
                    'status' => PaymentAttempt::STATUS_SUCCESS,
                    'amount' => $groupTotal,
                    'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                    'transaction_id' => $verifiedPaymentId,
                    'created_by' => $user->id,
                ]);
            }
            // cod: no payment taken now, both the group and every order stay
            // payment_status=pending until the runner collects on delivery,
            // same as a normal COD order.

            // Reaching this line means payment already succeeded for every
            // non-cod method (both branches above throw on failure, which
            // rolls back everything before we ever get here).
            if ($request->payment_method !== 'cod') {
                foreach ($orders as $order) {
                    $order->update(['payment_status' => 'success']);
                }
                $orderGroup->update(['payment_status' => 'success', 'payment_reference' => $verifiedPaymentId]);
            }

            DB::commit();

            // Release each stall's order to ITS OWN restaurant -- same
            // real-time broadcast/push/auto-print/ledger/email pipeline a
            // normal single-restaurant order goes through
            // (Api\OrderController does the identical call in the identical
            // place: after commit, outside the transaction, non-fatal on
            // failure). Each restaurant only ever sees its own order, never
            // its sibling stalls' orders in the same group.
            //
            // An interval order is the exception: it is paid for and real,
            // but the kitchen must not see it yet or a dozen of them get
            // cooked an hour early and arrive cold. It stays held
            // (released_at null) until ReleaseHeldTheatreOrders hands it
            // over a prep-time before the interval.
            if ($timing !== 'interval') {
                foreach ($orders as $order) {
                    try {
                        app(OrderReleaseService::class)->releaseToRestaurant($order);
                        $order->forceFill(['released_at' => now()])->save();
                    } catch (\Throwable $e) {
                        Log::warning('Theatre order restaurant release failed after placement.', [
                            'order_id' => $order->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => $timing === 'interval'
                    ? 'Order placed. It will be made fresh and brought to you at the interval.'
                    : 'Order placed.',
                'data' => [
                    'order_group_id' => $orderGroup->id,
                    'group_number' => $orderGroup->group_number,
                    'total' => $groupTotal,
                    'delivery_timing' => $timing,
                    'scheduled_for' => $scheduledFor?->toIso8601String(),
                    'order_ids' => collect($orders)->pluck('id'),
                ],
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Could not place this order. Please try again.'], 500);
        }
    }

    public function showOrderGroup(Request $request, OrderGroup $orderGroup)
    {
        abort_unless((int) $orderGroup->user_id === (int) $request->user()->id, 403);

        $orderGroup->load(['theatre:id,name,aisle_handoff_only', 'screen:id,name']);
        $orders = Order::with('restaurant:id,name,logo_image')
            ->where('order_group_id', $orderGroup->id)
            ->get(['id', 'order_number', 'restaurant_id', 'status', 'payment_status', 'total', 'items']);

        return response()->json([
            'success' => true,
            'data' => [
                'order_group' => $orderGroup,
                'orders' => $orders,
            ],
        ]);
    }

    /**
     * Shared validation rules for both checkout() and paymentSession() --
     * they must accept the exact same cart shape so a payment session's
     * amount is guaranteed reproducible by checkout()'s own re-pricing.
     */
    private function checkoutValidationRules(bool $requirePaymentProof): array
    {
        return [
            'theatre_id' => ['required', 'exists:theatres,id'],
            'screen_id' => ['required', 'exists:theatre_screens,id'],
            'seat_label' => ['required', 'string', 'max:20'],
            // Presence is proven EITHER by a scanned seat QR's signature or
            // by GPS, never by neither -- so each is only required when the
            // other is absent. See resolveTheatreAndScreen().
            'seat_token' => ['nullable', 'string', 'max:64', 'required_without:lat'],
            'lat' => ['required_without:seat_token', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_with:lat', 'nullable', 'numeric', 'between:-180,180'],
            // Show times are optional everywhere: a screen with no
            // schedule loaded still takes deliver-now orders, which is the
            // behaviour every theatre had before shows existed.
            'show_id' => ['nullable', 'integer', 'exists:theatre_shows,id'],
            'delivery_timing' => ['nullable', 'in:asap,interval'],
            'stalls' => ['required', 'array', 'min:1'],
            'stalls.*.restaurant_id' => ['required', 'exists:restaurants,id'],
            'stalls.*.items' => ['required', 'array', 'min:1'],
            'stalls.*.items.*.id' => ['required', 'exists:menu_items,id'],
            'stalls.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'stalls.*.items.*.selected_variant' => ['nullable', 'array'],
            'stalls.*.items.*.selected_add_ons' => ['nullable', 'array'],
            'payment_method' => ['required', 'in:cod,wallet,razorpay,stripe,cashfree'],
            'payment_id' => [$requirePaymentProof ? 'nullable' : 'nullable', 'string'],
            'razorpay_order_id' => ['nullable', 'string'],
            'razorpay_signature' => ['nullable', 'string'],
            'stripe_payment_intent_id' => ['nullable', 'string'],
        ];
    }

    /**
     * Theatre-active + real server-side geofence + screen/seat-map checks,
     * shared by checkout() and paymentSession(). Throws (never returns a
     * response itself) so both callers can format the 422 the same way.
     */
    private function resolveTheatreAndScreen(Request $request): array
    {
        $theatre = Theatre::find($request->theatre_id);
        if (! $theatre || $theatre->status !== 'active') {
            throw new \RuntimeException('This theatre is not accepting orders right now.');
        }

        $screen = TheatreScreen::where('theatre_id', $theatre->id)->where('is_active', true)->find($request->screen_id);
        if (! $screen || ! $screen->hasSeatMap()) {
            throw new \RuntimeException('This screen is not available for ordering.');
        }

        if (! $screen->hasSeat((string) $request->seat_label)) {
            throw new \RuntimeException('That seat could not be found on this screen.');
        }

        // The real presence check. Two ways to pass it, checked only after
        // the seat itself is known to be real so a forged token can never
        // reach a seat that doesn't exist:
        //
        //  1. A scanned QR's signature. The sticker is physically on that
        //     armrest, so this is stronger evidence than a coordinate and
        //     replaces the geofence rather than adding to it -- which also
        //     means the web flow works for a customer whose browser denies
        //     location, the common case mid-film.
        //  2. GPS inside the theatre's own radius, for the app's manual
        //     pick-a-theatre-and-seat flow where there is no QR.
        $scanned = TheatreSeatToken::verify(
            (int) $theatre->id,
            (int) $screen->id,
            (string) $request->seat_label,
            $request->input('seat_token')
        );

        if (! $scanned) {
            if ($request->filled('seat_token')) {
                throw new \RuntimeException('That seat code is not valid for this screen. Please scan the QR on your seat again.');
            }

            if (! $theatre->containsPoint((float) $request->lat, (float) $request->lng)) {
                throw new \RuntimeException('You need to be at ' . $theatre->name . ' to order here.');
            }
        }

        return [$theatre, $screen];
    }

    /**
     * Resolves the screening this order belongs to and when the food is
     * wanted. Returns [show|null, timing, scheduledFor|null].
     *
     * Ordering closes shortly before the credits so nothing is cooked for
     * an auditorium that is emptying, and "at the interval" is only
     * allowed while there is still time to actually make the food -- both
     * checked here rather than trusted from the client, which may have had
     * the show list cached since before the trailers.
     */
    private function resolveShowAndTiming(Request $request, TheatreScreen $screen): array
    {
        $timing = strtolower((string) $request->input('delivery_timing', 'asap')) === 'interval'
            ? 'interval'
            : 'asap';

        $show = null;
        if ($request->filled('show_id')) {
            $show = TheatreShow::where('screen_id', $screen->id)->find($request->input('show_id'));
            if (! $show) {
                throw new \RuntimeException('That show is not playing on this screen.');
            }
            if (! $show->isAcceptingOrders()) {
                throw new \RuntimeException('Orders for this show have closed.');
            }
        }

        if ($timing === 'asap') {
            return [$show, 'asap', null];
        }

        if (! $show) {
            throw new \RuntimeException('Pick a show before choosing interval delivery.');
        }

        if (! $show->canDeliverAtInterval(TheatreShow::RELEASE_LEAD_MINUTES)) {
            throw new \RuntimeException('The interval is too close now — please choose "deliver now" instead.');
        }

        return [$show, 'interval', $show->interval_at];
    }

    /**
     * Prices every stall server-side -- never trust client-sent prices --
     * shared by checkout() and paymentSession() so a gateway session's
     * amount and checkout()'s final charge are always computed identically.
     */
    private function priceStalls(Theatre $theatre, array $stalls): array
    {
        $priced = [];
        foreach ($stalls as $index => $stall) {
            $restaurant = $theatre->restaurants()->wherePivot('is_active', true)->where('restaurants.is_open', true)->find($stall['restaurant_id']);
            if (! $restaurant) {
                throw new \RuntimeException("Stall #{$index} is not available at this theatre right now.");
            }

            $lines = $this->priceStallItems($stall['items']);
            if ($lines === null) {
                throw new \RuntimeException("One of the items for {$restaurant->name} is no longer available.");
            }

            $subtotal = round(collect($lines)->sum('total'), 2);
            $platformFee = DeliveryChargeSetting::getPlatformFee();

            $rawLines = collect($lines)->map(fn ($line) => [
                'menu_item_id' => $line['menu_item_id'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'line_total' => $line['total'],
            ])->all();

            $tax = round((float) TaxSetting::calculateTax($subtotal, 0), 2);
            $taxBreakdown = null;
            $taxOverride = app(TaxCalculationService::class)->override($restaurant, $rawLines, [
                'delivery_fee' => 0,
                'platform_fee' => $platformFee,
            ]);
            if ($taxOverride) {
                $tax = $taxOverride->tax;
                $taxBreakdown = $taxOverride->detailed;
            }

            $total = round($subtotal + $platformFee + $tax, 2);

            $priced[] = [
                'restaurant' => $restaurant,
                'lines' => $lines,
                'subtotal' => $subtotal,
                'platform_fee' => $platformFee,
                'tax' => $tax,
                'tax_breakdown' => $taxBreakdown,
                'total' => $total,
            ];
        }

        $groupTotal = round(collect($priced)->sum('total'), 2);

        return [$priced, $groupTotal];
    }

    private function priceStallItems(array $items): ?array
    {
        $lines = [];
        foreach ($items as $item) {
            $menuItem = MenuItem::find($item['id']);
            if (! $menuItem || ! $menuItem->is_scheduled_available) {
                return null;
            }

            $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
            $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
            $unitPrice = $menuItem->getFinalPriceAttribute()
                + ($variant['price'] ?? 0)
                + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));
            $itemTotal = round($unitPrice * (int) $item['quantity'], 2);

            $lines[] = [
                'id' => $menuItem->id,
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'price' => $unitPrice,
                'quantity' => (int) $item['quantity'],
                'selected_variant' => $variant,
                'selected_add_ons' => $addOns,
                'total' => $itemTotal,
            ];
        }

        return $lines;
    }

    private function resolveSelectedOption(array $options, ?array $selected): ?array
    {
        if (! $selected || empty($selected['name'])) {
            return null;
        }

        return collect($options)
            ->filter(fn ($option) => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN))
            ->firstWhere('name', $selected['name']);
    }

    private function resolveSelectedAddOns(array $options, array $selected): array
    {
        $names = collect($selected)->pluck('name')->filter()->all();

        return collect($options)
            ->filter(fn ($option) => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN))
            ->filter(fn ($option) => in_array($option['name'] ?? null, $names, true))
            ->values()
            ->all();
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMeters = 6371000;
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'THR';
        $date = now()->format('Ymd');
        do {
            $orderNumber = $prefix . $date . random_int(1000, 9999);
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function generateGroupNumber(): string
    {
        do {
            $groupNumber = 'THG' . now()->format('Ymd') . strtoupper(Str::random(5));
        } while (OrderGroup::where('group_number', $groupNumber)->exists());

        return $groupNumber;
    }
}
