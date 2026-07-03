<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use App\Models\MasterMenuItem;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantMenuController extends Controller
{
    private function getAuthenticatedRestaurant(Request $request): ?Restaurant
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $selectedId = $request->input('restaurant_id');
        if ($selectedId && $selectedId !== 'all') {
            if ($user->restaurants()->exists()) {
                return $user->restaurants()->whereKey((int) $selectedId)->first();
            }

            $staffRestaurant = $user->restaurantStaff()->with('restaurant')->first()?->restaurant;
            return $staffRestaurant && (int) $staffRestaurant->id === (int) $selectedId
                ? $staffRestaurant
                : null;
        }

        $activeRestaurant = $user->activeRestaurant();
        if ($activeRestaurant) {
            return $activeRestaurant;
        }

        $staffRestaurant = $user->restaurantStaff()->with('restaurant')->first()?->restaurant;
        if ($staffRestaurant) {
            return $staffRestaurant;
        }

        if ($user->current_restaurant_id) {
            return Restaurant::find($user->current_restaurant_id);
        }

        return null;
    }

    private function ensureMenuAccess()
    {
        $user = auth()->user();

        if (!$user || $user->restaurants()->exists()) {
            return null;
        }

        if ($user->can('manage_menu') || $user->can('view_menu_items')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to manage menu items.'
        ], 403);
    }

    public function index(Request $request)
    {
        $restaurant = $this->getAuthenticatedRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found for current user.'
            ], 404);
        }

        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $menuItems = MenuItem::with(['category', 'cuisine'])
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => MenuItemResource::collection($menuItems)
        ]);
    }

    public function globalCatalog(Request $request)
    {
        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $query = MasterMenuItem::query()->with('cuisine')->where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category_name', 'like', "%{$search}%")
                    ->orWhere('subcategory_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_name', $request->input('category'));
        }

        if ($request->filled('subcategory')) {
            $query->where('subcategory_name', $request->input('subcategory'));
        }

        if ($request->filled('food_type')) {
            $query->where('food_type', $request->input('food_type'));
        }

        $items = $query->orderBy('category_name')->orderBy('name')->limit(100)->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn (MasterMenuItem $item) => $this->formatGlobalCatalogItem($item))->values(),
            'filters' => [
                'categories' => MasterMenuItem::where('is_active', true)->whereNotNull('category_name')->distinct()->orderBy('category_name')->pluck('category_name'),
                'subcategories' => MasterMenuItem::where('is_active', true)->whereNotNull('subcategory_name')->distinct()->orderBy('subcategory_name')->pluck('subcategory_name'),
            ],
        ]);
    }

    public function globalCategories()
    {
        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $categories = GlobalMenuCategory::active()
            ->parents()
            ->with(['children' => fn ($query) => $query->active()])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                $children = $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'description' => $child->description,
                    'image' => $child->image,
                    'image_url' => \App\Services\MediaStorage::url($child->image),
                ])->values();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'image' => $category->image,
                    'image_url' => \App\Services\MediaStorage::url($category->image),
                    'subcategories' => $children,
                    'children' => $children,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function importFromGlobal(Request $request)
    {
        $restaurant = $this->getAuthenticatedRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found for current user.'
            ], 404);
        }

        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.master_menu_item_id' => 'required|integer|exists:master_menu_items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discounted_price' => 'nullable|numeric|min:0',
            'items.*.preparation_time' => 'nullable|integer|min:1|max:120',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.cuisine_id' => 'nullable|integer|exists:cuisines,id',
            'items.*.global_category_id' => 'nullable|integer|exists:global_menu_categories,id',
            'items.*.global_subcategory_id' => 'nullable|integer|exists:global_menu_categories,id',
            'items.*.variants' => 'nullable|array',
            'items.*.add_ons' => 'nullable|array',
            'items.*.availability_schedule' => 'nullable|array',
        ]);

        $created = [];
        $skipped = [];

        foreach ($validated['items'] as $row) {
            $master = MasterMenuItem::where('is_active', true)->findOrFail($row['master_menu_item_id']);

            if ($restaurant->is_pure_veg && $master->food_type !== 'veg') {
                $skipped[] = $master->name;
                continue;
            }

            $categoryId = $this->resolveRestaurantCategoryId($restaurant->id, null, $row['global_category_id'] ?? null, $row['global_subcategory_id'] ?? null, $master->category_name);
            $cuisineId = $this->resolveCuisineId(
                $row['cuisine_id'] ?? null,
                $master,
                $master->subcategory_name,
                $master->category_name,
                $master->name
            );

            $menuItem = MenuItem::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'master_menu_item_id' => $master->id,
                ],
                [
                    'item_source' => 'global',
                    'category_id' => $categoryId,
                    'cuisine_id' => $cuisineId,
                    'name' => $master->name,
                    'description' => $master->description,
                    'price' => $row['price'],
                    'discounted_price' => $row['discounted_price'] ?? null,
                    'images' => $master->images ?? [],
                    'food_type' => $master->food_type,
                    'is_veg' => $master->food_type === 'veg',
                    'is_available' => array_key_exists('is_available', $row) ? (bool) $row['is_available'] : true,
                    'preparation_time' => $row['preparation_time'] ?? $master->preparation_time ?? 15,
                    'variants' => $this->normalizeOptionRows($row['variants'] ?? $master->variants ?? []),
                    'add_ons' => $this->normalizeOptionRows($row['add_ons'] ?? $master->add_ons ?? []),
                    'availability_schedule' => $this->normalizeAvailabilitySchedule($row['availability_schedule'] ?? []),
                    'approval_status' => 'approved',
                ]
            );

            $created[] = new MenuItemResource($menuItem->load(['category', 'cuisine']));
        }

        if (count($created) === 0) {
            $message = count($skipped) > 0
                ? 'This item cannot be added because the restaurant is marked pure veg.'
                : 'No global menu items were added.';

            return response()->json([
                'success' => false,
                'data' => [],
                'message' => $message,
            ], 422);
        }

        $message = count($created) . ' global menu item(s) added to your menu.';
        if (count($skipped) > 0) {
            $message .= ' Skipped ' . count($skipped) . ' item(s).';
        }

        return response()->json([
            'success' => true,
            'data' => $created,
            'message' => $message,
        ], 201);
    }

    public function store(Request $request)
    {
        $this->decodeJsonArrayFields($request, ['tags', 'variants', 'add_ons', 'availability_schedule']);
        $this->normalizeRequestScalars($request);

        $restaurant = $this->getAuthenticatedRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found for current user.'
            ], 404);
        }

        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'global_category_id' => 'nullable|integer|exists:global_menu_categories,id',
            'global_subcategory_id' => 'nullable|integer|exists:global_menu_categories,id',
            'cuisine_id' => 'nullable|integer|exists:cuisines,id',
            'description' => 'nullable|string',
            'discounted_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'food_type' => 'nullable|in:veg,egg,non_veg',
            'is_veg' => 'nullable|boolean',
            'is_available' => 'nullable|boolean',
            'is_recommended' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'is_spicy' => 'nullable|boolean',
            'is_combo' => 'nullable|boolean',
            'variants' => 'nullable|array',
            'add_ons' => 'nullable|array',
            'availability_schedule' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:80',
        ], $this->menuCustomizationRules()));

        $foodType = $request->food_type ?: ($request->boolean('is_veg', true) ? 'veg' : 'non_veg');
        if ($restaurant->is_pure_veg && $foodType !== 'veg') {
            return response()->json([
                'success' => false,
                'message' => 'Pure veg restaurants can only create vegetarian menu items.'
            ], 422);
        }

        $menuItem = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'item_source' => 'custom',
            'approval_status' => 'approved',
            'category_id' => $this->resolveRestaurantCategoryId($restaurant->id, $request->category_id, $request->global_category_id, $request->global_subcategory_id),
            'name' => $request->name,
            'price' => $request->price,
            'discounted_price' => $request->discounted_price,
            'description' => $request->description,
            'cuisine_id' => $this->resolveCuisineId(
                $request->cuisine_id,
                null,
                $request->input('name')
            ),
            'food_type' => $foodType,
            'is_veg' => $foodType === 'veg',
            'is_available' => $request->filled('is_available') ? $request->is_available : true,
            'is_recommended' => $request->boolean('is_recommended'),
            'is_bestseller' => $request->boolean('is_bestseller'),
            'is_new' => $request->boolean('is_new'),
            'is_spicy' => $request->boolean('is_spicy'),
            'is_combo' => $request->boolean('is_combo'),
            'tags' => $this->normalizeTags($request->input('tags', [])),
            'variants' => $this->normalizeOptionRows($request->input('variants', [])),
            'add_ons' => $this->normalizeOptionRows($request->input('add_ons', [])),
            'availability_schedule' => $this->normalizeAvailabilitySchedule($request->input('availability_schedule', [])),
        ]);

        $menuItem->images = $this->resolveImagesFromRequest($request);
        $menuItem->save();

        return response()->json([
            'success' => true,
            'data' => new MenuItemResource($menuItem->load(['category', 'cuisine'])),
            'message' => 'Menu item created successfully.'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->decodeJsonArrayFields($request, ['tags', 'variants', 'add_ons', 'availability_schedule']);
        $this->normalizeRequestScalars($request);

        $restaurant = $this->getAuthenticatedRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found for current user.'
            ], 404);
        }

        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found.'
            ], 404);
        }

        $validated = $request->validate(array_merge([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'global_category_id' => 'nullable|integer|exists:global_menu_categories,id',
            'global_subcategory_id' => 'nullable|integer|exists:global_menu_categories,id',
            'cuisine_id' => 'nullable|integer|exists:cuisines,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'food_type' => 'nullable|in:veg,egg,non_veg',
            'is_veg' => 'nullable|boolean',
            'is_available' => 'nullable|boolean',
            'is_recommended' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'is_spicy' => 'nullable|boolean',
            'is_combo' => 'nullable|boolean',
            'variants' => 'nullable|array',
            'add_ons' => 'nullable|array',
            'availability_schedule' => 'nullable|array',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:80',
        ], $this->menuCustomizationRules()));

        if (array_key_exists('food_type', $validated)) {
            if ($restaurant->is_pure_veg && $validated['food_type'] !== 'veg') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pure veg restaurants can only create vegetarian menu items.'
                ], 422);
            }
            $validated['is_veg'] = $validated['food_type'] === 'veg';
        } elseif (array_key_exists('is_veg', $validated)) {
            $validated['food_type'] = $validated['is_veg'] ? 'veg' : 'non_veg';
        }

        if ($request->hasFile('image') || $request->filled('image_url')) {
            $this->deleteStoredImage($menuItem->image);
            $validated['images'] = $this->resolveImagesFromRequest($request);
        }

        if (array_key_exists('variants', $validated)) {
            $validated['variants'] = $this->normalizeOptionRows($request->input('variants', []));
        }

        if (array_key_exists('add_ons', $validated)) {
            $validated['add_ons'] = $this->normalizeOptionRows($request->input('add_ons', []));
        }

        if (array_key_exists('availability_schedule', $validated)) {
            $validated['availability_schedule'] = $this->normalizeAvailabilitySchedule($request->input('availability_schedule', []));
        }

        if (array_key_exists('tags', $validated)) {
            $validated['tags'] = $this->normalizeTags($request->input('tags', []));
        }

        if ($request->filled('global_category_id') || array_key_exists('category_id', $validated)) {
            $validated['category_id'] = $this->resolveRestaurantCategoryId(
                $restaurant->id,
                $validated['category_id'] ?? null,
                $request->input('global_category_id'),
                $request->input('global_subcategory_id')
            );
        }

        unset($validated['global_category_id'], $validated['global_subcategory_id']);

        $menuItem->update($validated);

        return response()->json([
            'success' => true,
            'data' => new MenuItemResource($menuItem->fresh(['category', 'cuisine'])),
            'message' => 'Menu item updated successfully.'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $restaurant = $this->getAuthenticatedRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found for current user.'
            ], 404);
        }

        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found.'
            ], 404);
        }

        $menuItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu item deleted successfully.'
        ]);
    }

    public function toggleAvailability(Request $request, $id)
    {
        $restaurant = $this->getAuthenticatedRestaurant($request);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found for current user.'
            ], 404);
        }

        if ($response = $this->ensureMenuAccess()) {
            return $response;
        }

        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found.'
            ], 404);
        }

        $menuItem->is_available = $request->has('is_available')
            ? $request->boolean('is_available')
            : !$menuItem->is_available;
        $menuItem->save();

        return response()->json([
            'success' => true,
            'data' => $menuItem,
            'message' => 'Menu item availability updated successfully.'
        ]);
    }

    private function normalizeRequestScalars(Request $request): void
    {
        $updates = [];

        foreach ([
            'discounted_price',
            'category_id',
            'global_category_id',
            'global_subcategory_id',
            'cuisine_id',
            'preparation_time',
            'image_url',
        ] as $field) {
            if (!$request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            if (is_string($value) && in_array(strtolower(trim($value)), ['', 'null', 'undefined'], true)) {
                $updates[$field] = null;
            }
        }

        foreach ([
            'is_veg',
            'is_available',
            'is_recommended',
            'is_bestseller',
            'is_new',
            'is_spicy',
            'is_combo',
        ] as $field) {
            if (!$request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            if (is_string($value) && in_array(strtolower(trim($value)), ['true', 'false'], true)) {
                $updates[$field] = strtolower(trim($value)) === 'true' ? 1 : 0;
            }
        }

        if (!empty($updates)) {
            $request->merge($updates);
        }
    }

    private function resolveCuisineId(?int $cuisineId = null, ?MasterMenuItem $master = null, ?string ...$terms): ?int
    {
        if ($cuisineId) {
            return $cuisineId;
        }

        if ($master && $master->cuisine_id) {
            return (int) $master->cuisine_id;
        }

        $cuisines = Cuisine::query()->where('is_active', true)->get(['id', 'name', 'slug']);
        $values = collect($terms)
            ->filter(fn ($term) => filled($term))
            ->map(fn ($term) => Str::slug((string) $term))
            ->filter()
            ->values();

        foreach ($values as $value) {
            $match = $cuisines->first(function ($cuisine) use ($value) {
                $name = Str::slug((string) $cuisine->name);
                $slug = Str::slug((string) ($cuisine->slug ?: $cuisine->name));

                return $value === $name ||
                    $value === $slug ||
                    str_contains($value, $name) ||
                    str_contains($name, $value) ||
                    str_contains($value, $slug) ||
                    str_contains($slug, $value);
            });

            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }

    private function resolveRestaurantCategoryId(int $restaurantId, ?int $categoryId = null, ?int $globalCategoryId = null, ?int $globalSubcategoryId = null, ?string $fallbackName = null): ?int
    {
        if ($categoryId) {
            return $categoryId;
        }

        $name = $fallbackName;
        if ($globalCategoryId) {
            $globalCategory = GlobalMenuCategory::active()->find($globalCategoryId);
            $name = $globalCategory?->name;
            if ($globalSubcategoryId) {
                $globalSubcategory = GlobalMenuCategory::active()
                    ->where('parent_id', $globalCategoryId)
                    ->find($globalSubcategoryId);
                $name = $globalCategory && $globalSubcategory
                    ? "{$globalCategory->name} / {$globalSubcategory->name}"
                    : $name;
            }
        }

        if (!$name) {
            return null;
        }

        return Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => $name],
            ['display_order' => 0, 'is_active' => true]
        )->id;
    }

    private function decodeJsonArrayFields(Request $request, array $fields): void
    {
        $decoded = [];

        foreach ($fields as $field) {
            $value = $request->input($field);
            if (! is_string($value)) {
                continue;
            }

            $parsed = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $decoded[$field] = $parsed;
            }
        }

        if ($decoded !== []) {
            $request->merge($decoded);
        }
    }

    private function resolveImagesFromRequest(Request $request): array
    {
        if ($request->hasFile('image')) {
            $path = MediaStorage::store($request->file('image'), 'menu-items');

            return [$path];
        }

        if ($request->filled('image_url')) {
            return [(string) $request->input('image_url')];
        }

        return [];
    }

    private function formatGlobalCatalogItem(MasterMenuItem $item): array
    {
        $images = collect($item->images ?? [])
            ->map(fn ($image) => MediaStorage::url((string) $image))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $item->id,
            'name' => $item->name,
            'category_name' => $item->category_name,
            'subcategory_name' => $item->subcategory_name,
            'cuisine_id' => $item->cuisine_id,
            'cuisine_name' => $item->cuisine?->name,
            'description' => $item->description,
            'food_type' => $item->food_type,
            'diet_label' => $item->diet_label,
            'images' => $images,
            'image' => $images[0] ?? null,
            'image_url' => $images[0] ?? null,
            'preparation_time' => $item->preparation_time,
            'gst' => $item->gst,
            'hsn_code' => $item->hsn_code,
            'variants' => $item->variants ?? [],
            'add_ons' => $item->add_ons ?? [],
            'is_active' => (bool) $item->is_active,
        ];
    }

    private function normalizeTags($tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->values()
            ->all();
    }

    private function menuCustomizationRules(): array
    {
        return [
            'variants.*.name' => 'nullable|string|max:120',
            'variants.*.label' => 'nullable|string|max:120',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.additional_price' => 'nullable|numeric|min:0',
            'variants.*.is_available' => 'nullable|boolean',
            'variants.*.custom_fields' => 'nullable|array',
            'variants.*.custom_fields_text' => 'nullable|string|max:1000',
            'add_ons.*.name' => 'nullable|string|max:120',
            'add_ons.*.label' => 'nullable|string|max:120',
            'add_ons.*.price' => 'nullable|numeric|min:0',
            'add_ons.*.additional_price' => 'nullable|numeric|min:0',
            'add_ons.*.is_available' => 'nullable|boolean',
            'add_ons.*.custom_fields' => 'nullable|array',
            'add_ons.*.custom_fields_text' => 'nullable|string|max:1000',
        ];
    }

    private function normalizeOptionRows($options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($options)) {
            return [];
        }

        return collect($options)
            ->map(function ($option) {
                if (is_string($option)) {
                    $name = trim($option);

                    return $name === '' ? null : [
                        'name' => $name,
                        'price' => 0,
                        'is_available' => true,
                        'custom_fields' => [],
                    ];
                }

                if (!is_array($option)) {
                    return null;
                }

                $name = trim((string) ($option['name'] ?? $option['label'] ?? $option['title'] ?? ''));
                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'price' => max(0, (float) ($option['price'] ?? $option['additional_price'] ?? $option['amount'] ?? 0)),
                    'is_available' => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'custom_fields' => $this->normalizeCustomFields($option),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeCustomFields(array $option): array
    {
        $fields = [];

        if (isset($option['custom_fields']) && is_array($option['custom_fields'])) {
            foreach ($option['custom_fields'] as $key => $value) {
                if (is_scalar($value) && trim((string) $key) !== '') {
                    $fields[trim((string) $key)] = trim((string) $value);
                }
            }
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) ($option['custom_fields_text'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$key, $value] = array_pad(preg_split('/[:=|]/', $line, 2), 2, '');
            $key = trim((string) $key);
            $value = trim((string) $value);

            if ($key !== '' && $value !== '') {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    private function normalizeAvailabilitySchedule($slots): array
    {
        if (is_string($slots)) {
            $decoded = json_decode($slots, true);
            $slots = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($slots)) {
            return [];
        }

        return collect($slots)
            ->map(function ($slot) {
                if (!is_array($slot)) {
                    return null;
                }

                $start = trim((string) ($slot['start'] ?? ''));
                $end = trim((string) ($slot['end'] ?? ''));

                if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
                    return null;
                }

                $days = $slot['days'] ?? [];
                $days = is_array($days)
                    ? array_values(array_filter(array_map(fn ($day) => strtolower(trim((string) $day)), $days)))
                    : [];

                return [
                    'label' => trim((string) ($slot['label'] ?? '')),
                    'start' => $start,
                    'end' => $end,
                    'days' => $days,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function deleteStoredImage(?string $image): void
    {
        if (! $image || Str::startsWith($image, ['http://', 'https://'])) {
            return;
        }

        MediaStorage::delete($image);
    }
}
