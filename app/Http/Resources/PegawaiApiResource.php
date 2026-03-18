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
     * @param  \Illuminate\Http\Request  $request
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
            'foto_url' => $this->foto ? asset('storage/' . $this->foto) : null,
        ];
    }
}
