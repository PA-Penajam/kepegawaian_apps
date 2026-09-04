<?php

use App\Models\Pegawai;
use App\Models\PegawaiSyncPull;
use App\Models\SyncConsumer;
use App\Services\Iam\SyncConsumerCredentialService;
use App\Services\Sync\SyncConnectionTester;
use Laravel\Sanctum\Sanctum;

function makeAuthUser(): Pegawai
{
    return Pegawai::factory()->admin()->create();
}

beforeEach(function () {
    $this->user = makeAuthUser();
    Sanctum::actingAs($this->user);
});

test('halaman sinkronisasi menampilkan daftar konsumen dan stats', function () {
    SyncConsumer::factory()->count(3)->create();

    $this->get(route('iam.sinkronisasi.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('iam/sinkronisasi/index')
            ->has('konsumen', 3)
            ->where('stats.total_konsumen', 3));
});

test('halaman sinkronisasi tertutup untuk user tanpa permission', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user);

    $this->get(route('iam.sinkronisasi.index'))->assertForbidden();
});

test('konsumen dapat dibuat dan token API diterbitkan', function () {
    $response = $this->post(route('iam.sinkronisasi.store'), [
        'nama' => 'WFA Task',
        'slug' => 'wfa-task',
        'base_url' => 'https://wfa-task.test',
        'deskripsi' => 'Aplikasi pelacakan tugas',
    ]);

    $response->assertRedirect();

    $consumer = SyncConsumer::where('slug', 'wfa-task')->first();
    expect($consumer)->not->toBeNull();

    // Flash berisi plaintext token + HMAC secret per konsumen sekali tampil
    $response->assertSessionHas('sync_token_once', fn ($token) => (
        $token['consumer_slug'] === 'wfa-task'
        && str_starts_with($token['plaintext'], '1|')
        && $token['hmac_secret'] === $consumer->fresh()->hmac_secret
        && $token['hmac_secret'] !== config('kepegawaian.secret_key')
    ));

    // Token Sanctum aktif dengan ability app:kepegawaian
    $token = $consumer->tokens()->first();
    expect($token)->not->toBeNull();
    expect($token->name)->toBe('sync:wfa-task');
    expect($token->abilities)->toContain('app:kepegawaian');
});

test('slug konsumen duplikat ditolak', function () {
    SyncConsumer::factory()->create(['slug' => 'wfa-task']);

    $this->post(route('iam.sinkronisasi.store'), [
        'nama' => 'WFA Task 2',
        'slug' => 'wfa-task',
    ])->assertSessionHasErrors('slug');
});

test('konsumen dapat diperbarui', function () {
    $consumer = SyncConsumer::factory()->create(['nama' => 'Nama Lama']);

    $this->put(route('iam.sinkronisasi.update', $consumer), [
        'nama' => 'Nama Baru',
        'slug' => $consumer->slug,
        'is_active' => false,
    ])->assertRedirect();

    expect($consumer->fresh()->nama)->toBe('Nama Baru');
    expect($consumer->fresh()->is_active)->toBeFalse();
});

test('konsumen dapat dihapus via soft delete', function () {
    $consumer = SyncConsumer::factory()->create();

    $this->delete(route('iam.sinkronisasi.destroy', $consumer))->assertRedirect();

    expect(SyncConsumer::where('id', $consumer->id)->exists())->toBeFalse();
    expect(SyncConsumer::withTrashed()->where('id', $consumer->id)->exists())->toBeTrue();
});

test('konsumen tanpa token tidak bisa lulus uji koneksi', function () {
    $consumer = SyncConsumer::factory()->create();

    $result = app(SyncConnectionTester::class)->test($consumer);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('belum memiliki token');
});

test('token konsumen dapat diregenerasi dan yang lama ter-revoke', function () {
    $consumer = SyncConsumer::factory()->create();
    $service = app(SyncConsumerCredentialService::class);

    $first = $service->issueToken($consumer);
    $oldTokenId = $consumer->tokens()->first()->id;

    $second = $service->regenerateToken($consumer);

    expect($second['plaintext'])->not->toBe($first['plaintext']);
    expect($consumer->tokens()->count())->toBe(1);
    expect($consumer->tokens()->first()->id)->not->toBe($oldTokenId);
});

test('regenerasi via controller menampilkan token + HMAC secret sekali', function () {
    $consumer = SyncConsumer::factory()->create(['slug' => 'wfa-task']);

    $response = $this->post(route('iam.sinkronisasi.regenerate-token', $consumer));

    $response->assertRedirect();
    $response->assertSessionHas('sync_token_once', fn ($token) => (
        $token['consumer_slug'] === 'wfa-task'
        && str_starts_with($token['plaintext'], '1|')
        && $token['hmac_secret'] === ''
    ));
    expect($consumer->tokens()->count())->toBe(1);
});

test('token konsumen dapat di-revoke', function () {
    $consumer = SyncConsumer::factory()->create();
    $service = app(SyncConsumerCredentialService::class);

    $service->issueToken($consumer);
    expect($consumer->tokens()->count())->toBe(1);

    $service->revokeToken($consumer);
    expect($consumer->tokens()->count())->toBe(0);
});

test('pull di-record dengan identitas konsumen dari token sync', function () {
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'wfa-task',
        'nama' => 'WFA Task',
    ]);

    $service = app(SyncConsumerCredentialService::class);
    $issued = $service->issueToken($consumer);

    // Simulasikan request ke endpoint sync dengan token konsumen
    $params = ['page' => 1, 'per_page' => 5];
    $path = '/api/v1/pegawai/sync';
    $queryString = http_build_query(collect($params)->sortKeys()->all());
    $timestamp = now()->timestamp;
    $payload = 'GET:'.$path.':'.$queryString.':'.hash('sha256', '[]').':'.$timestamp;
    $signature = hash_hmac('sha256', $payload, config('kepegawaian.secret_key'));

    $plain = substr($issued['plaintext'], 0);

    // Reset guard agar auth:sanctum me-resolve token sungguhan dari header,
    // bukan TransientToken dari Sanctum::actingAs() di beforeEach.
    $this->app['auth']->forgetGuards();

    $response = $this->withToken($plain)
        ->withHeaders([
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ])
        ->getJson('/api/v1/pegawai/sync?'.http_build_query($params));

    $response->assertOk();

    $latest = PegawaiSyncPull::latest('pulled_at')->first();
    expect($latest)->not->toBeNull();
    expect($latest->sync_consumer_id)->toBe($consumer->id);
    expect($latest->token_name)->toBe('sync:wfa-task');

    $pull = $consumer->pulls()->latest('pulled_at')->first();
    expect($pull)->not->toBeNull();
    expect($pull->token_name)->toBe('sync:wfa-task');

    $consumer->refresh();
    expect($consumer->last_pull_at)->not->toBeNull();
    expect($consumer->last_pull_status)->toBe('success');
});
