<?php

namespace App\States\Cuti;

class DitolakAtasanState extends PengajuanState
{
    public static $name = 'DITOLAK_ATASAN';

    public function name(): string
    {
        return 'DITOLAK_ATASAN';
    }

    public function label(): string
    {
        return 'Ditolak Atasan';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
