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
    /** The live plan that gives this restaurant Stories (a bundled full plan first, else the stories add-on). */
    public function plan(Restaurant $restaurant): ?\App\Models\SubscriptionPlan
    {
        if ($restaurant->isOnSubscriptionBilling()) {
            $subscription = $restaurant->activeSubscription;
            if ($subscription && $subscription->isLive() && (bool) $subscription->plan?->includes_stories) {
                return $subscription->plan;
            }
        }

        $addon = $restaurant->storiesAddonSubscription;

        return $addon !== null && $addon->isLive() ? $addon->plan : null;
    }

    /** Whether this restaurant's plan lets it attach a menu item (with a button) to a story. */
    public function canLinkMenu(Restaurant $restaurant): bool
    {
        return (bool) $this->plan($restaurant)?->story_menu_link;
    }

    /** @return array{per_day: ?int, hours: int, posted_today: int, left_today: ?int} */
    public function limits(Restaurant $restaurant): array
    {
        $plan = $this->plan($restaurant);
        $perDay = $plan?->stories_per_day ? (int) $plan->stories_per_day : null;
        $hours = $plan?->story_duration_hours ? (int) $plan->story_duration_hours : 24;
        $posted = \App\Models\RestaurantStory::where('restaurant_id', $restaurant->id)->where('posted_at', '>=', now()->startOfDay())->count();

        return ['per_day' => $perDay, 'hours' => $hours, 'posted_today' => $posted, 'left_today' => $perDay === null ? null : max(0, $perDay - $posted)];
    }

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
