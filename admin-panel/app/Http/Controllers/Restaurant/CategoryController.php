<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    use ResolvesRestaurantContext;

    public function index()
    {
        $restaurant = $this->currentRestaurant();
        $categories = Category::where('restaurant_id', $restaurant->id)
            ->orderBy('display_order')
            ->withCount('menuItems')
            ->get();
            
        return view('restaurant.categories.index', compact('categories'));
    }
    
    public function create()
    {
        return view('restaurant.categories.create');
    }
     
    public function edit($id)
    {
        $restaurant = $this->currentRestaurant();
        $category = Category::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
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
        
        if ($category->menuItems()->count() > 0) {
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
    // app/Http/Controllers/Restaurant/CategoryController.php - Add store method with restaurant selection

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
            $path = $request->file('image')->store('categories', 'public');
            $validated['image'] = $path;
        }
        
        $validated['restaurant_id'] = $restaurant->id;
        $validated['is_active'] = true;
        
        Category::create($validated);
        
        return redirect()->route('restaurant.categories.index')
            ->with('success', 'Category created successfully!');
    }
}
