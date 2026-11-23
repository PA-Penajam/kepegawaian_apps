<?php

namespace Tests\Feature\Policies;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Policies\UsulanKenaikanPangkatPolicy;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\DikirimBiroState;
use App\States\UsulanKenaikanPangkat\DitandatanganiKetuaState;
use App\States\UsulanKenaikanPangkat\DiverifikasiKasubbagState;
use App\States\UsulanKenaikanPangkat\DiverifikasiSekretarisState;
use App\States\UsulanKenaikanPangkat\DraftState;
use App\States\UsulanKenaikanPangkat\MenungguSkState;
use App\States\UsulanKenaikanPangkat\PerluPerbaikanState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new UsulanKenaikanPangkatPolicy;

    $this->grant = function (Pegawai $pegawai, string $slug): void {
        $app = IamApplication::firstOrCreate(
            ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
            ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
        );
        $role = IamRole::firstOrCreate(
            ['slug' => 'kp-role', 'iam_application_id' => $app->id],
            ['nama' => 'KP']
        );
        $perm = IamPermission::firstOrCreate(
            ['slug' => $slug, 'iam_application_id' => $app->id],
            ['nama' => $slug]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
    };
});

test('viewAny requires permission', function () {
    $user = Pegawai::factory()->create();
    $grant = $this->grant;

    expect($this->policy->viewAny($user))->toBeFalse();

    $grant($user, 'kenaikan-pangkat.usulan.view');
    $user->refresh();

    expect($this->policy->viewAny($user))->toBeTrue();
});

test('view allows owner only without permission', function () {
    $owner = Pegawai::factory()->create();
    $other = Pegawai::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DraftState::class,
    ]);

    expect($this->policy->view($owner, $usulan))->toBeTrue();
    expect($this->policy->view($other, $usulan))->toBeFalse();
});

test('submit only Draft + permission', function () {
    $p = Pegawai::factory()->create();
    $grant = $this->grant;
    $grant($p, 'kenaikan-pangkat.usulan.submit');

    $draft = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $p->id,
        'state' => DraftState::class,
    ]);
    $ajukan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $p->id,
        'state' => DiajukanState::class,
    ]);

    expect($this->policy->submit($p, $draft))->toBeTrue();
    expect($this->policy->submit($p, $ajukan))->toBeFalse();
});

test('owner dapat memperbarui usulan yang perlu perbaikan', function () {
    $owner = Pegawai::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => PerluPerbaikanState::class,
    ]);

    expect($this->policy->update($owner, $usulan))->toBeTrue();
});

test('usulan yang perlu perbaikan dapat diajukan kembali dengan permission', function () {
    $owner = Pegawai::factory()->create();
    $grant = $this->grant;
    $grant($owner, 'kenaikan-pangkat.usulan.submit');
    $owner->refresh();

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => PerluPerbaikanState::class,
    ]);

    expect($this->policy->submit($owner, $usulan))->toBeTrue();
});

test('owner dapat membatalkan usulan yang perlu perbaikan', function () {
    $owner = Pegawai::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => PerluPerbaikanState::class,
    ]);

    expect($this->policy->batalkan($owner, $usulan))->toBeTrue();
});

test('mintaPerbaikan dan tolak menolak pemilik viewer dan approver tahap berbeda', function () {
    $owner = Pegawai::factory()->create();
    $viewer = Pegawai::factory()->create();
    $kasubbag = Pegawai::factory()->create();
    $sekretaris = Pegawai::factory()->create();

    $grantIsolated = function (Pegawai $pegawai, string $slug): void {
        $app = IamApplication::firstOrCreate(
            ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
            ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
        );
        $role = IamRole::firstOrCreate(
            ['slug' => 'kp-isolated-'.str_replace('.', '-', $slug), 'iam_application_id' => $app->id],
            ['nama' => 'KP '.$slug]
        );
        $perm = IamPermission::firstOrCreate(
            ['slug' => $slug, 'iam_application_id' => $app->id],
            ['nama' => $slug]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
    };

    $grantIsolated($owner, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    $grantIsolated($viewer, 'kenaikan-pangkat.usulan.view');
    $grantIsolated($kasubbag, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    $grantIsolated($sekretaris, 'kenaikan-pangkat.usulan.verifikasi-sekretaris');

    $owner->refresh();
    $viewer->refresh();
    $kasubbag->refresh();
    $sekretaris->refresh();

    $usulanDiajukan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DiajukanState::class,
    ]);

    expect($this->policy->mintaPerbaikan($owner, $usulanDiajukan))->toBeFalse()
        ->and($this->policy->mintaPerbaikan($viewer, $usulanDiajukan))->toBeFalse()
        ->and($this->policy->mintaPerbaikan($sekretaris, $usulanDiajukan))->toBeFalse()
        ->and($this->policy->mintaPerbaikan($kasubbag, $usulanDiajukan))->toBeTrue()
        ->and($this->policy->tolak($kasubbag, $usulanDiajukan))->toBeFalse();

    $usulanKasubbag = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DiverifikasiKasubbagState::class,
    ]);

    expect($this->policy->tolak($owner, $usulanKasubbag))->toBeFalse()
        ->and($this->policy->tolak($viewer, $usulanKasubbag))->toBeFalse()
        ->and($this->policy->tolak($kasubbag, $usulanKasubbag))->toBeFalse()
        ->and($this->policy->tolak($sekretaris, $usulanKasubbag))->toBeTrue()
        ->and($this->policy->mintaPerbaikan($sekretaris, $usulanKasubbag))->toBeTrue();

    $usulanDraft = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DraftState::class,
    ]);

    expect($this->policy->mintaPerbaikan($kasubbag, $usulanDraft))->toBeFalse()
        ->and($this->policy->tolak($sekretaris, $usulanDraft))->toBeFalse();

    $ketua = Pegawai::factory()->create();
    $grantIsolated($ketua, 'kenaikan-pangkat.usulan.tanda-tangan-ketua');
    $ketua->refresh();

    $usulanSekretaris = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DiverifikasiSekretarisState::class,
    ]);

    expect($this->policy->mintaPerbaikan($ketua, $usulanSekretaris))->toBeTrue()
        ->and($this->policy->tolak($ketua, $usulanSekretaris))->toBeTrue();

    $biro = Pegawai::factory()->create();
    $grantIsolated($biro, 'kenaikan-pangkat.usulan.kirim-biro');
    $biro->refresh();

    $usulanDikirimBiro = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DikirimBiroState::class,
    ]);
    $usulanMenungguSk = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => MenungguSkState::class,
    ]);

    expect($this->policy->mintaPerbaikan($biro, $usulanDikirimBiro))->toBeTrue()
        ->and($this->policy->tolak($biro, $usulanMenungguSk))->toBeTrue();
});

test('validateChecklist menolak validasi oleh pemilik usulan sendiri', function () {
    $owner = Pegawai::factory()->create();
    $validator = Pegawai::factory()->create();
    $grant = $this->grant;
    $grant($owner, 'checklist.submission.validate-item');
    $grant($validator, 'checklist.submission.validate-item');
    $owner->refresh();
    $validator->refresh();

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => DraftState::class,
    ]);

    expect($this->policy->validateChecklist($owner, $usulan))->toBeFalse()
        ->and($this->policy->validateChecklist($validator, $usulan))->toBeTrue();
});

test('policy menolak self-approval pada seluruh 5 tahap persetujuan dan finalisasi SK', function () {
    $owner = Pegawai::factory()->create();
    $grant = $this->grant;

    foreach ([
        'kenaikan-pangkat.usulan.verifikasi-kasubbag',
        'kenaikan-pangkat.usulan.verifikasi-sekretaris',
        'kenaikan-pangkat.usulan.tanda-tangan-ketua',
        'kenaikan-pangkat.usulan.kirim-biro',
        'kenaikan-pangkat.usulan.upload-sk',
    ] as $perm) {
        $grant($owner, $perm);
    }
    $owner->refresh();

    $usulanDiajukan = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => DiajukanState::class]);
    $usulanKasubbag = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => DiverifikasiKasubbagState::class]);
    $usulanSekretaris = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => DiverifikasiSekretarisState::class]);
    $usulanKetua = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => DitandatanganiKetuaState::class]);
    $usulanMenungguSk = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => MenungguSkState::class]);

    expect($this->policy->verifikasiKasubbag($owner, $usulanDiajukan))->toBeFalse()
        ->and($this->policy->verifikasiSekretaris($owner, $usulanKasubbag))->toBeFalse()
        ->and($this->policy->tandaTanganKetua($owner, $usulanSekretaris))->toBeFalse()
        ->and($this->policy->kirimBiro($owner, $usulanKetua))->toBeFalse()
        ->and($this->policy->uploadSk($owner, $usulanMenungguSk))->toBeFalse();
});
