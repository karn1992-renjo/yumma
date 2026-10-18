<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PetpoojaAddonStockOverride;
use App\Models\PetpoojaRestaurantConfig;
use App\Models\Restaurant;
use App\Services\OrderStatusPushService;
use App\Services\Petpooja\PetpoojaMenuSyncService;
use App\Services\Petpooja\PetpoojaOrderStatusMapper;
use App\Services\Petpooja\PetpoojaStockSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The 6 inbound endpoints Petpooja calls into us. None of them carry any
 * documented signature/shared-secret/IP-allowlist in Petpooja's API
 * blueprint -- only a `restID` field. Interim mitigation (see the
 * approved plan): every method requires `restID` to match an ACTIVE
 * PetpoojaRestaurantConfig, rejects anything else with a flat 404 (not
 * 401, so valid-vs-invalid restIDs can't be distinguished from the
 * outside), and logs rejections separately from accepted calls. Routes
 * are additionally rate-limited (see routes/web.php). This is a stopgap
 * that limits blast radius, not real authentication -- harden once
 * Petpooja's team confirms what's actually available.
 */
class PetpoojaWebhookController extends Controller
{
    public function pushMenu(Request $request, PetpoojaMenuSyncService $menuSyncService)
    {
        $payload = $request->all();
        $restId = $this->extractRestId($payload);
        $config = $this->findActiveConfig($restId, 'push-menu', $payload);
        if (! $config) {
            return response()->json(['success' => '0', 'message' => 'Unknown restaurant.'], 404);
        }

        $result = $menuSyncService->applyPushPayload($config->restaurant, $payload, 'push');

        return response()->json([
            'success' => $result['success'] ? '1' : '0',
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    public function orderCallback(Request $request, Order $order)
    {
        $restId = (string) $request->input('restID', '');
        $config = $this->findActiveConfig($restId, 'order-callback', $request->all(), $order->restaurant_id);
        if (! $config) {
            return response()->json(['success' => '0'], 404);
        }

        $status = (string) $request->input('status', '');
        $mapper = app(PetpoojaOrderStatusMapper::class);
        $mapped = $mapper->map($status);

        if ($mapped !== null && $mapper->shouldApply($order, $mapped)) {
            $order->status = $mapped;
            if ($mapped === 'cancelled') {
                $order->cancelled_at = now();
                $order->cancellation_reason = (string) $request->input('cancel_reason', 'Cancelled via Petpooja POS.');
                $order->cancelled_by_role = 'restaurant';
                $order->cancelled_by_name = 'Petpooja POS';
            }
            $order->save();

            app(OrderStatusPushService::class)->notifyParticipants($order->fresh(['customer', 'restaurant', 'driver']));
        } elseif ($mapped === null) {
            Log::warning('Petpooja callback sent an unrecognised status.', [
                'order_id' => $order->id,
                'status' => $status,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function stock(Request $request, PetpoojaStockSyncService $stockSyncService)
    {
        $restId = (string) $request->input('restID', '');
        $config = $this->findActiveConfig($restId, 'stock', $request->all());
        if (! $config) {
            return response()->json(['code' => 404, 'status' => 'failed', 'message' => 'Unknown restaurant.'], 404);
        }

        $result = $stockSyncService->applyStockPayload($config->restaurant, $request->all());

        return response()->json([
            'code' => $result['success'] ? 200 : 400,
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    public function getStoreStatus(Request $request)
    {
        $restId = (string) $request->input('restID', '');
        $config = $this->findActiveConfig($restId, 'get-store-status', $request->all());
        if (! $config) {
            return response()->json(['http_code' => 404, 'status' => 'failed', 'message' => 'Unknown restaurant.'], 404);
        }

        $restaurant = $config->restaurant;

        return response()->json([
            'http_code' => 200,
            'status' => 'success',
            'store_status' => $restaurant->is_open ? '1' : '0',
            'message' => 'Store Delivery Status fetched successfully',
        ]);
    }

    public function updateStoreStatus(Request $request)
    {
        $restId = (string) $request->input('restID', '');
        $config = $this->findActiveConfig($restId, 'update-store-status', $request->all());
        if (! $config) {
            return response()->json(['http_code' => 404, 'status' => 'failed', 'message' => 'Unknown restaurant.'], 404);
        }

        $restaurant = $config->restaurant;
        $storeStatus = (string) $request->input('store_status', '1');
        $turnOnTime = $request->input('turn_on_time');

        $restaurant->is_open = $storeStatus === '1';
        $restaurant->petpooja_store_closed_until = $storeStatus === '0' && filled($turnOnTime)
            ? \Illuminate\Support\Carbon::parse($turnOnTime)
            : null;
        $restaurant->save();

        return response()->json([
            'http_code' => 200,
            'status' => 'success',
            'message' => "Store Status updated successfully for store {$restId}",
        ]);
    }

    private function extractRestId(array $payload): string
    {
        $restaurants = $payload['restaurants'] ?? [];
        if (is_array($restaurants) && count($restaurants) > 0) {
            return (string) ($restaurants[0]['restaurantid'] ?? '');
        }

        return (string) ($payload['restID'] ?? '');
    }

    private function findActiveConfig(
        string $restId,
        string $endpoint,
        array $payload,
        ?int $expectedRestaurantId = null
    ): ?PetpoojaRestaurantConfig {
        if ($restId === '') {
            Log::warning('Petpooja webhook rejected: missing restID.', ['endpoint' => $endpoint]);

            return null;
        }

        $config = PetpoojaRestaurantConfig::active()->where('rest_id', $restId)->first();

        if (! $config || ($expectedRestaurantId !== null && $config->restaurant_id !== $expectedRestaurantId)) {
            Log::warning('Petpooja webhook rejected: restID not recognised or mismatched.', [
                'endpoint' => $endpoint,
                'rest_id' => $restId,
                'expected_restaurant_id' => $expectedRestaurantId,
            ]);

            return null;
        }

        return $config;
    }
}
