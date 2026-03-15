<?php

namespace App\Enums;

enum StatusPerkawinan: string
{
    case BelumKawin = 'belum_kawin';
    case Kawin = 'kawin';
    case CeraiHidup = 'cerai_hidup';
    case CeraiMati = 'cerai_mati';

    public function label(): string
    {
        return match ($this) {
            self::BelumKawin => 'Belum Kawin',
            self::Kawin => 'Kawin',
            self::CeraiHidup => 'Cerai Hidup',
            self::CeraiMati => 'Cerai Mati',
        };
    }
}
