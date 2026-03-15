<?php

namespace App\Enums;

enum StatusPegawai: string
{
    case Aktif = 'aktif';
    case MutasiKeluar = 'mutasi_keluar';
    case Pensiun = 'pensiun';
    case Meninggal = 'meninggal';
    case Diberhentikan = 'diberhentikan';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::MutasiKeluar => 'Mutasi Keluar',
            self::Pensiun => 'Pensiun',
            self::Meninggal => 'Meninggal',
            self::Diberhentikan => 'Diberhentikan',
        };
    }
}
