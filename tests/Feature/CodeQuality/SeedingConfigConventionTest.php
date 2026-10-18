<?php

/*
|--------------------------------------------------------------------------
| Konvensi: env() hanya di config/ + fallback seeding aman
|--------------------------------------------------------------------------
|
| - env() tidak boleh dipanggil langsung di app/, routes/, database/.
|   Semua nilai environment harus dibaca via config().
| - config/seeding.php harus memberi default non-kosong walau variable
|   env ada tapi kosong (bug lama: SEEDER_ADMIN_PASSWORD= membuat
|   password admin ter-seed string kosong karena env() mengembalikan
|   string kosong, bukan default).
*/

it('tidak ada pemanggilan env() langsung di luar direktori config', function () {
    $pelanggaran = [];

    foreach (['app', 'routes', 'database'] as $dir) {
        $base = base_path($dir);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) as $i => $teks) {
                if (preg_match('/\benv\s*\(/', $teks)) {
                    $pelanggaran[] = $file->getPathname().':'.($i + 1);
                }
            }
        }
    }

    expect($pelanggaran)->toBeEmpty();
});

it('config seeding memberi default aman saat env kosong maupun tidak diset', function () {
    $cadanganAdmin = $_ENV['SEEDER_ADMIN_PASSWORD'] ?? null;
    $cadanganOperator = $_ENV['SEEDER_OPERATOR_PASSWORD'] ?? null;
    $cadanganAttendance = $_ENV['ATTENDANCE_APP_URL'] ?? null;

    try {
        // Kasus bug lama: variable ada tapi string kosong → wajib fallback default
        putenv('SEEDER_ADMIN_PASSWORD=');
        putenv('SEEDER_OPERATOR_PASSWORD=');
        $_ENV['SEEDER_ADMIN_PASSWORD'] = '';
        $_ENV['SEEDER_OPERATOR_PASSWORD'] = '';

        $cfg = require config_path('seeding.php');

        expect($cfg['admin_password'])->toBe('admin123')
            ->and($cfg['operator_password'])->toBe('operator123');

        // Kasus variable tidak diset sama sekali → default juga dipakai
        putenv('SEEDER_ADMIN_PASSWORD');
        putenv('SEEDER_OPERATOR_PASSWORD');
        unset($_ENV['SEEDER_ADMIN_PASSWORD'], $_ENV['SEEDER_OPERATOR_PASSWORD']);

        $cfg = require config_path('seeding.php');

        expect($cfg['admin_password'])->toBe('admin123')
            ->and($cfg['operator_password'])->toBe('operator123');

        // Kasus variable terisi → nilai env dipakai, bukan default
        putenv('SEEDER_ADMIN_PASSWORD=rahasia-admin-1');
        putenv('SEEDER_OPERATOR_PASSWORD=rahasia-operator-1');
        $_ENV['SEEDER_ADMIN_PASSWORD'] = 'rahasia-admin-1';
        $_ENV['SEEDER_OPERATOR_PASSWORD'] = 'rahasia-operator-1';

        $cfg = require config_path('seeding.php');

        expect($cfg['admin_password'])->toBe('rahasia-admin-1')
            ->and($cfg['operator_password'])->toBe('rahasia-operator-1');
    } finally {
        foreach (['SEEDER_ADMIN_PASSWORD' => $cadanganAdmin, 'SEEDER_OPERATOR_PASSWORD' => $cadanganOperator, 'ATTENDANCE_APP_URL' => $cadanganAttendance] as $nama => $nilai) {
            if ($nilai === null) {
                putenv($nama);
                unset($_ENV[$nama]);
            } else {
                putenv("{$nama}={$nilai}");
                $_ENV[$nama] = $nilai;
            }
        }
    }
});

it('config seeding menyediakan URL aplikasi attendance', function () {
    $cfg = require config_path('seeding.php');

    expect($cfg['attendance_app_url'] ?? null)->not->toBeEmpty();
});
