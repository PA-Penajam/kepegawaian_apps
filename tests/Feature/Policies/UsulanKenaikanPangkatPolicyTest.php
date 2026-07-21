<?php

namespace Tests\Feature\Policies;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Policies\UsulanKenaikanPangkatPolicy;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\DraftState;
use App\States\UsulanKenaikanPangkat\PerluPerbaikanState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new UsulanKenaikanPangkatPolicy;

    $this->grant = function (Pegawai $pegawai, string $slug): void {
        $app = IamApplication::firstOrCreate(
            ['slug' => 'test-kp'],
            ['nama' => 'KP Test', 'url' => 'https://local']
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
