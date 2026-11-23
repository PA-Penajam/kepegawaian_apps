<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Helper lokal: pegawai dengan permission iam.manage pada aplikasi kepegawaian.
 */
function buatPengelolaRole(): Pegawai
{
    return Pegawai::factory()->admin()->create();
}

test('index role menampilkan jumlah pegawai yang ter-assign', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Hitung Pegawai',
        'slug' => 'role-hitung-pegawai',
    ]);

    $anggota = Pegawai::factory()->count(2)->create();
    $role->users()->sync($anggota->pluck('id')->all());

    $this->actingAs($admin)
        ->get(route('referensi.roles.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('referensi/roles/index')
            ->where('roles.data', function ($data) use ($role): bool {
                $item = collect($data)->firstWhere('slug', $role->slug);

                return $item !== null && (int) $item['pegawai_count'] === 2;
            })
        );

    // Role tanpa anggota tetap konsisten bernilai nol
    $roleKosong = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Tanpa Anggota',
        'slug' => 'role-tanpa-anggota',
    ]);

    expect($roleKosong->users()->count())->toBe(0);
});

test('edit role mengirim daftar id pegawai yang ter-assign', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Edit Anggota',
        'slug' => 'role-edit-anggota',
    ]);

    $anggota = Pegawai::factory()->create();
    $role->users()->sync([$anggota->id]);

    $this->actingAs($admin)
        ->get(route('referensi.roles.edit', $role))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('referensi/roles/edit')
            ->where('assignedPegawaiIds', [$anggota->id])
        );
});

test('role yang masih memiliki pegawai tidak dapat dihapus', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Terpakai',
        'slug' => 'role-terpakai',
    ]);

    $role->users()->sync([Pegawai::factory()->create()->id]);

    $this->actingAs($admin)
        ->delete(route('referensi.roles.destroy', $role))
        ->assertRedirect(route('referensi.roles.index'));

    expect(IamRole::whereKey($role->id)->exists())->toBeTrue();
});

test('update role melakukan sinkronisasi anggota pegawai', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Sinkron Anggota',
        'slug' => 'role-sinkron-anggota',
    ]);

    $anggotaBaru = Pegawai::factory()->count(2)->create();

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $role), [
            'nama' => 'Role Sinkron Anggota',
            'pegawai_ids' => $anggotaBaru->pluck('id')->all(),
        ])
        ->assertRedirect(route('referensi.roles.index'));

    expect($role->users()->pluck('pegawai.id')->all())
        ->toEqualCanonicalizing($anggotaBaru->pluck('id')->all());
});

test('edit, update, dan destroy role aplikasi lain ditolak 404', function () {
    $admin = buatPengelolaRole();
    $otherApp = IamApplication::factory()->create(['slug' => 'app-asing', 'is_active' => true]);
    $foreignRole = IamRole::factory()->create([
        'iam_application_id' => $otherApp->id,
        'nama' => 'Role Asing',
        'slug' => 'role-asing',
    ]);

    $this->actingAs($admin)->get(route('referensi.roles.edit', $foreignRole))->assertNotFound();
    $this->actingAs($admin)->put(route('referensi.roles.update', $foreignRole), ['nama' => 'Dibajak'])->assertNotFound();
    $this->actingAs($admin)->delete(route('referensi.roles.destroy', $foreignRole))->assertNotFound();
});

test('role sistem tidak dapat diubah namanya atau dihapus tetapi tetap bisa disinkronkan anggotanya', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $systemRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'operator')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $systemRole), ['nama' => 'Operator Diganti'])
        ->assertSessionHasErrors('nama');

    expect($systemRole->fresh()->nama)->not->toBe('Operator Diganti')
        ->and($systemRole->fresh()->slug)->toBe('operator');

    $target = Pegawai::factory()->create();
    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $systemRole), [
            'nama' => $systemRole->nama,
            'pegawai_ids' => [$target->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    expect($systemRole->users()->pluck('pegawai.id')->all())->toContain($target->id);

    $this->actingAs($admin)
        ->delete(route('referensi.roles.destroy', $systemRole))
        ->assertRedirect(route('referensi.roles.index'));

    expect(IamRole::whereKey($systemRole->id)->exists())->toBeTrue();
});

test('store dan update menolak permission lintas aplikasi dan menginvalidasi cache iam_app:kepegawaian', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $otherApp = IamApplication::factory()->create(['slug' => 'app-lain', 'is_active' => true]);
    $foreignPerm = IamPermission::factory()->create(['iam_application_id' => $otherApp->id, 'slug' => 'lain.view']);
    $localPerm = IamPermission::where('iam_application_id', $app->id)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Role Lintas',
            'permissions' => [$foreignPerm->id],
        ])
        ->assertSessionHasErrors('permissions.0');

    Cache::put('iam_app:kepegawaian', $app, 3600);
    Cache::put('non_iam_key', 'aman', 3600);

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Role Lokal Valid',
            'permissions' => [$localPerm->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    expect(Cache::has('iam_app:kepegawaian'))->toBeFalse()
        ->and(Cache::get('non_iam_key'))->toBe('aman');
});

test('store dan update menangani input permissions dan pegawai_ids bernilai null tanpa TypeError', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $localPerm = IamPermission::where('iam_application_id', $app->id)->firstOrFail();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Role Null Permissions',
            'keterangan' => 'Uji payload null',
            'permissions' => null,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('referensi.roles.index'));

    $role = IamRole::where('iam_application_id', $app->id)
        ->where('nama', 'Role Null Permissions')
        ->firstOrFail();

    expect($role->permissions()->count())->toBe(0);

    $role->permissions()->sync([$localPerm->id]);
    $role->users()->sync([$pegawai->id]);

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $role), [
            'nama' => 'Role Null Permissions',
            'keterangan' => 'Uji update null',
            'permissions' => null,
            'pegawai_ids' => null,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('referensi.roles.index'));

    expect($role->permissions()->count())->toBe(0)
        ->and($role->users()->count())->toBe(0);
});

test('store dan update role menolak nested array pada permissions dan pegawai_ids dengan 422', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $localPerm = IamPermission::where('iam_application_id', $app->id)->firstOrFail();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('referensi.roles.store'), [
            'nama' => 'Role Nested Permissions',
            'permissions' => [[$localPerm->id]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions.0');

    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Update Nested',
        'slug' => 'role-update-nested',
    ]);

    $this->actingAs($admin)
        ->putJson(route('referensi.roles.update', $role), [
            'nama' => 'Role Update Nested',
            'permissions' => [[$localPerm->id]],
            'pegawai_ids' => [[$pegawai->id]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0', 'pegawai_ids.0']);
});

test('store role menolak tabrakan slug, re-create role soft-deleted, dan nama simbol dengan 422 bukan 500', function () {
    $admin = buatPengelolaRole();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();

    IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Admin Cuti',
        'slug' => 'admin-cuti',
    ]);

    // (a) Slug collision antar nama berbeda ('Admin  Cuti' & 'Admin-Cuti' -> slug 'admin-cuti')
    $this->actingAs($admin)
        ->postJson(route('referensi.roles.store'), [
            'nama' => 'Admin  Cuti',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nama');

    $this->actingAs($admin)
        ->postJson(route('referensi.roles.store'), [
            'nama' => 'Admin-Cuti',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nama');

    // (b) Re-create role yang pernah di-soft-delete
    $deletedRole = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Operator Lama',
        'slug' => 'operator-lama',
    ]);
    $deletedRole->delete();

    $this->actingAs($admin)
        ->postJson(route('referensi.roles.store'), [
            'nama' => 'Operator Lama',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nama');

    // (c) Nama simbol saja ('!!!') menghasilkan slug kosong -> harus ditolak 422
    $this->actingAs($admin)
        ->postJson(route('referensi.roles.store'), [
            'nama' => '!!!',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('nama');

    expect(IamRole::withTrashed()->where('iam_application_id', $app->id)->where('slug', '')->exists())->toBeFalse();
});
