<?php

namespace App\States\Cuti;

class DisetujuiState extends PengajuanState
{
    public static $name = 'DISETUJUI';

    public function name(): string
    {
        return 'DISETUJUI';
    }

    public function label(): string
    {
        return 'Disetujui';
    }
}
