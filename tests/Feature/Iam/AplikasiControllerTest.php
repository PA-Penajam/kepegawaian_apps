<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    // Gunakan data dari IamSeeder (sudah di-seed oleh Pest.php beforeEach)
    $this->kepegawaianApp = IamApplication::where('slug', 'kepegawaian')->first();

    $this->adminRole = IamRole::where('iam_application_id', $this->kepegawaianApp->id)
        ->where('slug', 'admin')->first();

    $this->viewerRole = IamRole::where('iam_application_id', $this->kepegawaianApp->id)
        ->where('slug', 'viewer')->first();

    $this->adminPermission = IamPermission::where('iam_application_id', $this->kepegawaianApp->id)
        ->where('slug', 'iam-manage')->first();
});

test('admin dapat melihat daftar aplikasi', function () {
    $admin = Pegawai::factory()->admin()->create();

    $this->actingAs($admin)->get('/iam/aplikasi')->assertOk();
});

test('user tanpa role tidak bisa akses halaman IAM', function () {
    // Buat user tanpa role IAM (hapus viewer role yang di-assign otomatis oleh factory)
    $user = Pegawai::factory()->create();
    IamUserRole::where('user_id', $user->id)->delete();

    $this->actingAs($user)->get('/iam/aplikasi')->assertForbidden();
});

test('admin dapat mendaftarkan aplikasi baru', function () {
    $admin = Pegawai::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/iam/aplikasi', [
        'nama' => 'E-Surat',
        'slug' => 'e-surat',
        'url' => 'http://esurat.local',
        'deskripsi' => 'Aplikasi e-surat PA Penajam',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('iam_applications', ['slug' => 'e-surat']);
});

test('aplikasi is_system tidak bisa dihapus', function () {
    $admin = Pegawai::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete("/iam/aplikasi/{$this->kepegawaianApp->id}")
        ->assertForbidden();
});

test('admin dapat meregenerasi api key aplikasi', function () {
    $admin = Pegawai::factory()->admin()->create();

    // Buat aplikasi test dengan manual assignment karena field tidak fillable
    $app = new IamApplication([
        'nama' => 'Test',
        'slug' => 'test-regen',
        'url' => 'http://x.local',
    ]);
    $app->api_key = 'old-key';
    $app->api_secret_hash = Crypt::encryptString('old-secret');
    $app->is_system = false;
    $app->save();

    $oldKey = $app->api_key;
    $this->actingAs($admin)->post("/iam/aplikasi/{$app->id}/regenerate-key")->assertRedirect();

    $app->refresh();
    expect($app->api_key)->not->toBe($oldKey);
});

it('menghapus cache iam_app saat aplikasi diupdate', function () {
    $admin = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create(['slug' => 'test-app', 'is_system' => false]);

    // Set cache seolah-olah sudah ada sebelumnya
    Cache::put('iam_app:test-app', $app, 3600);
    expect(Cache::has('iam_app:test-app'))->toBeTrue();

    $this->actingAs($admin)
        ->put("/iam/aplikasi/{$app->id}", [
            'nama' => 'Updated App',
            'slug' => 'test-app',
            'url' => 'https://example.com',
            'is_active' => true,
            'deskripsi' => null,
        ]);

    expect(Cache::has('iam_app:test-app'))->toBeFalse();
});

it('menghapus cache iam_app saat aplikasi dihapus', function () {
    $admin = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create(['slug' => 'hapus-app', 'is_system' => false]);

    Cache::put('iam_app:hapus-app', $app, 3600);
    expect(Cache::has('iam_app:hapus-app'))->toBeTrue();

    $this->actingAs($admin)
        ->delete("/iam/aplikasi/{$app->id}");

    expect(Cache::has('iam_app:hapus-app'))->toBeFalse();
});

it('viewer tidak bisa mendaftarkan aplikasi baru', function () {
    $viewer = Pegawai::factory()->viewer()->create();

    $response = $this->actingAs($viewer)->post('/iam/aplikasi', [
        'nama' => 'Test App',
        'slug' => 'test-app',
        'url' => 'http://test.local',
    ]);

    $response->assertForbidden();
});

it('viewer tidak bisa mengupdate aplikasi', function () {
    $viewer = Pegawai::factory()->viewer()->create();
    $app = IamApplication::factory()->create(['is_system' => false]);

    $response = $this->actingAs($viewer)->put("/iam/aplikasi/{$app->id}", [
        'nama' => 'Updated',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);

    $response->assertForbidden();
});

it('viewer tidak bisa memberikan akses role ke user', function () {
    $viewer = Pegawai::factory()->viewer()->create();
    $targetUser = Pegawai::factory()->create();

    $response = $this->actingAs($viewer)
        ->post("/iam/users/{$targetUser->id}/akses", [
            'iam_role_id' => $this->adminRole->id,
        ]);

    $response->assertForbidden();
});
