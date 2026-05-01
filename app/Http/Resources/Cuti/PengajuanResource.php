<?php

namespace App\Http\Resources\Cuti;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk transformasi data CutiPengajuan ke format API response.
 */
class PengajuanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_pengajuan' => $this->nomor_pengajuan,
            'pegawai_nip' => $this->pegawai_nip,
            'jenis_cuti_kode' => $this->jenis_cuti_kode,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'jumlah_hari_kerja' => $this->jumlah_hari_kerja,
            'alasan' => $this->alasan,
            'state' => $this->state?->name(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'pegawai' => $this->when(
                $this->relationLoaded('pegawai') && $this->pegawai,
                fn () => [
                    'nip' => $this->pegawai->nip,
                    'nama' => $this->pegawai->nama_lengkap,
                ],
            ),
            'jenis_cuti' => $this->when(
                $this->relationLoaded('jenisCuti') && $this->jenisCuti,
                fn () => [
                    'kode' => $this->jenisCuti->kode,
                    'nama' => $this->jenisCuti->nama,
                ],
            ),
            'lampiran' => $this->when(
                $this->relationLoaded('lampiran'),
                fn () => $this->lampiran->map(fn ($l) => [
                    'id' => $l->id,
                    'jenis_lampiran' => $l->jenis_lampiran,
                    'nama_file_asli' => $l->nama_file_asli,
                    'mime_type' => $l->mime_type,
                    'size_bytes' => $l->size_bytes,
                ]),
            ),
        ];
    }
}
