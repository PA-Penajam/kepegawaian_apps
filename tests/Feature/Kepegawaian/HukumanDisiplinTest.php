<?php

use App\Models\HukumanDisiplin;
use App\Models\Pegawai;
use App\Models\RefJenisHukumanDisiplin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function makeHukumanDisiplinPayload(?RefJenisHukumanDisiplin $jenis = null, array $overrides = []): array
{
    return array_merge([
        'ref_jenis_hukuman_disiplin_id' => $jenis?->id,
        'no_sk' => 'SK/001/2024',
        'tanggal_sk' => '2024-01-10',
        'tmt_berlaku' => '2024-01-15',
        'tmt_selesai' => null,
        'pelanggaran' => 'Tidak hadir tanpa izin',
        'pejabat_penetap' => 'Ketua Pengadilan',
        'keterangan' => 'Data uji hukuman disiplin',
    ], $overrides);
}

dataset('hukuman-disiplin-allowed-users', [
    'admin' => [fn () => User::factory()->admin()->create()],
    'operator' => [fn () => User::factory()->operator()->create()],
]);

test('dapat membuat hukuman disiplin untuk pegawai', function () {
    $pegawai = Pegawai::factory()->create();

    $hukumanDisiplin = HukumanDisiplin::query()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/001/2024',
        'tanggal_sk' => '2024-01-10',
        'tmt_berlaku' => '2024-01-15',
        'pelanggaran' => 'Tidak hadir tanpa izin',
    ]);

    expect($hukumanDisiplin->no_sk)->toBe('SK/001/2024');
});

test('scope aktif mengembalikan hukuman yang belum selesai', function () {
    $pegawai = Pegawai::factory()->create();

    HukumanDisiplin::query()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/001/2024',
        'tanggal_sk' => '2024-01-01',
        'tmt_berlaku' => '2024-01-01',
        'tmt_selesai' => null,
        'pelanggaran' => 'Pelanggaran A',
    ]);

    HukumanDisiplin::query()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/002/2023',
        'tanggal_sk' => '2023-01-01',
        'tmt_berlaku' => '2023-01-01',
        'tmt_selesai' => '2023-12-31',
        'pelanggaran' => 'Pelanggaran B',
    ]);

    $aktif = HukumanDisiplin::query()->aktif()->get();

    expect($aktif)->toHaveCount(1);
    expect($aktif->first()->no_sk)->toBe('SK/001/2024');
});

test('scope aktif juga mengembalikan hukuman dengan tmt selesai setelah hari ini', function () {
    $pegawai = Pegawai::factory()->create();

    HukumanDisiplin::query()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/003/2025',
        'tanggal_sk' => now()->subMonths(2)->toDateString(),
        'tmt_berlaku' => now()->subMonths(2)->toDateString(),
        'tmt_selesai' => now()->addMonths(3)->toDateString(),
        'pelanggaran' => 'Pelanggaran C',
    ]);

    HukumanDisiplin::query()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/004/2024',
        'tanggal_sk' => now()->subYear()->toDateString(),
        'tmt_berlaku' => now()->subYear()->toDateString(),
        'tmt_selesai' => now()->subDay()->toDateString(),
        'pelanggaran' => 'Pelanggaran D',
    ]);

    $aktif = HukumanDisiplin::query()->aktif()->get();

    expect($aktif)->toHaveCount(1);
    expect($aktif->first()->no_sk)->toBe('SK/003/2025');
});

test('hukuman disiplin dapat di-soft-delete', function () {
    $pegawai = Pegawai::factory()->create();

    $hukumanDisiplin = HukumanDisiplin::query()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/005/2024',
        'tanggal_sk' => '2024-05-01',
        'tmt_berlaku' => '2024-05-01',
        'pelanggaran' => 'Test',
    ]);

    $hukumanDisiplin->delete();

    expect(HukumanDisiplin::query()->find($hukumanDisiplin->id))->toBeNull();
    expect(HukumanDisiplin::query()->withTrashed()->find($hukumanDisiplin->id)?->trashed())->toBeTrue();
});

test('admin dan operator dapat mengakses halaman hukuman disiplin', function (Closure $makeUser) {
    $pegawai = Pegawai::factory()->create();
    $hukumanDisiplin = HukumanDisiplin::factory()->create([
        'pegawai_id' => $pegawai->id,
        'no_sk' => 'SK/006/2024',
    ]);
    $user = $makeUser();

    actingAs($user);

    get(route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/hukuman-disiplin')
            ->where('pegawai.id', $pegawai->id)
            ->where('pegawai.nama', $pegawai->nama_lengkap)
            ->has('hukumanDisiplin', 1)
            ->where('hukumanDisiplin.0.id', $hukumanDisiplin->id),
        );
})->with('hukuman-disiplin-allowed-users');

test('viewer tidak dapat mengakses hukuman disiplin', function () {
    $viewer = User::factory()->create();
    $pegawai = Pegawai::factory()->create();

    actingAs($viewer);

    get(route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai))
        ->assertForbidden();
});

test('operator dapat menyimpan hukuman disiplin baru', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $jenis = RefJenisHukumanDisiplin::factory()->create();

    actingAs($operator);

    post(
        route('kepegawaian.pegawai.hukuman-disiplin.store', $pegawai),
        makeHukumanDisiplinPayload($jenis),
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai));

    expect(HukumanDisiplin::query()
        ->where('pegawai_id', $pegawai->id)
        ->where('ref_jenis_hukuman_disiplin_id', $jenis->id)
        ->where('no_sk', 'SK/001/2024')
        ->exists())->toBeTrue();
});

test('operator dapat memperbarui hukuman disiplin', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $jenis = RefJenisHukumanDisiplin::factory()->create();
    $hukumanDisiplin = HukumanDisiplin::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_jenis_hukuman_disiplin_id' => $jenis->id,
        'no_sk' => 'SK/LAMA/2024',
        'pelanggaran' => 'Pelanggaran lama',
    ]);

    actingAs($operator);

    put(
        route('kepegawaian.pegawai.hukuman-disiplin.update', [$pegawai, $hukumanDisiplin]),
        makeHukumanDisiplinPayload($jenis, [
            'no_sk' => 'SK/BARU/2024',
            'pelanggaran' => 'Pelanggaran baru',
            'tmt_selesai' => '2024-12-31',
        ]),
    )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai));

    expect($hukumanDisiplin->fresh()->no_sk)->toBe('SK/BARU/2024');
    expect($hukumanDisiplin->fresh()->pelanggaran)->toBe('Pelanggaran baru');
});

test('operator dapat menghapus hukuman disiplin secara soft delete', function () {
    $operator = User::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();
    $hukumanDisiplin = HukumanDisiplin::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    actingAs($operator);

    delete(route('kepegawaian.pegawai.hukuman-disiplin.destroy', [$pegawai, $hukumanDisiplin]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.hukuman-disiplin.index', $pegawai));

    expect(HukumanDisiplin::query()->find($hukumanDisiplin->id))->toBeNull();
    expect(HukumanDisiplin::query()->withTrashed()->find($hukumanDisiplin->id)?->trashed())->toBeTrue();
});
