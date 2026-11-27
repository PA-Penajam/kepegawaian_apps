<?php

namespace App\Services\SuratDinas\DTO;

readonly class RegistrationResult
{
    public function __construct(
        public string $publicId,
        public string $agendaNumber,
        public string $status,
    ) {}
}
