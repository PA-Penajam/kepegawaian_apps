<?php

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Crypt;

test('GET /sso/login tanpa app param mengembalikan 422', function () {
    $this->get('/sso/login')->assertStatus(422);
});

test('GET /sso/login dengan app tidak dikenal mengembalikan 404', function () {
    $this->get('/sso/login?app=unknown&redirect=http://test.local/callback')
        ->assertStatus(404);
});

test('GET /sso/login user belum login diredirect ke SSO eksternal', function () {
    IamApplication::create([
        'nama' => 'Test', 'slug' => 'test', 'url' => 'http://test.local',
        'api_key' => 'k', 'api_secret_hash' => Crypt::encryptString('s'),
    ]);

    $this->get('/sso/login?app=test&redirect=http://test.local/callback')
        ->assertRedirect(route('auth.sso.login'));
});

test('GET /sso/login user sudah login generate SSO code dan redirect', function () {
    IamApplication::create([
        'nama' => 'Attendance', 'slug' => 'attendance', 'url' => 'http://att.local',
        'api_key' => 'att-key', 'api_secret_hash' => Crypt::encryptString('att-secret'),
    ]);

    $user = Pegawai::factory()->create();

    $response = $this->actingAs($user)
        ->get('/sso/login?app=attendance&redirect=http://att.local/callback');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('http://att.local/callback');
    expect($location)->toContain('?code=');

    $code = IamSsoCode::where('user_id', $user->id)->first();
    expect($code)->not->toBeNull();
    expect($code->app_slug)->toBe('attendance');
    expect($code->isValid())->toBeTrue();
});

test('GET /sso/login menolak redirect ke domain lain (open redirect prevention)', function () {
    IamApplication::create([
        'nama' => 'Attendance', 'slug' => 'attendance', 'url' => 'http://att.local',
        'api_key' => 'att-key', 'api_secret_hash' => Crypt::encryptString('att-secret'),
    ]);

    $user = Pegawai::factory()->create();

    // Redirect ke domain berbeda dari app.url — harus ditolak
    $this->actingAs($user)
        ->get('/sso/login?app=attendance&redirect=http://evil.com/steal')
        ->assertStatus(422);
});

it('menolak open redirect via subdomain spoofing', function () {
    $app = IamApplication::factory()->create(['slug' => 'att-app', 'url' => 'http://att.local', 'is_active' => true]);
    $user = Pegawai::factory()->create();

    $response = $this->actingAs($user)->get('/sso/login?app=att-app&redirect=http://att.local.evil.com/steal');
    $response->assertStatus(422);
});

it('menolak open redirect via URL authority confusion', function () {
    $app = IamApplication::factory()->create(['slug' => 'att-app', 'url' => 'http://att.local', 'is_active' => true]);
    $user = Pegawai::factory()->create();

    $response = $this->actingAs($user)->get('/sso/login?app=att-app&redirect=http://att.local@evil.com/steal');
    $response->assertStatus(422);
});

it('mengizinkan redirect ke subdirectory host yang sama', function () {
    $app = IamApplication::factory()->create(['slug' => 'att-app', 'url' => 'http://att.local', 'is_active' => true]);
    $user = Pegawai::factory()->create();

    $response = $this->actingAs($user)->get('/sso/login?app=att-app&redirect=http://att.local/callback');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('http://att.local/callback?code=');
});
