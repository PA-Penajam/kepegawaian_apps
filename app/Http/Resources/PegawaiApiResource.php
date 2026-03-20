<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk transformasi data Pegawai ke format API response.
 *
 * Field mapping:
 * - nip → nip (langsung)
 * - nama_lengkap → nama (rename untuk API consumer)
 * - jabatan->nama → jabatan (nama dari relasi)
 * - unitKerja->nama → unit_kerja (nama dari relasi)
 * - status_pegawai → status_pegawai (enum value)
 * - foto → foto_url (via asset helper)
 */
class PegawaiApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nip' => $this->nip,
            'nama' => $this->nama_lengkap,
            'jabatan' => $this->jabatan?->nama ?? null,
            'unit_kerja' => $this->unitKerja?->nama ?? null,
            'status_pegawai' => $this->status_pegawai?->value ?? null,
            'foto_url' => $this->foto ? asset('storage/'.$this->foto) : null,
            'pangkat_nama' => $this->pangkat?->nama ?? null,
            'pangkat_kode' => $this->pangkat?->kode ?? null,
            'pangkat_golongan' => $this->pangkat
                ? "{$this->pangkat->nama} / {$this->pangkat->golongan}/{$this->pangkat->ruang}"
                : null,
            'tingkat_perjalanan' => $this->resolveTingkatPerjalanan(),
            'no_telepon' => $this->no_telepon,
            'email' => $this->email,
        ];
    }

    private function resolveTingkatPerjalanan(): ?string
    {
        if (! $this->pangkat) {
            return null;
        }

        $kode = strtoupper("{$this->pangkat->golongan}/{$this->pangkat->ruang}");

        return match (true) {
            in_array($kode, ['IV/C', 'IV/D', 'IV/E'], true) => 'A',
            in_array($kode, ['III/C', 'III/D', 'IV/A', 'IV/B'], true) => 'B',
            default => 'C',
        };
    }
}
