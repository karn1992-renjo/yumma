<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = collect($this->images ?? [])
            ->map(fn ($img) => MediaStorage::url((string) $img))
            ->filter()
            ->values()
            ->all();

        $categoryName = $this->category_name
            ?? $this->category?->name
            ?? $this->masterMenuItem?->category_name;
        $subcategoryName = $this->subcategory_name
            ?? $this->masterMenuItem?->subcategory_name;
        if (
            blank($subcategoryName)
            && is_string($categoryName)
            && str_contains($categoryName, '/')
        ) {
            $parts = array_values(array_filter(
                array_map('trim', explode('/', $categoryName)),
                fn ($part) => $part !== ''
            ));
            if (count($parts) > 1) {
                $subcategoryName = end($parts);
            }
        }

        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'master_menu_item_id' => $this->master_menu_item_id,
            'item_source' => $this->item_source ?? 'custom',
            'category_id' => $this->category_id,
            'category_name' => $categoryName,
            'subcategory_name' => $subcategoryName,
            'cuisine_id' => $this->cuisine_id,
            'cuisine_name' => $this->cuisine_name ?? $this->cuisine?->name,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'discounted_price' => $this->discounted_price,
            'is_price_inclusive_gst' => (bool) $this->is_price_inclusive_gst,
            'final_price' => $this->getFinalPriceAttribute(),
            'images' => $images,
            'image' => $images[0] ?? null,
            'image_url' => $images[0] ?? null,
            'is_veg' => $this->is_veg,
            'food_type' => $this->food_type ?: ($this->is_veg ? 'veg' : 'non_veg'),
            'diet_label' => $this->diet_label,
            'is_available' => $this->is_available,
            'unavailable_until' => optional($this->unavailable_until)?->toIso8601String(),
            'is_scheduled_available' => $this->is_scheduled_available,
            'availability_schedule' => $this->availability_schedule ?? [],
            'approval_status' => $this->approval_status ?? 'approved',
            'is_recommended' => $this->is_recommended,
            'is_bestseller' => (bool) $this->is_bestseller,
            'is_new' => (bool) $this->is_new,
            'is_spicy' => (bool) $this->is_spicy,
            'is_combo' => (bool) $this->is_combo,
            'variants' => $this->variants ?? [],
            'add_ons' => $this->add_ons ?? [],
            'preparation_time' => $this->preparation_time,
            'rating' => $this->rating,
            'total_orders' => $this->total_orders,
            'tags' => $this->tags,
        ];
    }
}

