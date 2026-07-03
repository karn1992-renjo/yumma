<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    use ResolvesRestaurantContext;

    public function index()
    {
        $restaurant = $this->currentRestaurant();
        $promos = PromoCode::where('restaurant_id', $restaurant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('restaurant.promos.index', compact('promos'));
    }
    
    public function create()
    {
        return view('restaurant.promos.create');
    }
    
    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);
        
        $validated['restaurant_id'] = $restaurant->id;
        $validated['used_count'] = 0;
        
        PromoCode::create($validated);
        
        return redirect()->route('restaurant.promos.index')
            ->with('success', 'Promo code created successfully!');
    }
    
    public function edit($id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        return view('restaurant.promos.edit', compact('promo'));
    }
    
    public function update(Request $request, $id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        $validated = $request->validate([
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);
        
        $promo->update($validated);
        
        return redirect()->route('restaurant.promos.index')
            ->with('success', 'Promo code updated successfully!');
    }
    
    public function destroy($id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        $promo->delete();
        
        return redirect()->route('restaurant.promos.index')
            ->with('success', 'Promo code deleted successfully!');
    }
    
    public function toggleStatus($id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        $promo->update(['is_active' => !$promo->is_active]);
        
        return response()->json(['success' => true, 'is_active' => $promo->is_active]);
    }
}
