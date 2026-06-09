<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Services\IamAuthorizationService;
use Database\Seeders\AttendanceAppSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates attendance IamApplication with correct slug and fields', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();

    expect($app)->not->toBeNull()
        ->and($app->nama)->toBe('Attendance System')
        ->and($app->slug)->toBe('attendance')
        ->and($app->url)->not->toBeEmpty()
        ->and($app->api_key)->toBe('attendance')
        ->and($app->api_secret_hash)->not->toBeEmpty()
        ->and($app->is_active)->toBeTrue();
});

it('generates a valid encrypted api_secret_hash that can be decrypted', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();

    $decryptedSecret = Crypt::decryptString($app->api_secret_hash);

    expect($decryptedSecret)->toBeString()
        ->and(strlen($decryptedSecret))->toBe(64);
});

it('is idempotent and does not create duplicate records', function () {
    $this->seed(AttendanceAppSeeder::class);
    $this->seed(AttendanceAppSeeder::class);

    $count = IamApplication::where('slug', 'attendance')->count();

    expect($count)->toBe(1);
});

it('creates roles pegawai, admin, pimpinan scoped to attendance app', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();

    $roles = IamRole::where('iam_application_id', $app->id)
        ->pluck('slug')
        ->toArray();

    expect($roles)->toContain('pegawai')
        ->toContain('admin')
        ->toContain('pimpinan');
});

it('verifies api_secret using verifySecret method', function () {
    // Kita perlu capture secret saat seeder berjalan
    // Karena seeder menampilkan secret via command output, kita test via model method
    $secret = Str::random(64);

    $app = new IamApplication;
    $app->nama = 'Test App';
    $app->slug = 'test-verify';
    $app->url = 'http://localhost:3000';
    $app->is_active = true;
    $app->is_system = false;
    $app->api_key = 'test-verify';
    $app->api_secret_hash = Crypt::encryptString($secret);
    $app->save();

    expect($app->verifySecret($secret))->toBeTrue()
        ->and($app->verifySecret('wrong-secret'))->toBeFalse();
});

it('IamAuthorizationService returns correct attendance roles for assigned user', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();
    $user = Pegawai::factory()->create();

    // Assign role 'pegawai' ke user
    $pegawaiRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'pegawai')->first();
    IamUserRole::create([
        'user_id' => $user->id,
        'iam_role_id' => $pegawaiRole->id,
        'assigned_at' => now(),
    ]);

    $service = app(IamAuthorizationService::class);
    $roles = $service->getUserRoles($user->id, $app->id);

    expect($roles)->toBe(['pegawai']);
});

it('IamAuthorizationService returns multiple attendance roles when user has several', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();
    $user = Pegawai::factory()->create();

    // Assign role 'admin' dan 'pimpinan' ke user
    $adminRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->first();
    $pimpinanRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'pimpinan')->first();

    IamUserRole::create([
        'user_id' => $user->id,
        'iam_role_id' => $adminRole->id,
        'assigned_at' => now(),
    ]);
    IamUserRole::create([
        'user_id' => $user->id,
        'iam_role_id' => $pimpinanRole->id,
        'assigned_at' => now(),
    ]);

    $service = app(IamAuthorizationService::class);
    $roles = $service->getUserRoles($user->id, $app->id);

    expect($roles)->toContain('admin')
        ->toContain('pimpinan')
        ->toHaveCount(2);
});

it('IamAuthorizationService returns correct permissions for attendance admin role', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();
    $user = Pegawai::factory()->create();

    $adminRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->first();
    IamUserRole::create([
        'user_id' => $user->id,
        'iam_role_id' => $adminRole->id,
        'assigned_at' => now(),
    ]);

    $service = app(IamAuthorizationService::class);
    $permissions = $service->getUserPermissions($user->id, $app->id);

    expect($permissions)->toContain('attendance.view')
        ->toContain('attendance.manage')
        ->toContain('users.view')
        ->toContain('users.manage')
        ->toContain('reports.view')
        ->toContain('reports.generate')
        ->toContain('settings.manage');
});

it('IamAuthorizationService does not return attendance roles for other applications', function () {
    $this->seed(AttendanceAppSeeder::class);

    $app = IamApplication::where('slug', 'attendance')->first();
    $otherApp = IamApplication::factory()->create(['is_active' => true]);

    $user = Pegawai::factory()->create();

    // Assign role attendance ke user
    $pegawaiRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'pegawai')->first();
    IamUserRole::create([
        'user_id' => $user->id,
        'iam_role_id' => $pegawaiRole->id,
        'assigned_at' => now(),
    ]);

    $service = app(IamAuthorizationService::class);

    // Query roles untuk aplikasi lain harus kosong
    $roles = $service->getUserRoles($user->id, $otherApp->id);
    expect($roles)->toBe([]);
});
