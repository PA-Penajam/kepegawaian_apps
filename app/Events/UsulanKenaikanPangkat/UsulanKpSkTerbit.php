<?php

namespace App\Events\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;

readonly class UsulanKpSkTerbit
{
    public function __construct(
        public UsulanKenaikanPangkat $usulan,
        public ?string $tmtPangkatBaru = null,
        public ?int $masaKerjaTahun = null,
        public ?int $masaKerjaBulan = null,
    ) {}
}
