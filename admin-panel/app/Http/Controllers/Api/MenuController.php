<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Models\Restaurant;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index($restaurantId)
    {
        try {
            $restaurant = Restaurant::findOrFail($restaurantId);
            
            $categories = $restaurant->categories()
                ->with(['menuItems' => function($q) {
                    $q->with('cuisine')
                        ->where('is_available', true)
                        ->where(function ($statusQuery) {
                            $statusQuery->whereNull('approval_status')
                                ->orWhere('approval_status', 'approved');
                        });
                }])
                ->orderBy('display_order')
                ->get();

            // Flatten menu items for Flutter app compatibility
            $menuItems = [];
            foreach ($categories as $category) {
                if ($category->menuItems) {
                    foreach ($category->menuItems as $item) {
                        if (!$item->is_scheduled_available) {
                            continue;
                        }
                        $item->category_name = $category->name;
                        $item->category_id = $category->id;
                        $item->cuisine_name = $item->cuisine?->name;
                        $menuItems[] = $item;
                    }
                }
            }

            $uncategorizedItems = MenuItem::where('restaurant_id', $restaurantId)
                ->whereNull('category_id')
                ->where('is_available', true)
                ->where(function ($statusQuery) {
                    $statusQuery->whereNull('approval_status')
                        ->orWhere('approval_status', 'approved');
                })
                ->with('cuisine')
                ->get();

            foreach ($uncategorizedItems as $item) {
                if (!$item->is_scheduled_available) {
                    continue;
                }

                $item->category_name = 'Uncategorized';
                $item->category_id = null;
                $item->cuisine_name = $item->cuisine?->name;
                $menuItems[] = $item;
            }

            $serializedMenuItems = MenuItemResource::collection(collect($menuItems))->resolve();

            return response()->json([
                'success' => true,
                'data' => [
                    'restaurant' => [
                        'id' => $restaurant->id,
                        'name' => $restaurant->name,
                        'is_open' => $restaurant->is_open,
                    ],
                    'menu_items' => $serializedMenuItems,
                    'categories' => $categories,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Menu loading error', [
                'restaurant_id' => $restaurantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load menu: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($restaurantId, $itemId)
    {
        try {
            $item = MenuItem::where('restaurant_id', $restaurantId)
                ->where('id', $itemId)
                ->with(['category', 'cuisine'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => (new MenuItemResource($item))->resolve()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    public function search(Request $request, $restaurantId)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2',
            ]);

            $search = trim((string) $request->input('query', ''));
            if ($search === '') {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $items = MenuItem::where('restaurant_id', $restaurantId)
                ->where(function ($itemQuery) use ($search) {
                    $itemQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('cuisine', function ($cuisineQuery) use ($search) {
                            $cuisineQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%");
                        });
                })
                ->where('is_available', true)
                ->where(function ($statusQuery) {
                    $statusQuery->whereNull('approval_status')
                        ->orWhere('approval_status', 'approved');
                })
                ->with(['category', 'cuisine'])
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => MenuItemResource::collection($items)->resolve()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
