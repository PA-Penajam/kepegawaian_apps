<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    // Buat aplikasi kepegawaian + roles + permissions untuk test
    $this->kepegawaianApp = IamApplication::create([
        'nama'            => 'Kepegawaian Apps',
        'slug'            => 'kepegawaian',
        'url'             => config('app.url'),
        'api_key'         => 'test-kepegawaian-key',
        'api_secret_hash' => Crypt::encryptString('test-secret-64chars-padding-here-abc123'),
        'is_active'       => true,
        'is_system'       => true,
    ]);

    $this->adminRole = IamRole::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Admin',
        'slug'               => 'admin',
        'is_system'          => true,
    ]);

    $this->viewerRole = IamRole::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Viewer',
        'slug'               => 'viewer',
        'is_system'          => true,
    ]);

    // Buat permission dan assign ke admin role
    $this->adminPermission = IamPermission::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Kelola Aplikasi',
        'slug'               => 'aplikasi:manage',
        'group'              => 'iam',
    ]);

    $this->adminRole->permissions()->attach($this->adminPermission->id);
});

test('admin dapat melihat daftar aplikasi', function () {
    $admin = User::factory()->create();
    IamUserRole::create(['user_id' => $admin->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);

    $this->actingAs($admin)->get('/iam/aplikasi')->assertOk();
});

test('user tanpa role tidak bisa akses halaman IAM', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/iam/aplikasi')->assertForbidden();
});

test('admin dapat mendaftarkan aplikasi baru', function () {
    $admin = User::factory()->create();
    IamUserRole::create(['user_id' => $admin->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);

    $response = $this->actingAs($admin)->post('/iam/aplikasi', [
        'nama'      => 'E-Surat',
        'slug'      => 'e-surat',
        'url'       => 'http://esurat.local',
        'deskripsi' => 'Aplikasi e-surat PA Penajam',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('iam_applications', ['slug' => 'e-surat']);
});

test('aplikasi is_system tidak bisa dihapus', function () {
    $admin = User::factory()->create();
    IamUserRole::create(['user_id' => $admin->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);

    $this->actingAs($admin)
        ->delete("/iam/aplikasi/{$this->kepegawaianApp->id}")
        ->assertForbidden();
});

test('admin dapat meregenerasi api key aplikasi', function () {
    $admin = User::factory()->create();
    IamUserRole::create(['user_id' => $admin->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);

    $app = IamApplication::create([
        'nama'            => 'Test',
        'slug'            => 'test-regen',
        'url'             => 'http://x.local',
        'api_key'         => 'old-key',
        'api_secret_hash' => Crypt::encryptString('old-secret'),
    ]);

    $oldKey = $app->api_key;
    $this->actingAs($admin)->post("/iam/aplikasi/{$app->id}/regenerate-key")->assertRedirect();

    $app->refresh();
    expect($app->api_key)->not->toBe($oldKey);
});
