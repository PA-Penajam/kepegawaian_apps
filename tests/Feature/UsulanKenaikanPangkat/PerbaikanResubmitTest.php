<?php

namespace Tests\Feature\UsulanKenaikanPangkat;

use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\PerluPerbaikanState;

it('mengizinkan transisi dari PerluPerbaikan langsung ke Diajukan', function (): void {
    $pegawai = Pegawai::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'state' => PerluPerbaikanState::class,
    ]);

    expect($usulan->state->canTransitionTo(DiajukanState::class))->toBeTrue();

    $usulan->state->transitionTo(DiajukanState::class);

    expect((string) $usulan->refresh()->state)->toBe('DIAJUKAN');
});
