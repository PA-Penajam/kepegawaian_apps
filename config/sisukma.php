<?php

use App\Services\Sisukma\Handlers\OutgoingMailWebhookHandler;
use App\Services\SuratDinas\Hooks\CutiSuratIzinHook;
use App\Services\SuratDinas\Hooks\UsulanKpPengantarHook;

return [
    /*
    |--------------------------------------------------------------------------
    | Integrasi SiSukma (Sistem Informasi Surat Kantor Mahkamah Agung)
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('SISUKMA_ENABLED', false),

    'base_url' => rtrim((string) env('SISUKMA_BASE_URL', ''), '/'),

    'client_id' => (string) env('SISUKMA_CLIENT_ID', 'kepegawaian'),

    'secret' => (string) env('SISUKMA_SHARED_SECRET', ''),

    'webhook_secret' => (string) env('SISUKMA_WEBHOOK_SECRET', env('SISUKMA_SHARED_SECRET', '')),

    'timeout' => (int) env('SISUKMA_REQUEST_TIMEOUT', 10),

    'timestamp_window_seconds' => (int) env('SISUKMA_TIMESTAMP_WINDOW', 300),

    'notifications_enabled' => (bool) env('SISUKMA_NOTIFICATIONS_ENABLED', false),

    'app_url' => rtrim((string) env('SISUKMA_KA_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),

    /*
    |--------------------------------------------------------------------------
    | Daftar Handler Webhook Inbound dari SiSukma
    |--------------------------------------------------------------------------
    */
    'inbound_handlers' => [
        OutgoingMailWebhookHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Hooks Permintaan Surat Dinas
    |--------------------------------------------------------------------------
    */
    'lifecycle_hooks' => [
        CutiSuratIzinHook::class,
        UsulanKpPengantarHook::class,
    ],
];
