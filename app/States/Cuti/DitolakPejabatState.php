<?php

namespace App\States\Cuti;

class DitolakPejabatState extends PengajuanState
{
    public static $name = 'DITOLAK_PEJABAT';

    public function name(): string
    {
        return 'DITOLAK_PEJABAT';
    }

    public function label(): string
    {
        return 'Ditolak Pejabat';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
