<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index()
    {
        $restaurants = Auth::user()->restaurants()
            ->withCount([
                'orders as delivered_orders_count' => fn ($query) => $query->where('status', 'delivered'),
            ])
            ->withSum([
                'orders as delivered_revenue' => fn ($query) => $query->where('status', 'delivered'),
            ], 'total')
            ->get();
        $currentRestaurant = Auth::user()->current_restaurant_id 
            ? Restaurant::find(Auth::user()->current_restaurant_id)
            : $restaurants->first();
            
        return view('restaurant.stores.index', compact('restaurants', 'currentRestaurant'));
    }
    
    public function create()
    {
        return view('restaurant.stores.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:restaurants',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'pincode' => 'required|string|max:10',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'restaurant_type' => 'required|in:' . implode(',', Restaurant::validServiceTypes()),
            'dining_charge' => 'nullable|numeric|min:0',
        ]);
        
        $restaurant = Restaurant::create([
            'owner_id' => Auth::id(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'restaurant_type' => $request->restaurant_type,
            'dining_charge' => $request->dining_charge ?? 0,
            'slug' => Str::slug($request->name) . '-' . uniqid(),
            'is_verified' => false,
            'is_open' => false,
            'cuisine' => json_encode([]),
            'min_order_amount' => 0,
            'delivery_fee' => 40,
            'delivery_time' => 30,
        ]);
        
        return redirect()->route('restaurant.stores.index')
            ->with('success', 'Restaurant created! It will be active after admin approval.');
    }
    
    public function edit($id)
    {
        $restaurant = Auth::user()->restaurants()->findOrFail($id);
        return view('restaurant.stores.edit', compact('restaurant'));
    }
    
    public function update(Request $request, $id)
    {
        $restaurant = Auth::user()->restaurants()->findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:restaurants,email,' . $restaurant->id,
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'pincode' => 'required|string|max:10',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'restaurant_type' => 'required|in:' . implode(',', Restaurant::validServiceTypes()),
            'dining_charge' => 'nullable|numeric|min:0',
        ]);
        
        $restaurant->update($request->only([
            'name', 'email', 'phone', 'address', 'city', 'state',
            'pincode', 'latitude', 'longitude', 'restaurant_type', 'dining_charge'
        ]));
        
        return redirect()->route('restaurant.stores.index')
            ->with('success', 'Restaurant updated successfully!');
    }
    
    /**
     * Switch current active restaurant
     */
    public function switchStore(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id'
        ]);
        
        $restaurant = Auth::user()->restaurants()->find($request->restaurant_id);
        
        if (!$restaurant) {
            return response()->json([
                'success' => false, 
                'message' => 'Restaurant not found or you do not have access'
            ], 404);
        }
        
        // Update current restaurant in session
        session(['current_restaurant_id' => $restaurant->id]);
        
        // Also update in database if you have a column for it
        $user = Auth::user();
        $user->current_restaurant_id = $restaurant->id;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Switched to ' . $restaurant->name,
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'is_open' => $restaurant->is_open
            ]
        ]);
    }
    
    /**
     * Get current active restaurant
     */
    public function getCurrentStore()
    {
        $currentRestaurantId = session('current_restaurant_id') ?? Auth::user()->current_restaurant_id;
        
        $currentRestaurant = $currentRestaurantId 
            ? Auth::user()->restaurants()->find($currentRestaurantId)
            : Auth::user()->restaurants()->first();
            
        return response()->json([
            'success' => true, 
            'restaurant' => $currentRestaurant
        ]);
    }
}
