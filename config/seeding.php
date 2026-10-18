<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kredensial Seeding
    |--------------------------------------------------------------------------
    |
    | Nilai dibaca dengan operator ?: (bukan default env()) agar variable
    | environment yang ADA TAPI KOSONG tetap jatuh ke default yang aman.
    | env('X', 'default') hanya memakai default jika variable TIDAK ADA;
    | jika variable ada tapi string kosong, env() mengembalikan '' dan
    | password admin/operator bisa ter-seed string kosong.
    |
    */

    'admin_password' => env('SEEDER_ADMIN_PASSWORD') ?: 'admin123',

    'operator_password' => env('SEEDER_OPERATOR_PASSWORD') ?: 'operator123',

    /*
    |--------------------------------------------------------------------------
    | URL Aplikasi Konsumen (dipakai seeder registrasi aplikasi)
    |--------------------------------------------------------------------------
    */

    'attendance_app_url' => env('ATTENDANCE_APP_URL') ?: 'http://localhost:3000',
];
