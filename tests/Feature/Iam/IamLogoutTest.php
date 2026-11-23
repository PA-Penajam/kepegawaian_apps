<?php

use App\Enums\StatusPegawai;
use App\Http\Controllers\Api\IamController;
use App\Models\IamApplication;
use App\Models\Pegawai;
use App\Models\SyncConsumer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->iamApp = IamApplication::factory()->create(['is_active' => true]);

    Route::middleware(['auth:sanctum', 'iam.signature'])->group(function () {
        Route::post('/test-iam-logout', [IamController::class, 'logout']);
    });
});

function buildIamLogoutHeaders(IamApplication $app, string $path, array $body = [], ?string $bearerToken = null): array
{
    $ts = now()->timestamp;
    $bodyHash = hash('sha256', json_encode($body));
    $payload = 'POST:'.$path.'::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($app->api_secret_hash));

    $headers = [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
    ];

    if ($bearerToken !== null) {
        $headers['Authorization'] = 'Bearer '.$bearerToken;
    }

    return $headers;
}

test('logout endpoint menghapus token dan mengembalikan message', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ["app:{$this->iamApp->slug}"]);

    $response = $this->postJson(
        '/test-iam-logout',
        [],
        buildIamLogoutHeaders($this->iamApp, '/test-iam-logout'),
    );

    $response->assertOk()
        ->assertJsonPath('message', 'Token invalidated');
});

test('logout endpoint mencabut token personal access milik pegawai untuk aplikasi terkait', function () {
    $user = Pegawai::factory()->create();
    $issued = $user->createToken("sso:{$this->iamApp->slug}", ["app:{$this->iamApp->slug}"]);

    $this->postJson(
        '/api/v1/iam/logout',
        [],
        buildIamLogoutHeaders($this->iamApp, '/api/v1/iam/logout', [], $issued->plainTextToken),
    )
        ->assertOk()
        ->assertJsonPath('message', 'Token invalidated');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $issued->accessToken->id,
    ]);
});

test('logout endpoint menolak Bearer token SyncConsumer dengan 403 tanpa menghapus token', function () {
    $consumer = SyncConsumer::factory()->create(['is_active' => true]);
    $syncToken = $consumer->createToken('sync', ['sync:pegawai']);

    $this->postJson(
        '/api/v1/iam/logout',
        [],
        buildIamLogoutHeaders($this->iamApp, '/api/v1/iam/logout', [], $syncToken->plainTextToken),
    )->assertForbidden();

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $syncToken->accessToken->id,
    ]);
});

test('logout endpoint menolak token pegawai milik aplikasi lain dengan 403 tanpa menghapus token', function () {
    $user = Pegawai::factory()->create();
    $otherApp = IamApplication::factory()->create(['is_active' => true]);
    $otherToken = $user->createToken("sso:{$otherApp->slug}", ["app:{$otherApp->slug}"]);

    $this->postJson(
        '/api/v1/iam/logout',
        [],
        buildIamLogoutHeaders($this->iamApp, '/api/v1/iam/logout', [], $otherToken->plainTextToken),
    )->assertForbidden();

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherToken->accessToken->id,
    ]);
});

test('logout endpoint menolak pegawai nonaktif dengan 403 tanpa menghapus token', function () {
    $user = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Pensiun->value,
    ]);
    $token = $user->createToken("sso:{$this->iamApp->slug}", ["app:{$this->iamApp->slug}"]);

    $this->postJson(
        '/api/v1/iam/logout',
        [],
        buildIamLogoutHeaders($this->iamApp, '/api/v1/iam/logout', [], $token->plainTextToken),
    )->assertForbidden();

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

test('logout endpoint guest ditolak 401', function () {
    $this->postJson('/test-iam-logout')->assertUnauthorized();
});
