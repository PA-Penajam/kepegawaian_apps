<?php

namespace App\Enums;

enum StatusKepegawaian: string
{
    case PNS = 'pns';
    case PPPK = 'pppk';
    case Honorer = 'honorer';

    public function label(): string
    {
        return match ($this) {
            self::PNS => 'PNS',
            self::PPPK => 'PPPK',
            self::Honorer => 'Honorer',
        };
    }
}
