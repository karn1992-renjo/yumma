<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use App\Models\GlobalMenuCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'parents' => $this->parentOptions(),
            'cuisines' => $this->cuisineOptions(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $cuisineIds] = $this->validatedData($request);

        $category = GlobalMenuCategory::create($data);
        $category->cuisines()->sync($cuisineIds);

        return redirect()->route('admin.global-menu-categories.index')
            ->with('success', 'Global category created successfully.');
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
