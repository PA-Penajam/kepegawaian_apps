<?php

namespace Tests\Feature\Http\Controllers\Api\UsulanKenaikanPangkat;

use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function kenaikanPangkatApiSignedHeaders(string $path, array $query = []): array
{
    $timestamp = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $bodyHash = hash('sha256', '[]');
    $payload = "GET:{$path}:{$queryString}:{$bodyHash}:{$timestamp}";

    return [
        'X-Timestamp' => $timestamp,
        'X-Signature' => hash_hmac('sha256', $payload, config('kepegawaian.secret_key')),
        'Accept' => 'application/json',
    ];
}

it('mengembalikan daftar usulan kenaikan pangkat sebagai JSON', function (): void {
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    UsulanKenaikanPangkat::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'state' => 'DRAFT',
    ]);

    Sanctum::actingAs($pegawai, ['app:kepegawaian']);

    $query = [
        'state' => 'DRAFT',
        'periode_usul_tahun' => 2026,
        'periode_usul_bulan' => 4,
    ];
    $path = '/api/kenaikan-pangkat/usulan';
    $response = getJson($path.'?'.http_build_query($query), kenaikanPangkatApiSignedHeaders($path, $query));

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

    Sanctum::actingAs($pegawai, ['app:kepegawaian']);

    $query = [
        'periode_usul_tahun' => 2026,
        'periode_usul_bulan' => 4,
    ];
    $path = '/api/kenaikan-pangkat/stats';
    $response = getJson($path.'?'.http_build_query($query), kenaikanPangkatApiSignedHeaders($path, $query));

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

it('menolak request tanpa signature HMAC', function (): void {
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    Sanctum::actingAs($pegawai, ['app:kepegawaian']);

    $response = getJson('/api/kenaikan-pangkat/usulan');

    $response->assertUnauthorized();
});

it('menolak token tanpa ability app kepegawaian', function (): void {
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    Sanctum::actingAs($pegawai, ['app:attendance']);

    $path = '/api/kenaikan-pangkat/usulan';
    $response = getJson($path, kenaikanPangkatApiSignedHeaders($path));

    $response->assertForbidden();
});
