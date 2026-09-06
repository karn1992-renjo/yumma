<?php

namespace App\Http\Controllers\Api\Restaurant\Concerns;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared restaurant-resolution logic for the batch-4f restaurant controllers.
 *
 * Mirrors RestaurantController::getAccessibleRestaurants() /
 * resolveSingleRestaurantForFeature() so these standalone controllers scope to
 * the same outlet the rest of the restaurant app uses (owner -> staff outlet ->
 * current_restaurant_id), honouring an explicit ?restaurant_id= override.
 */
trait ResolvesRestaurantScope
{
    protected function accessibleRestaurants(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($user->restaurants()->exists()) {
            return $user->restaurants()->orderBy('name')->get();
        }

        $staffRestaurant = $user->restaurantStaff()->with('restaurant')->first()?->restaurant;
        if ($staffRestaurant) {
            return collect([$staffRestaurant]);
        }

        if ($user->current_restaurant_id) {
            $current = Restaurant::find($user->current_restaurant_id);
            if ($current) {
                return collect([$current]);
            }
        }

        return collect();
    }

    protected function resolveSingleRestaurant(Request $request, ?User $user): ?Restaurant
    {
        $restaurants = $this->accessibleRestaurants($user);
        $selectedId = $request->input('restaurant_id');

        if ($selectedId && $selectedId !== 'all') {
            return $restaurants->firstWhere('id', (int) $selectedId);
        }

        $active = $user?->activeRestaurant();
        if ($active && $restaurants->contains('id', $active->id)) {
            return $active;
        }

        return $restaurants->first();
    }
}
