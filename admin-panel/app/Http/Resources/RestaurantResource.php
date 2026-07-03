<?php

namespace App\Http\Resources;

use App\Services\MediaStorage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Cuisine;
use Illuminate\Support\Str;

class RestaurantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalRatings = (int) ($this->total_ratings ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'email' => $this->email,
            'phone' => $this->phone,
            'fssai_license_number' => $this->fssai_license_number,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'cuisine' => $this->resolvedCuisineNames(),
            'cuisine_ids' => $this->resolvedCuisineIds(),
            'cuisine_text' => implode(', ', $this->resolvedCuisineNames()),
            'is_open' => $this->isOpenNow(),
            'manual_is_open' => (bool) $this->is_open,
            'is_open_now' => $this->isOpenNow(),
            'next_opening_time' => optional($this->getNextOpeningTime())->toIso8601String(),
            'next_opening_label' => $this->getNextOpeningLabel(),
            'is_pure_veg' => $this->is_pure_veg,
            'min_order_amount' => $this->min_order_amount,
            'amount_for_one' => $this->amountForOne(),
            'delivery_fee' => $this->delivery_fee,
            'delivery_time' => $this->delivery_time,
            'restaurant_type' => $this->restaurant_type,
            'accepts_delivery' => $this->acceptsService('delivery'),
            'accepts_dining' => $this->acceptsService('dining'),
            'accepts_takeaway' => $this->acceptsService('takeaway'),
            'dining_charge' => $this->dining_charge,
            'rating' => $totalRatings >= 3 ? (float) ($this->rating ?? 0) : null,
            'total_ratings' => $totalRatings,
            'banner_image' => $this->resolveImageUrl($this->banner_image),
            'logo_image' => $this->resolveImageUrl($this->logo_image),
            'distance' => $this->distance ?? null,
            'matched_item_names' => $this->matched_item_names ?? [],
            'matched_menu_items' => $this->normalizeMatchedMenuItems($this->matched_menu_items ?? []),
            'weekly_timings' => $this->weekly_timings,
            'is_featured' => (bool) ($this->is_featured ?? false),
            'orders_count' => (int) ($this->orders_count ?? $this->orders()->count()),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    private function resolvedCuisineNames(): array
    {
        $cuisine = $this->cuisine ?? [];
        if (is_string($cuisine)) {
            $decoded = json_decode($cuisine, true);
            $cuisine = is_array($decoded) ? $decoded : explode(',', $cuisine);
        }

        $values = collect($cuisine)->filter(fn ($value) => $value !== null && $value !== '');
        $ids = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value)->values();
        $names = $values->reject(fn ($value) => is_numeric($value))->map(function ($value) {
            return is_array($value) ? ($value['name'] ?? null) : trim((string) $value);
        })->filter()->values();

        if ($ids->isNotEmpty()) {
            $names = $names->merge(Cuisine::whereIn('id', $ids)->pluck('name'));
        }

        return $names->unique()->values()->all();
    }

    private function resolvedCuisineIds(): array
    {
        $cuisine = $this->cuisine ?? [];
        if (is_string($cuisine)) {
            $decoded = json_decode($cuisine, true);
            $cuisine = is_array($decoded) ? $decoded : explode(',', $cuisine);
        }

        $values = collect($cuisine)->filter(fn ($value) => $value !== null && $value !== '');
        $ids = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value);
        $names = $values->reject(fn ($value) => is_numeric($value))->map(function ($value) {
            return is_array($value) ? ($value['name'] ?? null) : trim((string) $value);
        })->filter()->values();

        if ($names->isNotEmpty()) {
            $ids = $ids->merge(Cuisine::whereIn('name', $names)->pluck('id'));
        }

        return $ids->unique()->values()->all();
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : MediaStorage::url($path);
    }

    private function normalizeMatchedMenuItems($items): array
    {
        if (! is_iterable($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item) {
                $payload = is_array($item) ? $item : (array) $item;

                if (array_key_exists('images', $payload)) {
                    $images = $payload['images'];
                    if (is_string($images)) {
                        $decoded = json_decode($images, true);
                        $images = is_array($decoded) ? $decoded : [$images];
                    }

                    $payload['images'] = collect(is_array($images) ? $images : [])
                        ->map(fn ($image) => MediaStorage::url((string) $image))
                        ->filter()
                        ->values()
                        ->all();
                }

                foreach (['image', 'image_url'] as $key) {
                    if (! empty($payload[$key]) && is_string($payload[$key])) {
                        $payload[$key] = MediaStorage::url($payload[$key]);
                    }
                }

                if (empty($payload['image_url']) && ! empty($payload['images'][0])) {
                    $payload['image_url'] = $payload['images'][0];
                }

                if (empty($payload['image']) && ! empty($payload['images'][0])) {
                    $payload['image'] = $payload['images'][0];
                }

                return $payload;
            })
            ->values()
            ->all();
    }
}
