<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Services\MediaStorage;
use App\Support\PromotionTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        return view('restaurant.promos.create', array_merge([
            'promotionTypes' => $this->promotionTypes(),
        ], $this->formOptions($this->currentRestaurant())));
    }
    
    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        $normalizedType = PromotionTypeRegistry::normalize($request->input('promotion_type'));
        $request->merge(['promotion_type' => $normalizedType !== '' ? $normalizedType : null]);
        
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'promotion_type' => ['nullable', 'string', 'max:80', Rule::in(array_keys($this->promotionTypes()))],
            'reward_type' => 'nullable|string|max:80',
            'reward_config' => 'nullable|array',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'target_type' => 'nullable|in:restaurant,categories,items',
            'target_ids' => 'nullable|array',
            'target_ids.*' => 'integer|min:1',
            'promo_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);
        
        $validated['restaurant_id'] = $restaurant->id;
        $validated['used_count'] = 0;
        $this->applyPromotionShapeDefaults($validated);
        $this->applyTargetDefaults($validated, $restaurant->id);
        $this->ensureTargetSelection($validated);
        if ($request->hasFile('promo_image')) {
            $validated['promo_image'] = MediaStorage::store($request->file('promo_image'), 'promos');
        }
        
        PromoCode::create($validated);
        
        return redirect()->route('restaurant.promos.index')
            ->with('success', 'Promo code created successfully!');
    }
    
    public function edit($id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        return view('restaurant.promos.edit', array_merge([
            'promo' => $promo,
            'promotionTypes' => $this->promotionTypes(),
        ], $this->formOptions($restaurant)));
    }
    
    public function update(Request $request, $id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        $normalizedType = PromotionTypeRegistry::normalize($request->input('promotion_type'));
        $request->merge(['promotion_type' => $normalizedType !== '' ? $normalizedType : null]);
        
        $validated = $request->validate([
            'description' => 'nullable|string',
            'title' => 'nullable|string|max:255',
            'promotion_type' => ['nullable', 'string', 'max:80', Rule::in(array_keys($this->promotionTypes()))],
            'reward_type' => 'nullable|string|max:80',
            'reward_config' => 'nullable|array',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'target_type' => 'nullable|in:restaurant,categories,items',
            'target_ids' => 'nullable|array',
            'target_ids.*' => 'integer|min:1',
            'promo_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $this->applyPromotionShapeDefaults($validated);
        $this->applyTargetDefaults($validated, $restaurant->id);
        $this->ensureTargetSelection($validated);
        if ($request->hasFile('promo_image')) {
            MediaStorage::delete($promo->promo_image);
            $validated['promo_image'] = MediaStorage::store($request->file('promo_image'), 'promos');
        }
        
        $promo->update($validated);
        
        return redirect()->route('restaurant.promos.index')
            ->with('success', 'Promo code updated successfully!');
    }
    
    public function destroy($id)
    {
        $restaurant = $this->currentRestaurant();
        $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);

        MediaStorage::delete($promo->promo_image);
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

    private function applyPromotionShapeDefaults(array &$validated): void
    {
        $types = $this->promotionTypes();
        $promotionType = PromotionTypeRegistry::normalize($validated['promotion_type'] ?? null);
        if (! is_string($promotionType) || ! array_key_exists($promotionType, $types)) {
            $promotionType = ($validated['discount_type'] ?? null) === 'fixed'
                ? 'flat_discount'
                : 'percentage_discount';
        }

        $meta = $types[$promotionType];
        $validated['promotion_type'] = $promotionType;
        $validated['reward_type'] = $meta['reward_type'];
        $validated['discount_type'] = $validated['discount_type'] ?? $meta['discount_type'];
        $validated['discount_value'] = (float) ($validated['discount_value'] ?? 0);
        $validated['reward_config'] = array_merge($meta['defaults'] ?? [], $validated['reward_config'] ?? []);
        unset($validated['reward_config']['custom_rule']);
    }

    private function applyTargetDefaults(array &$validated, int $restaurantId): void
    {
        $targetType = $validated['target_type'] ?? null;
        if (! in_array($targetType, ['restaurant', 'categories', 'items'], true)) {
            $targetType = $this->promotionTypes()[$validated['promotion_type']]['target_type'] ?? 'restaurant';
        }

        $ids = collect($validated['target_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($targetType === 'categories') {
            $allowed = Category::where('restaurant_id', $restaurantId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $validated['target_ids'] = $allowed;
        } elseif ($targetType === 'items') {
            $allowed = MenuItem::where('restaurant_id', $restaurantId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $validated['target_ids'] = $allowed;
        } else {
            $targetType = 'restaurant';
            $validated['target_ids'] = [];
        }

        $validated['target_type'] = $targetType;
    }

    private function ensureTargetSelection(array $validated): void
    {
        if (($validated['target_type'] ?? 'restaurant') !== 'restaurant' && empty($validated['target_ids'])) {
            throw ValidationException::withMessages([
                'target_ids' => 'Select at least one mapped item or category for this promotion type.',
            ]);
        }
    }

    private function formOptions($restaurant): array
    {
        return [
            'categories' => $restaurant->categories()
                ->withCount('menuItems')
                ->orderBy('name')
                ->get()
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'image_url' => MediaStorage::url($category->image),
                    'subtitle' => $category->menu_items_count . ' items',
                ])
                ->values(),
            'menuItems' => MenuItem::with('category:id,name')
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('name')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'image_url' => $item->image_url,
                    'subtitle' => trim(($item->category?->name ?: 'Menu item') . ' · ' . number_format((float) $item->price, 2)),
                ])
                ->values(),
        ];
    }

    private function promotionTypes(): array
    {
        return PromotionTypeRegistry::restaurantTypes();
    }
}
