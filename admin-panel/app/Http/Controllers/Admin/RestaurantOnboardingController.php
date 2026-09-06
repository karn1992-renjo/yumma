<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\RestaurantOnboarding;
use App\Models\RestaurantOnboardingIncentive;
use App\Models\User;
use App\Services\RestaurantOnboardingService;
use App\Services\RestaurantOnboardingSettings;
use Illuminate\Http\Request;

class RestaurantOnboardingController extends Controller
{
    public function __construct(
        private readonly RestaurantOnboardingService $onboardings,
        private readonly RestaurantOnboardingSettings $settings
    ) {
    }

    public function index(Request $request)
    {
        $query = RestaurantOnboarding::with(['driver.deliveryArea', 'restaurant', 'partnerApplication', 'incentive']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('incentive_status') && $request->incentive_status !== 'all') {
            $query->where('incentive_status', $request->incentive_status);
        }
        if ($request->filled('driver')) {
            $search = trim((string) $request->driver);
            $query->whereHas('driver', fn ($driver) => $driver->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }
        if ($request->filled('restaurant')) {
            $search = trim((string) $request->restaurant);
            $query->where(function ($outer) use ($search) {
                $outer->whereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('partnerApplication', fn ($application) => $application->where('business_name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('application_number')) {
            $query->where('application_number', 'like', '%' . trim((string) $request->application_number) . '%');
        }
        if ($request->filled('zone_id')) {
            $query->whereHas('driver', fn ($driver) => $driver->where('delivery_area_id', $request->integer('zone_id')));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }

        $onboardings = $query->latest()->paginate(20);
        $stats = [
            'total' => RestaurantOnboarding::count(),
            'draft' => RestaurantOnboarding::where('status', 'draft')->count(),
            'pending' => RestaurantOnboarding::whereIn('status', ['submitted', 'under_review', 'resubmitted'])->count(),
            'correction_required' => RestaurantOnboarding::where('status', 'correction_required')->count(),
            'successful' => RestaurantOnboarding::whereIn('status', ['approved', 'activated'])->count(),
            'rejected' => RestaurantOnboarding::where('status', 'rejected')->count(),
            'earned' => RestaurantOnboardingIncentive::whereIn('status', ['earned', 'included_in_payout', 'paid'])->sum('amount'),
            'paid' => RestaurantOnboardingIncentive::where('status', 'paid')->sum('amount'),
        ];
        $zones = DeliveryArea::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.restaurant-onboardings.index', compact('onboardings', 'stats', 'zones'));
    }

    public function show(RestaurantOnboarding $restaurantOnboarding)
    {
        $restaurantOnboarding->load(['driver.deliveryArea', 'restaurant.owner', 'partnerApplication', 'incentive.payout', 'events.actor']);

        return view('admin.restaurant-onboardings.show', ['onboarding' => $restaurantOnboarding]);
    }

    public function requestCorrection(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        $validated = $request->validate([
            'correction_notes' => ['required', 'string', 'max:2000'],
            'correction_fields' => ['nullable', 'string', 'max:1000'],
        ]);

        $fields = collect(explode(',', (string) ($validated['correction_fields'] ?? '')))
            ->map(fn ($field) => trim($field))
            ->filter()
            ->values()
            ->all();

        $this->onboardings->requestCorrection($restaurantOnboarding, $request->user(), $validated['correction_notes'], $fields);

        return redirect()->back()->with('success', 'Correction requested from driver.');
    }

    public function reject(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $this->onboardings->reject($restaurantOnboarding, $request->user(), $validated['rejection_reason']);

        return redirect()->route('admin.restaurant-onboardings.index')->with('success', 'Restaurant onboarding rejected.');
    }

    public function settings()
    {
        return view('admin.restaurant-onboardings.settings', [
            'settings' => $this->settings->all(),
            'drivers' => User::role('delivery_partner')->orderBy('name')->get(['id', 'name', 'phone']),
            'zones' => DeliveryArea::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'incentive_amount' => ['required', 'numeric', 'min:0'],
            'incentive_trigger' => ['required', 'in:restaurant_approved,restaurant_activated,first_successful_order'],
            'owner_otp' => ['required', 'in:required,optional,disabled'],
            'gps_required' => ['nullable', 'boolean'],
            'gps_radius_meters' => ['required', 'integer', 'min:0', 'max:100000'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'monthly_limit' => ['nullable', 'integer', 'min:1'],
            'eligible_driver_ids' => ['nullable', 'array'],
            'eligible_driver_ids.*' => ['integer', 'exists:users,id'],
            'eligible_zone_ids' => ['nullable', 'array'],
            'eligible_zone_ids.*' => ['integer', 'exists:delivery_areas,id'],
            'eligible_categories' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->settings->update([
            'enabled' => $request->boolean('enabled'),
            'incentive_amount' => $validated['incentive_amount'],
            'incentive_trigger' => $validated['incentive_trigger'],
            'owner_otp' => $validated['owner_otp'],
            'gps_required' => $request->boolean('gps_required'),
            'gps_radius_meters' => $validated['gps_radius_meters'],
            'daily_limit' => $validated['daily_limit'] ?? '',
            'monthly_limit' => $validated['monthly_limit'] ?? '',
            'eligible_driver_ids' => implode(',', $validated['eligible_driver_ids'] ?? []),
            'eligible_zone_ids' => implode(',', $validated['eligible_zone_ids'] ?? []),
            'eligible_categories' => $validated['eligible_categories'] ?? '',
        ]);

        return redirect()->back()->with('success', 'Driver restaurant onboarding settings updated.');
    }
}
