<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;

interface CutiRule
{
    /**
     * Apakah rule ini berlaku untuk jenis cuti tertentu.
     */
    public function applies(string $jenisKode): bool;

    /**
     * Validasi pengajuan cuti, throw exception jika gagal.
     */
    public function validate(CutiPengajuan $pengajuan): void;
}
