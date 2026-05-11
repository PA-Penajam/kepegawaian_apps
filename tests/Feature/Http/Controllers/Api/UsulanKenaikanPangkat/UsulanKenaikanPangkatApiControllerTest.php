<?php

namespace Tests\Feature\Http\Controllers\Api\UsulanKenaikanPangkat;

use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('mengembalikan daftar usulan kenaikan pangkat sebagai JSON', function (): void {
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    UsulanKenaikanPangkat::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'state' => 'DRAFT',
    ]);

    Sanctum::actingAs($pegawai, ['*']);

    $response = getJson('/api/kenaikan-pangkat/usulan?state=DRAFT&periode_usul_tahun=2026&periode_usul_bulan=4');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.state', 'DRAFT');
});

it('mengembalikan statistik usulan kenaikan pangkat sebagai JSON', function (): void {
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'state' => 'DRAFT',
    ]);
    UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'state' => 'DIAJUKAN',
    ]);

    Sanctum::actingAs($pegawai, ['*']);

    $response = getJson('/api/kenaikan-pangkat/stats?periode_usul_tahun=2026&periode_usul_bulan=4');

    $response->assertSuccessful()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('per_state.DRAFT', 1)
        ->assertJsonPath('per_state.DIAJUKAN', 1)
        ->assertJsonStructure(['total', 'per_state', 'periode']);
});

it('menolak request tanpa auth sanctum', function (): void {
    $response = getJson('/api/kenaikan-pangkat/usulan');

    $response->assertUnauthorized();
});
