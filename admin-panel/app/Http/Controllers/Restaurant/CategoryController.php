<?php

namespace App\Http\Controllers\Restaurant;

use App\Exports\CategoryExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CategoryController extends Controller
{
    use ResolvesRestaurantContext;

    public function index()
    {
        $restaurant = $this->currentRestaurant();
        $categories = Category::where('restaurant_id', $restaurant->id)
            ->parents()
            ->with(['children' => fn ($query) => $query->withCount('subcategoryMenuItems')])
            ->orderBy('display_order')
            ->withCount('menuItems')
            ->get();

        return view('restaurant.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('restaurant.categories.create');
    }

    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();

        if (!$restaurant) {
            return redirect()->back()->with('error', 'No restaurant found. Please create a restaurant first.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'display_order' => 'integer',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $validated['restaurant_id'] = $restaurant->id;
        $validated['parent_id'] = null;
        $validated['is_active'] = true;

        Category::create($validated);

        return redirect()->route('restaurant.categories.index')
            ->with('success', 'Category created successfully!');
    }

    /**
     * A subcategory is always created FROM a specific parent category (a "+
     * Subcategory" action on that category's row) -- the parent is fixed by
     * the route, never a dropdown the user has to remember to set.
     */
    public function createSubcategory(Category $category)
    {
        $restaurant = $this->currentRestaurant();
        abort_unless((int) $category->restaurant_id === (int) $restaurant->id && $category->parent_id === null, 404);

        return view('restaurant.categories.subcategory-create', compact('category'));
    }

    public function storeSubcategory(Request $request, Category $category)
    {
        $restaurant = $this->currentRestaurant();
        abort_unless((int) $category->restaurant_id === (int) $restaurant->id && $category->parent_id === null, 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'display_order' => 'integer',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $validated['restaurant_id'] = $restaurant->id;
        $validated['parent_id'] = $category->id;
        $validated['is_active'] = true;

        Category::create($validated);

        return redirect()->route('restaurant.categories.index')
            ->with('success', "Subcategory added under {$category->name}.");
    }

    public function edit($id)
    {
        $restaurant = $this->currentRestaurant();
        $category = Category::where('restaurant_id', $restaurant->id)->with('parent')->findOrFail($id);

        return view('restaurant.categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $restaurant = $this->currentRestaurant();
        $category = Category::where('restaurant_id', $restaurant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $path = $request->file('image')->store('categories', 'public');
            $validated['image'] = $path;
        }

        $category->update($validated);

        return redirect()->route('restaurant.categories.index')
            ->with('success', 'Category updated successfully!');
    }

    public function destroy($id)
    {
        $restaurant = $this->currentRestaurant();
        $category = Category::where('restaurant_id', $restaurant->id)->findOrFail($id);

        if ($category->children()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a category that has subcategories. Delete or move its subcategories first.');
        }

        // menuItems() covers items using this row as a top-level category;
        // subcategoryMenuItems() covers items using this row as a subcategory.
        if ($category->menuItems()->count() > 0 || $category->subcategoryMenuItems()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete category with menu items!');
        }

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()->route('restaurant.categories.index')
            ->with('success', 'Category deleted successfully!');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.order' => 'required|integer',
        ]);

        foreach ($request->categories as $categoryData) {
            Category::where('id', $categoryData['id'])->update(['display_order' => $categoryData['order']]);
        }

        return response()->json(['success' => true]);
    }

    public function downloadTemplate()
    {
        $filename = 'categories-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = ['Category', 'Parent Category', 'Display Order', 'Active'];
        $sampleRows = [
            ['Starters', '', '0', 'Yes'],
            ['Soups', 'Starters', '0', 'Yes'],
        ];

        $callback = function () use ($columns, $sampleRows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            foreach ($sampleRows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export()
    {
        $restaurant = $this->currentRestaurant();
        $categories = Category::where('restaurant_id', $restaurant->id)
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('display_order')
            ->get();

        $rows = $categories->map(fn (Category $category) => [
            $category->name,
            $category->parent?->name,
            $category->display_order,
            $category->is_active ? 'Yes' : 'No',
        ]);

        return Excel::download(
            new CategoryExport(collect($rows)),
            'categories-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function import(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (!$restaurant) {
            return redirect()->route('restaurant.categories.index')->with('error', 'Restaurant not found.');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $rows = $this->readUploadedRows($request->file('file'));
        if (empty($rows)) {
            return redirect()->route('restaurant.categories.index')->with('error', 'Uploaded file is empty or incorrectly formatted.');
        }

        $created = 0;
        $updated = 0;

        foreach ($rows as $record) {
            $name = trim((string) ($record['category'] ?? ''));
            if ($name === '') {
                continue;
            }

            $parentName = trim((string) ($record['parent_category'] ?? ''));
            $displayOrder = is_numeric($record['display_order'] ?? null) ? (int) $record['display_order'] : 0;
            $isActive = Str::of(strtolower((string) ($record['active'] ?? 'yes')))->contains(['1', 'yes', 'true', 'y']);

            $parentId = null;
            if ($parentName !== '') {
                $parent = Category::firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'parent_id' => null, 'name' => $parentName],
                    ['display_order' => 0, 'is_active' => true]
                );
                $parentId = $parent->id;
            }

            $category = Category::where('restaurant_id', $restaurant->id)
                ->where('parent_id', $parentId)
                ->where('name', $name)
                ->first();

            $attributes = [
                'restaurant_id' => $restaurant->id,
                'parent_id' => $parentId,
                'name' => $name,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
            ];

            if ($category) {
                $category->update($attributes);
                $updated++;
            } else {
                Category::create($attributes);
                $created++;
            }
        }

        return redirect()->route('restaurant.categories.index')
            ->with('success', "Imported {$created} new and updated {$updated} existing categor" . (($created + $updated) === 1 ? 'y' : 'ies') . ".");
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
}
