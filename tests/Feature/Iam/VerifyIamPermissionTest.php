<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Gunakan data dari IamSeeder
    $this->kepegawaianApp = IamApplication::where('slug', 'kepegawaian')->first();

    $this->adminRole = IamRole::where('iam_application_id', $this->kepegawaianApp->id)
        ->where('slug', 'admin')->first();

    $this->viewerRole = IamRole::where('iam_application_id', $this->kepegawaianApp->id)
        ->where('slug', 'viewer')->first();

    // Buat permission test-only 'pegawai:read'
    $this->pegawaiReadPerm = IamPermission::firstOrCreate([
        'iam_application_id' => $this->kepegawaianApp->id,
        'slug' => 'pegawai:read',
    ], [
        'nama' => 'Lihat Pegawai Test',
        'group' => 'pegawai',
    ]);

    $this->pegawaiWritePerm = IamPermission::firstOrCreate([
        'iam_application_id' => $this->kepegawaianApp->id,
        'slug' => 'pegawai:write',
    ], [
        'nama' => 'Tulis Pegawai Test',
        'group' => 'pegawai',
    ]);

    // Admin role punya permission pegawai:read
    $this->adminRole->permissions()->syncWithoutDetaching([$this->pegawaiReadPerm->id]);

    Route::middleware(['auth', 'iam.permission:pegawai:read'])
        ->get('/test-iam-perm', fn () => 'ok');

    Route::middleware(['auth', 'iam.permission:pegawai:read,pegawai:write'])
        ->get('/test-iam-perm-all', fn () => 'ok');

    Route::middleware(['auth', 'iam.permission:any:pegawai:read,pegawai:write'])
        ->get('/test-iam-perm-any', fn () => 'ok');
});

test('guest diredirect ke sso login', function () {
    $this->get('/test-iam-perm')->assertRedirectContains(route('auth.sso.login'));
});

test('user tanpa role di aplikasi ini mendapat 403', function () {
    $user = Pegawai::factory()->create();
    // Hapus viewer role otomatis dari factory
    IamUserRole::where('user_id', $user->id)->delete();

    $this->actingAs($user)->get('/test-iam-perm')->assertForbidden();
});

test('user dengan role tapi tidak punya permission mendapat 403', function () {
    $user = Pegawai::factory()->create();
    // Factory otomatis assign viewer. Viewer tidak punya pegawai:read

    $this->actingAs($user)->get('/test-iam-perm')->assertForbidden();
});

test('user dengan permission yang sesuai lolos', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->actingAs($user)->get('/test-iam-perm')->assertOk();
});

test('mode all: user harus punya semua permission', function () {
    $user = Pegawai::factory()->admin()->create();
    // Admin punya pegawai:read tapi tidak punya pegawai:write

    $this->actingAs($user)->get('/test-iam-perm-all')->assertForbidden();
});

test('mode all: user dengan semua permission lolos', function () {
    $user = Pegawai::factory()->admin()->create();
    $this->adminRole->permissions()->syncWithoutDetaching([$this->pegawaiWritePerm->id]);

    $this->actingAs($user)->get('/test-iam-perm-all')->assertOk();
});

test('mode any: user cukup punya salah satu permission', function () {
    $user = Pegawai::factory()->admin()->create();
    // Admin punya pegawai:read, tidak punya pegawai:write — tetap lolos karena mode any

    $this->actingAs($user)->get('/test-iam-perm-any')->assertOk();
});

test('mode any: user tanpa satupun permission mendapat 403', function () {
    $user = Pegawai::factory()->create();
    // Viewer tidak punya pegawai:read maupun pegawai:write

    $this->actingAs($user)->get('/test-iam-perm-any')->assertForbidden();
});
