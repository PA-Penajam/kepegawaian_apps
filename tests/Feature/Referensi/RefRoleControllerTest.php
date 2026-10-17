<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\Pegawai;
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
