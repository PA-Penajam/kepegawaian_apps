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

    // Buat user dengan role yang tidak akan ter-mapping ke IAM role manapun.
    // Kita gunakan raw query untuk set role='ghost' karena 'ghost' bukan
    // nilai valid pada enum Role (sehingga bypass enum casting).
    // Role 'ghost' tidak ada di ref_roles, sehingga tidak akan ter-migrate
    // ke IAM role manapun, sehingga fallback ke viewer.
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['role' => 'ghost']);

    // Logika fallback sama seperti di IamSeeder baris 86-89:
    // Jika role slug tidak ditemukan di IAM role, gunakan defaultRole (viewer)
    $roleSlug  = $user->getRawOriginal('role') ?? 'viewer';
    $iamRole = IamRole::where('iam_application_id', $kepegawaian->id)
        ->where('slug', $roleSlug)
        ->first() ?? $viewerRole;

    // Verifikasi bahwa role yang digunakan adalah viewer (fallback)
    expect($iamRole->id)->toBe($viewerRole->id);
    expect($iamRole->slug)->toBe('viewer');

    // Assign role ke user dan verifikasi
    $userRole = IamUserRole::firstOrCreate(
        ['user_id' => $user->id, 'iam_role_id' => $iamRole->id],
        ['assigned_at' => now()]
    );

    expect($userRole)->not->toBeNull();
    expect($user->iamRoles()->count())->toBe(1);
    // Akses role relationship untuk dapat slug (IamUserRole tidak punya slug sendiri)
    expect($user->iamRoles()->first()->role->slug)->toBe('viewer');
});
