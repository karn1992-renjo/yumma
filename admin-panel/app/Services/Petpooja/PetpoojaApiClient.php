<?php

namespace App\Services\Petpooja;

use App\Models\PetpoojaRestaurantConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper for the 4 outbound Petpooja calls (everything Petpooja
 * hosts that we call into). Handles the one real auth-placement quirk in
 * their spec internally so call sites never have to think about it:
 * Fetch Menu takes app-key/app-secret/access-token as HTTP headers, while
 * Save Order / Update Order Status / Rider Info take them as body fields
 * (app_key/app_secret/access_token).
 *
 * Base URLs come from config/petpooja.php, keyed by the restaurant
 * config's `environment`. When no real credentials/URLs are configured
 * yet, callers should point requests at Petpooja's public Apiary mock
 * server (config('petpooja.mock_base_url')) for shape-checking only --
 * the mock has no real per-restaurant/per-order state, so its response
 * content must never be trusted, only its ability to accept our payload
 * shape and return *a* response without erroring.
 */
class PetpoojaApiClient
{
    public function fetchMenu(PetpoojaRestaurantConfig $config): Response
    {
        return $this->http($config)
            ->withHeaders([
                'app-key' => $config->app_key_encrypted,
                'app-secret' => $config->app_secret_encrypted,
                'access-token' => $config->access_token_encrypted,
            ])
            ->post($this->url($config, 'fetch_menu'), [
                'restID' => $config->rest_id,
            ]);
    }

    public function saveOrder(PetpoojaRestaurantConfig $config, array $orderInfo): Response
    {
        return $this->http($config)->post($this->url($config, 'save_order'), [
            'app_key' => $config->app_key_encrypted,
            'app_secret' => $config->app_secret_encrypted,
            'access_token' => $config->access_token_encrypted,
            'orderinfo' => $orderInfo,
        ]);
    }

    public function cancelOrder(PetpoojaRestaurantConfig $config, string $clientOrderId, string $cancelReason): Response
    {
        return $this->http($config)->post($this->url($config, 'update_order_status'), [
            'app_key' => $config->app_key_encrypted,
            'app_secret' => $config->app_secret_encrypted,
            'access_token' => $config->access_token_encrypted,
            'restID' => $config->rest_id,
            'orderID' => '',
            'clientorderID' => $clientOrderId,
            'cancelReason' => $cancelReason,
            'status' => '-1',
        ]);
    }

    public function pushRiderStatus(
        PetpoojaRestaurantConfig $config,
        string $orderId,
        string $status,
        string $riderName,
        string $riderPhoneNumber
    ): Response {
        return $this->http($config)->post($this->url($config, 'rider_status'), [
            'app_key' => $config->app_key_encrypted,
            'app_secret' => $config->app_secret_encrypted,
            'access_token' => $config->access_token_encrypted,
            'order_id' => $orderId,
            'outlet_id' => $config->rest_id,
            'status' => $status,
            'rider_data' => [
                'rider_name' => $riderName,
                'rider_phone_number' => $riderPhoneNumber,
            ],
            'external_order_id' => '',
        ]);
    }

    private function http(PetpoojaRestaurantConfig $config)
    {
        return Http::timeout((int) config('petpooja.http_timeout_seconds', 15))
            ->acceptJson();
    }

    private function url(PetpoojaRestaurantConfig $config, string $endpoint): string
    {
        $configured = config("petpooja.environments.{$config->environment}.{$endpoint}");

        if (filled($configured)) {
            return $configured;
        }

        // No real URL configured for this environment -- fall back to the
        // public mock server so outbound payload-shape testing still
        // works without real credentials.
        $mockPaths = [
            'fetch_menu' => '/mapped_restaurant_menus',
            'save_order' => '/save_order',
            'update_order_status' => '/update_order_status',
            'rider_status' => '/rider_status_update',
        ];

        return rtrim((string) config('petpooja.mock_base_url'), '/') . ($mockPaths[$endpoint] ?? '/');
    }
}
