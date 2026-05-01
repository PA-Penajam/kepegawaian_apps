<?php

namespace App\Services\Cuti\Rules;

use App\Models\Cuti\CutiPengajuan;

class CutiRuleEngine
{
    /** @var CutiRule[] */
    private array $rules;

    /**
     * @param  CutiRule[]  $rules
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Jalankan semua rule yang berlaku untuk jenis cuti pada pengajuan.
     */
    public function validate(CutiPengajuan $pengajuan): void
    {
        foreach ($this->rules as $rule) {
            if ($rule->applies($pengajuan->jenis_cuti_kode)) {
                $rule->validate($pengajuan);
            }
        }
    }
}
