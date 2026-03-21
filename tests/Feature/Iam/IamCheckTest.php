<?php

use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;

it('mengembalikan allowed:true untuk user dengan permission yang diminta', function () {
    $user = User::factory()->create();
    $app = \App\Models\IamApplication::factory()->create(['is_active' => true]);

    $permission = $app->permissions()->create(['nama' => 'Manage Pegawai', 'slug' => 'manage-pegawai']);
    $role = $app->roles()->create(['nama' => 'Admin', 'slug' => 'admin-check-test']);
    $role->permissions()->attach($permission);
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $role->id, 'assigned_at' => now()]);

    Sanctum::actingAs($user, ['*']);

    // GET request dengan getJson() mengirim body '[]'
    $ts = now()->timestamp;
    $bodyHash = hash('sha256', '[]');
    $qs = 'permission=manage-pegawai';
    $payload = 'GET:/api/v1/iam/check:'.$qs.':'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($app->api_secret_hash));

    $response = $this->getJson('/api/v1/iam/check?permission=manage-pegawai', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(200)->assertJson(['allowed' => true, 'permission' => 'manage-pegawai']);
});

it('mengembalikan allowed:false untuk user tanpa permission', function () {
    $user = User::factory()->create();
    $app = \App\Models\IamApplication::factory()->create(['is_active' => true]);

    Sanctum::actingAs($user, ['*']);

    $ts = now()->timestamp;
    $bodyHash = hash('sha256', '[]');
    $qs = 'permission=admin-only';
    $payload = 'GET:/api/v1/iam/check:'.$qs.':'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($app->api_secret_hash));

    $response = $this->getJson('/api/v1/iam/check?permission=admin-only', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(200)->assertJson(['allowed' => false]);
});

it('menolak request tanpa token auth (401)', function () {
    $app = \App\Models\IamApplication::factory()->create(['is_active' => true]);

    $ts = now()->timestamp;
    $bodyHash = hash('sha256', '[]');
    $qs = 'permission=any';
    $payload = 'GET:/api/v1/iam/check:'.$qs.':'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($app->api_secret_hash));

    $response = $this->getJson('/api/v1/iam/check?permission=any', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(401);
});
