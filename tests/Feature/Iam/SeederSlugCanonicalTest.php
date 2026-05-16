<?php

use App\Services\Iam\IamPermissionAuditor;
use Database\Seeders\CutiPermissionSeeder;
use Database\Seeders\IamSeeder;
use Database\Seeders\PermissionSikepP1Seeder;
use Database\Seeders\PersediaanRoleSeeder;

it('semua slug dari seeder utama canonical', function (string $seederClass) {
    // Pest beforeEach untuk Feature tests sudah seed IamSeeder.
    // Re-seed seeder yang diuji (idempoten via firstOrCreate).
    $this->seed($seederClass);

    $auditor = app(IamPermissionAuditor::class);
    $nonCanonical = $auditor->findNonCanonical();

    expect($nonCanonical)->toBeEmpty(
        "{$seederClass} masih punya slug non-canonical: "
        .$nonCanonical->pluck('slug')->implode(', ')
    );
})->with([
    'IamSeeder' => IamSeeder::class,
    'CutiPermissionSeeder' => CutiPermissionSeeder::class,
    'PermissionSikepP1Seeder' => PermissionSikepP1Seeder::class,
    'PersediaanRoleSeeder' => PersediaanRoleSeeder::class,
]);
