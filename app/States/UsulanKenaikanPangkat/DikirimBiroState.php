<?php

namespace App\States\UsulanKenaikanPangkat;

class DikirimBiroState extends UsulanKenaikanPangkatState
{
    public static $name = 'DIKIRIM_BIRO';

    public function label(): string
    {
        return 'Dikirim Biro';
    }
}
