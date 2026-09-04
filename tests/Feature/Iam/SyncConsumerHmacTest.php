<?php

use App\Models\Pegawai;
use App\Models\SyncConsumer;
use App\Services\Iam\SyncConsumerCredentialService;
use App\Services\Sync\SyncConnectionTester;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = Pegawai::factory()->admin()->create();
    Sanctum::actingAs($this->user);
});

/**
 * Bangun header bertanda tangan untuk GET /api/v1/pegawai/sync.
 * Body request getJson() adalah '[]' sehingga body hash mengikuti itu.
 *
 * @return array{timestamp: string, signature: string, query: string}
 */
function hmacSignedHeaders(string $secret, array $params): array
{
    $query = http_build_query(collect($params)->sortKeys()->all());
    $timestamp = (string) now()->timestamp;
    $payload = 'GET:/api/v1/pegawai/sync:'.$query.':'.hash('sha256', '[]').':'.$timestamp;

    return [
        'timestamp' => $timestamp,
        'signature' => hash_hmac('sha256', $payload, $secret),
        'query' => $query,
    ];
}

function hmacGetJson($testCase, string $tokenPlain, string $secret, array $params = ['page' => 1, 'per_page' => 5])
{
    $signed = hmacSignedHeaders($secret, $params);

    // Reset guard agar auth:sanctum me-resolve token sungguhan dari header.
    auth()->forgetGuards();

    return $testCase->withToken($tokenPlain)->withHeaders([
        'X-Timestamp' => $signed['timestamp'],
        'X-Signature' => $signed['signature'],
        'Accept' => 'application/json',
    ])->getJson('/api/v1/pegawai/sync?'.$signed['query']);
}

test('konsumen baru mendapat hmac secret unik per konsumen', function () {
    $this->post(route('iam.sinkronisasi.store'), [
        'nama' => 'WFA Task',
        'slug' => 'wfa-task',
    ])->assertRedirect();

    $this->post(route('iam.sinkronisasi.store'), [
        'nama' => 'Absensi QR',
        'slug' => 'absensi-qr',
    ])->assertRedirect();

    $a = SyncConsumer::where('slug', 'wfa-task')->firstOrFail()->fresh();
    $b = SyncConsumer::where('slug', 'absensi-qr')->firstOrFail()->fresh();

    // Secret terisi, format 64 hex, dan berbeda antar konsumen
    expect($a->hmac_secret)->toBeString()
        ->and(strlen($a->hmac_secret))->toBe(64)
        ->and(ctype_xdigit($a->hmac_secret))->toBeTrue()
        ->and($a->hmac_secret)->not->toBe($b->hmac_secret);

    // Tersimpan terenkripsi saat istirahat (bukan plaintext di DB)
    $raw = DB::table('sync_consumers')->where('id', $a->id)->value('hmac_secret');
    expect($raw)->not->toBeNull()->and($raw)->not->toBe($a->hmac_secret);

    // Tidak bocor lewat serialisasi (props Inertia halaman index)
    expect($a->toArray())->not->toHaveKey('hmac_secret');
});

test('middleware menerima signature secret per konsumen', function () {
    $consumer = SyncConsumer::factory()->create(['slug' => 'wfa-task']);
    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $issued = $service->issueToken($consumer);

    hmacGetJson($this, $issued['plaintext'], $secret)->assertOk();
});

test('middleware menolak signature silang antar konsumen', function () {
    $a = SyncConsumer::factory()->create(['slug' => 'client-a']);
    $b = SyncConsumer::factory()->create(['slug' => 'client-b']);
    $service = app(SyncConsumerCredentialService::class);
    $secretA = $service->ensureHmacSecret($a);
    $secretB = $service->ensureHmacSecret($b);
    $issuedA = $service->issueToken($a);

    expect($secretA)->not->toBe($secretB);

    // Token milik A tapi signature memakai secret milik B → 401
    hmacGetJson($this, $issuedA['plaintext'], $secretB)->assertUnauthorized();
    // Pasangan yang benar tetap lolos
    hmacGetJson($this, $issuedA['plaintext'], $secretA)->assertOk();
});

test('konsumen lawas tanpa secret masih bisa pakai secret global', function () {
    $consumer = SyncConsumer::factory()->create(['slug' => 'legacy-app']);
    $consumer->forceFill(['hmac_secret' => null])->save();

    $issued = app(SyncConsumerCredentialService::class)->issueToken($consumer);

    hmacGetJson($this, $issued['plaintext'], config('kepegawaian.secret_key'))->assertOk();
});

test('regenerasi secret memutar secret tanpa mengubah token', function () {
    $consumer = SyncConsumer::factory()->create(['slug' => 'wfa-task']);
    $service = app(SyncConsumerCredentialService::class);
    $oldSecret = $service->ensureHmacSecret($consumer);
    $issued = $service->issueToken($consumer);
    $tokenId = $consumer->tokens()->first()->id;

    $response = $this->post(route('iam.sinkronisasi.regenerate-secret', $consumer));

    $response->assertRedirect();
    $response->assertSessionHas('sync_token_once', fn ($token) => (
        $token['consumer_slug'] === 'wfa-task'
        && $token['hmac_secret'] !== '' && $token['hmac_secret'] !== $oldSecret
    ));

    $consumer->refresh();
    $newSecret = $consumer->hmac_secret;

    // Token tidak tersentuh
    expect($consumer->tokens()->count())->toBe(1)
        ->and($consumer->tokens()->first()->id)->toBe($tokenId);

    // Secret lama mati, secret baru hidup
    hmacGetJson($this, $issued['plaintext'], $oldSecret)->assertUnauthorized();
    hmacGetJson($this, $issued['plaintext'], $newSecret)->assertOk();
});

test('regenerasi token tidak mengubah hmac secret', function () {
    $consumer = SyncConsumer::factory()->create(['slug' => 'wfa-task']);
    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $service->issueToken($consumer);

    $this->post(route('iam.sinkronisasi.regenerate-token', $consumer))->assertRedirect();

    expect($consumer->fresh()->hmac_secret)->toBe($secret);
});

test('penguji koneksi memakai secret per konsumen dan membersihkan token sementara', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $consumer = SyncConsumer::factory()->create(['slug' => 'wfa-task']);
    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $service->issueToken($consumer);

    $result = app(SyncConnectionTester::class)->test($consumer->fresh());

    expect($result['success'])->toBeTrue();
    // Token sementara uji koneksi dibersihkan, token asli tetap satu
    expect($consumer->tokens()->where('name', 'sync-test')->count())->toBe(0)
        ->and($consumer->tokens()->count())->toBe(1);

    // Request keluar memakai Bearer + signature dari secret per konsumen
    Http::assertSent(function ($request) use ($secret) {
        $auth = $request->header('Authorization')[0] ?? '';

        if (! str_starts_with($auth, 'Bearer ')) {
            return false;
        }

        $timestamp = $request->header('X-Timestamp')[0] ?? '';
        $signature = $request->header('X-Signature')[0] ?? '';
        $parts = parse_url($request->url());
        parse_str($parts['query'] ?? '', $query);
        ksort($query);
        $queryString = http_build_query($query);
        $payload = 'GET:'.$parts['path'].':'.$queryString.':'.hash('sha256', '').':'.$timestamp;

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    });
});
