<?php

use App\Models\IamApplication;
use App\Services\Iam\IamSecretService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Override cache store ke database agar getRecoveryTtlSeconds bisa query tabel cache
    config(['cache.default' => 'database']);

    $this->service = app(IamSecretService::class);

    // Buat aplikasi test (api_key & api_secret_hash tidak fillable)
    // PENTING: Jangan gunakan $this->app karena itu reserved oleh TestCase (Application container)
    $this->iamApp = new IamApplication([
        'nama' => 'Test App',
        'slug' => 'test-secret-svc',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);
    $this->iamApp->api_key = 'iam_initial_key_for_test';
    $this->iamApp->api_secret_hash = Crypt::encryptString('initial-secret');
    $this->iamApp->is_system = false;
    $this->iamApp->save();
});

test('generateAndStore creates new credentials, persists hash, caches plaintext', function () {
    $plaintext = $this->service->generateAndStore($this->iamApp);

    $this->iamApp->refresh();

    // Plaintext returned harus 64 char
    expect($plaintext)->toBeString()->toHaveLength(64);

    // api_key di DB harus berubah (bukan initial key)
    expect($this->iamApp->api_key)->not->toBe('iam_initial_key_for_test');
    expect($this->iamApp->api_key)->toStartWith('iam_');

    // Plaintext bisa di-decrypt dari hash dan sama dengan plaintext
    $decrypted = Crypt::decryptString($this->iamApp->api_secret_hash);
    expect($decrypted)->toBe($plaintext);

    // Cache berisi plaintext yang sama
    $cached = Cache::get("iam:secret:recovery:{$this->iamApp->id}");
    expect($cached)->toBe($plaintext);
});

test('regenerate overwrites existing credentials and old cache key', function () {
    Cache::put("iam:secret:recovery:{$this->iamApp->id}", 'OLD_PLAINTEXT', now()->addMinutes(15));

    $oldKey = $this->iamApp->api_key;
    $newPlaintext = $this->service->regenerate($this->iamApp);

    $this->iamApp->refresh();

    expect($this->iamApp->api_key)->not->toBe($oldKey);

    $cached = Cache::get("iam:secret:recovery:{$this->iamApp->id}");
    expect($cached)->toBe($newPlaintext);
    expect($cached)->not->toBe('OLD_PLAINTEXT');
});

test('regenerate logs activity with previous_key_prefix', function () {
    $oldKey = $this->iamApp->api_key;
    $expectedPrefix = substr($oldKey, 0, 8);

    $this->service->regenerate($this->iamApp);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.regenerated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['previous_key_prefix'])->toBe($expectedPrefix);
    expect($activity->properties['app_slug'])->toBe($this->iamApp->slug);
});

test('recoverFromCache returns plaintext when cache hit and logs viewed event', function () {
    Cache::put("iam:secret:recovery:{$this->iamApp->id}", 'CACHED_SECRET_PLAINTEXT', now()->addMinutes(15));

    $result = $this->service->recoverFromCache($this->iamApp);

    expect($result)->toBe('CACHED_SECRET_PLAINTEXT');

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_viewed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['app_slug'])->toBe($this->iamApp->slug);
});

test('recoverFromCache returns null when cache miss and does not log', function () {
    Cache::forget("iam:secret:recovery:{$this->iamApp->id}");

    $result = $this->service->recoverFromCache($this->iamApp);

    expect($result)->toBeNull();

    $activityCount = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_viewed')
        ->count();

    expect($activityCount)->toBe(0);
});

test('recoverFromCache idempotent: cache tidak hilang setelah view', function () {
    Cache::put("iam:secret:recovery:{$this->iamApp->id}", 'STAYS_VISIBLE', now()->addMinutes(15));

    $first = $this->service->recoverFromCache($this->iamApp);
    $second = $this->service->recoverFromCache($this->iamApp);

    expect($first)->toBe('STAYS_VISIBLE');
    expect($second)->toBe('STAYS_VISIBLE');
});

test('invalidateRecovery removes cache and logs acknowledged event', function () {
    Cache::put("iam:secret:recovery:{$this->iamApp->id}", 'TO_BE_REMOVED', now()->addMinutes(15));

    $this->service->invalidateRecovery($this->iamApp);

    expect(Cache::has("iam:secret:recovery:{$this->iamApp->id}"))->toBeFalse();

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_acknowledged')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
});

test('invalidateRecovery is idempotent when cache already empty', function () {
    Cache::forget("iam:secret:recovery:{$this->iamApp->id}");

    $this->service->invalidateRecovery($this->iamApp);

    expect(Cache::has("iam:secret:recovery:{$this->iamApp->id}"))->toBeFalse();
});

test('hasRecoverableSecret returns true when cache exists, false when empty', function () {
    Cache::forget("iam:secret:recovery:{$this->iamApp->id}");
    expect($this->service->hasRecoverableSecret($this->iamApp))->toBeFalse();

    Cache::put("iam:secret:recovery:{$this->iamApp->id}", 'X', now()->addMinutes(15));
    expect($this->service->hasRecoverableSecret($this->iamApp))->toBeTrue();
});

test('getRecoveryTtlSeconds returns positive int when cache exists, 0 when miss', function () {
    Cache::forget("iam:secret:recovery:{$this->iamApp->id}");
    expect($this->service->getRecoveryTtlSeconds($this->iamApp))->toBe(0);

    Cache::put("iam:secret:recovery:{$this->iamApp->id}", 'X', now()->addMinutes(15));
    $ttl = $this->service->getRecoveryTtlSeconds($this->iamApp);

    expect($ttl)->toBeGreaterThan(0);
    expect($ttl)->toBeLessThanOrEqual(900);
});
