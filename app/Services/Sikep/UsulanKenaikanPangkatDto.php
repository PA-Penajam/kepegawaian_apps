<?php

declare(strict_types=1);

namespace App\Services\Sikep;

readonly class UsulanKenaikanPangkatDto
{
    public function __construct(
        public string $nip,
        public string $nama_lengkap,
        public string $pangkat_asal_kode,
        public string $pangkat_tujuan_kode,
        public string $tmt_pangkat_asal,
        public int $periode_bulan,
        public int $periode_tahun,
        public ?string $nomor_usulan = null,
    ) {}
}
