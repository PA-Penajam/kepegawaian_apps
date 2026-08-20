<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server SSO PA Penajam (OAuth2 Server)
    |--------------------------------------------------------------------------
    |
    | Base URL dari aplikasi sso-papenajam yang bertindak sebagai Identity
    | Provider terpusat.
    |
    */
    'base_url' => env('SSO_BASE_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Client Credentials
    |--------------------------------------------------------------------------
    |
    | Client ID dan Secret yang telah didaftarkan di SSO PA Penajam
    | untuk aplikasi SIMPEG.
    |
    */
    'client_id' => env('SSO_CLIENT_ID', 'kepegawaian-apps'),
    'client_secret' => env('SSO_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Redirect URI (Callback)
    |--------------------------------------------------------------------------
    |
    | Alamat callback untuk menerima authorization code setelah user
    | berhasil login di SSO.
    |
    */
    'redirect_uri' => env('SSO_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | Daftar scope yang diminta saat proses otorisasi.
    |
    */
    'scopes' => [
        'openid',
        'profile',
        'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token & Request Settings
    |--------------------------------------------------------------------------
    */
    'tokens' => [
        'timeout' => (int) env('SSO_REQUEST_TIMEOUT', 5),
        'refresh_before_seconds' => (int) env('SSO_REFRESH_BEFORE_SECONDS', 60),
        'state_expiry_minutes' => (int) env('SSO_STATE_EXPIRY_MINUTES', 10),
    ],
];
