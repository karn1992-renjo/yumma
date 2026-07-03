<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use App\Models\MasterMenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MasterMenuItemController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterMenuItem::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('category_name', 'like', "%{$search}%")
                    ->orWhere('subcategory_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_name', $request->input('category'));
        }

        if ($request->filled('food_type')) {
            $query->where('food_type', $request->input('food_type'));
        }

        $items = $query->with('cuisine')->withCount('restaurantMenuItems')->latest()->paginate(20)->withQueryString();
        $categories = MasterMenuItem::whereNotNull('category_name')->distinct()->orderBy('category_name')->pluck('category_name');

        return view('admin.master-menu-items.index', compact('items', 'categories'));
    }

    public function create()
    {
        return view('admin.master-menu-items.create', [
            'item' => new MasterMenuItem(),
            'globalCategories' => $this->globalCategoryOptions(),
            'cuisines' => Cuisine::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function downloadTemplate()
    {
        $filename = 'global-menu-items-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = [
            'Menu Name',
            'Category',
            'Sub Category',
            'Description',
            'Food Type',
            'Image URL',
            'Preparation Time',
            'GST',
            'HSN Code',
            'Variants',
            'Addons',
            'Cuisine',
            'Active',
        ];

        $sampleRow = [
            'Margherita Pizza',
            'Pizza',
            'Veg Pizza',
            'Classic cheese pizza with tomato sauce',
            'veg',
            'https://example.com/margherita.jpg',
            '20',
            '5',
            '996331',
            'Small|199; Medium|299; Large|399',
            'Extra Cheese|50; Cold Drink|60',
            'Yes',
        ];

        return response()->stream(function () use ($columns, $sampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $sampleRow);
            fclose($handle);
        }, 200, $headers);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'upload_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $rows = $this->readUploadedRows($request->file('upload_file'));
        if (empty($rows)) {
            return back()->with('error', 'Uploaded file is empty or incorrectly formatted.');
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $record) {
            $rowNumber = $index + 2;
            $record = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $record);

            $validator = Validator::make($record, [
                'menu_name' => 'required|string|max:255',
                'category' => 'nullable|string|max:120',
                'sub_category' => 'nullable|string|max:120',
                'description' => 'nullable|string',
                'food_type' => 'nullable|in:veg,egg,non_veg',
                'image_url' => 'nullable|url|max:2048',
                'preparation_time' => 'nullable|integer|min:1|max:120',
                'gst' => 'nullable|numeric|min:0|max:99.99',
                'hsn_code' => 'nullable|string|max:50',
                'variants' => 'nullable|string',
                'addons' => 'nullable|string',
                'active' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            $item = MasterMenuItem::updateOrCreate(
                ['name' => $record['menu_name']],
                [
                    'category_name' => $record['category'] ?? null,
                    'subcategory_name' => $record['sub_category'] ?? null,
                    'cuisine_id' => $this->resolveCuisineId(
                        $record['cuisine'] ?? null,
                        $record['sub_category'] ?? null,
                        $record['category'] ?? null,
                        $record['menu_name'] ?? null
                    ),
                    'description' => $record['description'] ?? null,
                    'food_type' => ($record['food_type'] ?? '') ?: 'veg',
                    'images' => !empty($record['image_url']) ? [$record['image_url']] : [],
                    'preparation_time' => ($record['preparation_time'] ?? '') !== '' ? (int) $record['preparation_time'] : null,
                    'gst' => ($record['gst'] ?? '') !== '' ? (float) $record['gst'] : null,
                    'hsn_code' => $record['hsn_code'] ?? null,
                    'variants' => $this->parseDelimitedOptions($record['variants'] ?? ''),
                    'add_ons' => $this->parseDelimitedOptions($record['addons'] ?? ''),
                    'is_active' => $this->truthy($record['active'] ?? true),
                ]
            );

            $item->wasRecentlyCreated ? $created++ : $updated++;
        }

        $message = "Imported {$created} global menu item" . ($created === 1 ? '' : 's') . ".";
        if ($updated > 0) {
            $message .= " Updated {$updated}.";
        }

        $redirect = redirect()->route('admin.master-menu-items.index')->with('success', $message);

        return empty($errors) ? $redirect : $redirect->with('upload_errors', $errors);
    }

    public function store(Request $request)
    {
        MasterMenuItem::create($this->validatedData($request));

        return redirect()->route('admin.master-menu-items.index')->with('success', 'Global menu item created successfully.');
    }

    public function edit(MasterMenuItem $masterMenuItem)
    {
        return view('admin.master-menu-items.edit', [
            'item' => $masterMenuItem,
            'globalCategories' => $this->globalCategoryOptions(),
            'cuisines' => Cuisine::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, MasterMenuItem $masterMenuItem)
    {
        $masterMenuItem->update($this->validatedData($request, $masterMenuItem));

        return redirect()->route('admin.master-menu-items.index')->with('success', 'Global menu item updated successfully.');
    }

    public function destroy(MasterMenuItem $masterMenuItem)
    {
        $this->deleteStoredImage($masterMenuItem->image);
        $masterMenuItem->delete();

        return redirect()->route('admin.master-menu-items.index')->with('success', 'Global menu item deleted successfully.');
    }

    private function validatedData(Request $request, ?MasterMenuItem $item = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'global_category_id' => 'nullable|exists:global_menu_categories,id',
            'global_subcategory_id' => 'nullable|exists:global_menu_categories,id',
            'cuisine_id' => 'nullable|integer|exists:cuisines,id',
            'category_name' => 'nullable|string|max:120',
            'subcategory_name' => 'nullable|string|max:120',
            'description' => 'nullable|string',
            'food_type' => 'required|in:veg,egg,non_veg',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'preparation_time' => 'nullable|integer|min:1|max:120',
            'gst' => 'nullable|numeric|min:0|max:99.99',
            'hsn_code' => 'nullable|string|max:50',
            'variants_text' => 'nullable|string',
            'add_ons_text' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('global_category_id')) {
            $globalCategory = GlobalMenuCategory::find($request->input('global_category_id'));
            $validated['category_name'] = $globalCategory?->name ?: $validated['category_name'];
        }

        if ($request->filled('global_subcategory_id')) {
            $globalSubcategory = GlobalMenuCategory::find($request->input('global_subcategory_id'));
            $validated['subcategory_name'] = $globalSubcategory?->name ?: $validated['subcategory_name'];
        }

        $validated['cuisine_id'] = $validated['cuisine_id'] ?? $this->resolveCuisineId(
            null,
            $validated['subcategory_name'] ?? null,
            $validated['category_name'] ?? null,
            $validated['name'] ?? null
        );

        $validated['variants'] = $this->parseOptions($request->input('variants_text'));
        $validated['add_ons'] = $this->parseOptions($request->input('add_ons_text'));
        $validated['is_active'] = $request->has('is_active');

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($item?->image);
            $validated['images'] = [$request->file('image')->store('master-menu-items', 'public')];
        } elseif ($request->filled('image_url')) {
            $this->deleteStoredImage($item?->image);
            $validated['images'] = [$request->input('image_url')];
        } elseif ($item) {
            $validated['images'] = $item->images ?? [];
        }

        unset($validated['image'], $validated['image_url'], $validated['variants_text'], $validated['add_ons_text'], $validated['global_category_id'], $validated['global_subcategory_id']);

        return $validated;
    }

    private function resolveCuisineId(?string $explicitCuisine, ?string ...$terms): ?int
    {
        $cuisines = Cuisine::query()->where('is_active', true)->get(['id', 'name', 'slug']);
        $values = collect([$explicitCuisine, ...$terms])
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

    private function globalCategoryOptions()
    {
        return GlobalMenuCategory::active()
            ->parents()
            ->with(['children' => fn ($query) => $query->active()])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function parseOptions(?string $text): array
    {
        if (!$text) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$name, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, 0);

                return [
                    'name' => $name,
                    'price' => max(0, (float) $price),
                    'is_available' => true,
                    'custom_fields' => [],
                ];
            })
            ->filter(fn ($row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    private function parseDelimitedOptions(?string $text): array
    {
        if (!$text) {
            return [];
        }

        return collect(preg_split('/[;\r\n]+/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$name, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, 0);

                return [
                    'name' => $name,
                    'price' => max(0, (float) $price),
                    'is_available' => true,
                    'custom_fields' => [],
                ];
            })
            ->filter(fn ($row) => $row['name'] !== '')
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

        $header = array_map(fn ($column) => Str::of((string) $column)->trim()->lower()->replace([' ', '-'], '_')->toString(), $header);
        $rows = [];

        foreach ($sheet as $row) {
            if (empty(array_filter($row, fn ($value) => $value !== null && $value !== ''))) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($row, count($header), null));
        }

        return $rows;
    }

    private function truthy($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'y', 'on', 'active'], true);
    }

    private function deleteStoredImage(?string $image): void
    {
        if (!$image || Str::startsWith($image, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($image);
    }
}
