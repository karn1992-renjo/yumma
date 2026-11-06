<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\WhatsAppCatalogItem;
use App\Services\MediaStorage;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the restaurants' menus in the WhatsApp (Meta) product catalog, so customers can browse a
 * restaurant's dishes inside WhatsApp, pick quantities and send a cart.
 *
 * Only what changed is sent. An item is listed when it is available, approved, has a price and a
 * picture, belongs to a visible verified restaurant, and has no size/variant choices (those can only
 * be ordered in the app). Items that stop qualifying are removed from the catalog.
 */
class WhatsAppCatalogSync
{
    private const BATCH = 500;

    public function __construct(private WhatsAppService $whatsapp)
    {
    }

    public function ready(): bool
    {
        return $this->whatsapp->catalogProvider() !== null;
    }

    /** @return array{created:int, updated:int, removed:int, failed:int, total:int, error:?string, at:string} */
    public function run(bool $force = false): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'removed' => 0, 'failed' => 0, 'total' => 0, 'error' => null, 'at' => now()->toIso8601String()];
        $provider = $this->whatsapp->catalogProvider();
        if (! $this->ready() || ! $provider instanceof MetaCloudProvider) {
            $summary['error'] = 'Turn on the catalog and enter the Catalog ID for the Meta provider first.';

            return $this->store($summary);
        }
        $cfg = $this->whatsapp->config($provider->key());

        $payloads = [];
        $this->eligible()->each(function (MenuItem $item) use (&$payloads) {
            if ($data = $this->payload($item)) {
                $payloads[$item->id] = $data;
            }
        });
        $summary['total'] = count($payloads);

        $existing = WhatsAppCatalogItem::all()->keyBy('menu_item_id');
        $upsert = [];
        foreach ($payloads as $id => $data) {
            $hash = sha1(json_encode($data));
            $row = $existing[$id] ?? null;
            if ($force || ! $row || $row->status !== 'synced' || $row->content_hash !== $hash) {
                $upsert[$id] = ['data' => $data, 'hash' => $hash, 'new' => ! $row];
            }
        }
        $remove = $existing->filter(fn ($row, $id) => ! isset($payloads[$id]))->values();

        foreach (array_chunk($upsert, self::BATCH, true) as $chunk) {
            $requests = collect($chunk)->map(fn ($c) => ['method' => 'UPDATE', 'data' => $c['data']])->values()->all();
            try {
                $provider->syncCatalogItems($requests, $cfg);
                foreach ($chunk as $id => $c) {
                    WhatsAppCatalogItem::updateOrCreate(['menu_item_id' => $id], [
                        'restaurant_id' => (int) substr($c['data']['custom_label_0'], 1), 'retailer_id' => $c['data']['id'],
                        'content_hash' => $c['hash'], 'status' => 'synced', 'error' => null, 'synced_at' => now(),
                    ]);
                    $summary[$c['new'] ? 'created' : 'updated']++;
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp catalog sync failed.', ['error' => $e->getMessage(), 'items' => count($chunk)]);
                $summary['failed'] += count($chunk);
                $summary['error'] = mb_substr($e->getMessage(), 0, 300);
                foreach ($chunk as $id => $c) {
                    WhatsAppCatalogItem::updateOrCreate(['menu_item_id' => $id], [
                        'restaurant_id' => (int) substr($c['data']['custom_label_0'], 1), 'retailer_id' => $c['data']['id'],
                        'content_hash' => $c['hash'], 'status' => 'failed', 'error' => $summary['error'],
                    ]);
                }
            }
        }

        foreach ($remove->chunk(self::BATCH) as $chunk) {
            $requests = $chunk->map(fn ($row) => ['method' => 'DELETE', 'data' => ['id' => $row->retailer_id]])->all();
            try {
                $provider->syncCatalogItems($requests, $cfg);
                WhatsAppCatalogItem::whereIn('id', $chunk->pluck('id'))->delete();
                $summary['removed'] += $chunk->count();
            } catch (\Throwable $e) {
                $summary['failed'] += $chunk->count();
                $summary['error'] = mb_substr($e->getMessage(), 0, 300);
            }
        }

        return $this->store($summary);
    }

    /** Catalog item ids (retailer ids) a restaurant currently has in the catalog, best sellers first. */
    public function itemsFor(int $restaurantId)
    {
        $ids = WhatsAppCatalogItem::where('restaurant_id', $restaurantId)->where('status', 'synced')->pluck('menu_item_id');

        return MenuItem::with('category')->whereIn('id', $ids)->where('is_available', true)
            ->orderByDesc('is_bestseller')->orderByDesc('total_orders')->orderBy('name')->get()
            ->filter(fn (MenuItem $i) => $i->is_scheduled_available)->values();
    }

    private function eligible()
    {
        return MenuItem::with(['restaurant', 'category'])
            ->where('is_available', true)
            ->where(fn ($q) => $q->whereNull('approval_status')->orWhere('approval_status', 'approved'))
            ->whereHas('restaurant', fn ($q) => $q->customerVisible()->where('is_verified', true))
            ->lazyById(500);
    }

    /** @return array<string, mixed>|null null when the item cannot be sold from a WhatsApp cart */
    private function payload(MenuItem $item): ?array
    {
        $restaurant = $item->restaurant;
        $price = $item->getFinalPriceAttribute();
        if (! $restaurant || $price <= 0 || ! empty($item->variants)) {
            return null;
        }
        $image = $item->image_url ?: MediaStorage::url($restaurant->cover_image ?: ($restaurant->banner_image ?: $restaurant->logo_image));
        if (! $image) {
            return null;                    // Meta rejects catalog items without a picture
        }
        $currency = strtoupper((string) (AppSetting::getValue('currency_code', 'INR') ?: 'INR'));

        return array_filter([
            'id' => WhatsAppCatalogItem::retailerId($item->id),
            'title' => mb_substr($item->name, 0, 150),
            'description' => mb_substr(trim((string) $item->description) ?: "{$item->name} from {$restaurant->name}", 0, 900),
            'availability' => ($restaurant->is_open && $item->is_scheduled_available) ? 'in stock' : 'out of stock',
            'condition' => 'new',
            'price' => sprintf('%.2f %s', $price, $currency),
            'link' => route('restaurant.show', $restaurant->slug),
            'image_link' => $image,
            'brand' => mb_substr($restaurant->name, 0, 100),
            'custom_label_0' => 'r' . $restaurant->id,
            'product_type' => $item->category?->name,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function store(array $summary): array
    {
        AppSetting::setValue('whatsapp_catalog_last_sync', json_encode($summary));

        return $summary;
    }
}
