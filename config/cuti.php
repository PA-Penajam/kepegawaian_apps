<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Consumer Webhook untuk Event Cuti
    |--------------------------------------------------------------------------
    |
    | Setiap consumer didefinisikan dengan webhook_url dan shared_secret_encrypted.
    | shared_secret_encrypted dienkripsi menggunakan Crypt::encryptString().
    |
    */
    'consumers' => [
        'attendance-qr-system' => [
            'webhook_url' => env('CUTI_ATTENDANCE_WEBHOOK_URL'),
            'shared_secret_encrypted' => env('CUTI_ATTENDANCE_SHARED_SECRET_ENCRYPTED'),
        ],
        'sisukma' => [
            'webhook_url' => env('CUTI_SISUKMA_WEBHOOK_URL'),
            'shared_secret_encrypted' => env('CUTI_SISUKMA_SHARED_SECRET_ENCRYPTED'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengaturan Pengiriman Webhook Outbox
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        // Produksi wajib false. Aktifkan true hanya di lingkungan lokal (*.test / 127.0.0.1).
        'allow_private_hosts' => (bool) env('CUTI_WEBHOOK_ALLOW_PRIVATE_HOSTS', false),
    ],
];
