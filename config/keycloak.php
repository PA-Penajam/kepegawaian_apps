<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Keycloak Base URL & Realm
    |--------------------------------------------------------------------------
    |
    | URL dasar Keycloak server dan nama realm yang digunakan.
    |
    */
    'base_url' => env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
    'realm' => env('KEYCLOAK_REALM', 'kepegawaian'),

    /*
    |--------------------------------------------------------------------------
    | Client Configuration (OIDC - User Authentication)
    |--------------------------------------------------------------------------
    |
    | Konfigurasi client OIDC untuk autentikasi pengguna via Authorization Code + PKCE.
    |
    */
    'client' => [
        'id' => env('KEYCLOAK_CLIENT_ID', 'kepegawaian-apps'),
        'secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Account Configuration (M2M - Admin API)
    |--------------------------------------------------------------------------
    |
    | Konfigurasi service account untuk komunikasi machine-to-machine
    | dengan Keycloak Admin API (sync, user management).
    |
    */
    'service_account' => [
        'client_id' => env('KEYCLOAK_SERVICE_CLIENT_ID', 'kepegawaian-service'),
        'client_secret' => env('KEYCLOAK_SERVICE_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OIDC Scopes
    |--------------------------------------------------------------------------
    |
    | Scopes yang diminta saat authorization request.
    | 'openid' wajib ada untuk OIDC compliance.
    |
    */
    'scopes' => ['openid', 'profile', 'email', 'roles'],

    /*
    |--------------------------------------------------------------------------
    | Token Settings
    |--------------------------------------------------------------------------
    |
    | Pengaturan lifecycle token: proactive refresh threshold,
    | request timeout, dan state expiry.
    |
    */
    'tokens' => [
        'refresh_before_seconds' => (int) env('KEYCLOAK_REFRESH_BEFORE_SECONDS', 60),
        'request_timeout_seconds' => (int) env('KEYCLOAK_REQUEST_TIMEOUT', 5),
        'state_expiry_minutes' => (int) env('KEYCLOAK_STATE_EXPIRY_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Threshold dan timing untuk circuit breaker pattern.
    | - failure_threshold: jumlah failure berturut-turut sebelum circuit OPEN
    | - recovery_timeout: detik sebelum transisi OPEN → HALF_OPEN
    | - success_threshold: jumlah sukses berturut-turut untuk HALF_OPEN → CLOSED
    | - cache_driver: driver cache untuk menyimpan state circuit breaker
    |
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('KEYCLOAK_CB_FAILURE_THRESHOLD', 5),
        'recovery_timeout_seconds' => (int) env('KEYCLOAK_CB_RECOVERY_TIMEOUT', 30),
        'success_threshold' => (int) env('KEYCLOAK_CB_SUCCESS_THRESHOLD', 2),
        'cache_driver' => env('KEYCLOAK_CB_CACHE_DRIVER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Bypass Configuration
    |--------------------------------------------------------------------------
    |
    | Pengaturan emergency login saat Keycloak tidak tersedia.
    | Emergency login hanya aktif ketika circuit breaker OPEN dan enabled=true.
    |
    */
    'emergency' => [
        'enabled' => (bool) env('KEYCLOAK_EMERGENCY_ENABLED', false),
        'username' => env('KEYCLOAK_EMERGENCY_USERNAME'),
        'password' => env('KEYCLOAK_EMERGENCY_PASSWORD'),
        'session_timeout_minutes' => (int) env('KEYCLOAK_EMERGENCY_SESSION_TIMEOUT', 30),
        'allowed_roles' => explode(',', env('KEYCLOAK_EMERGENCY_ALLOWED_ROLES', 'admin')),
        'rate_limit_max_attempts' => (int) env('KEYCLOAK_EMERGENCY_RATE_LIMIT', 5),
        'rate_limit_decay_minutes' => (int) env('KEYCLOAK_EMERGENCY_RATE_DECAY', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Pengaturan untuk sinkronisasi data Pegawai ↔ Keycloak users.
    |
    */
    'sync' => [
        'incremental_window_hours' => (int) env('KEYCLOAK_SYNC_WINDOW_HOURS', 24),
        'batch_size' => (int) env('KEYCLOAK_SYNC_BATCH_SIZE', 100),
    ],
];
