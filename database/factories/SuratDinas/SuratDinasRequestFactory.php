<?php

namespace Database\Factories\SuratDinas;

use App\Enums\StatusSuratDinas;
use App\Models\Cuti\CutiPengajuan;
use App\Models\SuratDinas\SuratDinasRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SuratDinasRequest>
 */
class SuratDinasRequestFactory extends Factory
{
    protected $model = SuratDinasRequest::class;

    /**
     * Definisi default baris permintaan surat dinas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suratable_type' => (new CutiPengajuan)->getMorphClass(),
            'suratable_id' => (string) Str::ulid(),
            'jenis' => 'surat_izin_cuti',
            'klasifikasi' => 'KP5.3',
            'penandatangan' => 'ketua',
            'document_type' => 'surat_dinas',
            'perihal' => 'Surat Izin Cuti Tahunan',
            'tujuan_nama' => fake()->name(),
            'tujuan_instansi' => 'Pengadilan Agama Penajam',
            'status' => StatusSuratDinas::BelumTerkirim,
            'requested_by_nip' => '199001012020121001',
            'attempts' => 0,
        ];
    }

    public function menungguNomor(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusSuratDinas::MenungguNomor,
            'sisukma_public_id' => (string) Str::ulid(),
            'nomor_agenda' => fake()->numberBetween(1, 999).'/'.now()->year,
        ]);
    }

    public function nomorTerbit(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusSuratDinas::NomorTerbit,
            'sisukma_public_id' => (string) Str::ulid(),
            'nomor_agenda' => fake()->numberBetween(1, 999).'/'.now()->year,
            'nomor_surat' => fake()->unique()->numberBetween(1, 999).'/KPA.W17-A8/KP5.3/XI/'.now()->year,
        ]);
    }

    public function finalTersedia(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusSuratDinas::FinalTersedia,
            'sisukma_public_id' => (string) Str::ulid(),
            'nomor_agenda' => fake()->numberBetween(1, 999).'/'.now()->year,
            'nomor_surat' => fake()->unique()->numberBetween(1, 999).'/KPA.W17-A8/KP5.3/XI/'.now()->year,
            'final_checksum' => hash('sha256', (string) Str::uuid()),
            'final_size_bytes' => 1024,
            'final_uploaded_at' => now(),
        ]);
    }
}
