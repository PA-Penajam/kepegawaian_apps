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
