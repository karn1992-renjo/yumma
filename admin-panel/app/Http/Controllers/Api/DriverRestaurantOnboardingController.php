<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOnboarding;
use App\Models\RestaurantOnboardingIncentive;
use App\Services\RestaurantOnboardingService;
use Illuminate\Http\Request;

class DriverRestaurantOnboardingController extends Controller
{
    public function __construct(private readonly RestaurantOnboardingService $onboardings)
    {
    }

    public function summary(Request $request)
    {
        $driver = $request->user();
        $this->onboardings->assertDriverEligible($driver);

        $query = RestaurantOnboarding::where('driver_id', $driver->id);

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $this->onboardings->settings(),
                'metrics' => [
                    'total_submitted' => (clone $query)->whereNotNull('submitted_at')->count(),
                    'pending' => (clone $query)->whereIn('status', ['submitted', 'under_review', 'resubmitted'])->count(),
                    'successful' => (clone $query)->whereIn('status', ['approved', 'activated'])->count(),
                    'correction_required' => (clone $query)->where('status', 'correction_required')->count(),
                    'rejected' => (clone $query)->where('status', 'rejected')->count(),
                    'total_earned' => (float) RestaurantOnboardingIncentive::where('driver_id', $driver->id)
                        ->whereIn('status', ['earned', 'included_in_payout', 'paid'])
                        ->sum('amount'),
                ],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $driver = $request->user();
        $query = RestaurantOnboarding::with(['partnerApplication', 'restaurant', 'incentive'])
            ->where('driver_id', $driver->id)
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 20))
                ->through(fn (RestaurantOnboarding $onboarding) => $this->onboardings->format($onboarding)),
        ]);
    }

    public function store(Request $request)
    {
        $onboarding = $this->onboardings->createDraft($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Restaurant onboarding draft started.',
            'data' => $this->onboardings->format($onboarding, true),
        ], 201);
    }

    public function show(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        abort_unless((int) $restaurantOnboarding->driver_id === (int) $request->user()->id, 404);

        return response()->json([
            'success' => true,
            'data' => $this->onboardings->format($restaurantOnboarding->load(['partnerApplication', 'restaurant', 'incentive']), true),
        ]);
    }

    public function draft(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        $onboarding = $this->onboardings->saveDraft($restaurantOnboarding, $request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Draft saved.',
            'data' => $this->onboardings->format($onboarding, true),
        ]);
    }

    public function submit(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        $onboarding = $this->onboardings->submit($restaurantOnboarding, $request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant onboarding submitted successfully.',
            'data' => $this->onboardings->format($onboarding, true),
        ], 201);
    }

    public function sendOwnerOtp(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'app_signature' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => $this->onboardings->sendOwnerOtp($restaurantOnboarding, $request->user(), $validated['phone'], $validated['app_signature'] ?? null),
        ]);
    }

    public function verifyOwnerOtp(Request $request, RestaurantOnboarding $restaurantOnboarding)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'otp' => ['required', 'string', 'min:4', 'max:8'],
        ]);
        $onboarding = $this->onboardings->verifyOwnerOtp($restaurantOnboarding, $request->user(), $validated['phone'], $validated['otp']);

        return response()->json([
            'success' => true,
            'message' => 'Owner mobile verified.',
            'data' => $this->onboardings->format($onboarding, true),
        ]);
    }
}
