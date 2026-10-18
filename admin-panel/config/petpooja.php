<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Petpooja POS integration
    |--------------------------------------------------------------------------
    |
    | Base URLs for Petpooja's Online Ordering API (v2.1.0), keyed by
    | environment. "sandbox" defaults to Petpooja's documented dev AWS
    | Gateway URLs and their public Apiary mock server for anything not
    | credentialed yet. "production" is a placeholder until Petpooja's
    | team confirms whether the dev URLs are also the production ones --
    | do not assume they are the same without confirming.
    |
    */

    'environments' => [
        'sandbox' => [
            'fetch_menu' => env('PETPOOJA_SANDBOX_FETCH_MENU_URL', 'https://qle1yy2ydc.execute-api.ap-southeast-1.amazonaws.com/V1/mapped_restaurant_menus'),
            'save_order' => env('PETPOOJA_SANDBOX_SAVE_ORDER_URL', 'https://47pfzh5sf2.execute-api.ap-southeast-1.amazonaws.com/V1/save_order'),
            'update_order_status' => env('PETPOOJA_SANDBOX_UPDATE_ORDER_STATUS_URL', 'https://qle1yy2ydc.execute-api.ap-southeast-1.amazonaws.com/V1/update_order_status'),
            'rider_status' => env('PETPOOJA_SANDBOX_RIDER_STATUS_URL', 'https://qle1yy2ydc.execute-api.ap-southeast-1.amazonaws.com/V1/rider_status_update'),
        ],
        'production' => [
            'fetch_menu' => env('PETPOOJA_PRODUCTION_FETCH_MENU_URL'),
            'save_order' => env('PETPOOJA_PRODUCTION_SAVE_ORDER_URL'),
            'update_order_status' => env('PETPOOJA_PRODUCTION_UPDATE_ORDER_STATUS_URL'),
            'rider_status' => env('PETPOOJA_PRODUCTION_RIDER_STATUS_URL'),
        ],
    ],

    // Petpooja's public Apiary mock server -- used only when no real
    // credentials/URLs are configured, to shape-check outbound payloads
    // against their documented request/response examples.
    'mock_base_url' => env('PETPOOJA_MOCK_BASE_URL', 'https://private-anon-e90ab79e16-onlineorderingapisv210.apiary-mock.com'),

    'http_timeout_seconds' => env('PETPOOJA_HTTP_TIMEOUT', 15),
];
