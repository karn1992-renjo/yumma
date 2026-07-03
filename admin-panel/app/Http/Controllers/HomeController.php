<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\Banner;
use App\Models\Cuisine;
use App\Models\AppSetting;
use App\Services\HomeSectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    /**
     * Show the home page
     */
    public static function index(?HomeSectionService $homeSectionService = null)
    {
        $homeSectionService ??= app(HomeSectionService::class);

        $banners = Banner::where('is_active', true)
            ->where(function($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->orderBy('display_order')
            ->get();
        $homepageSections = $homeSectionService->publicSections();
        $homepageCollectionsEnabled = false;

        return view('home', compact('banners', 'homepageSections', 'homepageCollectionsEnabled'));
    }
    
    /**
     * Get all categories for home page
     */
    public function getCategories()
    {
        $cuisines = Cuisine::where('is_active', true)
            ->orderBy('popular', 'desc')
            ->orderBy('display_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'icon', 'description', 'image', 'slug'])
            ->map(function($cuisine) {
                $imageUrl = \App\Services\MediaStorage::url($cuisine->image);
                $icon = $cuisine->icon ?? 'fas fa-utensils';
                
                return [
                    'id' => $cuisine->id,
                    'name' => $cuisine->name,
                    'icon' => $icon,
                    'slug' => $cuisine->slug,
                    'description' => $cuisine->description,
                    'image' => $imageUrl,
                    'display' => $imageUrl ?? $icon, // Use image if available, else icon
                    'is_image' => (bool) $imageUrl, // Flag to indicate if display is an image
                ];
            });
        
        // If no cuisines in database, return default categories
        if ($cuisines->isEmpty()) {
            $cuisines = collect([
                ['id' => 1, 'name' => 'Pizza', 'icon' => 'fas fa-pizza-slice', 'slug' => 'pizza', 'description' => null, 'image' => null, 'display' => 'fas fa-pizza-slice', 'is_image' => false],
                ['id' => 2, 'name' => 'Burger', 'icon' => 'fas fa-hamburger', 'slug' => 'burger', 'description' => null, 'image' => null, 'display' => 'fas fa-hamburger', 'is_image' => false],
                ['id' => 3, 'name' => 'Sushi', 'icon' => 'fas fa-fish', 'slug' => 'sushi', 'description' => null, 'image' => null, 'display' => 'fas fa-fish', 'is_image' => false],
                ['id' => 4, 'name' => 'Chinese', 'icon' => 'fas fa-egg', 'slug' => 'chinese', 'description' => null, 'image' => null, 'display' => 'fas fa-egg', 'is_image' => false],
                ['id' => 5, 'name' => 'Indian', 'icon' => 'fas fa-pepper-hot', 'slug' => 'indian', 'description' => null, 'image' => null, 'display' => 'fas fa-pepper-hot', 'is_image' => false],
                ['id' => 6, 'name' => 'Italian', 'icon' => 'fas fa-music', 'slug' => 'italian', 'description' => null, 'image' => null, 'display' => 'fas fa-music', 'is_image' => false],
                ['id' => 7, 'name' => 'Mexican', 'icon' => 'fas fa-taco', 'slug' => 'mexican', 'description' => null, 'image' => null, 'display' => 'fas fa-taco', 'is_image' => false],
                ['id' => 8, 'name' => 'Desserts', 'icon' => 'fas fa-ice-cream', 'slug' => 'desserts', 'description' => null, 'image' => null, 'display' => 'fas fa-ice-cream', 'is_image' => false],
                ['id' => 9, 'name' => 'Beverages', 'icon' => 'fas fa-mug-hot', 'slug' => 'beverages', 'description' => null, 'image' => null, 'display' => 'fas fa-mug-hot', 'is_image' => false],
                ['id' => 10, 'name' => 'Healthy', 'icon' => 'fas fa-apple-alt', 'slug' => 'healthy', 'description' => null, 'image' => null, 'display' => 'fas fa-apple-alt', 'is_image' => false],
            ]);
        }
        
        return response()->json($cuisines);
    }
    
    /**
     * Get collections for home page
     */
    public function getCollections()
    {
        $collections = [
            [
                'id' => 1,
                'title' => 'Trending This Week',
                'places' => 24,
                'image' => 'https://placehold.co/400x250/FF7A6B/white?text=Trending'
            ],
            [
                'id' => 2,
                'title' => 'Best of Biryani',
                'places' => 18,
                'image' => 'https://placehold.co/400x250/EF4F5F/white?text=Biryani'
            ],
            [
                'id' => 3,
                'title' => 'Quick Bites',
                'places' => 32,
                'image' => 'https://placehold.co/400x250/E03546/white?text=Quick+Bites'
            ],
            [
                'id' => 4,
                'title' => 'Pizza Mania',
                'places' => 15,
                'image' => 'https://placehold.co/400x250/FF8C42/white?text=Pizza'
            ],
        ];
        
        return response()->json($collections);
    }
    
    /**
     * Search restaurants with filters
     */
    public function searchRestaurants(Request $request)
    {
        $query = Restaurant::with('owner')
            ->where('is_verified', true);
        
        // Search by location
        if ($request->lat && $request->lng) {
            if (DB::getDriverName() === 'sqlite') {
                $lat = $request->lat;
                $lng = $request->lng;
                $latDelta = 0.5;
                $lngDelta = 0.5;

                $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                    ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
                    ->orderByRaw('is_open DESC, ABS(latitude - ?) + ABS(longitude - ?)', [$lat, $lng]);
            } else {
                $distanceSql = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
                $query->select('*')
                    ->selectRaw("{$distanceSql} AS distance", [
                        $request->lat, $request->lng, $request->lat
                    ])
                    ->whereRaw("{$distanceSql} <= COALESCE(NULLIF(delivery_radius, 0), 50)", [
                        $request->lat, $request->lng, $request->lat
                    ])
                    ->orderByRaw('is_open DESC, distance');
            }
        } elseif ($request->location) {
            $query->where(function($q) use ($request) {
                $q->where('city', 'like', "%{$request->location}%")
                  ->orWhere('address', 'like', "%{$request->location}%");
            });
        }
        
        // Search by keyword (name or cuisine)
        if ($request->search) {
            $searchTerm = $request->search;
            $normalizedSearch = strtolower(trim($searchTerm));

            if (str_contains($normalizedSearch, 'under 99')) {
                $query->where('min_order_amount', '<=', 99);
            } elseif (str_contains($normalizedSearch, 'under 199')) {
                $query->where('min_order_amount', '<=', 199);
            } elseif (str_contains($normalizedSearch, 'bestseller') || str_contains($normalizedSearch, 'best seller')) {
                $query->whereHas('menuItems', fn ($menuQuery) => $menuQuery->where('is_recommended', true)->orWhere('total_orders', '>', 0));
            } else {
            
                // Check if search term matches a cuisine
                $cuisine = Cuisine::where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('slug', 'like', "%{$searchTerm}%")
                    ->first();
                
                if ($cuisine) {
                    // Search restaurants that have this cuisine
                    $query->where(function($q) use ($searchTerm, $cuisine) {
                        $q->where('name', 'like', "%{$searchTerm}%")
                          ->orWhere('cuisine', 'like', "%{$cuisine->name}%")
                          ->orWhere('cuisine', 'like', "%{$searchTerm}%");
                    });
                } else {
                    $query->where(function($q) use ($searchTerm) {
                        $q->where('name', 'like', "%{$searchTerm}%")
                          ->orWhere('cuisine', 'like', "%{$searchTerm}%");
                    });
                }
            }
        }
        
        // Filter by category/cuisine
        if ($request->category) {
            $query->where(function($q) use ($request) {
                $q->where('cuisine', 'like', "%{$request->category}%")
                  ->orWhere('name', 'like', "%{$request->category}%");
            });
        }
        
        // Filter by open status
        if ($request->open_now === 'true') {
            $query->where('is_open', true);
        }

        if ($request->pure_veg === 'true') {
            $query->where('is_pure_veg', true);
        }
        
        // Sorting
        switch ($request->sort) {
            case 'rating':
                $query->orderBy('rating', 'desc');
                break;
            case 'delivery_time':
                $query->orderBy('delivery_time', 'asc');
                break;
            case 'price_high':
                $query->orderBy('min_order_amount', 'desc');
                break;
            case 'price_low':
                $query->orderBy('min_order_amount', 'asc');
                break;
            default:
                $query->orderBy('rating', 'desc')
                      ->orderBy('is_featured', 'desc');
        }
        
        $perPage = 12;
        $restaurants = $query->paginate($perPage);
        
        // Transform data for frontend
        $restaurantData = $restaurants->map(function($restaurant) {
            return [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'image' => \App\Services\MediaStorage::url($restaurant->logo_image),
                'cuisine' => $restaurant->cuisine_text ?: 'Variety Cuisine',
                'cuisine_text' => $restaurant->cuisine_text,
                'cuisine_names' => $restaurant->cuisine_names,
                'rating' => $restaurant->rating ?? 4.0,
                'delivery_time' => $restaurant->delivery_time ?? rand(25, 50),
                'min_order' => $restaurant->min_order_amount ?? 199,
                'is_open' => $restaurant->is_open,
                'is_pure_veg' => (bool) $restaurant->is_pure_veg,
                'is_featured' => $restaurant->is_featured,
                'city' => $restaurant->city,
            ];
        });
        
        return response()->json([
            'restaurants' => $restaurantData,
            'current_page' => $restaurants->currentPage(),
            'has_more' => $restaurants->hasMorePages(),
            'total' => $restaurants->total()
        ]);
    }
    
    /**
     * Get featured restaurants
     */
    public function getFeaturedRestaurants()
    {
        $restaurants = Restaurant::where('is_verified', true)
            ->where('is_featured', true)
            ->limit(8)
            ->get();
            
        return response()->json($restaurants);
    }
    
    /**
     * Geocode location (convert lat/lng to city)
     */
    public function geocode(Request $request)
    {
        $googleMapsApiKey = trim((string) AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', '')));
        if ($googleMapsApiKey === '') {
            return response()->json(['city' => null, 'lat' => null, 'lng' => null]);
        }

        if ($request->q) {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $request->q,
                'key' => $googleMapsApiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['results'][0] ?? null;
                if ($result) {
                    return response()->json([
                        'city' => $result['formatted_address'] ?? null,
                        'lat' => $result['geometry']['location']['lat'] ?? null,
                        'lng' => $result['geometry']['location']['lng'] ?? null,
                    ]);
                }
            }

            return response()->json(['city' => null, 'lat' => null, 'lng' => null]);
        }

        $lat = $request->lat;
        $lng = $request->lng;

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $lat.','.$lng,
            'key' => $googleMapsApiKey,
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            $result = $data['results'][0] ?? null;
            $city = null;
            foreach (($result['address_components'] ?? []) as $component) {
                $types = $component['types'] ?? [];
                if (in_array('locality', $types, true) || in_array('administrative_area_level_2', $types, true)) {
                    $city = $component['long_name'] ?? null;
                    break;
                }
            }
                    
            return response()->json(['city' => $city]);
        }
        
        return response()->json(['city' => null]);
    }
    
    /**
     * Get popular cities
     */
    public function getPopularCities()
    {
        $cities = [
            ['name' => 'New York', 'count' => 250],
            ['name' => 'Los Angeles', 'count' => 180],
            ['name' => 'Chicago', 'count' => 120],
            ['name' => 'Houston', 'count' => 95],
            ['name' => 'Phoenix', 'count' => 78],
            ['name' => 'Philadelphia', 'count' => 65],
            ['name' => 'San Antonio', 'count' => 58],
            ['name' => 'San Diego', 'count' => 52],
        ];
        
        return response()->json($cities);
    }
    
    /**
     * Get restaurant details page
     */
    public function showRestaurant($id)
    {
        $restaurant = Restaurant::with([
            'owner',
            'menuItems' => function($q) {
                $q->where('is_available', true)
                    ->where('approval_status', 'approved')
                    ->with('category');
            },
            'promos' => function($q) {
                $q->where('is_active', true)
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now());
            }
        ])->findOrFail($id);

        $activePromos = $restaurant->promos->filter(fn($promo) => $promo->isValid())->values();
        $similarRestaurants = Restaurant::query()
            ->where('id', '!=', $restaurant->id)
            ->where('is_verified', true)
            ->where(function ($query) use ($restaurant) {
                $query->where('restaurant_type', $restaurant->restaurant_type);

                if (! empty($restaurant->city)) {
                    $query->orWhere('city', $restaurant->city);
                }
            })
            ->orderByDesc('rating')
            ->limit(6)
            ->get();

        if ($similarRestaurants->count() < 4) {
            $fallback = Restaurant::query()
                ->where('id', '!=', $restaurant->id)
                ->where('is_verified', true)
                ->whereNotIn('id', $similarRestaurants->pluck('id'))
                ->orderByDesc('rating')
                ->limit(6 - $similarRestaurants->count())
                ->get();

            $similarRestaurants = $similarRestaurants->concat($fallback)->values();
        }
        
        // Calculate banner URL in controller
        $restaurantBannerUrl = $restaurant->banner_image 
            ? \App\Services\MediaStorage::url($restaurant->banner_image)
            : ($restaurant->cover_image 
                ? \App\Services\MediaStorage::url($restaurant->cover_image)
                : \App\Services\MediaStorage::url($restaurant->logo_image));
        
        return view('restaurant-detail', compact('restaurant', 'activePromos', 'restaurantBannerUrl', 'similarRestaurants'));
    }
}
