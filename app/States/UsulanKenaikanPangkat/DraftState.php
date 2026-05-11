<?php

namespace App\States\UsulanKenaikanPangkat;

class DraftState extends UsulanKenaikanPangkatState
{
    public static $name = 'DRAFT';

    public function label(): string
    {
        return 'Draft';
    }
}
