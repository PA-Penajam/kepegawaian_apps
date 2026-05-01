<?php

namespace App\States\Cuti;

class DraftState extends PengajuanState
{
    public static $name = 'DRAFT';

    public function name(): string
    {
        return 'DRAFT';
    }

    public function label(): string
    {
        return 'Draft';
    }
}
