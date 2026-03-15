<?php

namespace App\Enums;

enum JenisJabatan: string
{
    case Struktural = 'struktural';
    case Fungsional = 'fungsional';
    case Pelaksana = 'pelaksana';

    public function label(): string
    {
        return match ($this) {
            self::Struktural => 'Struktural',
            self::Fungsional => 'Fungsional',
            self::Pelaksana => 'Pelaksana',
        };
    }
}
