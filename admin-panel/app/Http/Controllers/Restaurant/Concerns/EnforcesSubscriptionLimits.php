<?php

namespace App\Http\Controllers\Restaurant\Concerns;

use App\Models\MenuItem;
use App\Models\Restaurant;
use RuntimeException;

/**
 * Shared menu-item-cap check for the two places a menu item can be created:
 * the Flutter restaurant app's API path (RestaurantMenuController) and the
 * Blade restaurant web panel's path (Restaurant\MenuController). Keeps the
 * count query and the message text in one place.
 */
trait EnforcesSubscriptionLimits
{
    /**
     * @throws RuntimeException if the restaurant is on a subscription plan
     *         with a menu item cap it has already reached.
     */
    protected function assertMenuItemCapacity(Restaurant $restaurant): void
    {
        if (! $restaurant->isOnSubscriptionBilling()) {
            return;
        }

        $subscription = $restaurant->activeSubscription;
        if (! $subscription || ! $subscription->isLive()) {
            return;
        }

        $limit = $subscription->plan?->menu_item_limit;
        if ($limit === null) {
            return;
        }

        $currentCount = MenuItem::where('restaurant_id', $restaurant->id)->count();
        if ($currentCount >= $limit) {
            throw new RuntimeException(
                "Your subscription plan allows up to {$limit} menu items, and you've reached that limit. Upgrade your plan or switch to commission billing to add more."
            );
        }
    }
}
