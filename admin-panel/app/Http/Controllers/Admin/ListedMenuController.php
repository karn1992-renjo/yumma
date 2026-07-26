<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use App\Models\MasterMenuItem;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListedMenuController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::query()
            ->with(['restaurant:id,name,is_pure_veg', 'category:id,name,restaurant_id', 'cuisine:id,name', 'masterMenuItem:id,name']);

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', (int) $request->input('restaurant_id'));
        }

        if ($request->filled('status')) {
            $request->input('status') === 'available'
                ? $query->where('is_available', true)
                : $query->where('is_available', false);
        }

        if ($request->filled('source')) {
            $query->where('item_source', $request->input('source'));
        }

        if ($request->filled('food_type')) {
            $query->where('food_type', $request->input('food_type'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
            });
        }

        $menuItems = $query
            ->latest('updated_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.listed-menu.index', [
            'menuItems' => $menuItems,
            'restaurants' => $this->restaurantOptions(),
            'stats' => [
                'total' => MenuItem::count(),
                'available' => MenuItem::where('is_available', true)->count(),
                'global' => MenuItem::where('item_source', 'global')->count(),
                'custom' => MenuItem::where('item_source', 'custom')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.listed-menu.create', $this->formData([
            'menuItem' => new MenuItem([
                'food_type' => 'veg',
                'is_available' => true,
                'preparation_time' => 20,
            ]),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $restaurant = Restaurant::findOrFail((int) $validated['restaurant_id']);

        if ($restaurant->is_pure_veg && $validated['food_type'] !== 'veg') {
            return back()->withInput()->with('error', 'Pure veg restaurants can only have vegetarian menu items.');
        }

        $menuItem = MenuItem::create($this->menuItemData($request, $validated, $restaurant));
        $menuItem->images = $this->resolveImagesFromRequest($request);
        $menuItem->save();

        return redirect()->route('admin.listed-menu.show', $menuItem)
            ->with('success', 'Menu item created for '.$restaurant->name.'.');
    }

    public function storeFromGlobal(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'master_menu_item_id' => ['required', 'integer', 'exists:master_menu_items,id'],
            'global_category_id' => ['nullable', 'integer', 'exists:global_menu_categories,id'],
            'global_subcategory_id' => ['nullable', 'integer', 'exists:global_menu_categories,id'],
            'cuisine_id' => ['nullable', 'integer', 'exists:cuisines,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'preparation_time' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_available' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_available' => ['nullable', 'boolean'],
            'variants.*.custom_fields_text' => ['nullable', 'string', 'max:1000'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.name' => ['nullable', 'string', 'max:120'],
            'add_ons.*.price' => ['nullable', 'numeric', 'min:0'],
            'add_ons.*.is_available' => ['nullable', 'boolean'],
            'add_ons.*.custom_fields_text' => ['nullable', 'string', 'max:1000'],
            'availability_schedule_text' => ['nullable', 'string'],
        ]);

        $restaurant = Restaurant::findOrFail((int) $validated['restaurant_id']);
        $master = MasterMenuItem::where('is_active', true)->findOrFail((int) $validated['master_menu_item_id']);

        if ($restaurant->is_pure_veg && $master->food_type !== 'veg') {
            return back()->withInput()->with('error', 'Pure veg restaurants can only have vegetarian menu items.');
        }

        $categoryId = $this->resolveRestaurantCategoryId(
            $restaurant->id,
            null,
            $validated['global_category_id'] ?? null,
            $validated['global_subcategory_id'] ?? null,
            $master->category_name
        );

        $menuItem = MenuItem::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'master_menu_item_id' => $master->id,
            ],
            [
                'item_source' => 'global',
                'category_id' => $categoryId,
                'cuisine_id' => $validated['cuisine_id']
                    ?? $master->cuisine_id
                    ?? $this->resolveGlobalCategoryCuisineId($validated['global_category_id'] ?? null, $validated['global_subcategory_id'] ?? null),
                'name' => $master->name,
                'description' => $master->description,
                'price' => $validated['price'],
                'discounted_price' => $validated['discounted_price'] ?? null,
                'images' => $master->images ?? [],
                'food_type' => $master->food_type,
                'is_veg' => $master->food_type === 'veg',
                'is_available' => $request->boolean('is_available', true),
                'preparation_time' => $validated['preparation_time'] ?? $master->preparation_time ?? 20,
                'variants' => $request->has('variants')
                    ? $this->normalizeOptionRows($request->input('variants', []))
                    : ($master->variants ?? []),
                'add_ons' => $request->has('add_ons')
                    ? $this->normalizeOptionRows($request->input('add_ons', []))
                    : ($master->add_ons ?? []),
                'availability_schedule' => $this->parseAvailabilityScheduleText($request->input('availability_schedule_text')),
                'approval_status' => 'approved',
            ]
        );

        return redirect()->route('admin.listed-menu.show', $menuItem)
            ->with('success', "{$master->name} assigned to {$restaurant->name}.");
    }

    public function show(MenuItem $listedMenu)
    {
        $listedMenu->load(['restaurant', 'category', 'cuisine', 'masterMenuItem']);

        return view('admin.listed-menu.show', [
            'menuItem' => $listedMenu,
        ]);
    }

    public function edit(MenuItem $listedMenu)
    {
        $listedMenu->load(['restaurant', 'category', 'cuisine', 'masterMenuItem']);

        return view('admin.listed-menu.edit', $this->formData([
            'menuItem' => $listedMenu,
        ]));
    }

    public function update(Request $request, MenuItem $listedMenu)
    {
        $validated = $request->validate($this->rules($listedMenu));
        $restaurant = Restaurant::findOrFail((int) $validated['restaurant_id']);

        if ($restaurant->is_pure_veg && $validated['food_type'] !== 'veg') {
            return back()->withInput()->with('error', 'Pure veg restaurants can only have vegetarian menu items.');
        }

        $data = $this->menuItemData($request, $validated, $restaurant);

        if ($request->hasFile('image') || $request->filled('image_url')) {
            $this->deleteStoredImage($listedMenu->image);
            $data['images'] = $this->resolveImagesFromRequest($request);
        }

        $listedMenu->update($data);

        return redirect()->route('admin.listed-menu.show', $listedMenu)
            ->with('success', 'Menu item updated.');
    }

    public function destroy(MenuItem $listedMenu)
    {
        $this->deleteStoredImage($listedMenu->image);
        $listedMenu->delete();

        return redirect()->route('admin.listed-menu.index')
            ->with('success', 'Menu item deleted.');
    }

    public function toggleAvailability(MenuItem $listedMenu)
    {
        $listedMenu->update(['is_available' => ! $listedMenu->is_available]);

        return back()->with('success', $listedMenu->is_available ? 'Menu item is available.' : 'Menu item is unavailable.');
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'restaurants' => $this->restaurantOptions(),
            'categories' => Category::query()
                ->with('restaurant:id,name')
                ->orderBy('restaurant_id')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'restaurant_id', 'name']),
            'cuisines' => Cuisine::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'globalCategories' => $this->globalCategoryOptions(),
            'globalMenuItems' => MasterMenuItem::query()
                ->with('cuisine:id,name')
                ->where('is_active', true)
                ->orderBy('category_name')
                ->orderBy('subcategory_name')
                ->orderBy('name')
                ->get(),
        ], $overrides);
    }

    private function restaurantOptions()
    {
        return Restaurant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_pure_veg']);
    }

    private function globalCategoryOptions()
    {
        return GlobalMenuCategory::active()
            ->parents()
            ->with([
                'cuisines:id,name',
                'children' => fn ($query) => $query->active()->with('cuisines:id,name'),
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function rules(?MenuItem $menuItem = null): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'master_menu_item_id' => ['nullable', 'integer', 'exists:master_menu_items,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'global_category_id' => ['nullable', 'integer', 'exists:global_menu_categories,id'],
            'global_subcategory_id' => ['nullable', 'integer', 'exists:global_menu_categories,id'],
            'cuisine_id' => ['nullable', 'integer', 'exists:cuisines,id'],
            'food_type' => ['required', 'in:veg,egg,non_veg'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'is_available' => ['nullable', 'boolean'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_bestseller' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'is_spicy' => ['nullable', 'boolean'],
            'is_combo' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:80'],
            'tags_text' => ['nullable', 'string', 'max:500'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_available' => ['nullable', 'boolean'],
            'variants.*.custom_fields_text' => ['nullable', 'string', 'max:1000'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.name' => ['nullable', 'string', 'max:120'],
            'add_ons.*.price' => ['nullable', 'numeric', 'min:0'],
            'add_ons.*.is_available' => ['nullable', 'boolean'],
            'add_ons.*.custom_fields_text' => ['nullable', 'string', 'max:1000'],
            'availability_schedule_text' => ['nullable', 'string'],
            'preparation_time' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }

    private function menuItemData(Request $request, array $validated, Restaurant $restaurant): array
    {
        $categoryId = $this->resolveRestaurantCategoryId(
            $restaurant->id,
            $validated['category_id'] ?? null,
            $validated['global_category_id'] ?? null,
            $validated['global_subcategory_id'] ?? null
        );

        return [
            'restaurant_id' => $restaurant->id,
            'master_menu_item_id' => $validated['master_menu_item_id'] ?? null,
            'item_source' => ! empty($validated['master_menu_item_id']) ? 'global' : 'custom',
            'approval_status' => 'approved',
            'category_id' => $categoryId,
            'cuisine_id' => $validated['cuisine_id']
                ?? $this->resolveGlobalCategoryCuisineId($validated['global_category_id'] ?? null, $validated['global_subcategory_id'] ?? null),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'discounted_price' => $validated['discounted_price'] ?? null,
            'food_type' => $validated['food_type'],
            'is_veg' => $validated['food_type'] === 'veg',
            'is_available' => $request->boolean('is_available', true),
            'is_recommended' => $request->boolean('is_recommended'),
            'is_bestseller' => $request->boolean('is_bestseller'),
            'is_new' => $request->boolean('is_new'),
            'is_spicy' => $request->boolean('is_spicy'),
            'is_combo' => $request->boolean('is_combo'),
            'tags' => $this->tagsFromRequest($request, $validated),
            'variants' => $this->normalizeOptionRows($request->input('variants', [])),
            'add_ons' => $this->normalizeOptionRows($request->input('add_ons', [])),
            'availability_schedule' => $this->parseAvailabilityScheduleText($request->input('availability_schedule_text')),
            'preparation_time' => $validated['preparation_time'] ?? 20,
        ];
    }

    private function resolveRestaurantCategoryId(int $restaurantId, ?int $categoryId = null, ?int $globalCategoryId = null, ?int $globalSubcategoryId = null, ?string $fallbackName = null): ?int
    {
        if ($categoryId) {
            $category = Category::where('restaurant_id', $restaurantId)->findOrFail($categoryId);

            return $category->id;
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

        if (! $name) {
            return null;
        }

        return Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => $name],
            ['display_order' => 0, 'is_active' => true]
        )->id;
    }

    private function resolveGlobalCategoryCuisineId(?int $globalCategoryId = null, ?int $globalSubcategoryId = null): ?int
    {
        if ($globalSubcategoryId) {
            $subcategory = GlobalMenuCategory::active()
                ->where('parent_id', $globalCategoryId)
                ->with('cuisines:id')
                ->find($globalSubcategoryId);

            if ($cuisineId = $this->singleMappedCuisineId($subcategory)) {
                return $cuisineId;
            }
        }

        if ($globalCategoryId) {
            $category = GlobalMenuCategory::active()
                ->with('cuisines:id')
                ->find($globalCategoryId);

            return $this->singleMappedCuisineId($category);
        }

        return null;
    }

    private function singleMappedCuisineId(?GlobalMenuCategory $category): ?int
    {
        if (! $category) {
            return null;
        }

        $ids = $category->cuisines->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $ids->count() === 1 ? $ids->first() : null;
    }

    private function tagsFromRequest(Request $request, array $data): array
    {
        $inputTags = $request->input('tags', []);
        if (! is_array($inputTags)) {
            $inputTags = [$inputTags];
        }

        $textTags = preg_split('/[,;\r\n]+/', (string) $request->input('tags_text', '')) ?: [];
        $tags = array_merge($inputTags, $textTags);

        foreach ([
            'is_bestseller' => 'bestseller',
            'is_recommended' => 'recommended',
            'is_new' => 'new',
            'is_spicy' => 'spicy',
            'is_combo' => 'combo',
        ] as $flag => $tag) {
            if (! empty($data[$flag])) {
                $tags[] = $tag;
            }
        }

        $normalized = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }

            $key = mb_strtolower($tag);
            if (! array_key_exists($key, $normalized)) {
                $normalized[$key] = $tag;
            }
        }

        return array_values($normalized);
    }

    private function normalizeOptionRows($options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);

            return is_array($decoded) ? $this->normalizeOptionRows($decoded) : [];
        }

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->map(function ($option) {
                if (! is_array($option)) {
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

    private function parseAvailabilityScheduleText(?string $text): array
    {
        if (! $text) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$label, $start, $end, $days] = array_pad(array_map('trim', explode('|', $line)), 4, '');

                if (! $end) {
                    $end = $start;
                    $start = $label;
                    $label = '';
                }

                if (! preg_match('/^\d{1,2}:\d{2}$/', $start) || ! preg_match('/^\d{1,2}:\d{2}$/', $end)) {
                    return null;
                }

                return [
                    'label' => $label,
                    'start' => $start,
                    'end' => $end,
                    'days' => $days
                        ? array_values(array_filter(array_map(fn ($day) => strtolower(trim($day)), explode(',', $days))))
                        : [],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveImagesFromRequest(Request $request): array
    {
        if ($request->hasFile('image')) {
            return [MediaStorage::store($request->file('image'), 'menu-items')];
        }

        if ($request->filled('image_url')) {
            return [(string) $request->input('image_url')];
        }

        return [];
    }

    private function deleteStoredImage(?string $image): void
    {
        if (! $image || Str::startsWith($image, ['http://', 'https://'])) {
            return;
        }

        MediaStorage::delete($image);
    }
}
