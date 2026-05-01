<?php

namespace App\States\Cuti;

class DiverifikasiState extends PengajuanState
{
    public static $name = 'DIVERIFIKASI';

    public function name(): string
    {
        return 'DIVERIFIKASI';
    }

    public function label(): string
    {
        return 'Diverifikasi';
    }
}
