<?php

namespace App\Enums;

enum DomainPengajuan: string
{
    case ProfilPribadi = 'profil_pribadi';
    case Pasangan = 'pasangan';
    case Anak = 'anak';
    case OrangTua = 'orang_tua';
    case KeluargaLain = 'keluarga_lain';
}
