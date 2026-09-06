<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo / App-review login bypass
    |--------------------------------------------------------------------------
    |
    | Lets a single, known phone number log in with a fixed OTP without any
    | SMS / Firebase / MSG91 round-trip. Intended for App Store / Play Store
    | reviewers who cannot receive an Indian OTP SMS.
    |
    | The bypass is only active when ALL of `enabled`, `phone` and `otp` are
    | set. Turn it off (or clear the values) once the app is approved.
    |
    */

    'enabled' => filter_var(env('AUTH_DEMO_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Full number including country code, e.g. +919279380225
    'phone' => trim((string) env('AUTH_DEMO_LOGIN_PHONE', '')),

    // Fixed OTP the reviewer types, e.g. 1234 or 123456
    'otp' => trim((string) env('AUTH_DEMO_LOGIN_OTP', '')),

];
