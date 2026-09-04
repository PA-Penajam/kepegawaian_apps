<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HMAC Secret Key untuk Integrasi API Kepegawaian
    |--------------------------------------------------------------------------
    |
    | Shared secret untuk verifikasi HMAC-SHA256 dari aplikasi konsumen
    | (sso-papenajam, wfa-task, attendance-qr-system).
    | Harus sama dengan KEPEGAWAIAN_HMAC_SECRET di .env tiap konsumen.
    |
    | Generate dengan: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
    |
    */
    'secret_key' => env('KEPEGAWAIAN_HMAC_SECRET', ''),
];
