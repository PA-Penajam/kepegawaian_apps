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

    // Admin role punya permission pegawai:read
    $this->adminRole->permissions()->syncWithoutDetaching([$this->pegawaiReadPerm->id]);

    Route::middleware(['auth', 'iam.permission:pegawai:read'])
        ->get('/test-iam-perm', fn () => 'ok');
});

test('guest diredirect ke login', function () {
    $this->get('/test-iam-perm')->assertRedirectContains(route('sso.login'));
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
