<?php

namespace App\Services;

use App\Models\Restaurant;

/**
 * Stories is bundled into a restaurant's full_plan subscription
 * (SubscriptionPlan::includes_stories) OR purchasable standalone as a
 * `scope=stories_addon` subscription by a restaurant on commission billing
 * -- that addon reuses the whole restaurant_subscriptions billing/trial/
 * renewal machinery for free (see Restaurant::storiesAddonSubscription()),
 * so the only new logic here is this OR-check.
 */
class StoriesAccessService
{
    public function canPost(Restaurant $restaurant): bool
    {
        if ($restaurant->isOnSubscriptionBilling()) {
            $subscription = $restaurant->activeSubscription;
            if ($subscription && $subscription->isLive() && (bool) $subscription->plan?->includes_stories) {
                return true;
            }
        }

        $addon = $restaurant->storiesAddonSubscription;

        return $addon !== null && $addon->isLive();
    }
}
