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
    ],
];
