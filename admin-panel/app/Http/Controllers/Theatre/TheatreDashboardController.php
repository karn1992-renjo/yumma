<?php

namespace App\Http\Controllers\Theatre;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Theatre;
use App\Models\TheatreRunner;
use App\Models\TheatreScreen;
use App\Models\TheatreUser;
use App\Models\User;
use App\Rules\UniqueUserContactForRole;
use App\Services\TheatreManagementService;
use App\Support\TheatreSeatToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * swaad-only: the theatre-owner-facing panel for the movie-theatre in-seat
 * food ordering module. Mirrors Branch\BranchDashboardController's shape
 * (currentX()/authorizeX()/xCan() pattern) closely, deliberately leaner --
 * no wallet/settlement/order-live-board/COD here, see the project plan.
 *
 * Phase 1 scope only: dashboard, restaurant mapping, runner roster, a
 * name-only screen list (the seat-map builder itself is a later phase), and
 * a read-only orders list (empty until the checkout engine phase lands).
 */
class TheatreDashboardController extends Controller
{
    public function __construct(private TheatreManagementService $theatres)
    {
    }

    public function index(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.dashboard.view');

        $stats = [
            'restaurants' => $theatre->restaurants()->count(),
            'screens' => $theatre->screens()->count(),
            'runners' => TheatreRunner::where('theatre_id', $theatre->id)->where('approval_status', 'active')->count(),
            'orders' => $theatre->orders()->count(),
        ];
        $capabilities = $this->theatreCapabilities($request);

        return view('theatre.dashboard', compact('theatre', 'stats', 'capabilities'));
    }

    public function restaurants(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.restaurants.view');

        $mapped = $theatre->restaurants()->orderBy('name')->get();
        $mappedIds = $mapped->pluck('id');
        $allRestaurants = Restaurant::orderBy('name')->get(['id', 'name', 'city', 'is_open']);
        $capabilities = $this->theatreCapabilities($request);

        return view('theatre.restaurants', compact('theatre', 'allRestaurants', 'mapped', 'mappedIds', 'capabilities'));
    }

    public function mapRestaurant(Request $request, Restaurant $restaurant)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.restaurants.manage');

        $this->theatres->mapRestaurant($restaurant, $theatre, $request->user());

        return back()->with('success', "{$restaurant->name} mapped to this theatre.");
    }

    public function unmapRestaurant(Request $request, Restaurant $restaurant)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.restaurants.manage');

        $this->theatres->unmapRestaurant($restaurant, $theatre, $request->user());

        return back()->with('success', "{$restaurant->name} unmapped from this theatre.");
    }

    /**
     * A theatre owner creating their own concession stall, rather than
     * mapping an existing restaurant -- mirrors
     * Branch\BranchDashboardController::createRestaurant()/storeRestaurant()
     * closely, minus the delivery-zone map/validation (a theatre stall has
     * one fixed location -- the theatre itself -- there is no polygon to be
     * inside). Goes through the SAME admin approval every restaurant on the
     * platform does: created with is_verified = false, so it shows up on
     * the existing admin "Pending" restaurant filter for an admin to verify
     * -- no new admin-side UI needed.
     */
    public function createRestaurant(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.restaurants.manage');

        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();

        return view('theatre.restaurants-create', compact('theatre', 'cuisines'));
    }

    public function storeRestaurant(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.restaurants.manage');

        $data = $this->validateTheatreRestaurant($request);

        $restaurant = DB::transaction(function () use ($request, $theatre, $data) {
            $owner = User::create(array_merge([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
                'password' => Hash::make($data['owner_password']),
                'email_verified_at' => now(),
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'upi_id' => $data['upi_id'] ?? null,
            ], $this->payoutProviderAccountAttributes($request)));
            $owner->assignRole('restaurant_owner');

            $slug = $this->uniqueRestaurantSlug($data['name']);
            $restaurant = Restaurant::create([
                'owner_id' => $owner->id,
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'description' => $data['description'] ?? null,
                // A theatre stall has exactly one location -- the theatre
                // itself -- so it's always taken from the theatre record,
                // never a form field a stall owner could get wrong (or
                // place outside the venue).
                'address' => $theatre->address,
                'city' => $theatre->city,
                'state' => $theatre->state,
                'pincode' => null,
                'latitude' => $theatre->latitude,
                'longitude' => $theatre->longitude,
                'delivery_radius' => 0,
                'restaurant_type' => 'delivery',
                'is_pure_veg' => $request->boolean('is_pure_veg'),
                'min_order_amount' => 0,
                'delivery_fee' => 0,
                'delivery_time' => $data['delivery_time'] ?? 15,
                'open_time' => $data['open_time'] ?? null,
                'close_time' => $data['close_time'] ?? null,
                'weekly_timings' => $this->weeklyTimingsFromFlatHours(null, $data['open_time'] ?? null, $data['close_time'] ?? null),
                'timezone' => $theatre->timezone ?? 'Asia/Kolkata',
                'cuisine' => $data['cuisine'] ?? [],
                'is_open' => false,
                'is_verified' => false,
            ]);

            $this->storeRestaurantImages($request, $restaurant);
            $this->theatres->assignOwnRestaurant($restaurant, $theatre, $request->user());

            return $restaurant;
        });

        return redirect()->route('theatre.restaurants')->with('success', "{$restaurant->name} created and sent for admin approval.");
    }

    private function validateTheatreRestaurant(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('restaurants', 'email')],
            'phone' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_pure_veg' => ['nullable', 'boolean'],
            'delivery_time' => ['nullable', 'integer', 'min:1', 'max:60'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'cuisine' => ['nullable', 'array'],
            'cuisine.*' => ['string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', UniqueUserContactForRole::email('restaurant_owner')],
            'owner_phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone('restaurant_owner')],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'routing_code' => ['nullable', 'string', 'max:32'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function uniqueRestaurantSlug(string $name): string
    {
        $slug = Str::slug($name);
        $base = $slug;
        $counter = 1;

        while (Restaurant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function weeklyTimingsFromFlatHours(?array $existing, ?string $openTime, ?string $closeTime): array
    {
        $timings = $existing ?: Restaurant::getDefaultWeeklyTimings();

        if (!$openTime && !$closeTime) {
            return $timings;
        }

        foreach ($timings as $day => $timing) {
            $timings[$day] = array_merge(Restaurant::getDefaultDayTiming(), $timing, [
                'open_time' => $openTime ?: ($timing['open_time'] ?? '09:00'),
                'close_time' => $closeTime ?: ($timing['close_time'] ?? '22:00'),
            ]);
        }

        return $timings;
    }

    private function storeRestaurantImages(Request $request, Restaurant $restaurant): void
    {
        if ($request->hasFile('logo')) {
            $restaurant->update(['logo_image' => $request->file('logo')->store('restaurants/logos', 'public')]);
        }

        if ($request->hasFile('banner')) {
            $restaurant->update(['banner_image' => $request->file('banner')->store('restaurants/banners', 'public')]);
        }
    }

    private function payoutProviderAccountAttributes(Request $request): array
    {
        $provider = AppSetting::getValue('payout_gateway_provider', 'razorpay');
        $gatewayAccountId = $request->gateway_account_id;

        return [
            'gateway_account_id' => $gatewayAccountId,
            'mollie_organization_id' => $provider === 'mollie' ? $gatewayAccountId : null,
            'mercadopago_collector_id' => $provider === 'mercadopago' ? $gatewayAccountId : null,
        ];
    }

    public function runners(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.runners.view');

        $roster = TheatreRunner::with('runner')->where('theatre_id', $theatre->id)->latest()->get();
        $capabilities = $this->theatreCapabilities($request);

        return view('theatre.runners', compact('theatre', 'roster', 'capabilities'));
    }

    public function storeRunner(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.runners.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $runner = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $this->theatres->addRunner($runner, $theatre, $request->user());

        return redirect()->route('theatre.runners')->with('success', "{$runner->name} added as a runner.");
    }

    public function removeRunner(Request $request, User $runner)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.runners.manage');

        $this->theatres->removeRunner($runner, $theatre, $request->user());

        return back()->with('success', "{$runner->name} removed from the runner roster.");
    }

    public function screens(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.screens.view');

        $screens = $theatre->screens()->orderBy('name')->get();
        $capabilities = $this->theatreCapabilities($request);

        return view('theatre.screens', compact('theatre', 'screens', 'capabilities'));
    }

    public function storeScreen(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.screens.manage');

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('theatre_screens', 'name')->where('theatre_id', $theatre->id),
            ],
        ]);

        $screen = TheatreScreen::create([
            'theatre_id' => $theatre->id,
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('theatre.screens.seat-map.edit', $screen)
            ->with('success', 'Screen added. Design its seat map below.');
    }

    public function editSeatMap(Request $request, TheatreScreen $screen)
    {
        $theatre = $this->currentTheatre($request);
        abort_unless((int) $screen->theatre_id === (int) $theatre->id, 404);
        $this->authorizeTheatre($request, 'theatre.screens.manage');

        $capabilities = $this->theatreCapabilities($request);

        return view('theatre.screen-seat-map', compact('theatre', 'screen', 'capabilities'));
    }

    /**
     * The seat map is never treated as ticket inventory -- only its shape
     * (rows, seat counts, section tags) is validated. A seat here is purely
     * a delivery-location label a customer will later pick; there is no
     * availability/booking state to protect, so validation just needs to
     * keep the JSON well-formed enough for both this builder and the future
     * customer-app picker to render safely.
     */
    public function updateSeatMap(Request $request, TheatreScreen $screen)
    {
        $theatre = $this->currentTheatre($request);
        abort_unless((int) $screen->theatre_id === (int) $theatre->id, 404);
        $this->authorizeTheatre($request, 'theatre.screens.manage');

        $request->validate(['seat_map_config' => ['required', 'string']]);

        $decoded = json_decode($request->input('seat_map_config'), true);
        if (! is_array($decoded) || empty($decoded['rows']) || ! is_array($decoded['rows'])) {
            return back()->withErrors(['seat_map_config' => 'Add at least one row before saving.'])->withInput();
        }

        foreach ($decoded['rows'] as $row) {
            if (empty($row['label']) || empty($row['seat_count']) || (int) $row['seat_count'] < 1 || (int) $row['seat_count'] > 60) {
                return back()->withErrors(['seat_map_config' => 'Every row needs a label and 1-60 seats.'])->withInput();
            }
        }

        $screen->update(['seat_map_config' => $decoded]);

        return redirect()->route('theatre.screens.seat-map.edit', $screen)->with('success', 'Seat map saved.');
    }

    /**
     * The printable sticker sheet: one QR per seat, each encoding that
     * seat's signed ordering URL. This is how a theatre actually switches
     * the feature on -- print, peel, stick on the armrests.
     *
     * Rendered as a plain print stylesheet rather than a generated PDF so
     * an owner can reprint a single damaged sticker by printing one page
     * range, and the codes are drawn in the browser (nothing is stored per
     * seat -- the URLs are signed, not registered).
     */
    public function seatQrStickers(Request $request, TheatreScreen $screen)
    {
        $theatre = $this->currentTheatre($request);
        abort_unless((int) $screen->theatre_id === (int) $theatre->id, 404);
        $this->authorizeTheatre($request, 'theatre.screens.manage');

        abort_unless($screen->hasSeatMap(), 404);

        $rows = collect($screen->seatRows())
            ->map(fn (array $row) => [
                'label' => $row['label'],
                'seats' => collect($row['seats'])->map(fn (string $seat) => [
                    'label' => $seat,
                    'url' => TheatreSeatToken::url($theatre->id, $screen->id, $seat),
                ])->all(),
            ])
            ->all();

        return view('theatre.screen-qr-stickers', compact('theatre', 'screen', 'rows'));
    }

    public function orders(Request $request)
    {
        $theatre = $this->currentTheatre($request);
        $this->authorizeTheatre($request, 'theatre.orders.view');

        $orders = $theatre->orders()->with(['restaurant', 'screen'])->latest()->paginate(20)->withQueryString();
        $capabilities = $this->theatreCapabilities($request);

        return view('theatre.orders', compact('theatre', 'orders', 'capabilities'));
    }

    private function currentTheatre(Request $request): Theatre
    {
        $theatre = $this->theatres->theatreForUser($request->user());
        abort_unless($theatre, 403);

        return $theatre;
    }

    private function authorizeTheatre(Request $request, string $permission): void
    {
        abort_unless($this->theatreCan($request, $permission), 403);
    }

    private function theatreCapabilities(Request $request): array
    {
        return [
            'dashboard_view' => $this->theatreCan($request, 'theatre.dashboard.view'),
            'restaurants_view' => $this->theatreCan($request, 'theatre.restaurants.view'),
            'restaurants_manage' => $this->theatreCan($request, 'theatre.restaurants.manage'),
            'screens_view' => $this->theatreCan($request, 'theatre.screens.view'),
            'screens_manage' => $this->theatreCan($request, 'theatre.screens.manage'),
            'runners_view' => $this->theatreCan($request, 'theatre.runners.view'),
            'runners_manage' => $this->theatreCan($request, 'theatre.runners.manage'),
            'orders_view' => $this->theatreCan($request, 'theatre.orders.view'),
        ];
    }

    /**
     * Mirrors Branch\BranchDashboardController::branchCan()'s exact
     * fallback shape -- and the exact reason it needs one. Real Spatie role
     * permissions are never actually granted here (permissionsFor() only
     * ever writes the JSON `permissions` column on TheatreUser), so
     * `$user->can($permission)` alone would silently 403 every real theatre
     * user; the JSON-array fallback below is load-bearing, not optional.
     * See the branch-fleet-live-map-and-permission-bug project memory for
     * the same bug already found and fixed once in the Branch panel.
     */
    private function theatreCan(Request $request, string $permission): bool
    {
        $user = $request->user();

        try {
            if ($user->can($permission)) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to the membership check below
        }

        $theatre = $this->theatres->theatreForUser($user);
        $membership = $theatre
            ? TheatreUser::where('theatre_id', $theatre->id)->where('user_id', $user->id)->where('is_active', true)->first()
            : null;

        return $membership && in_array($permission, $membership->permissions ?? [], true);
    }
}
