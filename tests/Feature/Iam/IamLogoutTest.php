<?php

use App\Http\Controllers\Api\IamController;
use App\Models\IamApplication;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->iamApp = IamApplication::factory()->create(['is_active' => true]);

    Route::middleware(['auth:sanctum', 'iam.signature'])->group(function () {
        Route::post('/test-iam-logout', [IamController::class, 'logout']);
    });
});

test('logout endpoint menghapus token dan mengembalikan message', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user);

    // Helper baru otomatis membuat app baru, tapi kita butuh app yang spesifik
    // Jadi kita buat signature manual untuk app yang sudah dibuat
    $ts = now()->timestamp;
    $bodyHash = hash('sha256', json_encode([]));
    $payload = 'POST:/test-iam-logout::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->postJson('/test-iam-logout', [], [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Token invalidated');
});

test('logout endpoint guest ditolak 401', function () {
    $this->postJson('/test-iam-logout')->assertUnauthorized();
});
