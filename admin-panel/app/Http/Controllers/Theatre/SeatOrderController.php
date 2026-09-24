<?php

namespace App\Http\Controllers\Theatre;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Theatre;
use App\Models\TheatreScreen;
use App\Support\CurrencyFormatter;
use App\Support\TheatreSeatToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * swaad-only: the no-app, no-login web ordering flow reached by scanning
 * the QR sticker on a cinema seat.
 *
 * This exists because the app install is the single biggest drop-off in
 * the in-seat funnel: nobody installs a food app in a dark auditorium ten
 * minutes into a film. So the QR lands on a plain web page that can browse
 * and order immediately, and only asks for a phone number at the moment of
 * payment (for the order updates and the runner's call).
 *
 * Deliberately thin. Browsing is served from here (public, signature-gated,
 * read-only), but the moment real money is involved the page hands off to
 * the SAME Sanctum-authenticated /api/theatre/checkout engine the app uses
 * -- server-side re-pricing, multi-stall fan-out, payment verification and
 * all. There is no second checkout implementation to keep in sync.
 */
class SeatOrderController extends Controller
{
    /**
     * The scanned landing page. Everything the page needs to render its
     * first paint is embedded here so a phone on cinema wifi makes one
     * request, not four.
     */
    public function show(Request $request, Theatre $theatre, TheatreScreen $screen, string $seat, string $sig)
    {
        $seat = $this->resolveSeat($theatre, $screen, $seat, $sig);

        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
        $gatewayProvider = strtolower((string) AppSetting::getValue('payment_gateway_provider', 'razorpay'));
        $gatewayEnabled = filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);

        return view('theatre-web.seat-order', [
            'theatre' => $theatre,
            'screen' => $screen,
            'seatLabel' => $seat,
            'seatToken' => $sig,
            'stalls' => $this->stallsFor($theatre),
            'context' => [
                'theatre_id' => $theatre->id,
                'screen_id' => $screen->id,
                'seat_label' => $seat,
                'seat_token' => $sig,
                'theatre_name' => $theatre->name,
                'screen_name' => $screen->name,
                'aisle_handoff_only' => (bool) $theatre->aisle_handoff_only,
                'currency_symbol' => CurrencyFormatter::symbolFor($currency),
                // Only Razorpay has a browser SDK wired up here; on a
                // deployment running another provider the page falls back
                // to wallet/COD rather than pretending to offer a gateway
                // it cannot actually complete in the browser.
                'razorpay_available' => $gatewayEnabled && $gatewayProvider === 'razorpay',
                'cod_enabled' => filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
                'base_url' => url('/t/' . TheatreSeatToken::path($theatre->id, $screen->id, $seat)),
            ],
        ]);
    }

    public function stalls(Request $request, Theatre $theatre, TheatreScreen $screen, string $seat, string $sig): JsonResponse
    {
        $this->resolveSeat($theatre, $screen, $seat, $sig);

        return response()->json(['success' => true, 'data' => $this->stallsFor($theatre)]);
    }

    /**
     * A stall's menu, flattened to exactly the fields the seat page renders
     * -- not the full app menu payload, which carries promotion, rating and
     * scheduling data this page has no use for on a cinema connection.
     */
    public function menu(Request $request, Theatre $theatre, TheatreScreen $screen, string $seat, string $sig, Restaurant $restaurant): JsonResponse
    {
        $this->resolveSeat($theatre, $screen, $seat, $sig);
        $this->assertStallServesTheatre($theatre, $restaurant);

        $items = MenuItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_available', true)
            ->where(fn ($query) => $query->whereNull('approval_status')->orWhere('approval_status', 'approved'))
            ->orderBy('category_id')
            ->orderByDesc('is_bestseller')
            ->get()
            ->filter(fn (MenuItem $item) => $item->is_scheduled_available)
            ->values();

        $categoryNames = Category::whereIn('id', $items->pluck('category_id')->filter()->unique())
            ->pluck('name', 'id');

        $grouped = $items
            ->groupBy(fn (MenuItem $item) => $categoryNames[$item->category_id] ?? 'Menu')
            ->map(fn ($group, $categoryName) => [
                'category' => $categoryName,
                'items' => $group->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => Str::limit((string) $item->description, 90),
                    'price' => round((float) $item->getFinalPriceAttribute(), 2),
                    'is_veg' => (bool) $item->is_veg,
                    'is_bestseller' => (bool) $item->is_bestseller,
                    'image' => $item->image_url,
                    'variants' => $this->availableOptions($item->variants ?? []),
                    'add_ons' => $this->availableOptions($item->add_ons ?? []),
                ])->values(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'restaurant' => ['id' => $restaurant->id, 'name' => $restaurant->name],
                'categories' => $grouped,
            ],
        ]);
    }

    /**
     * Guest checkout, step 1: send the OTP.
     *
     * The phone may or may not already have a swaad account, and the app's
     * own send-OTP endpoint deliberately refuses the wrong flow for each
     * case (404 for an unknown number on `login`, 409 for a known one on
     * `signup`). Rather than reimplement that endpoint's provider
     * branching -- MSG91 widget vs manual vs demo numbers -- this probes
     * `login` first and retries as `signup`, which is safe because the 404
     * is returned before any OTP is generated or sent.
     */
    public function sendOtp(Request $request, Theatre $theatre, TheatreScreen $screen, string $seat, string $sig): JsonResponse
    {
        $this->resolveSeat($theatre, $screen, $seat, $sig);
        $request->validate(['phone' => ['required', 'string', 'max:40']]);

        $auth = app(AuthController::class);

        $response = $auth->sendOtp(new Request([
            'phone' => $request->input('phone'),
            'flow' => 'login',
            'role' => 'customer',
        ]));
        $flow = 'login';

        if ($this->responseCode($response) === 'ACCOUNT_NOT_FOUND') {
            $response = $auth->sendOtp(new Request([
                'phone' => $request->input('phone'),
                'flow' => 'signup',
                'role' => 'customer',
            ]));
            $flow = 'signup';
        }

        $payload = $this->responseData($response);
        $payload['flow'] = $flow;
        // The page needs to know whether to ask for a name on the next
        // screen -- a returning customer should never be asked again.
        $payload['needs_name'] = $flow === 'signup';

        return response()->json(
            array_merge($this->responseBody($response), ['data' => $payload]),
            $response->getStatusCode()
        );
    }

    /**
     * Guest checkout, step 2: verify the OTP and hand the page a Sanctum
     * token it can call /api/theatre/checkout with.
     *
     * A brand-new number is registered on the spot through the app's own
     * register endpoint (which consumes the verified-phone token the verify
     * step just issued), so a QR customer ends up with a perfectly ordinary
     * customer account rather than some second-class guest record -- their
     * order history, wallet and support tickets all work afterwards.
     */
    public function verifyOtp(Request $request, Theatre $theatre, TheatreScreen $screen, string $seat, string $sig): JsonResponse
    {
        $this->resolveSeat($theatre, $screen, $seat, $sig);
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'otp' => ['required', 'string', 'min:4', 'max:8'],
            'flow' => ['required', 'in:login,signup'],
            'name' => ['nullable', 'string', 'max:255'],
            'msg91_access_token' => ['nullable', 'string', 'max:4096'],
            'msg91_req_id' => ['nullable', 'string', 'max:255'],
        ]);

        $auth = app(AuthController::class);

        $verifyResponse = $auth->verifyOtp(new Request([
            'phone' => $validated['phone'],
            'otp' => $validated['otp'],
            'flow' => $validated['flow'],
            'role' => 'customer',
            'msg91_access_token' => $validated['msg91_access_token'] ?? null,
            'msg91_req_id' => $validated['msg91_req_id'] ?? null,
        ]));

        if ($verifyResponse->getStatusCode() !== 200) {
            return $verifyResponse;
        }

        $data = $this->responseData($verifyResponse);

        if ($validated['flow'] === 'login') {
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $data['token'] ?? null,
                    'name' => $data['user']['name'] ?? null,
                ],
            ]);
        }

        $registerResponse = $auth->register(new Request([
            'name' => trim((string) ($validated['name'] ?? '')) ?: 'Guest',
            'phone' => $validated['phone'],
            'verified_phone_token' => $data['verified_phone_token'] ?? '',
            'role' => 'customer',
        ]));

        if ($registerResponse->getStatusCode() >= 300) {
            return $registerResponse;
        }

        $registered = $this->responseData($registerResponse);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $registered['token'] ?? null,
                'name' => $registered['user']['name'] ?? null,
            ],
        ]);
    }

    /**
     * Rejects a forged or mistyped seat URL before anything else happens.
     * Returns the seat label in its canonical (stored, upper-cased) form so
     * everything downstream -- validation, the order's seat_label, the
     * runner's screen -- agrees on one spelling.
     */
    private function resolveSeat(Theatre $theatre, TheatreScreen $screen, string $seat, string $sig): string
    {
        abort_unless($theatre->isActive(), 404);
        abort_unless((int) $screen->theatre_id === (int) $theatre->id, 404);
        abort_unless($screen->is_active && $screen->hasSeatMap(), 404);

        $seat = strtoupper(trim($seat));
        abort_unless(TheatreSeatToken::verify($theatre->id, $screen->id, $seat, $sig), 404);

        return $seat;
    }

    private function assertStallServesTheatre(Theatre $theatre, Restaurant $restaurant): void
    {
        $serves = $theatre->restaurants()
            ->wherePivot('is_active', true)
            ->where('restaurants.id', $restaurant->id)
            ->exists();

        abort_unless($serves, 404);
    }

    private function stallsFor(Theatre $theatre): array
    {
        return $theatre->restaurants()
            ->wherePivot('is_active', true)
            ->get(['restaurants.id', 'restaurants.name', 'restaurants.logo_image', 'restaurants.cuisine', 'restaurants.rating', 'restaurants.is_open', 'restaurants.is_pure_veg'])
            ->map(fn (Restaurant $restaurant) => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'cuisine' => $restaurant->cuisine,
                'rating' => $restaurant->rating,
                'is_open' => (bool) $restaurant->is_open,
                'is_pure_veg' => (bool) $restaurant->is_pure_veg,
                'logo' => $restaurant->logo_image
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($restaurant->logo_image)
                    : null,
            ])
            ->values()
            ->all();
    }

    private function availableOptions(array $options): array
    {
        return collect($options)
            ->filter(fn ($option) => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN))
            ->map(fn ($option) => [
                'name' => $option['name'] ?? '',
                'price' => round((float) ($option['price'] ?? 0), 2),
            ])
            ->filter(fn ($option) => $option['name'] !== '')
            ->values()
            ->all();
    }

    private function responseBody(JsonResponse $response): array
    {
        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function responseData(JsonResponse $response): array
    {
        $body = $this->responseBody($response);

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    private function responseCode(JsonResponse $response): ?string
    {
        return $this->responseBody($response)['code'] ?? null;
    }
}
