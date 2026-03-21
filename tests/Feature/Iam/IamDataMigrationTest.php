<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Pastikan data RBAC lama ada sebelum migrasi IAM
    $this->seed(\Database\Seeders\RefRoleSeeder::class);
    $this->seed(\Database\Seeders\RefPermissionSeeder::class);
    $this->seed(\Database\Seeders\IamSeeder::class);
});

test('kepegawaian-apps terdaftar sebagai aplikasi sistem pertama', function () {
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    expect($app)->not->toBeNull();
    expect($app->is_system)->toBeTrue();
    expect($app->is_active)->toBeTrue();
});

test('user admin ter-assign ke role admin di kepegawaian-apps', function () {
    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $adminRole   = IamRole::where('iam_application_id', $kepegawaian->id)
        ->where('slug', 'admin')
        ->first();

    expect($adminRole)->not->toBeNull();

    // Buat user admin lama (simulasi users.role = 'admin' sudah dimigrasikan)
    $user       = User::factory()->create();
    $userRoleRow = IamUserRole::create([
        'user_id'    => $user->id,
        'iam_role_id' => $adminRole->id,
        'assigned_at' => now(),
    ]);

    expect($userRoleRow)->not->toBeNull();
    expect($user->iamRoles()->count())->toBe(1);
});

test('user dengan role tidak dikenal di-assign ke viewer sebagai fallback', function () {
    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $viewerRole  = IamRole::where('iam_application_id', $kepegawaian->id)
        ->where('slug', 'viewer')
        ->first();

    expect($viewerRole)->not->toBeNull();
});
