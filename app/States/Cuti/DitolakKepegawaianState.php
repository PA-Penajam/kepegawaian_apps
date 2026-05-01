<?php

namespace App\States\Cuti;

class DitolakKepegawaianState extends PengajuanState
{
    public static $name = 'DITOLAK_KEPEGAWAIAN';

    public function name(): string
    {
        return 'DITOLAK_KEPEGAWAIAN';
    }

    public function label(): string
    {
        return 'Ditolak Kepegawaian';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
