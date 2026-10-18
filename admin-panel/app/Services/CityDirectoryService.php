<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Normalizes the free-text Restaurant.city column into a stable list of
 * "cities we actually serve" for SEO landing pages, without requiring a
 * schema change. Restaurants are grouped case/whitespace-insensitively so
 * "Adityapur" and "adityapur " collapse into one page instead of two thin
 * near-duplicates.
 */
class CityDirectoryService
{
    private const CACHE_KEY = 'city_directory:active_cities';
    private const CACHE_TTL = 300; // seconds -- short, so a new restaurant shows up quickly

    /**
     * @return Collection<int, array{slug: string, name: string, count: int}>
     */
    public function listActiveCities(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Restaurant::query()
                ->where('is_verified', true)
                ->customerVisible()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->pluck('city')
                ->groupBy(fn (string $city) => Str::lower(trim($city)))
                ->map(function (Collection $variants, string $normalized) {
                    // Use the most common casing as the canonical display name.
                    $name = $variants->countBy(fn (string $c) => trim($c))
                        ->sortDesc()
                        ->keys()
                        ->first();

                    return [
                        'slug' => Str::slug($normalized),
                        'name' => $name,
                        'count' => $variants->count(),
                    ];
                })
                ->filter(fn (array $city) => $city['slug'] !== '')
                ->values();
        });
    }

    /**
     * @return array{slug: string, name: string, count: int}|null
     */
    public function resolveBySlug(string $slug): ?array
    {
        return $this->listActiveCities()
            ->firstWhere('slug', $slug);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
