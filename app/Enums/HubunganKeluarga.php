<?php

namespace App\Enums;

enum HubunganKeluarga: string
{
    case Suami = 'suami';
    case Istri = 'istri';
    case Anak = 'anak';
    case AyahKandung = 'ayah_kandung';
    case IbuKandung = 'ibu_kandung';

    public function label(): string
    {
        return match ($this) {
            self::Suami => 'Suami',
            self::Istri => 'Istri',
            self::Anak => 'Anak',
            self::AyahKandung => 'Ayah Kandung',
            self::IbuKandung => 'Ibu Kandung',
        };
    }
}
