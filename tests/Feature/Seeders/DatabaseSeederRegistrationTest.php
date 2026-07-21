<?php

namespace Tests\Feature\Seeders;

it('mendaftarkan setiap seeder IAM tepat satu kali', function (string $seeder): void {
    $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

    expect($source)->not->toBeFalse()
        ->and(substr_count($source, $seeder.'::class'))->toBe(1);
})->with([
    'IAM utama' => 'IamSeeder',
    'aplikasi attendance' => 'AttendanceAppSeeder',
]);
