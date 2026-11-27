<?php

namespace App\Services\Cuti;

use App\Enums\JenisJabatan;
use App\Models\Cuti\CutiKewenangan;
use App\Models\Pegawai;

/**
 * Menentukan apakah kombinasi jenis cuti dan jenis jabatan pegawai memerlukan izin PTA/MA.
 */
class KewenanganResolverService
{
    /**
     * Mengembalikan aturan kewenangan aktif yang cocok, atau null bila cukup diputus internal PA.
     */
    public function resolve(Pegawai $pegawai, string $jenisCutiKode): ?CutiKewenangan
    {
        $pegawai->loadMissing('jabatan');
        $jenisJabatan = $pegawai->jabatan?->jenis_jabatan;

        if (! $jenisJabatan instanceof JenisJabatan) {
            return null;
        }

        return CutiKewenangan::query()
            ->where('jenis_cuti_kode', $jenisCutiKode)
            ->where('jenis_jabatan', $jenisJabatan->value)
            ->where('aktif', true)
            ->first();
    }
}
