<?php

namespace App\States\UsulanKenaikanPangkat;

class DiajukanState extends UsulanKenaikanPangkatState
{
    public static $name = 'DIAJUKAN';

    public function label(): string
    {
        return 'Diajukan';
    }
}
