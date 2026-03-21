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

// Helper untuk membuat IAM signed headers
if (! function_exists('makeIamHeaders')) {
    function makeIamHeaders(string $method, string $path, string $apiKey, string $apiSecret, array $query = []): array
{
    $timestamp   = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $payload     = strtoupper($method) . ':' . $path . ':' . $queryString . ':' . $timestamp;
    $signature   = hash_hmac('sha256', $payload, $apiSecret);

    return [
        'X-App-Key'   => $apiKey,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'Accept'      => 'application/json',
    ];
    }
}

beforeEach(function () {
    $this->iamApp = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test-app',
        'url'             => 'http://test.local',
        'api_key'         => 'test-api-key',
        'api_secret_hash' => Crypt::encryptString('test-secret-64chars-padding-here-abc123'),
        'is_active'       => true,
        'is_system'       => false,
    ]);

    $this->adminRole = IamRole::create([
        'iam_application_id' => $this->iamApp->id,
        'nama'               => 'Admin',
        'slug'               => 'admin',
        'is_system'          => false,
    ]);

    $this->perm = IamPermission::create([
        'iam_application_id' => $this->iamApp->id,
        'nama'               => 'Manage Users',
        'slug'               => 'users:manage',
        'group'              => 'users',
    ]);

    IamRolePermission::create([
        'iam_role_id'       => $this->adminRole->id,
        'iam_permission_id' => $this->perm->id,
    ]);
});

test('validate endpoint guest ditolak 401', function () {
    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [App\Http\Controllers\Api\IamController::class, 'validate']);

    $this->getJson('/test-iam-validate')->assertUnauthorized();
});

test('validate endpoint tanpa iam signature ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [App\Http\Controllers\Api\IamController::class, 'validate']);

    $this->getJson('/test-iam-validate')->assertUnauthorized();
});

test('validate endpoint user tanpa role di aplikasi', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [App\Http\Controllers\Api\IamController::class, 'validate']);

    $headers = makeIamHeaders('GET', '/test-iam-validate', $this->iamApp->api_key, 'test-secret-64chars-padding-here-abc123');

    $response = $this->getJson('/test-iam-validate', $headers);

    $response->assertOk()
        ->assertJsonPath('roles', [])
        ->assertJsonPath('permissions', []);
});

test('validate endpoint dengan user berrole', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $user->pegawai()->create([
        'nip'              => '123456789012345678',
        'nama_lengkap'     => 'Test User',
        'tempat_lahir'     => 'Jakarta',
        'tanggal_lahir'    => '1990-01-01',
        'jenis_kelamin'    => 'laki_laki',
        'agama'            => 'islam',
        'status_perkawinan'=> 'belum_kawin',
        'alamat'           => 'Jl. Test No. 1',
        'status_kepegawaian' => 'pns',
        'status_pegawai'   => 'aktif',
        'tanggal_masuk'    => '2015-01-01',
    ]);

    IamUserRole::create([
        'user_id'      => $user->id,
        'iam_role_id'  => $this->adminRole->id,
        'assigned_at'  => now(),
    ]);

    Sanctum::actingAs($user);

    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-validate', [App\Http\Controllers\Api\IamController::class, 'validate']);

    $headers = makeIamHeaders('GET', '/test-iam-validate', $this->iamApp->api_key, 'test-secret-64chars-padding-here-abc123');

    $response = $this->getJson('/test-iam-validate', $headers);

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
