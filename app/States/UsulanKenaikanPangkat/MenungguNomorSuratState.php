<?php

namespace App\States\UsulanKenaikanPangkat;

class MenungguNomorSuratState extends UsulanKenaikanPangkatState
{
    public static $name = 'MENUNGGU_NOMOR_SURAT';

    public function label(): string
    {
        return 'Menunggu Nomor Surat (SiSukma)';
    }
}
