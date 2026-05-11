<?php

namespace App\States\UsulanKenaikanPangkat;

class DibatalkanState extends UsulanKenaikanPangkatState
{
    public static $name = 'DIBATALKAN';

    public function label(): string
    {
        return 'Dibatalkan';
    }
}
