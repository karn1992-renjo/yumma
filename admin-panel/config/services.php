<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'x_account_number' => env('RAZORPAYX_ACCOUNT_NUMBER'),
        'webhook_secret' => env('RAZORPAYX_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'cashfree' => [
        'client_id' => env('CASHFREE_CLIENT_ID'),
        'client_secret' => env('CASHFREE_CLIENT_SECRET'),
        'beneficiary_mode' => env('CASHFREE_BENEFICIARY_MODE', 'banktransfer'),
        'api_version' => env('CASHFREE_API_VERSION', '2022-09-01'),
    ],

    'firebase_client' => [
        'api_key' => env('FIREBASE_CLIENT_API_KEY', 'AIzaSyDs06Xh5QCDpQiy37L-RR0hrNqGPvx2paE'),
        'project_id' => env('FIREBASE_CLIENT_PROJECT_ID', 'yumma-458b0'),
    ],

];
