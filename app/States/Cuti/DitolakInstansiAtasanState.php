<?php

namespace App\States\Cuti;

class DitolakInstansiAtasanState extends PengajuanState
{
    public static $name = 'DITOLAK_INSTANSI_ATASAN';

    public function name(): string
    {
        return 'DITOLAK_INSTANSI_ATASAN';
    }

    public function label(): string
    {
        return 'Ditolak Instansi Atasan';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
