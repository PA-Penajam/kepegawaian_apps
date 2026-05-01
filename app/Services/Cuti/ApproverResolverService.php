<?php

namespace App\Services\Cuti;

use App\Models\Pegawai;

class ApproverResolverService
{
    /**
     * Resolve snapshot approver chain untuk pegawai.
     * MVP: Lookup berdasarkan permission yang dimiliki pegawai lain.
     *
     * @return array{petugas_kepegawaian: ?string, atasan_langsung: ?string, pejabat_berwenang: ?string}
     */
    public function resolveSnapshot(string $pegawaiNip): array
    {
        // Petugas kepegawaian = pegawai yang punya permission 'cuti.pengajuan.verify'
        $petugas = Pegawai::aktif()
            ->whereHas('iamRoles.permissions', function ($q) {
                $q->where('slug', 'cuti.pengajuan.verify');
            })
            ->where('nip', '!=', $pegawaiNip)
            ->first();

        // Atasan langsung = pegawai yang punya permission 'cuti.pengajuan.approve-langsung'
        $atasan = Pegawai::aktif()
            ->whereHas('iamRoles.permissions', function ($q) {
                $q->where('slug', 'cuti.pengajuan.approve-langsung');
            })
            ->where('nip', '!=', $pegawaiNip)
            ->first();

        // Pejabat berwenang = pegawai yang punya permission 'cuti.pengajuan.approve-pejabat'
        $pejabat = Pegawai::aktif()
            ->whereHas('iamRoles.permissions', function ($q) {
                $q->where('slug', 'cuti.pengajuan.approve-pejabat');
            })
            ->where('nip', '!=', $pegawaiNip)
            ->first();

        return [
            'petugas_kepegawaian' => $petugas?->nip,
            'atasan_langsung' => $atasan?->nip,
            'pejabat_berwenang' => $pejabat?->nip,
        ];
    }
}
