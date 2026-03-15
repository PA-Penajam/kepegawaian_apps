<?php

namespace App\Enums;

enum HubunganKeluarga: string
{
    case Suami = 'Suami';
    case Istri = 'Istri';
    case Anak = 'Anak';
    case AyahKandung = 'AyahKandung';
    case IbuKandung = 'IbuKandung';

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
