<?php

namespace App\States\Cuti;

class DiajukanState extends PengajuanState
{
    public static $name = 'DIAJUKAN';

    public function name(): string
    {
        return 'DIAJUKAN';
    }

    public function label(): string
    {
        return 'Diajukan';
    }
}
