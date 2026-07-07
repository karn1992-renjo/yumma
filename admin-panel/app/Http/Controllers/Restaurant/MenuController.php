<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\MenuItem;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use App\Models\MasterMenuItem;
use App\Services\MediaStorage;
use App\Services\MenuPriceAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MenuController extends Controller
{
    public function adjustPrices(Request $request, MenuPriceAdjustmentService $adjuster)
    {
        $restaurant = $this->currentRestaurant();
        abort_unless($restaurant, 404, 'Restaurant not found.');
        $data = $request->validate([
            'direction' => 'required|in:increase,decrease',
            'adjustment_type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|gt:0|max:1000000',
        ]);
        $count = $adjuster->adjust($restaurant, $data['direction'], $data['adjustment_type'], (float) $data['value']);
        return back()->with('success', "Updated prices for {$count} menu items.");
    }

    use ResolvesRestaurantContext;

    public function index()
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return redirect()->route('restaurant.settings.index')->with('error', 'Please complete your restaurant profile first.');
        }
        
        $menuItems = MenuItem::where('restaurant_id', $restaurant->id)
            ->with('category')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(20);
            
        return view('restaurant.menu.index', compact('menuItems'));
    }
    
    public function create()
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return redirect()->route('restaurant.settings.index')->with('error', 'Please complete your restaurant profile first.');
        }
        
        $categories = Category::where('restaurant_id', $restaurant->id)
            ->orderBy('display_order')
            ->get();
        $cuisines = Cuisine::active()->ordered()->get();
        $globalMenuItems = MasterMenuItem::where('is_active', true)
            ->orderBy('category_name')
            ->orderBy('name')
            ->get();
        $globalCategories = $this->globalCategoryOptions();
            
        return view('restaurant.menu.create', compact('categories', 'cuisines', 'restaurant', 'globalMenuItems', 'globalCategories'));
    }
    
    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'global_category_id' => 'nullable|exists:global_menu_categories,id',
            'global_subcategory_id' => 'nullable|exists:global_menu_categories,id',
            'cuisine_id' => 'nullable|exists:cuisines,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'food_type' => 'required|in:veg,egg,non_veg',
            'is_available' => 'boolean',
            'is_recommended' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_new' => 'boolean',
            'is_spicy' => 'boolean',
            'is_combo' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:80',
            'tags_text' => 'nullable|string|max:500',
            'variants_text' => 'nullable|string',
            'add_ons_text' => 'nullable|string',
            'availability_schedule_text' => 'nullable|string',
            'preparation_time' => 'integer|min:1|max:120',
        ], $this->menuCustomizationRules()));

        if ($restaurant->is_pure_veg && $validated['food_type'] !== 'veg') {
            return back()->withInput()->with('error', 'Pure veg restaurants can only create vegetarian menu items.');
        }
        
        $validated['restaurant_id'] = $restaurant->id;
        $validated['category_id'] = $this->resolveRestaurantCategoryId($restaurant->id, $validated['category_id'] ?? null, $request->input('global_category_id'), $request->input('global_subcategory_id'));
        $validated['item_source'] = 'custom';
        $validated['approval_status'] = 'approved';
        $validated['is_veg'] = $validated['food_type'] === 'veg';
        $validated['is_available'] = $request->has('is_available');
        $validated['is_recommended'] = $request->has('is_recommended');
        $validated['is_bestseller'] = $request->has('is_bestseller');
        $validated['is_new'] = $request->has('is_new');
        $validated['is_spicy'] = $request->has('is_spicy');
        $validated['is_combo'] = $request->has('is_combo');
        $validated['tags'] = $this->tagsFromRequest($request, $validated);
        $validated['variants'] = $this->normalizeOptionRows(
            $request->input('variants', $request->input('variants_text'))
        );
        $validated['add_ons'] = $this->normalizeOptionRows(
            $request->input('add_ons', $request->input('add_ons_text'))
        );
        $validated['availability_schedule'] = $this->parseAvailabilityScheduleText($request->input('availability_schedule_text'));
        unset($validated['tags_text'], $validated['variants_text'], $validated['add_ons_text'], $validated['availability_schedule_text'], $validated['global_category_id'], $validated['global_subcategory_id']);
        $validated['images'] = $this->resolveImagesFromRequest($request);
        unset($validated['image_url']);
        
        MenuItem::create($validated);
        
        return redirect()->route('restaurant.menu.index')->with('success', 'Menu item added successfully!');
    }

    public function importFromGlobal(Request $request)
    {
        $restaurant = $this->currentRestaurant();

        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $validated = $request->validate(array_merge([
            'master_menu_item_id' => 'required|exists:master_menu_items,id',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'preparation_time' => 'nullable|integer|min:1|max:120',
            'is_available' => 'nullable|boolean',
            'variants_text' => 'nullable|string',
            'add_ons_text' => 'nullable|string',
            'availability_schedule_text' => 'nullable|string',
        ], $this->menuCustomizationRules()));

        $master = MasterMenuItem::where('is_active', true)->findOrFail($validated['master_menu_item_id']);

        if ($restaurant->is_pure_veg && $master->food_type !== 'veg') {
            return back()->withInput()->with('error', 'Pure veg restaurants can only import vegetarian menu items.');
        }

        $categoryId = $this->resolveRestaurantCategoryId($restaurant->id, null, $request->input('global_category_id'), $request->input('global_subcategory_id'), $master->category_name);

        MenuItem::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'master_menu_item_id' => $master->id,
            ],
            [
                'item_source' => 'global',
                'category_id' => $categoryId,
                'name' => $master->name,
                'description' => $master->description,
                'price' => $validated['price'],
                'discounted_price' => $validated['discounted_price'] ?? null,
                'images' => $master->images ?? [],
                'food_type' => $master->food_type,
                'is_veg' => $master->food_type === 'veg',
                'is_available' => $request->has('is_available'),
                'preparation_time' => $validated['preparation_time'] ?? $master->preparation_time ?? 15,
                'variants' => $request->has('variants') || $request->has('variants_text')
                    ? $this->normalizeOptionRows($request->input('variants', $request->input('variants_text')))
                    : ($master->variants ?? []),
                'add_ons' => $request->has('add_ons') || $request->has('add_ons_text')
                    ? $this->normalizeOptionRows($request->input('add_ons', $request->input('add_ons_text')))
                    : ($master->add_ons ?? []),
                'availability_schedule' => $this->parseAvailabilityScheduleText($request->input('availability_schedule_text')),
                'approval_status' => 'approved',
            ]
        );

        return redirect()->route('restaurant.menu.index')->with('success', "{$master->name} added from the global menu.");
    }
    
    public function edit($id)
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return redirect()->route('restaurant.settings.index')->with('error', 'Please complete your restaurant profile first.');
        }
        
        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->findOrFail($id);
        $categories = Category::where('restaurant_id', $restaurant->id)->orderBy('display_order')->get();
        $cuisines = Cuisine::active()->ordered()->get();
        $globalCategories = $this->globalCategoryOptions();
            
        return view('restaurant.menu.edit', compact('menuItem', 'categories', 'cuisines', 'restaurant', 'globalCategories'));
    }
    
    public function update(Request $request, $id)
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'global_category_id' => 'nullable|exists:global_menu_categories,id',
            'global_subcategory_id' => 'nullable|exists:global_menu_categories,id',
            'cuisine_id' => 'nullable|exists:cuisines,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'food_type' => 'required|in:veg,egg,non_veg',
            'is_available' => 'boolean',
            'is_recommended' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_new' => 'boolean',
            'is_spicy' => 'boolean',
            'is_combo' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:80',
            'tags_text' => 'nullable|string|max:500',
            'variants_text' => 'nullable|string',
            'add_ons_text' => 'nullable|string',
            'availability_schedule_text' => 'nullable|string',
            'preparation_time' => 'integer|min:1|max:120',
        ], $this->menuCustomizationRules()));

        if ($restaurant->is_pure_veg && $validated['food_type'] !== 'veg') {
            return back()->withInput()->with('error', 'Pure veg restaurants can only create vegetarian menu items.');
        }
        
        $validated['category_id'] = $this->resolveRestaurantCategoryId($restaurant->id, $validated['category_id'] ?? null, $request->input('global_category_id'), $request->input('global_subcategory_id'));
        $validated['is_veg'] = $validated['food_type'] === 'veg';
        $validated['is_available'] = $request->has('is_available');
        $validated['is_recommended'] = $request->has('is_recommended');
        $validated['is_bestseller'] = $request->has('is_bestseller');
        $validated['is_new'] = $request->has('is_new');
        $validated['is_spicy'] = $request->has('is_spicy');
        $validated['is_combo'] = $request->has('is_combo');
        $validated['tags'] = $this->tagsFromRequest($request, $validated);
        $validated['variants'] = $this->normalizeOptionRows(
            $request->input('variants', $request->input('variants_text'))
        );
        $validated['add_ons'] = $this->normalizeOptionRows(
            $request->input('add_ons', $request->input('add_ons_text'))
        );
        $validated['availability_schedule'] = $this->parseAvailabilityScheduleText($request->input('availability_schedule_text'));
        unset($validated['tags_text'], $validated['variants_text'], $validated['add_ons_text'], $validated['availability_schedule_text'], $validated['global_category_id'], $validated['global_subcategory_id']);

        if ($request->hasFile('image') || $request->filled('image_url')) {
            $this->deleteStoredImage($menuItem->image);
            $validated['images'] = $this->resolveImagesFromRequest($request);
        }
        unset($validated['image_url']);
        
        $menuItem->update($validated);
        
        return redirect()->route('restaurant.menu.index')->with('success', 'Menu item updated successfully!');
    }

    public function downloadTemplate()
    {
        $filename = 'menu-upload-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = ['Name', 'Description', 'Price', 'Discounted Price', 'Category', 'Sub Category', 'Food Type', 'Is Available', 'Preparation Time'];
        $sampleRow = ['Paneer Tikka', 'Smoky tandoori paneer', '250', '225', 'Starters', 'Tandoor', 'veg', 'Yes', '25'];

        $callback = function() use ($columns, $sampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $sampleRow);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkUpload(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (!$restaurant) {
            return redirect()->route('restaurant.menu.index')->with('error', 'Restaurant not found.');
        }

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $rows = $this->readUploadedRows($request->file('csv_file'));
        if (empty($rows)) {
            return redirect()->route('restaurant.menu.index')->with('error', 'Uploaded file is empty or incorrectly formatted.');
        }

        $header = array_keys($rows[0]);

        $expectedColumns = ['name', 'description', 'price', 'discounted_price', 'category', 'food_type', 'is_available', 'preparation_time'];
        $missingColumns = array_diff($expectedColumns, $header);

        if (!empty($missingColumns)) {
            return redirect()->route('restaurant.menu.index')->with('error', 'CSV is missing required columns: ' . implode(', ', $missingColumns));
        }

        $createdItems = 0;
        $errors = [];
        $rowNumber = 1;

        foreach ($rows as $record) {
            $rowNumber++;
            $record = array_map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, $record);

            $validator = Validator::make($record, [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'discounted_price' => 'nullable|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'sub_category' => 'nullable|string|max:255',
                'subcategory' => 'nullable|string|max:255',
                'food_type' => 'nullable|string',
                'is_available' => 'nullable|string',
                'preparation_time' => 'nullable|numeric|min:1|max:120',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            $categoryId = null;
            $subCategoryName = trim((string) ($record['sub_category'] ?? $record['subcategory'] ?? ''));
            if (!empty($record['category']) || $subCategoryName !== '') {
                $categoryName = trim($record['category']);
                $categoryName = $categoryName !== '' && $subCategoryName !== ''
                    ? "{$categoryName} / {$subCategoryName}"
                    : ($categoryName ?: $subCategoryName);
                $category = Category::firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $categoryName],
                    ['display_order' => 0, 'is_active' => true]
                );
                $categoryId = $category->id;
            }

            $foodType = strtolower(trim($record['food_type'] ?? 'veg'));
            $foodType = in_array($foodType, ['veg', 'egg', 'non_veg'], true) ? $foodType : 'veg';
            if ($restaurant->is_pure_veg && $foodType !== 'veg') {
                $errors[] = "Row {$rowNumber}: Pure veg restaurants can only import vegetarian items.";
                continue;
            }
            $isVeg = $foodType === 'veg';
            $isAvailable = Str::of(strtolower($record['is_available'] ?? 'yes'))->contains(['1', 'yes', 'true', 'y', 'on']);

            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'category_id' => $categoryId,
                'name' => $record['name'],
                'description' => $record['description'] ?: null,
                'price' => (float) $record['price'],
                'discounted_price' => $record['discounted_price'] !== '' ? (float) $record['discounted_price'] : null,
                'is_veg' => $isVeg,
                'food_type' => $foodType,
                'is_available' => $isAvailable,
                'preparation_time' => $record['preparation_time'] ? (int) $record['preparation_time'] : null,
                'images' => [],
            ]);

            $createdItems++;
        }

        $message = "Imported {$createdItems} menu item" . ($createdItems === 1 ? '' : 's') . ".";
        $redirect = redirect()->route('restaurant.menu.index')->with('success', $message);

        if (!empty($errors)) {
            $redirect = $redirect->with('upload_errors', $errors);
        }

        return $redirect;
    }
    
    public function destroy($id)
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->findOrFail($id);
        $menuItem->delete();
        
        return redirect()->route('restaurant.menu.index')->with('success', 'Menu item deleted successfully!');
    }
    
    public function toggleAvailability($id)
    {
        $restaurant = $this->currentRestaurant();
        
        if (!$restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found']);
        }
        
        $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->findOrFail($id);
        $menuItem->update(['is_available' => !$menuItem->is_available]);
        
        return response()->json(['success' => true, 'is_available' => $menuItem->is_available]);
    }

    private function globalCategoryOptions()
    {
        return GlobalMenuCategory::active()
            ->parents()
            ->with(['children' => fn ($query) => $query->active()])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
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

    private function parseOptionText(?string $text): array
    {
        if (!$text) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$name, $price] = array_pad(explode('|', $line, 2), 2, 0);
                return [
                    'name' => trim($name),
                    'price' => max(0, (float) trim((string) $price)),
                ];
            })
            ->filter(fn ($item) => $item['name'] !== '')
            ->values()
            ->all();
    }

    private function menuCustomizationRules(): array
    {
        return [
            'variants' => 'nullable|array',
            'variants.*.name' => 'nullable|string|max:120',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.is_available' => 'nullable|boolean',
            'variants.*.custom_fields_text' => 'nullable|string|max:1000',
            'add_ons' => 'nullable|array',
            'add_ons.*.name' => 'nullable|string|max:120',
            'add_ons.*.price' => 'nullable|numeric|min:0',
            'add_ons.*.is_available' => 'nullable|boolean',
            'add_ons.*.custom_fields_text' => 'nullable|string|max:1000',
        ];
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
            return is_array($decoded) ? $this->normalizeOptionRows($decoded) : $this->parseOptionText($options);
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

                $name = trim((string) ($option['name'] ?? $option['label'] ?? ''));
                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'price' => max(0, (float) ($option['price'] ?? $option['additional_price'] ?? 0)),
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
        if (!$text) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$label, $start, $end, $days] = array_pad(array_map('trim', explode('|', $line)), 4, '');

                if (!$end) {
                    $end = $start;
                    $start = $label;
                    $label = '';
                }

                if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
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

    private function readUploadedRows($file): array
    {
        $sheet = IOFactory::load($file->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);
        $header = array_shift($sheet);

        if (!$header) {
            return [];
        }

        $header = array_map(function ($column) {
            return Str::of((string) $column)->trim()->lower()->replace([' ', '-'], '_')->toString();
        }, $header);

        $rows = [];
        foreach ($sheet as $row) {
            if (empty(array_filter($row, fn ($value) => $value !== null && $value !== ''))) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($row, count($header), null));
        }

        return $rows;
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

    private function deleteStoredImage(?string $image): void
    {
        if (! $image || Str::startsWith($image, ['http://', 'https://'])) {
            return;
        }

        MediaStorage::delete($image);
    }
}
