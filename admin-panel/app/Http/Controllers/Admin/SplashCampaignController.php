<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\SplashCampaign;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SplashCampaignController extends Controller
{
    public function index()
    {
        $campaigns = SplashCampaign::orderByDesc('sort_order')->orderByDesc('created_at')->get();

        return view('admin.splash-campaigns.index', [
            'campaigns' => $campaigns,
            'stats' => [
                'total' => $campaigns->count(),
                'active' => $campaigns->filter->isCurrentlyEligible()->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.splash-campaigns.create', [
            'restaurants' => $this->restaurantOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, isUpdate: false);

        $validated['image_path'] = MediaStorage::store($request->file('image'), 'splash-campaigns');
        if ($request->hasFile('logo')) {
            $validated['logo_path'] = MediaStorage::store($request->file('logo'), 'splash-campaigns/logos');
        }
        $validated = $this->normalizeTapAction($validated);
        $validated['is_active'] = $request->boolean('is_active');

        SplashCampaign::create($validated);
        $this->flushPublicCache();

        return redirect()->route('admin.splash-campaigns.index')
            ->with('success', 'Splash campaign created successfully!');
    }

    public function edit(SplashCampaign $splashCampaign)
    {
        return view('admin.splash-campaigns.edit', [
            'campaign' => $splashCampaign,
            'restaurants' => $this->restaurantOptions(),
        ]);
    }

    public function update(Request $request, SplashCampaign $splashCampaign)
    {
        $validated = $this->validatePayload($request, isUpdate: true);

        if ($request->hasFile('image')) {
            $validated['image_path'] = MediaStorage::store($request->file('image'), 'splash-campaigns');
            MediaStorage::delete($splashCampaign->image_path);
        }
        if ($request->hasFile('logo')) {
            $validated['logo_path'] = MediaStorage::store($request->file('logo'), 'splash-campaigns/logos');
            MediaStorage::delete($splashCampaign->logo_path);
        } elseif ($request->boolean('remove_logo')) {
            MediaStorage::delete($splashCampaign->logo_path);
            $validated['logo_path'] = null;
        }
        $validated = $this->normalizeTapAction($validated);
        $validated['is_active'] = $request->boolean('is_active');

        $splashCampaign->update($validated);
        $this->flushPublicCache();

        return redirect()->route('admin.splash-campaigns.index')
            ->with('success', 'Splash campaign updated successfully!');
    }

    public function destroy(SplashCampaign $splashCampaign)
    {
        MediaStorage::delete($splashCampaign->image_path);
        MediaStorage::delete($splashCampaign->logo_path);
        $splashCampaign->delete();
        $this->flushPublicCache();

        return redirect()->route('admin.splash-campaigns.index')
            ->with('success', 'Splash campaign deleted successfully!');
    }

    public function toggleStatus(SplashCampaign $splashCampaign)
    {
        $splashCampaign->update(['is_active' => !$splashCampaign->is_active]);
        $this->flushPublicCache();

        return response()->json([
            'success' => true,
            'is_active' => $splashCampaign->is_active,
            'message' => $splashCampaign->is_active ? 'Campaign activated!' : 'Campaign deactivated!',
        ]);
    }

    private function validatePayload(Request $request, bool $isUpdate): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'headline' => 'nullable|string|max:120',
            'subtitle' => 'nullable|string|max:200',
            'image' => [$isUpdate ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => 'nullable|boolean',
            'background_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'platform' => ['required', Rule::in(SplashCampaign::PLATFORMS)],
            'is_active' => 'nullable|boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'display_duration_seconds' => 'required|integer|min:2|max:15',
            'tap_action' => ['required', Rule::in(SplashCampaign::TAP_ACTIONS)],
            'tap_restaurant_id' => ['nullable', 'required_if:tap_action,restaurant', 'exists:restaurants,id'],
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }

    private function normalizeTapAction(array $validated): array
    {
        $validated['tap_restaurant_id'] = ($validated['tap_action'] ?? 'none') === 'restaurant'
            ? ($validated['tap_restaurant_id'] ?? null)
            : null;

        return $validated;
    }

    private function restaurantOptions()
    {
        return Restaurant::where('is_verified', true)->orderBy('name')->get(['id', 'name']);
    }

    private function flushPublicCache(): void
    {
        Cache::forget('splash_campaign_active:android');
        Cache::forget('splash_campaign_active:ios');
        Cache::forget('splash_campaign_active:both');
    }
}
