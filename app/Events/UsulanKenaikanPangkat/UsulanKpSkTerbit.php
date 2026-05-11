<?php

namespace App\Events\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;

readonly class UsulanKpSkTerbit
{
    public function __construct(public UsulanKenaikanPangkat $usulan) {}
}
