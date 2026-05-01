<?php

namespace App\States\Cuti;

class DicabutSetelahDisetujuiState extends PengajuanState
{
    public static $name = 'DICABUT_SETELAH_DISETUJUI';

    public function name(): string
    {
        return 'DICABUT_SETELAH_DISETUJUI';
    }

    public function label(): string
    {
        return 'Dicabut Setelah Disetujui';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
