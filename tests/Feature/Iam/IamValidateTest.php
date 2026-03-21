<?php

use App\Http\Controllers\Api\IamController;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->iamApp = IamApplication::factory()->create(['is_active' => true]);

    $this->adminRole = IamRole::create([
        'iam_application_id' => $this->iamApp->id,
        'nama' => 'Admin',
        'slug' => 'admin',
        'is_system' => false,
    ]);

    $this->perm = IamPermission::create([
        'iam_application_id' => $this->iamApp->id,
        'nama' => 'Manage Users',
        'slug' => 'users:manage',
        'group' => 'users',
    ]);

    IamRolePermission::create([
        'iam_role_id' => $this->adminRole->id,
        'iam_permission_id' => $this->perm->id,
    ]);
});

test('validate endpoint guest ditolak 401', function () {
    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [IamController::class, 'validate']);

    $this->getJson('/test-iam-validate')->assertUnauthorized();
});

test('validate endpoint tanpa iam signature ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [IamController::class, 'validate']);

    $this->getJson('/test-iam-validate')->assertUnauthorized();
});

test('validate endpoint user tanpa role di aplikasi', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [IamController::class, 'validate']);

    // GET request dengan getJson() mengirim body '[]'
    $ts = now()->timestamp;
    $bodyHash = hash('sha256', '[]');
    $payload = 'GET:/test-iam-validate:'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->getJson('/test-iam-validate', [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJsonPath('roles', [])
        ->assertJsonPath('permissions', []);
});

test('validate endpoint dengan user berrole', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $user->pegawai()->create([
        'nip' => '123456789012345678',
        'nama_lengkap' => 'Test User',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'laki_laki',
        'agama' => 'islam',
        'status_perkawinan' => 'belum_kawin',
        'alamat' => 'Jl. Test No. 1',
        'status_kepegawaian' => 'pns',
        'status_pegawai' => 'aktif',
        'tanggal_masuk' => '2015-01-01',
    ]);

    IamUserRole::create([
        'user_id' => $user->id,
        'iam_role_id' => $this->adminRole->id,
        'assigned_at' => now(),
    ]);

    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [IamController::class, 'validate']);

    // GET request dengan getJson() mengirim body '[]'
    $ts = now()->timestamp;
    $bodyHash = hash('sha256', '[]');
    $payload = 'GET:/test-iam-validate:'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->getJson('/test-iam-validate', [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'nip'],
            'roles',
            'permissions',
            'token_expires_at',
        ])
        ->assertJsonPath('user.name', 'Test User')
        ->assertJsonPath('roles', ['admin'])
        ->assertJsonPath('permissions', ['users:manage']);
});
