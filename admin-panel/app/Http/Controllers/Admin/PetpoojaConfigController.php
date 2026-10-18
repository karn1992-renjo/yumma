<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PetpoojaMenuSync;
use App\Models\PetpoojaRestaurantConfig;
use App\Models\Restaurant;
use App\Services\Petpooja\PetpoojaMenuSyncService;
use Illuminate\Http\Request;

class PetpoojaConfigController extends Controller
{
    public function show(Restaurant $restaurant)
    {
        $config = $restaurant->petpoojaConfig;

        return view('admin.restaurants.petpooja', [
            'restaurant' => $restaurant,
            'config' => $config,
        ]);
    }

    public function store(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'rest_id' => 'required|string|max:255',
            'app_key' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string|max:255',
            'access_token' => 'nullable|string|max:255',
            'environment' => 'required|in:sandbox,production',
            'is_active' => 'nullable|boolean',
            'is_menu_managed_by_pos' => 'nullable|boolean',
        ]);

        // Query fresh rather than via $restaurant->petpoojaConfig -- that
        // dynamic relation property is cached on the model instance after
        // first access, which would silently return a stale null (and
        // attempt a second insert, violating the unique constraint) if
        // this restaurant instance is reused for a second save within
        // the same request lifecycle.
        $config = PetpoojaRestaurantConfig::where('restaurant_id', $restaurant->id)->first()
            ?? new PetpoojaRestaurantConfig(['restaurant_id' => $restaurant->id]);

        $config->rest_id = $validated['rest_id'];
        $config->environment = $validated['environment'];
        $config->is_active = $request->boolean('is_active');
        $config->is_menu_managed_by_pos = $request->boolean('is_menu_managed_by_pos', true);

        // Only overwrite a credential if a new value was actually typed --
        // the form never echoes these back, so an empty field means
        // "leave the existing stored value alone", not "clear it".
        if (filled($validated['app_key'] ?? null)) {
            $config->app_key_encrypted = $validated['app_key'];
        }
        if (filled($validated['app_secret'] ?? null)) {
            $config->app_secret_encrypted = $validated['app_secret'];
        }
        if (filled($validated['access_token'] ?? null)) {
            $config->access_token_encrypted = $validated['access_token'];
        }

        $config->save();

        return redirect()->route('admin.restaurants.petpooja.show', $restaurant)
            ->with('success', 'Petpooja configuration saved.');
    }

    public function syncMenu(Restaurant $restaurant, PetpoojaMenuSyncService $service)
    {
        $config = $restaurant->petpoojaConfig;

        if (! $config || ! $config->hasCredentials()) {
            return redirect()->route('admin.restaurants.petpooja.show', $restaurant)
                ->with('error', 'Add Petpooja credentials before syncing the menu.');
        }

        $result = $service->fetchAndApply($restaurant);

        return redirect()->route('admin.restaurants.petpooja.show', $restaurant)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function syncHistory(Restaurant $restaurant)
    {
        $syncs = PetpoojaMenuSync::where('restaurant_id', $restaurant->id)
            ->latest()
            ->paginate(20);

        return view('admin.restaurants.petpooja-syncs', [
            'restaurant' => $restaurant,
            'syncs' => $syncs,
        ]);
    }
}
