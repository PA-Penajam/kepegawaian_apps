<?php

namespace App\Exceptions\UsulanKenaikanPangkat;

use RuntimeException;

class PegawaiTidakEligibleException extends RuntimeException
{
    public function __construct(string $message = 'Pegawai tidak memenuhi syarat kenaikan pangkat.')
    {
        parent::__construct($message);
    }
}
