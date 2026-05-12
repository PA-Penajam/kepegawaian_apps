<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;

beforeEach(function () {
    $this->kepegawaianApp = IamApplication::where('slug', 'kepegawaian')->first();

    $this->adminRole = IamRole::where('iam_application_id', $this->kepegawaianApp->id)
        ->where('slug', 'admin')->first();

    $this->testPerm = IamPermission::firstOrCreate([
        'iam_application_id' => $this->kepegawaianApp->id,
        'slug' => 'test:cache-perm',
    ], [
        'nama' => 'Test Cache Permission',
        'group' => 'test',
    ]);

    $this->adminRole->permissions()->syncWithoutDetaching([$this->testPerm->id]);
});

test('hasPermission menggunakan cache dan tidak query ulang', function () {
    $user = Pegawai::factory()->admin()->create();

    expect($user->hasPermission('test:cache-perm'))->toBeTrue();

    // Hapus permission dari DB — cache masih menyimpan hasil lama
    $this->adminRole->permissions()->detach($this->testPerm->id);

    // Masih true karena cache per-request (tanpa refresh)
    expect($user->hasPermission('test:cache-perm'))->toBeTrue();
});

test('refresh() membersihkan cache permission', function () {
    $user = Pegawai::factory()->admin()->create();

    expect($user->hasPermission('test:cache-perm'))->toBeTrue();

    // Hapus permission dari DB
    $this->adminRole->permissions()->detach($this->testPerm->id);

    // Refresh model — cache harus di-clear
    $user->refresh();

    expect($user->hasPermission('test:cache-perm'))->toBeFalse();
});

test('clearPermissionCache memaksa reload dari database', function () {
    $user = Pegawai::factory()->admin()->create();

    expect($user->hasPermission('test:cache-perm'))->toBeTrue();

    $this->adminRole->permissions()->detach($this->testPerm->id);

    $user->clearPermissionCache();

    expect($user->hasPermission('test:cache-perm'))->toBeFalse();
});

test('hasAnyPermission menggunakan cache yang sama', function () {
    $user = Pegawai::factory()->admin()->create();

    expect($user->hasAnyPermission('test:cache-perm', 'nonexistent:perm'))->toBeTrue();
    expect($user->hasAnyPermission('nonexistent:perm', 'another:fake'))->toBeFalse();
});
