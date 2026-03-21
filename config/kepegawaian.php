<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HMAC Secret Key untuk Integrasi Attendance
    |--------------------------------------------------------------------------
    |
    | Shared secret untuk verifikasi HMAC-SHA256 dari attendance-qr-system.
    | Harus sama dengan KEPEGAWAIAN_SECRET_KEY di attendance-qr-system .env
    |
    | Generate dengan: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
    |
    */
    'secret_key' => env('ATTENDANCE_HMAC_SECRET', ''),
];
