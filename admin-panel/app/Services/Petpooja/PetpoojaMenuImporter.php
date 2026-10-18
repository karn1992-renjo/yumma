<?php

namespace App\Services\Petpooja;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/**
 * Wholesale-replace importer for a Petpooja Push/Fetch Menu payload.
 * Materializes real `categories`/`menu_items` rows (matched by the
 * petpooja_category_id/petpooja_item_id external-id columns) so every
 * existing customer-facing/ordering code path (menu display, cart,
 * checkout validation, promotions, the GST ledger) works unchanged --
 * see the approved plan's "materialize into real rows" decision.
 *
 * A Petpooja item with variations gets one menu_items row PER variation
 * (our cart has no native "one product, many purchasable variants"
 * concept), with petpooja_variation_id set and the name suffixed.
 *
 * True wholesale-replace semantics: any previously materialized row
 * whose external id is absent from the new payload gets deactivated
 * (is_available = false), not deleted -- so historical orders that
 * reference it stay intact.
 */
class PetpoojaMenuImporter
{
    /**
     * @return array{category_count: int, item_count: int}
     */
    public function import(Restaurant $restaurant, array $payload): array
    {
        $taxRatesById = $this->indexTaxRatesById($payload['taxes'] ?? []);

        return DB::transaction(function () use ($restaurant, $payload, $taxRatesById) {
            $categoryIdMap = $this->importCategories($restaurant, $payload['categories'] ?? []);
            $itemCount = $this->importItems($restaurant, $payload['items'] ?? [], $categoryIdMap, $taxRatesById);

            return [
                'category_count' => count($categoryIdMap),
                'item_count' => $itemCount,
            ];
        });
    }

    /**
     * @return array<string, int> Petpooja categoryid => our categories.id
     */
    private function importCategories(Restaurant $restaurant, array $categories): array
    {
        $seenExternalIds = [];
        $map = [];

        foreach ($categories as $category) {
            $externalId = (string) ($category['categoryid'] ?? '');
            if ($externalId === '') {
                continue;
            }
            $seenExternalIds[] = $externalId;

            $row = Category::where('restaurant_id', $restaurant->id)
                ->where('petpooja_category_id', $externalId)
                ->first();

            if (! $row) {
                $row = new Category([
                    'restaurant_id' => $restaurant->id,
                    'petpooja_category_id' => $externalId,
                ]);
            }

            $row->name = (string) ($category['categoryname'] ?? $row->name ?? 'Unnamed');
            $row->display_order = (int) ($category['categoryrank'] ?? 0);
            $row->is_active = (string) ($category['active'] ?? '1') === '1';
            $row->save();

            $map[$externalId] = $row->id;
        }

        // Wholesale replace: deactivate categories previously synced from
        // Petpooja for this restaurant that are no longer in the payload.
        Category::where('restaurant_id', $restaurant->id)
            ->whereNotNull('petpooja_category_id')
            ->when(count($seenExternalIds) > 0, fn ($q) => $q->whereNotIn('petpooja_category_id', $seenExternalIds))
            ->update(['is_active' => false]);

        return $map;
    }

    /**
     * @param array<string, int> $categoryIdMap
     * @param array<string, float> $taxRatesById
     */
    private function importItems(Restaurant $restaurant, array $items, array $categoryIdMap, array $taxRatesById): int
    {
        $seenExternalKeys = [];
        $count = 0;

        foreach ($items as $item) {
            $itemExternalId = (string) ($item['itemid'] ?? '');
            if ($itemExternalId === '') {
                continue;
            }

            $categoryId = $categoryIdMap[(string) ($item['item_categoryid'] ?? '')] ?? null;
            $gstRate = $this->sumTaxRates((string) ($item['item_tax'] ?? ''), $taxRatesById);
            $foodType = $this->foodTypeFromAttributeId((string) ($item['item_attributeid'] ?? ''));

            $variations = is_array($item['variation'] ?? null) ? $item['variation'] : [];
            $hasVariations = (string) ($item['itemallowvariation'] ?? '0') === '1' && count($variations) > 0;

            if (! $hasVariations) {
                $key = $itemExternalId;
                $seenExternalKeys[] = $key;
                $this->upsertMenuItem($restaurant, $categoryId, $itemExternalId, null, $item, $item, $gstRate, $foodType);
                $count++;
                continue;
            }

            foreach ($variations as $variation) {
                $variationExternalId = (string) ($variation['variationid'] ?? $variation['id'] ?? '');
                if ($variationExternalId === '') {
                    continue;
                }
                $key = $itemExternalId . ':' . $variationExternalId;
                $seenExternalKeys[] = $key;
                $this->upsertMenuItem($restaurant, $categoryId, $itemExternalId, $variationExternalId, $item, $variation, $gstRate, $foodType);
                $count++;
            }
        }

        // Wholesale replace: deactivate items (not variations collapsed
        // into one row, each variation IS its own row) absent from the
        // new payload.
        $existing = MenuItem::where('restaurant_id', $restaurant->id)
            ->whereNotNull('petpooja_item_id')
            ->get(['id', 'petpooja_item_id', 'petpooja_variation_id']);

        foreach ($existing as $row) {
            $key = $row->petpooja_variation_id
                ? $row->petpooja_item_id . ':' . $row->petpooja_variation_id
                : $row->petpooja_item_id;

            if (! in_array($key, $seenExternalKeys, true)) {
                $row->is_available = false;
                $row->save();
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $item The parent item payload (name, addons, tags, etc.)
     * @param array<string, mixed> $priced The price-bearing payload -- the item itself when
     *     there's no variation, or the specific variation entry otherwise.
     */
    private function upsertMenuItem(
        Restaurant $restaurant,
        ?int $categoryId,
        string $itemExternalId,
        ?string $variationExternalId,
        array $item,
        array $priced,
        ?float $gstRate,
        string $foodType
    ): void {
        $query = MenuItem::where('restaurant_id', $restaurant->id)
            ->where('petpooja_item_id', $itemExternalId);
        $query = $variationExternalId === null
            ? $query->whereNull('petpooja_variation_id')
            : $query->where('petpooja_variation_id', $variationExternalId);

        $row = $query->first() ?? new MenuItem([
            'restaurant_id' => $restaurant->id,
            'petpooja_item_id' => $itemExternalId,
            'petpooja_variation_id' => $variationExternalId,
        ]);

        $name = (string) ($item['itemname'] ?? 'Unnamed Item');
        if ($variationExternalId !== null && filled($priced['name'] ?? null)) {
            $name .= ' (' . $priced['name'] . ')';
        }

        $row->item_source = 'petpooja';
        $row->category_id = $categoryId;
        $row->name = $name;
        $row->description = (string) ($item['itemdescription'] ?? '');
        $row->price = (float) ($priced['price'] ?? $item['price'] ?? 0);
        $row->food_type = $foodType;
        $row->is_veg = $foodType === 'veg';
        $row->gst_rate = $gstRate;
        $row->is_available = (string) ($item['active'] ?? '1') === '1'
            && (string) ($priced['active'] ?? '1') === '1'
            && (string) ($item['in_stock'] ?? '2') !== '0';
        $row->is_combo = (string) ($item['is_combo'] ?? '0') === '1';
        $row->is_recommended = (string) ($item['is_recommend'] ?? '0') === '1';
        $row->approval_status = 'approved';
        $row->add_ons = is_array($item['addon'] ?? null) ? $item['addon'] : [];
        $row->variants = []; // each variation is its own row -- nothing to nest here
        $row->petpooja_meta = [
            'item_tax' => $item['item_tax'] ?? null,
            'tax_inclusive' => $item['tax_inclusive'] ?? false,
            'gst_type' => $item['gst_type'] ?? null,
            'item_tags' => $item['item_tags'] ?? [],
            'cuisine' => $item['cuisine'] ?? [],
            'combo_items' => $item['combo_items'] ?? [],
            'nutrition' => $item['nutrition'] ?? null,
            'item_info' => $item['item_info'] ?? null,
            'variation_name' => $priced['name'] ?? null,
        ];

        $row->save();
    }

    /**
     * @return array<string, float> tax id => percentage rate
     */
    private function indexTaxRatesById(array $taxes): array
    {
        $map = [];
        foreach ($taxes as $tax) {
            $id = (string) ($tax['taxid'] ?? '');
            if ($id === '') {
                continue;
            }
            $map[$id] = (float) ($tax['tax'] ?? 0);
        }

        return $map;
    }

    private function sumTaxRates(string $commaSeparatedIds, array $taxRatesById): ?float
    {
        $ids = array_filter(array_map('trim', explode(',', $commaSeparatedIds)));
        if (count($ids) === 0) {
            return null;
        }

        $total = 0.0;
        foreach ($ids as $id) {
            $total += $taxRatesById[$id] ?? 0.0;
        }

        return $total;
    }

    private function foodTypeFromAttributeId(string $attributeId): string
    {
        return match ($attributeId) {
            '2' => 'non_veg',
            '24' => 'egg',
            default => 'veg',
        };
    }
}
