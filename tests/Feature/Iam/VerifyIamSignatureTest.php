<?php

use App\Models\IamApplication;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-signature', fn () => response()->json(['ok' => true]));
});

test('request tanpa X-App-Key ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature')->assertStatus(401);
});

test('request dengan api_key tidak dikenal ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => 'unknown-key',
        'X-Signature' => 'anything',
        'X-Timestamp' => now()->timestamp,
    ])->assertStatus(401);
});

test('request dengan signature salah ditolak 401', function () {
    $user = Pegawai::factory()->create();
    $secret = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app = IamApplication::create([
        'nama' => 'Test App',
        'slug' => 'test',
        'url' => 'http://test.local',
        'api_key' => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    // Buat signature dengan secret yang salah
    $ts = now()->timestamp;
    $payload = 'GET:/test-iam-signature:::'.$ts;
    $wrongSignature = hash_hmac('sha256', $payload, 'wrong-secret');

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $wrongSignature,
        'X-Timestamp' => $ts,
    ])->assertStatus(401);
});

test('request dengan timestamp kedaluwarsa ditolak 401', function () {
    $user = Pegawai::factory()->create();
    $secret = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app = IamApplication::create([
        'nama' => 'Test App',
        'slug' => 'test',
        'url' => 'http://test.local',
        'api_key' => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $oldTimestamp = now()->subMinutes(6)->timestamp;
    $payload = 'GET:/test-iam-signature:::'.$oldTimestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $oldTimestamp,
    ])->assertStatus(401);
});

test('request valid dengan signature benar lolos', function () {
    $user = Pegawai::factory()->create();
    $secret = 'correct-secret-64chars-padding-here-abc123def456ghi789';

    // Buat application dengan manual assignment karena field tidak fillable
    $app = new IamApplication([
        'nama' => 'Test App',
        'slug' => 'test',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);
    $app->api_key = 'valid-key-123';
    $app->api_secret_hash = Crypt::encryptString($secret);
    $app->is_system = false;
    $app->save();

    Sanctum::actingAs($user);

    // Buat signature manual dengan body hash
    // Note: get() mengirim body kosong, jadi body hash = hash('sha256', '')
    $ts = now()->timestamp;
    $bodyHash = hash('sha256', '');
    // Format: METHOD:PATH:SORTED_QUERY:BODY_HASH:TIMESTAMP
    $payload = 'GET:/test-iam-signature::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, $secret);

    // Gunakan get() dengan Accept header alih-alih getJson()
    $response = $this->withHeaders([
        'X-App-Key' => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ])->get('/test-iam-signature');

    $response->assertOk();
});

test('HMAC verification failure logs hmac.verification_failed event with reason', function () {
    $user = Pegawai::factory()->create();
    $secret = 'correct-secret-64chars-padding-here-abc123def456ghi789';

    $app = new IamApplication([
        'nama' => 'Test App HMAC Audit',
        'slug' => 'test-hmac-audit',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);
    $app->api_key = 'audit-test-key';
    $app->api_secret_hash = Crypt::encryptString($secret);
    $app->is_system = false;
    $app->save();

    Sanctum::actingAs($user);

    $ts = now()->timestamp;
    $payload = 'GET:/test-iam-signature:::'.$ts;
    $wrongSignature = hash_hmac('sha256', $payload, 'wrong-secret');

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $wrongSignature,
        'X-Timestamp' => $ts,
    ])->assertStatus(401);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'hmac.verification_failed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reason'])->toBe('signature_mismatch');
    expect($activity->properties['path'])->toBe('/test-iam-signature');
    expect($activity->properties['method'])->toBe('GET');
});

test('HMAC failure dengan api_key tidak terdaftar log reason=app_not_found', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => 'nonexistent-key',
        'X-Signature' => 'whatever',
        'X-Timestamp' => now()->timestamp,
    ])->assertStatus(401);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'hmac.verification_failed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reason'])->toBe('app_not_found');
});

test('HMAC failure missing header log reason=missing_header', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature')->assertStatus(401);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'hmac.verification_failed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reason'])->toBe('missing_header');
});
