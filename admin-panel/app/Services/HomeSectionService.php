<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\DeliveryChargeSetting;
use App\Models\GlobalMenuCategory;
use App\Models\HomeSection;
use App\Models\MasterMenuItem;
use App\Models\MenuItem;
use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HomeSectionService
{
    private const HIDDEN_BUILT_IN_SETTING = 'homepage_hidden_built_in_sections';

    private ?float $customerLatitude = null;
    private ?float $customerLongitude = null;
    private float $deliveryRadius = 15.0;
    private bool $deliveryZoneOnly = false;

    public function adminSections(): Collection
    {
        $sections = HomeSection::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $hiddenBuiltIns = $this->hiddenBuiltInTokens();

        return collect($this->resolveRuntimeOrder($sections))
            ->map(function (string $token, int $index) use ($sections, $hiddenBuiltIns): ?array {
                $builtIn = $this->builtInDefinitions()[$token] ?? null;
                if ($builtIn !== null) {
                    return [
                        'token' => $token,
                        'sort_order' => $index + 1,
                        'title' => $this->builtInTitle($token, $builtIn),
                        'subtitle' => $this->builtInSubtitle($token, $builtIn),
                        'type' => $builtIn['type'],
                        'source' => 'built_in',
                        'is_active' => ! in_array($token, $hiddenBuiltIns, true),
                        'model' => null,
                    ];
                }

                $id = (int) str_replace('home_section:', '', $token);
                $section = $sections->firstWhere('id', $id);

                if ($section === null) {
                    return null;
                }

                return [
                    'token' => $token,
                    'sort_order' => $index + 1,
                    'title' => $section->title,
                    'subtitle' => $section->subtitle,
                    'type' => $section->section_type,
                    'source' => 'dynamic',
                    'is_active' => $section->isLive(),
                    'model' => $section,
                ];
            })
            ->filter()
            ->values();
    }

    public function publicSections(
        ?float $latitude = null,
        ?float $longitude = null,
        float $radius = 15.0,
        bool $deliveryZoneOnly = false
    ): Collection
    {
        $this->customerLatitude = $latitude;
        $this->customerLongitude = $longitude;
        $this->deliveryRadius = max(1.0, min(100.0, $radius));
        $this->deliveryZoneOnly = $deliveryZoneOnly;

        $sections = HomeSection::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $hiddenBuiltIns = $this->hiddenBuiltInTokens();

        return collect($this->resolveRuntimeOrder($sections))
            ->map(function (string $token) use ($sections, $hiddenBuiltIns): ?array {
                $builtIn = $this->builtInDefinitions()[$token] ?? null;
                if ($builtIn !== null) {
                    if (in_array($token, $hiddenBuiltIns, true)) {
                        return null;
                    }

                    return $this->resolveBuiltInSection($token, $builtIn);
                }

                $id = (int) str_replace('home_section:', '', $token);
                $section = $sections->firstWhere('id', $id);

                if ($section === null || ! $section->isLive()) {
                    return null;
                }

                return $this->resolveDynamicSection($section);
            })
            ->filter(fn (?array $section) => $section !== null && ($section['enabled'] ?? false) === true)
            ->values();
    }

    public function reorder(array $orderedTokens): void
    {
        $sections = HomeSection::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $sanitized = $this->sanitizeRuntimeOrder($orderedTokens, $sections);
        AppSetting::setValue('homepage_section_order', json_encode($sanitized));

        $dynamicTokens = array_values(array_filter(
            $sanitized,
            static fn (string $token) => str_starts_with($token, 'home_section:')
        ));

        foreach ($dynamicTokens as $index => $token) {
            $id = (int) str_replace('home_section:', '', $token);
            HomeSection::query()->whereKey($id)->update(['display_order' => $index + 1]);
        }
    }

    public function toggleBuiltInVisibility(string $token): bool
    {
        if (! array_key_exists($token, $this->builtInDefinitions())) {
            abort(404);
        }

        $hidden = $this->hiddenBuiltInTokens();
        $wasHidden = in_array($token, $hidden, true);

        if ($wasHidden) {
            $hidden = array_values(array_diff($hidden, [$token]));
        } else {
            $hidden[] = $token;
            $hidden = array_values(array_unique($hidden));
        }

        AppSetting::setValue(self::HIDDEN_BUILT_IN_SETTING, json_encode($hidden));

        return ! $wasHidden;
    }

    public function updateBuiltInContent(string $token, ?string $title, ?string $subtitle): void
    {
        if (! array_key_exists($token, $this->builtInDefinitions())) {
            abort(404);
        }

        AppSetting::setValue($this->builtInTitleKey($token), trim((string) $title));
        AppSetting::setValue($this->builtInSubtitleKey($token), trim((string) $subtitle));
    }

    public function builtInDefinitions(): array
    {
        return [
            'categories' => [
                'title' => 'Explore Categories',
                'subtitle' => 'Discover food by cuisines & categories',
                'type' => 'categories',
            ],
            ...$this->promotionBuiltInDefinitions(),
            'restaurant_discovery' => [
                'title' => 'Restaurants Near You',
                'subtitle' => 'Discover the best restaurants in your area',
                'type' => 'restaurant_discovery',
            ],
        ];
    }

    public function resolveRuntimeOrder(Collection $sections): array
    {
        $stored = json_decode((string) AppSetting::getValue('homepage_section_order', '[]'), true);

        return $this->sanitizeRuntimeOrder(
            is_array($stored) ? $stored : [],
            $sections,
            $this->defaultRuntimeOrder($sections)
        );
    }

    private function defaultRuntimeOrder(Collection $sections): array
    {
        $dynamicTokens = $sections
            ->map(fn (HomeSection $section) => 'home_section:'.$section->id)
            ->values()
            ->all();

        return [
            'categories',
            ...array_keys($this->promotionBuiltInDefinitions()),
            ...$dynamicTokens,
            'restaurant_discovery',
        ];
    }

    private function sanitizeRuntimeOrder(array $tokens, Collection $sections, ?array $defaultOrder = null): array
    {
        $dynamicTokens = $sections
            ->map(fn (HomeSection $section) => 'home_section:'.$section->id)
            ->values()
            ->all();

        $knownTokens = array_values(array_unique([
            ...array_keys($this->builtInDefinitions()),
            ...$dynamicTokens,
        ]));

        $ordered = array_values(array_filter(
            array_map('strval', $tokens),
            static fn (string $token) => in_array($token, $knownTokens, true)
        ));

        $missing = array_values(array_diff($defaultOrder ?? $knownTokens, $ordered));

        return array_values(array_unique([...$ordered, ...$missing]));
    }

    private function resolveBuiltInSection(string $token, array $definition): ?array
    {
        if ($token === 'categories') {
            $items = GlobalMenuCategory::query()
                ->with('cuisines:id,name')
                ->active()
                ->parents()
                ->orderBy('display_order')
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'slug', 'description', 'image']);

            if ($items->isNotEmpty()) {
                $items = $this->attachCuisineImagesToGlobalCategories($items);
            }

            if ($items->isEmpty()) {
                $items = Cuisine::query()
                    ->where('is_active', true)
                    ->orderByDesc('popular')
                    ->orderBy('display_order')
                    ->orderBy('name')
                    ->limit(12)
                    ->get(['id', 'name', 'icon', 'image']);
            }

            return [
                'token' => $token,
                'type' => 'categories',
                'title' => $this->builtInTitle($token, $definition),
                'subtitle' => $this->builtInSubtitle($token, $definition),
                'enabled' => $items->isNotEmpty(),
                'items' => $items,
            ];
        }

        if ($token === 'restaurant_discovery') {
            return [
                'token' => $token,
                'type' => 'restaurant_discovery',
                'title' => $this->builtInTitle($token, $definition),
                'subtitle' => $this->builtInSubtitle($token, $definition),
                'enabled' => true,
                'items' => collect(),
            ];
        }

        if (($definition['source'] ?? null) === 'promotions') {
            $items = $this->activePromotionDeals(
                (int) ($definition['limit'] ?? 24),
                (array) ($definition['promotion_types'] ?? [])
            );
            $cardMode = $this->promotionSectionCardMode($token);

            if ($token === 'combo_deals') {
                $items = $this->comboGroupPromotionCards($items);
            }

            return [
                'token' => $token,
                'type' => $definition['type'],
                'promotion_type' => $definition['promotion_type'] ?? null,
                'promotion_types' => $definition['promotion_types'] ?? [],
                'card_mode' => $cardMode,
                'display_mode' => $cardMode,
                'title' => $this->builtInTitle($token, $definition),
                'subtitle' => $this->builtInSubtitle($token, $definition),
                'enabled' => $items->isNotEmpty(),
                'strict_items' => true,
                'items' => $items->all(),
            ];
        }

        return null;
    }

    private function resolveDynamicSection(HomeSection $section): ?array
    {
        return match ($section->section_type) {
            'banner_carousel' => $this->resolveBannerSection($section),
            'hero_banner' => $this->resolveHeroSection($section),
            'restaurant_grid' => $this->resolveRestaurantSection($section),
            'cuisine_grid' => $this->resolveCuisineSection($section),
            'custom_section' => $this->resolveGenericSection($section),
            'featured_restaurants' => $this->resolveRestaurantSectionWithDefaultScope($section, 'featured_restaurants', 'featured'),
            'recommended_for_you' => $this->resolveRecommendedForYouSection($section),
            'nearby_restaurants' => $this->resolveClientFeedSection($section, 'nearby_restaurants'),
            'popular_restaurants' => $this->resolveRestaurantSectionWithDefaultScope($section, 'popular_restaurants', 'top_rated'),
            'new_arrivals' => $this->resolveRestaurantSectionWithDefaultScope($section, 'new_arrivals', 'latest'),
            'trending_near_you' => $this->resolveRestaurantSectionWithDefaultScope($section, 'trending_near_you', 'most_ordered'),
            'popular_dishes' => $this->resolvePopularDishesSection($section),
            'admin_offers' => null,
            'shop_by_brand' => $this->resolveBrandSection($section),
            default => $this->resolveGenericSection($section),
        };
    }

    private function resolveGenericSection(HomeSection $section): ?array
    {
        $configuration = $section->configuration ?? [];

        if (! empty($configuration['restaurant_ids']) || $section->data_source === 'auto') {
            $items = $this->resolveRestaurantFeed($section, 'featured');

            return [
                'token' => 'home_section:'.$section->id,
                'type' => 'restaurant_grid',
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => $items !== null,
                'items' => $items?->values()->all() ?? [],
                'style' => $this->sectionStyle($section),
            ];
        }

        if (! empty($configuration['global_category_ids']) || ! empty($configuration['cuisine_ids'])) {
            return $this->resolveCuisineSection($section);
        }

        if (! empty($configuration['banner_ids'])) {
            return $this->resolveBannerSection($section);
        }

        if (! empty($configuration['hero_media'])) {
            return $this->resolveHeroSection($section);
        }

        if (! empty($configuration['menu_item_ids'])) {
            return $this->resolveMenuItemSection($section, 'popular_dishes');
        }

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'restaurant_grid',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => false,
            'items' => [],
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveRestaurantSectionWithDefaultScope(
        HomeSection $section,
        string $type,
        string $defaultScope
    ): ?array {
        $resolved = $this->resolveRestaurantFeed($section, $defaultScope);
        if ($resolved === null) {
            return null;
        }

        return [
            'token' => 'home_section:'.$section->id,
            'type' => $type,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => true,
            'items' => $resolved->values()->all(),
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveClientFeedSection(HomeSection $section, string $type): ?array
    {
        return [
            'token' => 'home_section:'.$section->id,
            'type' => $type,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => true,
            'client_feed' => true,
            'items' => [],
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolvePopularDishesSection(HomeSection $section): ?array
    {
        if ($section->data_source !== 'manual') {
            return $this->resolveAutoPopularDishesSection($section);
        }

        return $this->resolveMenuItemSection($section, 'popular_dishes')
            ?? $this->resolveMenuItemCategorySection($section, 'popular_dishes')
            ?? $this->resolveClientFeedSection($section, 'popular_dishes');
    }

    private function resolveAutoPopularDishesSection(HomeSection $section): array
    {
        $configuration = $section->configuration ?? [];
        $limit = max(1, min(24, (int) ($configuration['limit'] ?? 10)));

        if (! $this->hasCustomerLocation()) {
            return [
                'token' => 'home_section:'.$section->id,
                'type' => 'popular_dishes',
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => true,
                'strict_items' => true,
                'items' => [],
                'style' => $this->sectionStyle($section),
            ];
        }

        $query = MenuItem::query()
            ->where('is_available', true)
            ->where(function ($builder) {
                $builder->whereNull('approval_status')
                    ->orWhere('approval_status', 'approved');
            })
            ->whereHas('restaurant', function ($builder) {
                $builder->where('is_verified', true);
                if ($this->hasCustomerLocation()) {
                    $builder->nearby(
                        $this->customerLatitude,
                        $this->customerLongitude,
                        $this->deliveryRadius
                    );
                }
            })
            ->with('restaurant')
            ->orderByDesc('total_orders')
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_recommended')
            ->orderByDesc('rating');

        $items = $query
            ->limit($limit * 3)
            ->get()
            ->filter(fn (MenuItem $item) => filled($item->image))
            ->take($limit)
            ->values();

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'popular_dishes',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $items->isNotEmpty(),
            'strict_items' => true,
            'items' => $items->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'image' => $this->resolveStoredOrAbsoluteImage($item->image),
                'image_url' => $this->resolveStoredOrAbsoluteImage($item->image),
                'price' => (float) $item->price,
                'discounted_price' => $item->discounted_price !== null
                    ? (float) $item->discounted_price
                    : null,
                'restaurant_id' => $item->restaurant_id,
                'restaurant_name' => $item->restaurant?->name ?? 'Restaurant',
                'is_veg' => (bool) $item->is_veg,
                'rating' => (float) ($item->rating ?? 0),
                'total_orders' => (int) ($item->total_orders ?? 0),
            ])->all(),
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveRecommendedForYouSection(HomeSection $section): ?array
    {
        $restaurants = $this->resolveRestaurantFeed($section, 'featured');

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'recommended_for_you',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $restaurants !== null && $restaurants->isNotEmpty(),
            'strict_items' => true,
            'items' => $restaurants?->values()->all() ?? [],
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveMenuItemCategorySection(HomeSection $section, string $type): ?array
    {
        $configuration = $section->configuration ?? [];
        $categoryIds = collect($configuration['global_category_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($categoryIds->isEmpty()) {
            return null;
        }

        if (! $this->hasCustomerLocation()) {
            return [
                'token' => 'home_section:'.$section->id,
                'type' => $type,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => true,
                'strict_items' => true,
                'items' => [],
                'style' => $this->sectionStyle($section),
            ];
        }

        $globalCategories = GlobalMenuCategory::query()
            ->with('parent')
            ->whereIn('id', $categoryIds->all())
            ->get()
            ->keyBy('id');

        $selected = $categoryIds
            ->map(fn (int $id) => $globalCategories->get($id))
            ->filter()
            ->values();

        if ($selected->isEmpty()) {
            return null;
        }

        $matchesGlobalSelection = function ($query) use ($selected) {
            $query->where(function ($builder) use ($selected) {
                foreach ($selected as $category) {
                    if ($category->parent_id && $category->parent) {
                        $builder->orWhere(function ($nested) use ($category) {
                            $nested->where('category_name', $category->parent->name)
                                ->where('subcategory_name', $category->name);
                        });
                    } else {
                        $builder->orWhere('category_name', $category->name);
                    }
                }
            });
        };

        if ($this->hasCustomerLocation()) {
            $items = MenuItem::query()
                ->where('is_available', true)
                ->where(function ($query) {
                    $query->whereNull('approval_status')
                        ->orWhere('approval_status', 'approved');
                })
                ->whereHas('masterMenuItem', $matchesGlobalSelection)
                ->whereHas('restaurant', function ($query) {
                    $query->where('is_verified', true)
                        ->nearby(
                            $this->customerLatitude,
                            $this->customerLongitude,
                            $this->deliveryRadius
                        );
                })
                ->with('restaurant')
                ->orderByDesc('is_bestseller')
                ->orderByDesc('is_recommended')
                ->orderByDesc('rating')
                ->limit(max(1, min(24, (int) (($section->configuration ?? [])['limit'] ?? 8))))
                ->get();

            return [
                'token' => 'home_section:'.$section->id,
                'type' => $type,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => $items->isNotEmpty(),
                'strict_items' => true,
                'items' => $items->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'master_menu_item_id' => $item->master_menu_item_id,
                    'name' => $item->name,
                    'image' => $this->resolveStoredOrAbsoluteImage($item->image),
                    'image_url' => $this->resolveStoredOrAbsoluteImage($item->image),
                    'price' => (float) $item->price,
                    'discounted_price' => $item->discounted_price !== null ? (float) $item->discounted_price : null,
                    'restaurant_id' => $item->restaurant_id,
                    'restaurant_name' => $item->restaurant?->name ?? 'Restaurant',
                    'is_veg' => (bool) $item->is_veg,
                    'rating' => (float) ($item->rating ?? 0),
                    'total_orders' => (int) ($item->total_orders ?? 0),
                ])->values()->all(),
                'style' => $this->sectionStyle($section),
            ];
        }

        $items = MasterMenuItem::query()
            ->where($matchesGlobalSelection)
            ->where('is_active', true)
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->orderBy('name')
            ->limit(max(1, min(24, (int) (($section->configuration ?? [])['limit'] ?? 8))))
            ->get();
        $pricesByMasterId = $this->minimumMenuItemsForMasterItems($items->pluck('id')->all());

        return [
            'token' => 'home_section:'.$section->id,
            'type' => $type,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $items->isNotEmpty(),
            'strict_items' => true,
            'items' => $items->map(function (MasterMenuItem $item) use ($pricesByMasterId) {
                $menuItem = $pricesByMasterId->get($item->id);

                return [
                    'id' => $menuItem?->id ?? $item->id,
                    'master_menu_item_id' => $item->id,
                    'name' => $menuItem?->name ?? $item->name,
                    'image' => $this->resolveStoredOrAbsoluteImage($menuItem?->image ?: $item->image),
                    'image_url' => $this->resolveStoredOrAbsoluteImage($menuItem?->image ?: $item->image),
                    'price' => (float) ($menuItem?->price ?? 0),
                    'discounted_price' => $menuItem?->discounted_price !== null ? (float) $menuItem->discounted_price : null,
                    'restaurant_id' => (int) ($menuItem?->restaurant_id ?? 0),
                    'restaurant_name' => $menuItem?->restaurant?->name ?? $item->category_name ?? 'Global Menu',
                    'is_veg' => $menuItem !== null ? (bool) $menuItem->is_veg : $item->food_type === 'veg',
                ];
            })->values()->all(),
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveMenuItemSection(HomeSection $section, string $type): ?array
    {
        $configuration = $section->configuration ?? [];
        $ids = collect($configuration['menu_item_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return null;
        }

        if ($this->deliveryZoneOnly && ! $this->hasCustomerLocation()) {
            return [
                'token' => 'home_section:'.$section->id,
                'type' => $type,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => true,
                'strict_items' => true,
                'items' => [],
                'style' => $this->sectionStyle($section),
            ];
        }

        if ($this->hasCustomerLocation()) {
            $menuItems = MenuItem::query()
                ->whereIn('master_menu_item_id', $ids->all())
                ->where('is_available', true)
                ->where(function ($query) {
                    $query->whereNull('approval_status')
                        ->orWhere('approval_status', 'approved');
                })
                ->whereHas('restaurant', function ($query) {
                    $query->where('is_verified', true)
                        ->nearby(
                            $this->customerLatitude,
                            $this->customerLongitude,
                            $this->deliveryRadius
                        );
                })
                ->with('restaurant')
                ->orderByDesc('is_bestseller')
                ->orderByDesc('is_recommended')
                ->orderByDesc('rating')
                ->get()
                ->groupBy('master_menu_item_id');

            $ordered = $ids
                ->map(fn (int $id) => $menuItems->get($id)?->first())
                ->filter()
                ->values();

            return [
                'token' => 'home_section:'.$section->id,
                'type' => $type,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => true,
                'strict_items' => true,
                'items' => $ordered->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'master_menu_item_id' => $item->master_menu_item_id,
                    'name' => $item->name,
                    'image' => $this->resolveStoredOrAbsoluteImage($item->image),
                    'image_url' => $this->resolveStoredOrAbsoluteImage($item->image),
                    'price' => (float) $item->price,
                    'discounted_price' => $item->discounted_price !== null ? (float) $item->discounted_price : null,
                    'restaurant_id' => $item->restaurant_id,
                    'restaurant_name' => $item->restaurant?->name ?? 'Restaurant',
                    'is_veg' => (bool) $item->is_veg,
                    'rating' => (float) ($item->rating ?? 0),
                    'total_orders' => (int) ($item->total_orders ?? 0),
                ])->values()->all(),
                'style' => $this->sectionStyle($section),
            ];
        }

        $items = MasterMenuItem::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        $ordered = $ids->map(fn (int $id) => $items->get($id))->filter()->values();
        $pricesByMasterId = $this->minimumMenuItemsForMasterItems($ids->all());

        return [
            'token' => 'home_section:'.$section->id,
            'type' => $type,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $ordered->isNotEmpty(),
            'strict_items' => true,
            'items' => $ordered->map(function (MasterMenuItem $item) use ($pricesByMasterId) {
                $menuItem = $pricesByMasterId->get($item->id);

                return [
                    'id' => $menuItem?->id ?? $item->id,
                    'master_menu_item_id' => $item->id,
                    'name' => $menuItem?->name ?? $item->name,
                    'image' => $this->resolveStoredOrAbsoluteImage($menuItem?->image ?: $item->image),
                    'image_url' => $this->resolveStoredOrAbsoluteImage($menuItem?->image ?: $item->image),
                    'price' => (float) ($menuItem?->price ?? 0),
                    'discounted_price' => $menuItem?->discounted_price !== null ? (float) $menuItem->discounted_price : null,
                    'restaurant_id' => (int) ($menuItem?->restaurant_id ?? 0),
                    'restaurant_name' => $menuItem?->restaurant?->name ?? $item->category_name ?? 'Global Menu',
                    'is_veg' => $menuItem !== null ? (bool) $menuItem->is_veg : $item->food_type === 'veg',
                ];
            })->values()->all(),
            'style' => $this->sectionStyle($section),
        ];
    }

    private function minimumMenuItemsForMasterItems(array $masterMenuItemIds)
    {
        return MenuItem::query()
            ->whereIn('master_menu_item_id', $masterMenuItemIds)
            ->where('is_available', true)
            ->where(function ($query) {
                $query->whereNull('approval_status')
                    ->orWhere('approval_status', 'approved');
            })
            ->whereHas('restaurant', fn ($query) => $query->where('is_verified', true))
            ->with('restaurant')
            ->orderBy('price')
            ->get()
            ->groupBy('master_menu_item_id')
            ->map(fn ($items) => $items->first());
    }

    private function resolveAdminOffersSection(HomeSection $section): ?array
    {
        $configuration = $section->configuration ?? [];
        $promotionIds = collect($configuration['promotion_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($section->data_source !== 'manual' || $promotionIds->isEmpty()) {
            return $this->resolveClientFeedSection($section, 'admin_offers');
        }

        $promotions = Promotion::query()
            ->active()
            ->whereIn('id', $promotionIds->all())
            ->get()
            ->keyBy('id');

        $orderedPromotions = $promotionIds
            ->map(fn (int $id) => $promotions->get($id))
            ->filter()
            ->map(fn (Promotion $promotion) => $this->promotionPayload($promotion));
        $ordered = $orderedPromotions->values();

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'admin_offers',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $ordered->isNotEmpty(),
            'items' => $ordered->all(),
            'style' => $this->sectionStyle($section),
        ];
    }

    private function activePromotionDeals(int $limit = 24, array $promotionTypes = []): Collection
    {
        $normalizedTypes = collect($promotionTypes)
            ->map(fn ($type) => strtolower(trim((string) $type)))
            ->filter()
            ->values();

        return app(PromotionEngineService::class)
            ->list([
                'limit' => $normalizedTypes->isNotEmpty() ? max($limit * 4, 40) : $limit,
                'user_id' => auth()->id(),
                'platform' => 'customer_app',
            ])
            ->reject(fn (array $offer) => $this->isScratchOnlyDeal($offer))
            ->when($normalizedTypes->isNotEmpty(), fn (Collection $offers) => $offers
                ->filter(fn (array $offer) => $normalizedTypes->contains(
                    strtolower((string) ($offer['promotion_type'] ?? $offer['reward_type'] ?? ''))
                )))
            ->unique(fn (array $offer) => $offer['display_id'] ?? ('promotion:'.($offer['id'] ?? '0')))
            ->take($limit)
            ->values();
    }

    private function isScratchOnlyDeal(array $offer): bool
    {
        $type = strtolower((string) ($offer['promotion_type'] ?? $offer['reward_type'] ?? data_get($offer, 'rewards.type', '')));
        $title = strtolower((string) ($offer['title'] ?? ''));

        return $type === 'scratch_card'
            || str_contains($type, 'scratch_card')
            || str_starts_with($title, 'scratch reward');
    }

    private function promotionSectionCardMode(string $token): string
    {
        return in_array($token, ['bogo_deals', 'combo_deals'], true)
            ? 'menu_cards'
            : 'promo_cards';
    }

    private function comboGroupPromotionCards(Collection $promotions): Collection
    {
        return $promotions
            ->flatMap(function (array $offer): array {
                $groups = collect(data_get($offer, 'reward_config.combo_groups', data_get($offer, 'rewards.combo_groups', [])))
                    ->filter(fn ($group) => is_array($group))
                    ->values();

                if ($groups->isEmpty()) {
                    return [$offer];
                }

                $menuItems = collect((array) ($offer['menu_items'] ?? []))
                    ->filter(fn ($item) => is_array($item))
                    ->map(fn (array $item) => $item)
                    ->values();
                $itemsById = $menuItems->keyBy(fn (array $item) => (int) ($item['menu_item_id'] ?? $item['id'] ?? 0));
                $cards = [];

                foreach ($groups as $index => $group) {
                    $itemIds = collect((array) ($group['item_ids'] ?? []))
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->unique()
                        ->values();
                    $groupItems = $itemIds
                        ->map(fn (int $id) => $itemsById->get($id))
                        ->filter()
                        ->values();

                    if ($groupItems->count() < 2) {
                        continue;
                    }

                    $actualPrice = (float) ($group['actual_price'] ?? $groupItems->sum(
                        fn (array $item) => (float) ($item['discounted_price'] ?? $item['price'] ?? 0)
                    ));
                    $effectivePrice = (float) ($group['price'] ?? $group['effective_price'] ?? data_get($offer, 'discount_value', 0));
                    if ($effectivePrice <= 0) {
                        $effectivePrice = $actualPrice;
                    }
                    $discountPercent = (float) ($group['discount_percent'] ?? (
                        $actualPrice > 0 ? round((($actualPrice - $effectivePrice) / $actualPrice) * 100, 2) : 0
                    ));
                    $title = trim((string) ($group['name'] ?? '')) ?: ($offer['title'] ?? 'Combo Deal');
                    $subtitle = $groupItems
                        ->map(fn (array $item) => trim((string) ($item['name'] ?? $item['title'] ?? '')))
                        ->filter()
                        ->implode(' + ');
                    $firstImage = $groupItems
                        ->map(fn (array $item) => $item['image_url'] ?? $item['image'] ?? null)
                        ->first(fn ($url) => filled($url));
                    $rewardConfig = (array) ($offer['reward_config'] ?? $offer['rewards'] ?? []);
                    $rewardConfig['value'] = $effectivePrice;
                    $rewardConfig['actual_price'] = $actualPrice;
                    $rewardConfig['discount_percent'] = $discountPercent;
                    $rewardConfig['combo_group'] = $group;
                    $rewardConfig['combo_groups'] = [$group];

                    $cards[] = array_merge($offer, [
                        'source_type' => 'promotion',
                        'card_type' => 'combo_group',
                        'display_id' => ($offer['display_id'] ?? ('promotion:'.($offer['id'] ?? '0'))).':combo_group:'.$index,
                        'title' => $title,
                        'name' => $title,
                        'subtitle' => $subtitle,
                        'description' => $subtitle,
                        'image' => $firstImage ?: ($offer['image'] ?? null),
                        'image_url' => $firstImage ?: ($offer['image_url'] ?? null),
                        'promo_image' => $firstImage ?: ($offer['promo_image'] ?? null),
                        'actual_price' => $actualPrice,
                        'original_price' => $actualPrice,
                        'price' => $effectivePrice,
                        'effective_price' => $effectivePrice,
                        'discount_value' => $effectivePrice,
                        'discount_percent' => $discountPercent,
                        'combo_group_index' => $index,
                        'combo_group' => $group,
                        'reward_config' => $rewardConfig,
                        'rewards' => $rewardConfig,
                        'menu_items' => $groupItems->all(),
                    ]);
                }

                return $cards ?: [$offer];
            })
            ->unique(fn (array $offer) => $offer['display_id'] ?? ('promotion:'.($offer['id'] ?? '0')))
            ->values();
    }

    private function promotionBuiltInDefinitions(): array
    {
        return [
            'bogo_deals' => [
                'title' => 'BOGO Deals',
                'subtitle' => 'Buy more and unlock free items',
                'type' => 'promotion_type_section',
                'source' => 'promotions',
                'promotion_type' => 'bogo',
                'promotion_types' => ['bogo', 'buy_x_get_y', 'buy_2_get_1', 'buy_3_get_1', 'buy_3_get_2'],
                'limit' => 12,
            ],
            'combo_deals' => [
                'title' => 'Combo Deals',
                'subtitle' => 'Best combos at better prices',
                'type' => 'promotion_type_section',
                'source' => 'promotions',
                'promotion_type' => 'combo_deal',
                'promotion_types' => ['combo_deal', 'meal_deal'],
                'limit' => 12,
            ],
            'free_item_offers' => [
                'title' => 'Free Item Offers',
                'subtitle' => 'Order eligible items and get rewards',
                'type' => 'promotion_type_section',
                'source' => 'promotions',
                'promotion_type' => 'free_item',
                'promotion_types' => ['free_item'],
                'limit' => 12,
            ],
            'flash_sale_offers' => [
                'title' => 'Flash Sale',
                'subtitle' => 'Limited-time offers on selected items',
                'type' => 'promotion_type_section',
                'source' => 'promotions',
                'promotion_type' => 'flash_sale',
                'promotion_types' => ['flash_sale'],
                'limit' => 12,
            ],
            'festival_offers' => [
                'title' => 'Festival Offers',
                'subtitle' => 'Seasonal savings and special campaigns',
                'type' => 'promotion_type_section',
                'source' => 'promotions',
                'promotion_type' => 'festival_offer',
                'promotion_types' => ['festival_offer'],
                'limit' => 12,
            ],
            'deals_for_you' => [
                'title' => 'Deals for You',
                'subtitle' => 'All active offers and promotions in one place',
                'type' => 'deals_for_you',
                'source' => 'promotions',
                'limit' => 24,
            ],
        ];
    }

    private function promotionPayload(Promotion $promotion): array
    {
        $reward = $promotion->rewards ?? [];
        $coupon = $promotion->relationLoaded('couponCodes')
            ? $promotion->couponCodes->first()
            : $promotion->couponCodes()->where('is_active', true)->first();

        $type = (string) $promotion->promotion_type;

        return [
            'id' => $promotion->id,
            'source_type' => 'promotion',
            'display_id' => 'promotion:'.$promotion->id,
            'display_mode' => $this->promotionDisplayMode($type),
            'code' => $coupon?->code,
            'coupon_code' => $coupon?->code,
            'title' => $promotion->title,
            'subtitle' => $promotion->description ?? 'Special offer',
            'description' => $promotion->description ?? 'Special offer',
            'image' => $promotion->image_url,
            'promo_image' => $promotion->image_url,
            'promotion_type' => $promotion->promotion_type,
            'reward_type' => $reward['type'] ?? $promotion->promotion_type,
            'reward_config' => $reward,
            'discount_type' => $reward['type'] ?? null,
            'discount_value' => $reward['value'] ?? null,
            'min_order_value' => data_get($promotion->conditions, 'min_order_amount'),
            'min_order_amount' => data_get($promotion->conditions, 'min_order_amount'),
            'max_discount' => $reward['max_discount'] ?? null,
            'usage_limit' => $promotion->total_usage_limit,
            'valid_from' => $promotion->starts_at,
            'valid_to' => $promotion->ends_at,
            'start_date' => $promotion->starts_at,
            'end_date' => $promotion->ends_at,
            'rewards' => $reward,
            'menu_items' => $this->promotionMenuItems($promotion),
            'promotion_categories' => $this->promotionCategories($promotion),
        ];
    }

    private function promotionCategories(Promotion $promotion): array
    {
        $reward = $promotion->rewards ?? [];
        $targets = $promotion->targets ?? [];
        $conditions = $promotion->conditions ?? [];
        $ids = collect([
            ...((array) data_get($reward, 'category_ids', [])),
            ...((array) data_get($reward, 'menu_category_ids', [])),
            ...((array) data_get($targets, 'category_ids', [])),
            ...((array) data_get($targets, 'menu_category_ids', [])),
            ...((array) data_get($conditions, 'contains_category_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.category_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.menu_category_ids', [])),
            ...((array) data_get($reward, 'reward_rule.category_ids', [])),
            ...((array) data_get($reward, 'reward_rule.menu_category_ids', [])),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');
        $fallbackImages = MenuItem::query()
            ->whereIn('category_id', $ids->all())
            ->whereNotNull('images')
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_recommended')
            ->get(['id', 'category_id', 'images'])
            ->groupBy('category_id')
            ->map(fn (Collection $items) => $items
                ->map(fn (MenuItem $item) => $item->image_url)
                ->first(fn (?string $url) => filled($url)));

        return $ids
            ->map(fn (int $id) => $categories->get($id))
            ->filter()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'category_id' => $category->id,
                'name' => $category->name,
                'title' => $category->name,
                'image' => $this->resolveStoredOrAbsoluteImage($category->image) ?: $fallbackImages->get($category->id),
                'image_url' => $this->resolveStoredOrAbsoluteImage($category->image) ?: $fallbackImages->get($category->id),
                'restaurant_id' => $category->restaurant_id,
            ])
            ->values()
            ->all();
    }

    private function promotionMenuItems(Promotion $promotion): array
    {
        $reward = $promotion->rewards ?? [];
        $targets = $promotion->targets ?? [];
        $conditions = $promotion->conditions ?? [];
        $ids = collect([
            ...((array) data_get($reward, 'item_ids', [])),
            ...((array) data_get($reward, 'menu_item_ids', [])),
            ...((array) data_get($targets, 'item_ids', [])),
            ...((array) data_get($targets, 'menu_item_ids', [])),
            ...((array) data_get($conditions, 'contains_item_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.item_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.menu_item_ids', [])),
            ...((array) data_get($reward, 'reward_rule.item_ids', [])),
            ...((array) data_get($reward, 'reward_rule.menu_item_ids', [])),
            data_get($reward, 'free_item_id'),
            data_get($reward, 'item_id'),
            data_get($reward, 'reward_rule.free_item_id'),
            data_get($reward, 'reward_rule.item_id'),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $items = MenuItem::query()
            ->with('restaurant:id,name')
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        return $ids
            ->map(fn (int $id) => $items->get($id))
            ->filter()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'image' => $item->image_url,
                'image_url' => $item->image_url,
                'price' => (float) $item->price,
                'discounted_price' => $item->discounted_price !== null ? (float) $item->discounted_price : null,
                'restaurant_id' => $item->restaurant_id,
                'restaurant_name' => $item->restaurant?->name,
                'is_veg' => (bool) $item->is_veg,
                'is_combo' => (bool) $item->is_combo,
                'is_reward_item' => (int) data_get($reward, 'free_item_id') === (int) $item->id,
            ])
            ->values()
            ->all();
    }

    private function promotionDisplayMode(string $type): string
    {
        $type = strtolower($type);
        if (str_contains($type, 'combo') || str_contains($type, 'meal')) {
            return 'bundle';
        }

        if ($type === 'bogo' || str_starts_with($type, 'buy_') || str_starts_with($type, 'free_')) {
            return 'item_reward';
        }

        if (str_contains($type, 'item') || str_contains($type, 'category')) {
            return 'item_offer';
        }

        return 'order_offer';
    }

    private function resolveBannerSection(HomeSection $section): ?array
    {
        $configuration = $section->configuration ?? [];
        $limit = max(1, min(12, (int) ($configuration['limit'] ?? 6)));

        $query = Banner::query()
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($builder) {
                $builder->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('display_order')
            ->orderByDesc('id');

        if ($section->data_source === 'manual') {
            $bannerIds = collect($configuration['banner_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            if ($bannerIds->isEmpty()) {
                return null;
            }

            $query->whereIn('id', $bannerIds->all());
        }

        $items = $query->limit($limit)->get();

        if ($section->data_source === 'manual') {
            $ordered = $items->keyBy('id');
            $items = $bannerIds->map(fn (int $id) => $ordered->get($id))->filter()->values();
        }

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'banner_carousel',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $items->isNotEmpty(),
            'items' => $items,
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveHeroSection(HomeSection $section): ?array
    {
        $configuration = $section->configuration ?? [];
        $heroMedia = $this->resolveStoredOrAbsoluteImage($configuration['hero_media'] ?? null);

        if (! $heroMedia) {
            return null;
        }

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'hero_banner',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => true,
            'items' => [[
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->subtitle,
                'image' => $heroMedia,
                'image_url' => $heroMedia,
                'media_type' => $this->heroMediaType($heroMedia),
                'layout_mode' => 'full_image',
                'image_ratio' => 100,
                'link' => $configuration['hero_link'] ?? null,
                'banner_type' => 'hero',
            ]],
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveRestaurantSection(HomeSection $section): ?array
    {
        $items = $this->resolveRestaurantFeed($section, $this->restaurantGridDefaultScope($section));

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'restaurant_grid',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $items !== null,
            'items' => $items?->values()->all() ?? [],
            'style' => $this->sectionStyle($section),
        ];
    }

    private function restaurantGridDefaultScope(HomeSection $section): string
    {
        $title = mb_strtolower(trim((string) $section->title));
        $subtitle = mb_strtolower(trim((string) ($section->subtitle ?? '')));
        $haystack = trim($title.' '.$subtitle);

        if ($haystack !== '') {
            if (str_contains($haystack, 'popular') || str_contains($haystack, 'top rated')) {
                return 'top_rated';
            }

            if (str_contains($haystack, 'new arrival') || str_contains($haystack, 'latest')) {
                return 'latest';
            }

            if (str_contains($haystack, 'trending') || str_contains($haystack, 'most ordered')) {
                return 'most_ordered';
            }
        }

        return 'featured';
    }

    private function resolveRestaurantFeed(HomeSection $section, string $defaultScope): ?Collection
    {
        $configuration = $section->configuration ?? [];
        $defaultLimit = $section->section_type === 'recommended_for_you' ? 12 : 8;
        $configuredLimit = (int) ($configuration['limit'] ?? $defaultLimit);
        $limit = max(1, min(24, $configuredLimit));

        if ($this->deliveryZoneOnly && ! $this->hasCustomerLocation()) {
            return collect();
        }

        $query = Restaurant::query()
            ->where('is_verified', true)
            ->with('owner')
            ->withCount('orders');

        if ($section->data_source === 'manual') {
            $restaurantIds = collect($configuration['restaurant_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($restaurantIds->isEmpty()) {
                return null;
            }

            $query->whereIn('id', $restaurantIds->all());
        } else {
            match ($configuration['restaurant_scope'] ?? $defaultScope) {
                'top_rated' => $query->orderByDesc('rating')->orderByDesc('total_ratings'),
                'latest' => $query->latest(),
                'most_ordered' => $query->orderByDesc('orders_count')->orderByDesc('rating'),
                'pure_veg' => $query->where('is_pure_veg', true)->orderByDesc('rating'),
                'open_now' => $query->where('is_open', true)->orderByDesc('rating'),
                default => $query
                    ->where('is_featured', true)
                    ->where(function ($builder) {
                        $builder->whereNull('ad_expiry')->orWhere('ad_expiry', '>=', now());
                    })
                    ->orderByDesc('rating'),
            };
        }

        if ($this->hasCustomerLocation()) {
            $query->nearby(
                $this->customerLatitude,
                $this->customerLongitude,
                $this->deliveryRadius
            );
        }

        $restaurants = $query->limit($limit)->get();

        if ($section->data_source === 'manual') {
            $orderedRestaurants = $restaurants->keyBy('id');
            $restaurants = $restaurantIds
                ->map(fn (int $id) => $orderedRestaurants->get($id))
                ->filter()
                ->values();
        }

        return $restaurants->map(function (Restaurant $restaurant) use ($section) {
            $minimumMenuPrice = $restaurant->amountForOne();
            $eta = null;
            if ($section->section_type === 'recommended_for_you' && $this->hasCustomerLocation()) {
                try {
                    $eta = app(GoogleMapsEtaService::class)->estimateDelivery(
                        $restaurant->latitude !== null ? (float) $restaurant->latitude : null,
                        $restaurant->longitude !== null ? (float) $restaurant->longitude : null,
                        $this->customerLatitude,
                        $this->customerLongitude,
                        (int) ($restaurant->order_lead_time ?? 20)
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                    $eta = null;
                }
            }
            $etaData = is_array($eta) ? $eta : [];
            $etaMinutes = isset($etaData['eta_minutes']) ? (int) $etaData['eta_minutes'] : null;
            $etaDistance = isset($etaData['travel_distance_km'])
                ? (float) $etaData['travel_distance_km']
                : (isset($restaurant->distance) ? (float) $restaurant->distance : null);
            $isNearAndFast = $etaMinutes !== null
                && $etaDistance !== null
                && $etaMinutes <= 30
                && $etaDistance <= 5;
            $menuItems = $restaurant->menuItems()
                ->where('is_available', true)
                ->where(function ($query) {
                    $query->whereNull('approval_status')
                        ->orWhere('approval_status', 'approved');
                })
                ->orderByDesc('is_recommended')
                ->orderByDesc('is_bestseller')
                ->orderByDesc('rating')
                ->limit(18)
                ->get()
                ->filter(fn (MenuItem $item) => filled($item->image))
                ->take(6)
                ->values();

            return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'image' => MediaStorage::url($restaurant->banner_image ?: $restaurant->logo_image),
            'banner_image' => MediaStorage::url($restaurant->banner_image),
            'logo_image' => MediaStorage::url($restaurant->logo_image),
            'cuisine' => $restaurant->cuisine_text ?: 'Various cuisines',
            'cuisine_text' => $restaurant->cuisine_text ?: 'Various cuisines',
            'cuisine_names' => $restaurant->cuisine_names,
            'rating' => $restaurant->rating ?? 0,
            'total_ratings' => $restaurant->total_ratings ?? 0,
            'delivery_time' => $etaMinutes ?? ($restaurant->delivery_time ?? 30),
            'eta_minutes' => $etaMinutes,
            'eta_range' => $etaData['eta_range'] ?? null,
            'travel_minutes' => $etaData['traffic_travel_minutes'] ?? null,
            'travel_distance_km' => $etaDistance,
            'eta_source' => $etaData['source'] ?? null,
            'is_near_and_fast' => $isNearAndFast,
            'delivery_fee' => $this->deliveryFeeForRestaurant($restaurant),
            'min_order_amount' => (float) ($restaurant->min_order_amount ?? 0),
            'minimum_menu_price' => $minimumMenuPrice,
            'amount_for_one' => $minimumMenuPrice,
            'menu_items' => $menuItems->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'image' => $this->resolveStoredOrAbsoluteImage($item->image),
                'image_url' => $this->resolveStoredOrAbsoluteImage($item->image),
                'price' => (float) $item->price,
                'discounted_price' => $item->discounted_price !== null
                    ? (float) $item->discounted_price
                    : null,
                'is_veg' => (bool) $item->is_veg,
            ])->values()->all(),
            'is_open' => (bool) $restaurant->is_open,
            'is_open_now' => $restaurant->isOpenNow(),
            'next_opening_time' => optional($restaurant->getNextOpeningTime())->toIso8601String(),
            'next_opening_label' => $restaurant->getNextOpeningLabel(),
            'is_featured' => (bool) $restaurant->is_featured,
            'is_pure_veg' => (bool) $restaurant->is_pure_veg,
            'orders_count' => (int) ($restaurant->orders_count ?? 0),
            'distance' => isset($restaurant->distance) ? round((float) $restaurant->distance, 2) : null,
            'created_at' => optional($restaurant->created_at)?->toIso8601String(),
            ];
        });
    }

    private function hasCustomerLocation(): bool
    {
        return $this->customerLatitude !== null && $this->customerLongitude !== null;
    }

    private function deliveryFeeForRestaurant(Restaurant $restaurant): float
    {
        if (isset($restaurant->distance)) {
            return round((float) DeliveryChargeSetting::getDeliveryCharge((float) $restaurant->distance), 2);
        }

        return (float) ($restaurant->delivery_fee ?? DeliveryChargeSetting::getDeliveryCharge());
    }

    private function resolveCuisineSection(HomeSection $section): ?array
    {
        $configuration = $section->configuration ?? [];
        $limit = max(1, min(24, (int) ($configuration['limit'] ?? 8)));
        $globalCategoryIds = collect($configuration['global_category_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($globalCategoryIds->isNotEmpty() || $section->data_source === 'auto') {
            $globalQuery = GlobalMenuCategory::query()
                ->active()
                ->with(['parent', 'cuisines:id,name'])
                ->orderBy('display_order')
                ->orderBy('name');

            if ($globalCategoryIds->isNotEmpty()) {
                $globalQuery->whereIn('id', $globalCategoryIds->all());
            } else {
                $globalQuery->parents();
            }

            $items = $globalQuery->limit($limit)->get(['id', 'parent_id', 'name', 'slug', 'description', 'image']);

            if ($globalCategoryIds->isNotEmpty()) {
                $ordered = $items->keyBy('id');
                $items = $globalCategoryIds->map(fn (int $id) => $ordered->get($id))->filter()->values();
            }

            if ($items->isNotEmpty()) {
                $items = $this->attachCuisineImagesToGlobalCategories($items);
            }

            if ($items->isNotEmpty() || $globalCategoryIds->isNotEmpty()) {
                return [
                    'token' => 'home_section:'.$section->id,
                    'type' => 'cuisine_grid',
                    'title' => $section->title,
                    'subtitle' => $section->subtitle,
                    'enabled' => $items->isNotEmpty(),
                    'items' => $items,
                    'style' => $this->sectionStyle($section),
                ];
            }
        }

        $query = Cuisine::query()
            ->where('is_active', true)
            ->orderByDesc('popular')
            ->orderBy('display_order')
            ->orderBy('name');

        if ($section->data_source === 'manual') {
            $cuisineIds = collect($configuration['cuisine_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            if ($cuisineIds->isEmpty()) {
                return null;
            }

            $query->whereIn('id', $cuisineIds->all());
        } elseif ((bool) ($configuration['popular_only'] ?? false)) {
            $query->where('popular', true);
        }

        $items = $query->limit($limit)->get(['id', 'name', 'icon', 'image']);

        if ($section->data_source === 'manual') {
            $ordered = $items->keyBy('id');
            $items = $cuisineIds->map(fn (int $id) => $ordered->get($id))->filter()->values();
        }

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'cuisine_grid',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $items->isNotEmpty(),
            'items' => $items,
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveBrandSection(HomeSection $section): ?array
    {
        $configuration = $section->configuration ?? [];
        $limit = max(1, min(18, (int) ($configuration['limit'] ?? 8)));
        $selectedScope = $configuration['restaurant_scope'] ?? 'featured';

        if ($this->deliveryZoneOnly && ! $this->hasCustomerLocation()) {
            return [
                'token' => 'home_section:'.$section->id,
                'type' => 'shop_by_brand',
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'enabled' => true,
                'items' => [],
                'style' => $this->sectionStyle($section),
            ];
        }

        $query = Restaurant::query()
            ->where('is_verified', true)
            ->where(function ($builder) {
                $builder
                    ->whereNotNull('logo_image')
                    ->where('logo_image', '!=', '');
            });

        if ($section->data_source === 'manual') {
            $restaurantIds = collect($configuration['restaurant_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($restaurantIds->isEmpty()) {
                return null;
            }

            $query->whereIn('id', $restaurantIds->all());
        } else {
            match ($selectedScope) {
                'top_rated' => $query->orderByDesc('rating')->orderByDesc('total_ratings'),
                'latest' => $query->latest(),
                'most_ordered' => $query->withCount('orders')->orderByDesc('orders_count')->orderByDesc('rating'),
                'pure_veg' => $query->where('is_pure_veg', true)->orderByDesc('rating'),
                'open_now' => $query->where('is_open', true)->orderByDesc('rating'),
                default => $query
                    ->where('is_featured', true)
                    ->where(function ($builder) {
                        $builder->whereNull('ad_expiry')->orWhere('ad_expiry', '>=', now());
                    })
                    ->orderByDesc('rating'),
            };
        }

        if ($this->hasCustomerLocation()) {
            $query->nearby(
                $this->customerLatitude,
                $this->customerLongitude,
                $this->deliveryRadius
            );
        }

        $restaurants = $query->limit($limit)->get();

        if (
            $section->data_source !== 'manual' &&
            $restaurants->isEmpty() &&
            $selectedScope === 'featured'
        ) {
            $restaurants = Restaurant::query()
                ->where('is_verified', true)
                ->where(function ($builder) {
                    $builder
                        ->whereNotNull('logo_image')
                        ->where('logo_image', '!=', '');
                })
                ->when($this->hasCustomerLocation(), function ($query) {
                    $query->nearby(
                        $this->customerLatitude,
                        $this->customerLongitude,
                        $this->deliveryRadius
                    );
                })
                ->orderByDesc('rating')
                ->orderByDesc('total_ratings')
                ->orderBy('name')
                ->limit($limit)
                ->get();
        }

        if ($section->data_source === 'manual') {
            $orderedRestaurants = $restaurants->keyBy('id');
            $restaurants = $restaurantIds
                ->map(fn (int $id) => $orderedRestaurants->get($id))
                ->filter()
                ->values();
        }

        $items = $restaurants
            ->map(fn (Restaurant $restaurant) => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'logo' => MediaStorage::url($restaurant->logo_image),
                'logo_image' => MediaStorage::url($restaurant->logo_image),
                'image' => MediaStorage::url($restaurant->logo_image),
                'restaurant_id' => $restaurant->id,
            ])
            ->values();

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'shop_by_brand',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => true,
            'items' => $items,
            'style' => $this->sectionStyle($section),
        ];
    }

    private function resolveStoredOrAbsoluteImage(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return str_starts_with($image, 'http://') || str_starts_with($image, 'https://')
            ? $image
            : MediaStorage::url($image);
    }

    private function heroMediaType(string $mediaUrl): string
    {
        $normalized = strtolower(parse_url($mediaUrl, PHP_URL_PATH) ?? $mediaUrl);

        if (str_ends_with($normalized, '.json')) {
            return 'lottie';
        }

        return 'image';
    }

    private function attachCuisineImagesToGlobalCategories(Collection $categories): Collection
    {
        $imagesByKey = [];

        Cuisine::query()
            ->where('is_active', true)
            ->whereNotNull('image')
            ->get(['name', 'slug', 'image'])
            ->each(function (Cuisine $cuisine) use (&$imagesByKey): void {
                foreach ([$cuisine->slug, $cuisine->name] as $key) {
                    $normalized = Str::slug((string) $key);
                    if ($normalized !== '' && ! isset($imagesByKey[$normalized])) {
                        $imagesByKey[$normalized] = $cuisine->image;
                    }
                }
            });

        return $categories->map(function (GlobalMenuCategory $category) use ($imagesByKey): GlobalMenuCategory {
            $image = $imagesByKey[Str::slug((string) $category->slug)]
                ?? $imagesByKey[Str::slug((string) $category->name)]
                ?? null;

            if (! $category->image && $image) {
                $category->setAttribute('image', $image);
            }

            if ($category->image) {
                $category->setAttribute('image_url', $this->resolveStoredOrAbsoluteImage($category->image));
            }

            return $category;
        });
    }

    private function sectionStyle(HomeSection $section): array
    {
        $configuration = $section->configuration ?? [];
        $backgroundImage = $configuration['background_image'] ?? null;

        return [
            'background_color' => $configuration['background_color'] ?? '#FFFFFF',
            'background_opacity' => max(0, min(1, (float) ($configuration['background_opacity'] ?? 0.88))),
            'background_image' => is_string($backgroundImage) && $backgroundImage !== ''
                ? MediaStorage::url($backgroundImage)
                : null,
        ];
    }

    private function hiddenBuiltInTokens(): array
    {
        $stored = json_decode((string) AppSetting::getValue(self::HIDDEN_BUILT_IN_SETTING, '[]'), true);
        if (! is_array($stored)) {
            return [];
        }

        $knownTokens = array_keys($this->builtInDefinitions());

        return array_values(array_filter(
            array_map('strval', $stored),
            static fn (string $token) => in_array($token, $knownTokens, true)
        ));
    }

    private function builtInTitle(string $token, array $definition): string
    {
        $stored = trim((string) AppSetting::getValue($this->builtInTitleKey($token), ''));

        if ($stored !== '') {
            return $stored;
        }

        $legacyKey = match ($token) {
            'categories' => 'category_section_title',
            'restaurant_discovery' => 'restaurants_section_title',
            default => null,
        };

        if ($legacyKey !== null) {
            $legacy = trim((string) AppSetting::getValue($legacyKey, ''));
            if ($legacy !== '') {
                return $legacy;
            }
        }

        return $definition['title'];
    }

    private function builtInSubtitle(string $token, array $definition): string
    {
        $stored = trim((string) AppSetting::getValue($this->builtInSubtitleKey($token), ''));

        if ($stored !== '') {
            return $stored;
        }

        $legacyKey = match ($token) {
            'categories' => 'category_section_subtitle',
            'restaurant_discovery' => 'restaurants_section_subtitle',
            default => null,
        };

        if ($legacyKey !== null) {
            $legacy = trim((string) AppSetting::getValue($legacyKey, ''));
            if ($legacy !== '') {
                return $legacy;
            }
        }

        return $definition['subtitle'];
    }

    private function builtInTitleKey(string $token): string
    {
        return 'homepage_built_in_'.$token.'_title';
    }

    private function builtInSubtitleKey(string $token): string
    {
        return 'homepage_built_in_'.$token.'_subtitle';
    }
}
