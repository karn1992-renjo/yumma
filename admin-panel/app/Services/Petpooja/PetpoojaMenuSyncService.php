<?php

namespace App\Services\Petpooja;

use App\Models\PetpoojaMenuSync;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Log;

/**
 * Entry point for both menu-sync directions: the admin "Sync Menu Now"
 * button (outbound Fetch Menu) and the inbound Push Menu webhook. Both
 * funnel through applyPushPayload() so there's exactly one place that
 * writes the PetpoojaMenuSync audit row and invokes the importer.
 */
class PetpoojaMenuSyncService
{
    public function __construct(
        private readonly PetpoojaApiClient $client,
        private readonly PetpoojaMenuImporter $importer,
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function fetchAndApply(Restaurant $restaurant): array
    {
        $config = $restaurant->petpoojaConfig;
        if (! $config || ! $config->hasCredentials()) {
            return ['success' => false, 'message' => 'No Petpooja credentials configured for this restaurant.'];
        }

        try {
            $response = $this->client->fetchMenu($config);
        } catch (\Throwable $e) {
            Log::error('Petpooja fetch menu request failed.', [
                'restaurant_id' => $restaurant->id,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Could not reach Petpooja: ' . $e->getMessage()];
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return ['success' => false, 'message' => 'Petpooja returned an unexpected response.'];
        }

        return $this->applyPushPayload($restaurant, $payload, 'fetch');
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function applyPushPayload(Restaurant $restaurant, array $payload, string $source = 'push'): array
    {
        $sync = PetpoojaMenuSync::create([
            'restaurant_id' => $restaurant->id,
            'source' => $source,
            'raw_payload' => $payload,
            'status' => 'received',
            'received_at' => now(),
        ]);

        try {
            $result = $this->importer->import($restaurant, $payload);

            $sync->update([
                'status' => 'applied',
                'applied_at' => now(),
                'category_count' => $result['category_count'],
                'item_count' => $result['item_count'],
            ]);

            $restaurant->petpoojaConfig?->update([
                'last_menu_synced_at' => now(),
                'last_menu_sync_status' => 'applied',
                'last_menu_sync_error' => null,
            ]);

            return [
                'success' => true,
                'message' => "Menu synced: {$result['category_count']} categories, {$result['item_count']} items.",
            ];
        } catch (\Throwable $e) {
            Log::error('Petpooja menu import failed.', [
                'restaurant_id' => $restaurant->id,
                'sync_id' => $sync->id,
                'message' => $e->getMessage(),
            ]);

            $sync->update(['status' => 'failed', 'error' => $e->getMessage()]);

            $restaurant->petpoojaConfig?->update([
                'last_menu_sync_status' => 'failed',
                'last_menu_sync_error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Menu import failed: ' . $e->getMessage()];
        }
    }
}
