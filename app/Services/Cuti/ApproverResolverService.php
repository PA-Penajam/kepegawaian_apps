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
        $appSlug = (string) config('iam.app_slug', 'kepegawaian');

        return [
            'petugas_kepegawaian' => $this->findApproverNip($pegawaiNip, 'cuti.pengajuan.verify', $appSlug),
            'atasan_langsung' => $this->findApproverNip($pegawaiNip, 'cuti.pengajuan.approve-langsung', $appSlug),
            'pejabat_berwenang' => $this->findApproverNip($pegawaiNip, 'cuti.pengajuan.approve-pejabat', $appSlug),
        ];
    }

    /**
     * Mencari NIP pegawai aktif dengan permission tertentu khusus pada aplikasi kepegawaian.
     */
    private function findApproverNip(string $pegawaiNip, string $permissionSlug, string $appSlug): ?string
    {
        return Pegawai::aktif()
            ->whereHas('iamRoles', function ($roleQuery) use ($appSlug, $permissionSlug): void {
                $roleQuery
                    ->whereHas('application', fn ($appQuery) => $appQuery->where('slug', $appSlug))
                    ->whereHas('permissions', fn ($permQuery) => $permQuery->where('slug', $permissionSlug));
            })
            ->where('nip', '!=', $pegawaiNip)
            ->value('nip');
    }
}
