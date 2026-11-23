<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use Database\Seeders\IamSeeder;
use Database\Seeders\PermissionSikepP1Seeder;

use function Pest\Laravel\seed;

test('PermissionSikepP1Seeder idempoten, terisolasi per aplikasi, dan memetakan role kanonik IamSeeder', function () {
    seed(IamSeeder::class);

    // Buat aplikasi lain dengan slug permission yang sama untuk memastikan isolasi iam_application_id
    $otherApp = IamApplication::query()->create([
        'nama' => 'Aplikasi Lain',
        'slug' => 'aplikasi-lain',
        'url' => 'https://lain.example.test',
        'is_active' => true,
    ]);
    $otherPerm = IamPermission::query()->create([
        'iam_application_id' => $otherApp->id,
        'nama' => 'Lihat Usulan (Lain)',
        'slug' => 'kenaikan-pangkat.usulan.view',
        'group' => 'kenaikan-pangkat-usulan',
    ]);

    // Jalankan seeder 2x untuk menguji idempotensi
    seed(PermissionSikepP1Seeder::class);
    seed(PermissionSikepP1Seeder::class);

    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

    $adminRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'admin')
        ->firstOrFail();
    $operatorRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'operator')
        ->firstOrFail();
    $validatorRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'validator')
        ->firstOrFail();
    $viewerRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'viewer')
        ->firstOrFail();

    $adminP1Slugs = $adminRole->permissions()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where(function ($q): void {
            $q->where('slug', 'like', 'kenaikan-pangkat.%')
                ->orWhere('slug', 'like', 'checklist.%');
        })
        ->pluck('slug');

    expect($adminP1Slugs)->toHaveCount(19);

    $operatorSlugs = $operatorRole->permissions()->pluck('slug');
    $validatorSlugs = $validatorRole->permissions()->pluck('slug');
    $viewerSlugs = $viewerRole->permissions()->pluck('slug');

    expect($operatorSlugs)->toContain('kenaikan-pangkat.usulan.create')
        ->and($operatorSlugs)->not->toContain('kenaikan-pangkat.monitoring.view')
        ->and($validatorSlugs)->toContain('kenaikan-pangkat.usulan.verifikasi-kasubbag')
        ->and($viewerSlugs)->toContain('kenaikan-pangkat.usulan.view')
        ->and($viewerSlugs)->not->toContain('kenaikan-pangkat.monitoring.view');

    // Pastikan permission milik aplikasi lain tidak ikut terpasang ke role kepegawaian
    expect(IamRolePermission::query()
        ->whereIn('iam_role_id', [$adminRole->id, $operatorRole->id, $validatorRole->id, $viewerRole->id])
        ->where('iam_permission_id', $otherPerm->id)
        ->exists())->toBeFalse();
});
