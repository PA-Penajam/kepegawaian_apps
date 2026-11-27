<?php

namespace App\States\Cuti;

class DiteruskanInstansiAtasanState extends PengajuanState
{
    public static $name = 'DITERUSKAN_INSTANSI_ATASAN';

    public function name(): string
    {
        return 'DITERUSKAN_INSTANSI_ATASAN';
    }

    public function label(): string
    {
        return 'Diteruskan ke Instansi Atasan';
    }
}
