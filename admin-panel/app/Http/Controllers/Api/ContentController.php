<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Cuisine;
use App\Models\PromoCode;
use App\Services\HomeSectionService;
use App\Services\MediaStorage;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function homeSections(Request $request, HomeSectionService $homeSectionService)
    {
        try {
            $latitude = $request->filled('lat') ? (float) $request->input('lat') : null;
            $longitude = $request->filled('lng') ? (float) $request->input('lng') : null;
            $radius = $request->filled('radius') ? (float) $request->input('radius') : 15.0;
            // Restaurant and menu discovery is always scoped to what can
            // actually deliver to the customer's selected coordinates.
            $deliveryZoneOnly = true;

            $sections = $homeSectionService
                ->publicSections($latitude, $longitude, $radius, $deliveryZoneOnly)
                ->map(function (array $section) {
                return [
                    'token' => $section['token'],
                    'type' => $section['type'],
                    'title' => $section['title'] ?? null,
                    'subtitle' => $section['subtitle'] ?? null,
                    'style' => $section['style'] ?? null,
                    'client_feed' => (bool) ($section['client_feed'] ?? false),
                    'strict_items' => (bool) ($section['strict_items'] ?? false),
                    'items' => collect($section['items'] ?? [])->map(function ($item) use ($section) {
                        return match ($section['type']) {
                            'banner_carousel' => [
                                'id' => $item->id,
                                'title' => $item->title,
                                'description' => $item->description,
                                'image' => MediaStorage::url($item->image),
                                'image_url' => MediaStorage::url($item->image),
                                'lottie_url' => $this->bannerMediaType($item->image) === 'lottie' ? MediaStorage::url($item->image) : null,
                                'media_type' => $this->bannerMediaType($item->image),
                                'link' => $item->link,
                                'layout_mode' => $item->layout_mode ?? 'text_image',
                                'image_ratio' => (int) ($item->image_ratio ?? 46),
                                'redirect_type' => $item->redirect_type,
                                'redirect_category_id' => $item->redirect_category_id,
                                'redirect_restaurant_id' => $item->redirect_restaurant_id,
                                'redirect_menu_item_id' => $item->redirect_menu_item_id,
                                'redirect' => $this->bannerRedirectPayload($item),
                            ],
                            'categories', 'cuisine_grid' => $this->categoryPayload($item),
                            default => $item,
                        };
                    })->values()->all(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $sections,
            ]);
        } catch (\Exception $e) {
            \Log::error('Home sections fetch error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching home sections',
                'data' => [],
            ], 500);
        }
    }

    public function banners()
    {
        try {
            $banners = Banner::where('is_active', true)
                ->where('banner_type', 'home')
                ->where(function ($q) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->orderBy('display_order')
                ->get()
                ->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'description' => $banner->description,
                        'image' => MediaStorage::url($banner->image),
                        'image_url' => MediaStorage::url($banner->image),
                        'lottie_url' => $this->bannerMediaType($banner->image) === 'lottie' ? MediaStorage::url($banner->image) : null,
                        'media_type' => $this->bannerMediaType($banner->image),
                        'link' => $banner->link,
                        'banner_type' => $banner->banner_type ?? 'home',
                        'layout_mode' => $banner->layout_mode ?? 'text_image',
                        'image_ratio' => (int) ($banner->image_ratio ?? 46),
                        'redirect_type' => $banner->redirect_type,
                        'redirect_category_id' => $banner->redirect_category_id,
                        'redirect_restaurant_id' => $banner->redirect_restaurant_id,
                        'redirect_menu_item_id' => $banner->redirect_menu_item_id,
                        'redirect' => $this->bannerRedirectPayload($banner),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $banners,
            ]);
        } catch (\Exception $e) {
            \Log::error('Banners fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching banners',
                'data' => []
            ]);
        }
    }

    public function bannersByType($type)
    {
        try {
            $banners = Banner::where('banner_type', $type)
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->orderBy('display_order')
                ->get()
                ->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'description' => $banner->description,
                        'image' => MediaStorage::url($banner->image),
                        'image_url' => MediaStorage::url($banner->image),
                        'lottie_url' => $this->bannerMediaType($banner->image) === 'lottie' ? MediaStorage::url($banner->image) : null,
                        'media_type' => $this->bannerMediaType($banner->image),
                        'link' => $banner->link,
                        'banner_type' => $banner->banner_type,
                        'layout_mode' => $banner->layout_mode ?? 'text_image',
                        'image_ratio' => (int) ($banner->image_ratio ?? 46),
                        'redirect_type' => $banner->redirect_type,
                        'redirect_category_id' => $banner->redirect_category_id,
                        'redirect_restaurant_id' => $banner->redirect_restaurant_id,
                        'redirect_menu_item_id' => $banner->redirect_menu_item_id,
                        'redirect' => $this->bannerRedirectPayload($banner),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $banners,
            ]);
        } catch (\Exception $e) {
            \Log::error('Banners by type fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching banners',
                'data' => []
            ]);
        }
    }

    private function bannerRedirectPayload(Banner $banner): ?array
    {
        if (!$banner->redirect_type) {
            return null;
        }

        if ($banner->redirect_type === 'category' && $banner->redirect_category_id) {
            $banner->loadMissing('redirectCategory');

            return [
                'type' => 'category',
                'id' => (int) $banner->redirect_category_id,
                'name' => $banner->redirectCategory?->name,
                'restaurant_id' => $banner->redirectCategory?->restaurant_id,
            ];
        }

        if ($banner->redirect_type === 'restaurant' && $banner->redirect_restaurant_id) {
            $banner->loadMissing('redirectRestaurant');

            return [
                'type' => 'restaurant',
                'id' => (int) $banner->redirect_restaurant_id,
                'name' => $banner->redirectRestaurant?->name,
            ];
        }

        if ($banner->redirect_type === 'menu_item' && $banner->redirect_menu_item_id) {
            $banner->loadMissing('redirectMenuItem');

            return [
                'type' => 'menu_item',
                'id' => (int) $banner->redirect_menu_item_id,
                'name' => $banner->redirectMenuItem?->name,
                'restaurant_id' => $banner->redirectMenuItem?->restaurant_id,
            ];
        }

        return null;
    }

    public function popularCuisines()
    {
        try {
            $cuisines = Cuisine::where('is_active', true)
                ->orderBy('popular', 'desc')
                ->orderBy('display_order', 'asc')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'icon', 'description', 'image', 'slug'])
                ->map(function ($cuisine) {
                    $image = $this->resolveImageUrl($cuisine->image);

                    return [
                        'id' => $cuisine->id,
                        'name' => $cuisine->name,
                        'slug' => $cuisine->slug,
                        'description' => $cuisine->description,
                        'icon' => $cuisine->icon,
                        'image' => $image,
                        'image_url' => $image,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $cuisines,
            ]);
        } catch (\Exception $e) {
            \Log::error('Popular cuisines fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching cuisines',
                'data' => []
            ]);
        }
    }

    private function categoryPayload($item): array
    {
        $image = $this->resolveImageUrl($item->image ?? null);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug ?? null,
            'description' => $item->description ?? null,
            'icon' => $item->icon ?? null,
            'image' => $image,
            'image_url' => $image,
        ];
    }

    private function resolveImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return str_starts_with($image, 'http://') || str_starts_with($image, 'https://')
            ? $image
            : MediaStorage::url($image);
    }

    public function activeOffers()
    {
        try {
            $offers = PromoCode::where('is_active', true)
                ->whereNull('restaurant_id')
                ->where('created_by_type', 'admin')
                ->where(function ($q) {
                    $q->whereNull('start_date')
                      ->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now());
                })
                ->orderBy('discount_value', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($promo) {
                    return [
                        'id' => $promo->id,
                        'code' => $promo->code,
                        'title' => $promo->title ?: $promo->code,
                        'subtitle' => $promo->description ?? 'Special offer',
                        'description' => $promo->description ?? 'Special offer',
                        'image' => $promo->promo_image_url,
                        'promo_image' => $promo->promo_image_url,
                        'discount_type' => $promo->discount_type ?? 'percentage',
                        'discount_value' => $promo->discount_value ?? 0,
                        'min_order_value' => $promo->min_order_amount,
                        'max_discount' => $promo->max_discount_amount,
                        'usage_limit' => $promo->usage_limit,
                        'valid_from' => $promo->start_date,
                        'valid_to' => $promo->end_date,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $offers,
            ]);
        } catch (\Exception $e) {
            \Log::error('Active offers fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching offers',
                'data' => []
            ]);
        }
    }

    private function bannerMediaType(?string $path): string
    {
        return str_ends_with(strtolower((string) $path), '.json') ? 'lottie' : 'image';
    }
}
