<?php

namespace App\Services\Petpooja;

use App\Models\MenuItem;
use App\Models\PetpoojaAddonStockOverride;
use App\Models\Restaurant;
use Illuminate\Support\Carbon;

/**
 * Applies Petpooja's item/addon stock-on/off webhook payload (one shared
 * endpoint for both directions, per Petpooja's own suggestion).
 *
 * Item-level stock reuses the existing menu_items.is_available +
 * unavailable_until columns directly -- the already-running scheduler
 * block in routes/console.php (restore_timed_menu_items) handles
 * reopening for free. Addon-level stock has no addressable row (addons
 * live inside the `add_ons` JSON blob of whichever items reference them),
 * so it goes through the PetpoojaAddonStockOverride overlay table instead.
 */
class PetpoojaStockSyncService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function applyStockPayload(Restaurant $restaurant, array $payload): array
    {
        $type = (string) ($payload['type'] ?? 'item');
        $inStock = (bool) ($payload['inStock'] ?? true);
        $itemIds = is_array($payload['itemID'] ?? null) ? $payload['itemID'] : [];

        if (count($itemIds) === 0) {
            return ['success' => false, 'message' => 'No item/addon ids provided.'];
        }

        $unavailableUntil = null;
        if (! $inStock && (string) ($payload['autoTurnOnTime'] ?? '') === 'custom' && filled($payload['customTurnOnTime'] ?? null)) {
            try {
                $unavailableUntil = Carbon::parse($payload['customTurnOnTime']);
            } catch (\Throwable) {
                $unavailableUntil = null;
            }
        }

        if ($type === 'addon') {
            $this->applyAddonStock($restaurant, $itemIds, $inStock, $unavailableUntil);
        } else {
            $this->applyItemStock($restaurant, $itemIds, $inStock, $unavailableUntil);
        }

        return ['success' => true, 'message' => 'Stock status updated successfully'];
    }

    private function applyItemStock(Restaurant $restaurant, array $itemIds, bool $inStock, ?Carbon $unavailableUntil): void
    {
        $idStrings = array_map('strval', $itemIds);

        MenuItem::where('restaurant_id', $restaurant->id)
            ->whereIn('petpooja_item_id', $idStrings)
            ->update([
                'is_available' => $inStock,
                'unavailable_until' => $inStock ? null : $unavailableUntil,
            ]);
    }

    private function applyAddonStock(Restaurant $restaurant, array $addonIds, bool $inStock, ?Carbon $unavailableUntil): void
    {
        foreach ($addonIds as $addonId) {
            PetpoojaAddonStockOverride::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'petpooja_addon_id' => (string) $addonId],
                ['in_stock' => $inStock, 'unavailable_until' => $inStock ? null : $unavailableUntil]
            );
        }
    }

    /**
     * Filters a Petpooja item's raw `addon` array (group id/min/max
     * selection) or a resolved addon-item list down to only those not
     * currently out-of-stock per the override table. Called at display
     * time for Petpooja-sourced items only.
     *
     * @param array<int, array<string, mixed>> $addonItems Each entry must have an 'id' key.
     * @return array<int, array<string, mixed>>
     */
    public function filterAvailableAddons(Restaurant $restaurant, array $addonItems): array
    {
        $outOfStockIds = PetpoojaAddonStockOverride::where('restaurant_id', $restaurant->id)
            ->where('in_stock', false)
            ->pluck('petpooja_addon_id')
            ->all();

        if (count($outOfStockIds) === 0) {
            return $addonItems;
        }

        return array_values(array_filter(
            $addonItems,
            fn ($addon) => ! in_array((string) ($addon['id'] ?? $addon['addonitemid'] ?? ''), $outOfStockIds, true)
        ));
    }
}
