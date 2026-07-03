<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CuisineController extends Controller
{
    /**
     * Display list of cuisines
     */
    public function index(Request $request)
    {
        $query = Cuisine::query();
        
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }
        
        if ($request->popular === 'yes') {
            $query->where('popular', true);
        }
        
        $cuisines = $query->orderBy('display_order', 'asc')
            ->orderBy('name', 'asc')
            ->paginate(20);
            
        $stats = [
            'total' => Cuisine::count(),
            'active' => Cuisine::where('is_active', true)->count(),
            'inactive' => Cuisine::where('is_active', false)->count(),
            'popular' => Cuisine::where('popular', true)->count(),
        ];
        
        return view('admin.cuisines.index', compact('cuisines', 'stats'));
    }
    
    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.cuisines.create');
    }
    
    /**
     * Store new cuisine
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:cuisines,name',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'nullable|in:0,1,on',
            'popular' => 'nullable|in:0,1,on',
            'display_order' => 'nullable|integer|min:0',
        ]);
        
        $slug = Str::slug($validated['name']);
        if (Cuisine::where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'A cuisine with this slug already exists. Please choose a different name.']);
        }
        
        $data = [
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'fas fa-utensils',
            'is_active' => $request->has('is_active'),
            'popular' => $request->has('popular'),
            'display_order' => $validated['display_order'] ?? 0,
        ];
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('cuisines', 'public');
            $data['image'] = $path;
        }
        
        Cuisine::create($data);
        
        return redirect()->route('admin.cuisines.index')
            ->with('success', 'Cuisine created successfully!');
    }
    
    /**
     * Show edit form
     */
    public function edit(Cuisine $cuisine)
    {
        return view('admin.cuisines.edit', compact('cuisine'));
    }
    
    /**
     * Update cuisine
     */
    public function update(Request $request, Cuisine $cuisine)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('cuisines', 'name')->ignore($cuisine->id)],
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'nullable|in:0,1,on',
            'popular' => 'nullable|in:0,1,on',
            'display_order' => 'nullable|integer|min:0',
        ]);
        
        $slug = Str::slug($validated['name']);
        if (Cuisine::where('slug', $slug)->where('id', '!=', $cuisine->id)->exists()) {
            return back()->withInput()->withErrors(['name' => 'A cuisine with this slug already exists. Please choose a different name.']);
        }
        
        $data = [
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'fas fa-utensils',
            'is_active' => $request->has('is_active'),
            'popular' => $request->has('popular'),
            'display_order' => $validated['display_order'] ?? 0,
        ];
        
        if ($request->hasFile('image')) {
            if ($cuisine->image) {
                Storage::disk('public')->delete($cuisine->image);
            }
            $path = $request->file('image')->store('cuisines', 'public');
            $data['image'] = $path;
        }
        
        $cuisine->update($data);
        
        return redirect()->route('admin.cuisines.index')
            ->with('success', 'Cuisine updated successfully!');
    }
    
    /**
     * Delete cuisine
     */
    public function destroy(Cuisine $cuisine)
    {
        if ($cuisine->image) {
            Storage::disk('public')->delete($cuisine->image);
        }
        
        $cuisine->delete();
        
        return redirect()->route('admin.cuisines.index')
            ->with('success', 'Cuisine deleted successfully!');
    }
    
    /**
     * Toggle status
     */
    public function toggleStatus(Cuisine $cuisine)
    {
        $cuisine->update(['is_active' => !$cuisine->is_active]);
        
        return response()->json([
            'success' => true,
            'is_active' => $cuisine->is_active,
            'message' => $cuisine->is_active ? 'Cuisine activated!' : 'Cuisine deactivated!'
        ]);
    }
    
    /**
     * Toggle popular
     */
    public function togglePopular(Cuisine $cuisine)
    {
        $cuisine->update(['popular' => !$cuisine->popular]);
        
        return response()->json([
            'success' => true,
            'popular' => $cuisine->popular,
            'message' => $cuisine->popular ? 'Marked as popular!' : 'Removed from popular!'
        ]);
    }
    
    /**
     * Reorder cuisines
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'cuisines' => 'required|array',
            'cuisines.*.id' => 'required|exists:cuisines,id',
            'cuisines.*.order' => 'required|integer',
        ]);
        
        foreach ($request->cuisines as $cuisineData) {
            Cuisine::where('id', $cuisineData['id'])->update(['display_order' => $cuisineData['order']]);
        }
        
        return response()->json(['success' => true, 'message' => 'Order updated!']);
    }
    
    /**
     * Bulk import cuisines via JSON
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'cuisines' => 'required|json',
        ]);
        
        $cuisines = json_decode($request->cuisines, true);
        $imported = 0;
        $skipped = 0;
        
        foreach ($cuisines as $cuisine) {
            if (!Cuisine::where('name', $cuisine['name'])->exists()) {
                Cuisine::create([
                    'name' => $cuisine['name'],
                    'slug' => Str::slug($cuisine['name']),
                    'description' => $cuisine['description'] ?? null,
                    'icon' => $cuisine['icon'] ?? 'fas fa-utensils',
                    'display_order' => $cuisine['display_order'] ?? 0,
                    'popular' => $cuisine['popular'] ?? false,
                    'is_active' => true,
                ]);
                $imported++;
            } else {
                $skipped++;
            }
        }
        
        return redirect()->route('admin.cuisines.index')
            ->with('success', "Imported {$imported} cuisines! Skipped {$skipped} duplicates.");
    }
    
    /**
     * Export cuisines to JSON
     */
    public function export()
    {
        $cuisines = Cuisine::all(['id', 'name', 'description', 'icon', 'display_order', 'popular']);
        
        return response()->json([
            'success' => true,
            'data' => $cuisines
        ]);
    }
}