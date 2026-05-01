<?php

namespace App\States\Cuti;

class DisetujuiAtasanState extends PengajuanState
{
    public static $name = 'DISETUJUI_ATASAN';

    public function name(): string
    {
        return 'DISETUJUI_ATASAN';
    }

    public function label(): string
    {
        return 'Disetujui Atasan';
    }
}
