<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GlobalMenuCategoryExport;
use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GlobalMenuCategoryController extends Controller
{
    public function index()
    {
        $categories = GlobalMenuCategory::with(['children', 'cuisines'])
            ->parents()
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.global-menu-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.global-menu-categories.form', [
            'category' => new GlobalMenuCategory(),
            'cuisines' => $this->cuisineOptions(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $cuisineIds] = $this->validatedData($request);
        $data['parent_id'] = null;
        $this->ensureUniqueSlug($data['parent_id'], $data['slug']);

        $category = GlobalMenuCategory::create($data);
        $category->cuisines()->sync($cuisineIds);

        return redirect()->route('admin.global-menu-categories.index')
            ->with('success', 'Global category created successfully.');
    }

    /**
     * A subcategory is always created FROM a specific parent (a "+
     * Subcategory" action on that category's row) -- the parent is fixed by
     * the route, never a dropdown someone has to remember to set correctly.
     */
    public function createSubcategory(GlobalMenuCategory $globalMenuCategory)
    {
        abort_if($globalMenuCategory->parent_id, 404);

        return view('admin.global-menu-categories.subcategory-form', [
            'parent' => $globalMenuCategory,
            'category' => new GlobalMenuCategory(),
            'cuisines' => $this->cuisineOptions(),
        ]);
    }

    public function storeSubcategory(Request $request, GlobalMenuCategory $globalMenuCategory)
    {
        abort_if($globalMenuCategory->parent_id, 404);

        [$data, $cuisineIds] = $this->validatedData($request);
        $data['parent_id'] = $globalMenuCategory->id;
        $this->ensureUniqueSlug($data['parent_id'], $data['slug']);

        $category = GlobalMenuCategory::create($data);
        $category->cuisines()->sync($cuisineIds);

        return redirect()->route('admin.global-menu-categories.index')
            ->with('success', "Subcategory added under {$globalMenuCategory->name}.");
    }

    public function edit(GlobalMenuCategory $globalMenuCategory)
    {
        return view('admin.global-menu-categories.form', [
            'category' => $globalMenuCategory->load('cuisines'),
            'parents' => $this->parentOptions($globalMenuCategory->id),
            'cuisines' => $this->cuisineOptions(),
        ]);
    }

    public function update(Request $request, GlobalMenuCategory $globalMenuCategory)
    {
        [$data, $cuisineIds] = $this->validatedData($request, $globalMenuCategory);
        $this->ensureUniqueSlug($data['parent_id'] ?? null, $data['slug'], $globalMenuCategory->id);

        $globalMenuCategory->update($data);
        $globalMenuCategory->cuisines()->sync($cuisineIds);

        return redirect()->route('admin.global-menu-categories.index')
            ->with('success', 'Global category updated successfully.');
    }

    public function destroy(GlobalMenuCategory $globalMenuCategory)
    {
        $this->deleteCategoryImage($globalMenuCategory);

        $globalMenuCategory->delete();

        return redirect()->route('admin.global-menu-categories.index')
            ->with('success', 'Global category deleted successfully.');
    }

    public function downloadTemplate()
    {
        $filename = 'global-categories-template.csv';
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
        $categories = GlobalMenuCategory::with('parent')
            ->orderBy('parent_id')
            ->orderBy('display_order')
            ->get();

        $rows = $categories->map(fn (GlobalMenuCategory $category) => [
            $category->name,
            $category->parent?->name,
            $category->display_order,
            $category->is_active ? 'Yes' : 'No',
        ]);

        return Excel::download(
            new GlobalMenuCategoryExport(collect($rows)),
            'global-categories-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $rows = $this->readUploadedRows($request->file('file'));
        if (empty($rows)) {
            return redirect()->route('admin.global-menu-categories.index')->with('error', 'Uploaded file is empty or incorrectly formatted.');
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
                $parent = GlobalMenuCategory::whereNull('parent_id')
                    ->firstOrCreate(
                        ['name' => $parentName],
                        ['slug' => Str::slug($parentName), 'display_order' => 0, 'is_active' => true]
                    );
                $parentId = $parent->id;
            }

            $category = GlobalMenuCategory::where('parent_id', $parentId)
                ->where('name', $name)
                ->first();

            $attributes = [
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => Str::slug($name),
                'display_order' => $displayOrder,
                'is_active' => $isActive,
            ];

            if ($category) {
                $category->update($attributes);
                $updated++;
            } else {
                GlobalMenuCategory::create($attributes);
                $created++;
            }
        }

        return redirect()->route('admin.global-menu-categories.index')
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

    private function validatedData(Request $request, ?GlobalMenuCategory $category = null): array
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:global_menu_categories,id',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'display_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
            'cuisine_ids' => 'nullable|array',
            'cuisine_ids.*' => 'integer|exists:cuisines,id',
        ]);

        $cuisineIds = collect($validated['cuisine_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (($validated['parent_id'] ?? null) && $category && (int) $validated['parent_id'] === (int) $category->id) {
            abort(422, 'A category cannot be its own parent.');
        }

        $validated['slug'] = Str::slug($validated['name']);
        $validated['display_order'] = $validated['display_order'] ?? 0;
        $validated['is_active'] = $request->has('is_active');

        unset($validated['image'], $validated['cuisine_ids']);

        if ($request->hasFile('image')) {
            if ($category) {
                $this->deleteCategoryImage($category);
            }

            $validated['image'] = $request->file('image')->store('global-categories', 'public');
        }

        return [$validated, $cuisineIds];
    }

    /**
     * global_menu_categories has a DB-level unique(parent_id, slug) constraint
     * -- without this check, creating (or renaming into) a duplicate name
     * under the same parent crashes with a raw 500 from the constraint
     * violation instead of a friendly validation message.
     */
    private function ensureUniqueSlug(?int $parentId, string $slug, ?int $ignoreId = null): void
    {
        $exists = GlobalMenuCategory::where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'A category with this name already exists ' . ($parentId ? 'under this parent.' : 'at the top level.'),
            ]);
        }
    }

    private function deleteCategoryImage(GlobalMenuCategory $category): void
    {
        if (! $category->image || Str::startsWith($category->image, ['http://', 'https://', 'data:'])) {
            return;
        }

        Storage::disk('public')->delete($category->image);
    }

    private function parentOptions(?int $exceptId = null)
    {
        return GlobalMenuCategory::parents()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function cuisineOptions()
    {
        return Cuisine::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
