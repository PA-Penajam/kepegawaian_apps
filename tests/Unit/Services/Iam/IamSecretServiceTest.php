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
    $this->app = new IamApplication([
        'nama' => 'Test App',
        'slug' => 'test-secret-svc',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);
    $this->app->api_key = 'iam_initial_key_for_test';
    $this->app->api_secret_hash = Crypt::encryptString('initial-secret');
    $this->app->is_system = false;
    $this->app->save();
});

test('generateAndStore creates new credentials, persists hash, caches plaintext', function () {
    $plaintext = $this->service->generateAndStore($this->app);

    $this->app->refresh();

    // Plaintext returned harus 64 char
    expect($plaintext)->toBeString()->toHaveLength(64);

    // api_key di DB harus berubah (bukan initial key)
    expect($this->app->api_key)->not->toBe('iam_initial_key_for_test');
    expect($this->app->api_key)->toStartWith('iam_');

    // Plaintext bisa di-decrypt dari hash dan sama dengan plaintext
    $decrypted = Crypt::decryptString($this->app->api_secret_hash);
    expect($decrypted)->toBe($plaintext);

    // Cache berisi plaintext yang sama
    $cached = Cache::get("iam:secret:recovery:{$this->app->id}");
    expect($cached)->toBe($plaintext);
});
