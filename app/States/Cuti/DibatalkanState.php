<?php

namespace App\States\Cuti;

class DibatalkanState extends PengajuanState
{
    public static $name = 'DIBATALKAN';

    public function name(): string
    {
        return 'DIBATALKAN';
    }

    public function label(): string
    {
        return 'Dibatalkan';
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
