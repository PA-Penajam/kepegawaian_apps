<?php

namespace App\States\UsulanKenaikanPangkat;

class SelesaiSkTerbitState extends UsulanKenaikanPangkatState
{
    public static $name = 'SELESAI_SK_TERBIT';

    public function label(): string
    {
        return 'Selesai SK Terbit';
    }
}
