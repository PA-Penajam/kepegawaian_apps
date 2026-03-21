<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

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

    // Buat roles: admin, operator, viewer
    $this->adminRole = IamRole::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Admin',
        'slug'               => 'admin',
        'is_system'          => true,
    ]);

    $this->operatorRole = IamRole::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Operator',
        'slug'               => 'operator',
        'is_system'          => true,
    ]);

    $this->viewerRole = IamRole::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Viewer',
        'slug'               => 'viewer',
        'is_system'          => true,
    ]);

    // Buat permission pegawai:read
    $this->pegawaiReadPerm = IamPermission::create([
        'iam_application_id' => $this->kepegawaianApp->id,
        'nama'               => 'Lihat Pegawai',
        'slug'               => 'pegawai:read',
        'group'              => 'pegawai',
    ]);

    // Admin role punya permission pegawai:read
    $this->adminRole->permissions()->attach($this->pegawaiReadPerm->id);

    Route::middleware(['auth', 'iam.permission:pegawai:read'])
        ->get('/test-iam-perm', fn () => 'ok');
});

test('guest diredirect ke login', function () {
    $this->get('/test-iam-perm')->assertRedirect(route('login'));
});

test('user tanpa role di aplikasi ini mendapat 403', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/test-iam-perm')->assertForbidden();
});

test('user dengan role tapi tidak punya permission mendapat 403', function () {
    $user = User::factory()->create();

    // viewer role tidak punya permission pegawai:read
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $this->viewerRole->id, 'assigned_at' => now()]);

    $this->actingAs($user)->get('/test-iam-perm')->assertForbidden();
});

test('user dengan permission yang sesuai lolos', function () {
    $user = User::factory()->create();

    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);

    $this->actingAs($user)->get('/test-iam-perm')->assertOk();
});
