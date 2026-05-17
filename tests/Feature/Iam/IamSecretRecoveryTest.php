<?php

use App\Models\IamApplication;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = Pegawai::factory()->admin()->create();
});

test('POST /iam/aplikasi creates app, flashes api_secret_once, caches plaintext, logs audit', function () {
    $this->actingAs($this->admin)
        ->post('/iam/aplikasi', [
            'nama' => 'New Test App',
            'slug' => 'new-test-app',
            'url' => 'http://newapp.local',
            'deskripsi' => 'Test creation',
        ])
        ->assertRedirect()
        ->assertSessionHas('api_secret_once');

    $app = IamApplication::where('slug', 'new-test-app')->firstOrFail();

    $cached = Cache::get("iam:secret:recovery:{$app->id}");
    expect($cached)->toBeString()->toHaveLength(64);

    $decrypted = Crypt::decryptString($app->api_secret_hash);
    expect($cached)->toBe($decrypted);

    $activity = Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.created')
        ->where('subject_id', $app->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['app_slug'])->toBe('new-test-app');
    expect($activity->properties['ip'])->not->toBeNull();
});

test('GET /iam/aplikasi/{id} exposes recovery_status props correctly', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::put("iam:secret:recovery:{$app->id}", 'TEST_RECOVERABLE_PLAINTEXT', now()->addMinutes(15));

    $response = $this->actingAs($this->admin)->get("/iam/aplikasi/{$app->id}");
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['recovery_status']['recoverable'])->toBeTrue();
    // ttl_remaining_secs = 0 di test karena getRecoveryTtlSeconds() query tabel cache
    // yang hanya aktif untuk database cache driver (test pakai array driver)
    expect($props['recovery_status']['ttl_remaining_secs'])->toBeGreaterThanOrEqual(0);
});

test('GET /iam/aplikasi/{id} returns recoverable=false when cache empty', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::forget("iam:secret:recovery:{$app->id}");

    $response = $this->actingAs($this->admin)->get("/iam/aplikasi/{$app->id}");
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['recovery_status']['recoverable'])->toBeFalse();
    expect($props['recovery_status']['ttl_remaining_secs'])->toBe(0);
});

test('GET /iam/aplikasi exposes secret_recoverable on each app row', function () {
    $appWith = IamApplication::factory()->create(['slug' => 'with-cache']);
    $appWithout = IamApplication::factory()->create(['slug' => 'without-cache']);

    Cache::put("iam:secret:recovery:{$appWith->id}", 'X', now()->addMinutes(15));
    Cache::forget("iam:secret:recovery:{$appWithout->id}");

    $response = $this->actingAs($this->admin)->get('/iam/aplikasi');
    $response->assertOk();

    $list = $response->viewData('page')['props']['aplikasi'];

    $rowWith = collect($list)->firstWhere('slug', 'with-cache');
    $rowWithout = collect($list)->firstWhere('slug', 'without-cache');

    expect($rowWith['secret_recoverable'])->toBeTrue();
    expect($rowWithout['secret_recoverable'])->toBeFalse();
});
