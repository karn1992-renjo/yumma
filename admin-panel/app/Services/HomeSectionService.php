<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Banner;
use App\Models\Cuisine;
use App\Models\DeliveryChargeSetting;
use App\Models\GlobalMenuCategory;
use App\Models\HomeSection;
use App\Models\MasterMenuItem;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HomeSectionService
{
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

        return collect($this->resolveRuntimeOrder($sections))
            ->map(function (string $token, int $index) use ($sections): ?array {
                $builtIn = $this->builtInDefinitions()[$token] ?? null;
                if ($builtIn !== null) {
                    return [
                        'token' => $token,
                        'sort_order' => $index + 1,
                        'title' => $builtIn['title'],
                        'subtitle' => $builtIn['subtitle'],
                        'type' => $builtIn['type'],
                        'source' => 'built_in',
                        'is_active' => true,
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

        return collect($this->resolveRuntimeOrder($sections))
            ->map(function (string $token) use ($sections): ?array {
                $builtIn = $this->builtInDefinitions()[$token] ?? null;
                if ($builtIn !== null) {
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

    public function builtInDefinitions(): array
    {
        return [
            'categories' => [
                'title' => 'Explore Categories',
                'subtitle' => 'Discover food by cuisines & categories',
                'type' => 'categories',
            ],
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
                'title' => AppSetting::getValue('category_section_title', $definition['title']),
                'subtitle' => AppSetting::getValue('category_section_subtitle', $definition['subtitle']),
                'enabled' => $items->isNotEmpty(),
                'items' => $items,
            ];
        }

        if ($token === 'restaurant_discovery') {
            return [
                'token' => $token,
                'type' => 'restaurant_discovery',
                'title' => AppSetting::getValue('restaurants_section_title', $definition['title']),
                'subtitle' => AppSetting::getValue('restaurants_section_subtitle', $definition['subtitle']),
                'enabled' => true,
                'items' => collect(),
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
            'admin_offers' => $this->resolveAdminOffersSection($section),
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
        $ids = collect($configuration['promo_code_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($section->data_source !== 'manual' || $ids->isEmpty()) {
            return $this->resolveClientFeedSection($section, 'admin_offers');
        }

        $items = PromoCode::query()
            ->where('is_active', true)
            ->whereNull('restaurant_id')
            ->where('created_by_type', 'admin')
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        $ordered = $ids->map(fn (int $id) => $items->get($id))->filter()->values();

        return [
            'token' => 'home_section:'.$section->id,
            'type' => 'admin_offers',
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'enabled' => $ordered->isNotEmpty(),
            'items' => $ordered->map(fn (PromoCode $promo) => [
                'id' => $promo->id,
                'code' => $promo->code,
                'title' => $promo->title ?: $promo->code,
                'subtitle' => $promo->description ?? 'Special offer',
                'description' => $promo->description ?? 'Special offer',
                'image' => $promo->promo_image_url,
                'promo_image' => $promo->promo_image_url,
                'discount_type' => $promo->discount_type ?? 'percentage',
                'discount_value' => $promo->discount_value ?? 0,
                'min_order_value' => $promo->min_order_amount,
                'max_discount' => $promo->max_discount_amount,
            ])->values()->all(),
            'style' => $this->sectionStyle($section),
        ];
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
                ->with('parent')
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
}
