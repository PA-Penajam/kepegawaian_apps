<?php

namespace App\States\UsulanKenaikanPangkat;

class PerluPerbaikanState extends UsulanKenaikanPangkatState
{
    public static $name = 'PERLU_PERBAIKAN';

    public function label(): string
    {
        return 'Perlu Perbaikan';
    }
}
