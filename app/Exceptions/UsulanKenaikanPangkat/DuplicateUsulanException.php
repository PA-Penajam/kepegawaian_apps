<?php

namespace App\Exceptions\UsulanKenaikanPangkat;

use RuntimeException;

class DuplicateUsulanException extends RuntimeException
{
    public function __construct(string $message = 'Usulan aktif untuk pegawai dan periode ini sudah ada.')
    {
        parent::__construct($message);
    }
}
