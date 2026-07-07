<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Cuisine;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\SearchIndex;
use App\Models\SearchLog;
use App\Models\SearchSynonym;
use App\Models\TrendingSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdvancedSearchService
{
    public function search(array $params, ?int $userId = null): array
    {
        $keyword = $this->correctKeyword((string) ($params['keyword'] ?? $params['query'] ?? $params['q'] ?? ''));
        $lat = $this->floatOrNull($params['lat'] ?? null);
        $lng = $this->floatOrNull($params['lng'] ?? null);
        $limit = max(1, min((int) ($params['limit'] ?? 20), 50));
        $page = max(1, (int) ($params['page'] ?? 1));

        if ($keyword === '') {
            return [
                'restaurants' => [],
                'foods' => [],
                'offers' => [],
                'categories' => [],
                'brands' => [],
                'trending' => $this->trending(),
                'corrected_keyword' => '',
            ];
        }

        $query = SearchIndex::query()->where('is_active', true);
        $this->applyKeywordMatch($query, $keyword);
        $this->applyDeliveryZone($query, $lat, $lng);

        $rows = $query
            ->select('search_indexes.*')
            ->selectRaw($this->scoreSql($lat, $lng), $this->scoreBindings($keyword, $lat, $lng))
            ->orderByDesc('final_score')
            ->orderByDesc('search_score')
            ->forPage($page, $limit * 3)
            ->get();

        $grouped = $rows->groupBy('entity_type');
        $foodRows = $grouped->get('menu_item', collect())->take($limit);
        $response = [
            'restaurants' => $this->serialize($grouped->get('restaurant', collect())->take($limit)),
            'foods' => $this->serializeMenuItems($foodRows),
            'offers' => $this->serialize($grouped->get('offer', collect())->take($limit)),
            'categories' => $this->serialize($grouped->get('category', collect())->merge($grouped->get('cuisine', collect()))->take($limit)),
            'brands' => $this->serialize($grouped->get('brand', collect())->take($limit)),
            'trending' => $this->trending(),
            'corrected_keyword' => $keyword,
        ];

        $totalCount = collect($response)
            ->except(['trending', 'corrected_keyword'])
            ->sum(fn ($items) => is_countable($items) ? count($items) : 0);
        $this->logSearch($keyword, $totalCount, $userId, $params['device_type'] ?? null);

        return $response;
    }

    public function suggestions(string $keyword, int $limit = 8): array
    {
        $keyword = $this->correctKeyword($keyword);
        if ($keyword === '') {
            return $this->trending($limit);
        }

        return SearchIndex::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($keyword) {
                $query->where('title', 'like', "{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('keywords', 'like', "%{$keyword}%");
            })
            ->orderByDesc('search_score')
            ->limit($limit)
            ->pluck('title')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => Str::lower($value))
            ->values()
            ->all();
    }

    public function logClick(?int $userId, string $keyword, string $type, int $id): void
    {
        SearchLog::create([
            'user_id' => $userId,
            'keyword' => $this->normalize($keyword),
            'clicked_result' => "{$type}:{$id}",
            'result_type' => $type,
            'result_id' => $id,
            'results_count' => 0,
        ]);
    }

    public function history(?int $userId): array
    {
        if (!$userId) return [];

        return SearchLog::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(20)
            ->get(['id', 'keyword', 'created_at'])
            ->unique('keyword')
            ->values()
            ->all();
    }

    public function clearHistory(?int $userId, ?int $id = null): void
    {
        if (!$userId) return;

        $query = SearchLog::query()->where('user_id', $userId);
        if ($id !== null) {
            $query->whereKey($id);
        }
        $query->delete();
    }

    public function rebuildIndex(): int
    {
        $count = 0;
        SearchIndex::query()->delete();

        Restaurant::query()->chunkById(200, function ($restaurants) use (&$count) {
            foreach ($restaurants as $restaurant) {
                $this->upsertIndex('restaurant', $restaurant->id, [
                    'title' => $restaurant->name,
                    'description' => $restaurant->description,
                    'keywords' => implode(' ', array_filter([
                        $restaurant->name,
                        $restaurant->slug,
                        $restaurant->city,
                        $restaurant->address,
                        $this->textFromMixed($restaurant->cuisine),
                    ])),
                    'tags' => $restaurant->cuisine,
                    'restaurant_id' => $restaurant->id,
                    'latitude' => $restaurant->latitude,
                    'longitude' => $restaurant->longitude,
                    'is_active' => (bool) $restaurant->is_verified,
                    'search_score' => (float) ($restaurant->rating ?? 0) * 10 + (int) ($restaurant->total_ratings ?? 0),
                ]);
                $count++;
            }
        });

        MenuItem::query()->with(['restaurant', 'category', 'cuisine'])->chunkById(500, function ($items) use (&$count) {
            foreach ($items as $item) {
                $restaurant = $item->restaurant;
                $this->upsertIndex('menu_item', $item->id, [
                    'title' => $item->name,
                    'description' => $item->description,
                    'keywords' => implode(' ', array_filter([
                        $item->name,
                        $item->description,
                        $item->category?->name,
                        $item->cuisine?->name,
                        $restaurant?->name,
                        $this->textFromMixed($item->tags),
                    ])),
                    'tags' => $item->tags,
                    'restaurant_id' => $item->restaurant_id,
                    'latitude' => $restaurant?->latitude,
                    'longitude' => $restaurant?->longitude,
                    'is_active' => (bool) $item->is_available,
                    'search_score' => (float) ($item->total_orders ?? 0) + (float) ($item->rating ?? 0) * 10,
                ]);
                $count++;
            }
        });

        Category::query()->chunkById(200, function ($categories) use (&$count) {
            foreach ($categories as $category) {
                $this->upsertIndex('category', $category->id, [
                    'title' => $category->name,
                    'keywords' => $category->name,
                    'restaurant_id' => $category->restaurant_id ?? null,
                    'is_active' => true,
                    'search_score' => 10,
                ]);
                $count++;
            }
        });

        Cuisine::query()->chunkById(200, function ($cuisines) use (&$count) {
            foreach ($cuisines as $cuisine) {
                $this->upsertIndex('cuisine', $cuisine->id, [
                    'title' => $cuisine->name,
                    'description' => $cuisine->description,
                    'keywords' => implode(' ', array_filter([$cuisine->name, $cuisine->slug, $cuisine->description])),
                    'is_active' => (bool) $cuisine->is_active,
                    'search_score' => $cuisine->popular ? 50 : 10,
                ]);
                $count++;
            }
        });

        PromoCode::query()->chunkById(200, function ($offers) use (&$count) {
            foreach ($offers as $offer) {
                $this->upsertIndex('offer', $offer->id, [
                    'title' => $offer->title ?: $offer->code,
                    'description' => $offer->description,
                    'keywords' => implode(' ', array_filter([$offer->title, $offer->code, $offer->description])),
                    'restaurant_id' => $offer->restaurant_id,
                    'is_active' => (bool) $offer->is_active,
                    'search_score' => (float) ($offer->discount_value ?? 0),
                ]);
                $count++;
            }
        });

        if (Schema::hasTable('brands')) {
            DB::table('brands')->orderBy('id')->chunk(200, function ($brands) use (&$count) {
                foreach ($brands as $brand) {
                    $this->upsertIndex('brand', $brand->id, [
                        'title' => $brand->name,
                        'description' => $brand->description ?? null,
                        'keywords' => implode(' ', array_filter([$brand->name, $brand->description ?? null])),
                        'is_active' => (bool) ($brand->is_active ?? true),
                        'search_score' => 10,
                    ]);
                    $count++;
                }
            });
        }

        return $count;
    }

    private function upsertIndex(string $type, int $id, array $data): void
    {
        SearchIndex::updateOrCreate(
            ['entity_type' => $type, 'entity_id' => $id],
            $data
        );
    }

    private function applyKeywordMatch(Builder $query, string $keyword): void
    {
        $tokens = collect(explode(' ', $keyword))->filter()->values();
        $query->where(function (Builder $builder) use ($keyword, $tokens) {
            $builder->where('title', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%")
                ->orWhere('keywords', 'like', "%{$keyword}%");
            foreach ($tokens as $token) {
                $builder->orWhere('title', 'like', "%{$token}%")
                    ->orWhere('keywords', 'like', "%{$token}%");
            }
        });
    }

    private function applyDeliveryZone(Builder $query, ?float $lat, ?float $lng): void
    {
        if ($lat === null || $lng === null) return;

        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
        $query->where(function (Builder $builder) use ($haversine, $lat, $lng) {
            $builder->whereNull('latitude')
                ->orWhereNull('longitude')
                ->orWhereRaw("{$haversine} <= 15", [$lat, $lng, $lat]);
        });
    }

    private function scoreSql(?float $lat, ?float $lng): string
    {
        $distanceScore = $lat !== null && $lng !== null
            ? "CASE WHEN latitude IS NULL OR longitude IS NULL THEN 0 WHEN (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= 2 THEN 100 WHEN (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= 5 THEN 80 WHEN (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= 10 THEN 50 ELSE 20 END"
            : '0';

        return "(CASE WHEN title LIKE ? THEN 100 WHEN title LIKE ? THEN 70 ELSE 30 END + {$distanceScore} + search_score) AS final_score";
    }

    private function scoreBindings(string $keyword, ?float $lat, ?float $lng): array
    {
        $bindings = ["{$keyword}%", "%{$keyword}%"];
        if ($lat !== null && $lng !== null) {
            $bindings = array_merge($bindings, [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat]);
        }
        return $bindings;
    }

    private function serialize(Collection $rows): array
    {
        return $rows->map(fn (SearchIndex $row) => [
            'type' => $row->entity_type,
            'id' => $row->entity_id,
            'title' => $row->title,
            'description' => $row->description,
            'restaurant_id' => $row->restaurant_id,
            'branch_id' => $row->branch_id,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
            'score' => round((float) ($row->final_score ?? $row->search_score), 2),
            'tags' => $row->tags ?? [],
        ])->values()->all();
    }

    private function serializeMenuItems(Collection $rows): array
    {
        $items = MenuItem::query()
            ->with(['category:id,name', 'cuisine:id,name'])
            ->whereKey($rows->pluck('entity_id')->filter())
            ->get()
            ->keyBy('id');

        return $rows->map(function (SearchIndex $row) use ($items) {
            $result = $this->serialize(collect([$row]))[0];
            /** @var MenuItem|null $item */
            $item = $items->get($row->entity_id);

            if (!$item) {
                return $result;
            }

            return array_merge($result, [
                'name' => $item->name,
                'price' => (float) $item->price,
                'discounted_price' => $item->discounted_price !== null
                    ? (float) $item->discounted_price
                    : null,
                'images' => $item->images ?? [],
                'image_url' => $item->image_url,
                'is_veg' => (bool) $item->is_veg,
                'food_type' => $item->food_type,
                'diet_label' => $item->diet_label,
                'is_available' => (bool) $item->is_available,
                'preparation_time' => $item->preparation_time,
                'total_orders' => (int) ($item->total_orders ?? 0),
                'rating' => $item->rating !== null ? (float) $item->rating : null,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'cuisine_id' => $item->cuisine_id,
                'cuisine_name' => $item->cuisine?->name,
                'is_recommended' => (bool) $item->is_recommended,
                'is_bestseller' => (bool) $item->is_bestseller,
                'is_new' => (bool) $item->is_new,
                'is_spicy' => (bool) $item->is_spicy,
                'is_combo' => (bool) $item->is_combo,
                'variants' => $item->variants ?? [],
                'add_ons' => $item->add_ons ?? [],
                'created_at' => optional($item->created_at)->toIso8601String(),
            ]);
        })->values()->all();
    }

    private function correctKeyword(string $keyword): string
    {
        $keyword = $this->normalize($keyword);
        if ($keyword === '') return '';

        $replacement = SearchSynonym::query()
            ->where('keyword', $keyword)
            ->value('replacement');

        return $this->normalize($replacement ?: $keyword);
    }

    private function logSearch(string $keyword, int $resultsCount, ?int $userId, ?string $deviceType): void
    {
        SearchLog::create([
            'user_id' => $userId,
            'keyword' => $keyword,
            'results_count' => $resultsCount,
            'device_type' => $deviceType,
        ]);

        $trend = TrendingSearch::query()->firstOrCreate(
            ['keyword' => $keyword],
            ['total_searches' => 0]
        );
        $trend->increment('total_searches');
        $trend->forceFill(['last_searched_at' => now()])->save();
    }

    private function trending(int $limit = 10): array
    {
        return TrendingSearch::query()
            ->orderByDesc('total_searches')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->pluck('keyword')
            ->all();
    }

    private function normalize(string $value): string
    {
        return trim(Str::lower(preg_replace('/\s+/', ' ', $value)));
    }

    private function textFromMixed(mixed $value): string
    {
        if ($value === null) return '';
        if (is_scalar($value)) return trim((string) $value);
        if (is_array($value)) {
            return collect($value)
                ->map(function ($item) {
                    if (is_array($item)) {
                        return $item['name'] ?? $item['title'] ?? $item['slug'] ?? '';
                    }
                    return is_scalar($item) ? (string) $item : '';
                })
                ->filter()
                ->implode(' ');
        }
        return '';
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
