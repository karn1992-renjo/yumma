<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\PetpoojaMenuSync;
use App\Models\PetpoojaRestaurantConfig;
use App\Services\Petpooja\PetpoojaMenuSyncService;
use Illuminate\Http\Request;

/**
 * Self-service Petpooja config for the restaurant owner's OWN restaurant
 * only -- scoped via currentRestaurant(), never by a route-bound
 * restaurant id, so one owner can never see or edit another's
 * credentials. Mirrors Admin\PetpoojaConfigController's logic; kept as a
 * separate controller since the two panels have different auth/scoping
 * and it's clearer to read than threading a "who's allowed" branch
 * through one shared controller.
 */
class PetpoojaConfigController extends Controller
{
    use ResolvesRestaurantContext;

    public function show()
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $config = PetpoojaRestaurantConfig::where('restaurant_id', $restaurant->id)->first();

        return view('restaurant.petpooja.index', [
            'restaurant' => $restaurant,
            'config' => $config,
        ]);
    }

    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $validated = $request->validate([
            'rest_id' => 'required|string|max:255',
            'app_key' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string|max:255',
            'access_token' => 'nullable|string|max:255',
            'environment' => 'required|in:sandbox,production',
            'is_active' => 'nullable|boolean',
            'is_menu_managed_by_pos' => 'nullable|boolean',
        ]);

        $config = PetpoojaRestaurantConfig::where('restaurant_id', $restaurant->id)->first()
            ?? new PetpoojaRestaurantConfig(['restaurant_id' => $restaurant->id]);

        $config->rest_id = $validated['rest_id'];
        $config->environment = $validated['environment'];
        $config->is_active = $request->boolean('is_active');
        $config->is_menu_managed_by_pos = $request->boolean('is_menu_managed_by_pos', true);

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

        return redirect()->route('restaurant.petpooja.show')->with('success', 'Petpooja configuration saved.');
    }

    public function syncMenu(PetpoojaMenuSyncService $service)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $config = PetpoojaRestaurantConfig::where('restaurant_id', $restaurant->id)->first();
        if (! $config || ! $config->hasCredentials()) {
            return redirect()->route('restaurant.petpooja.show')->with('error', 'Add Petpooja credentials before syncing the menu.');
        }

        $result = $service->fetchAndApply($restaurant);

        return redirect()->route('restaurant.petpooja.show')->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function syncHistory()
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $syncs = PetpoojaMenuSync::where('restaurant_id', $restaurant->id)
            ->latest()
            ->paginate(20);

        return view('restaurant.petpooja.syncs', [
            'restaurant' => $restaurant,
            'syncs' => $syncs,
        ]);
    }
}
