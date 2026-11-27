<?php

namespace App\Services\SuratDinas\DTO;

readonly class RemoteMailStatus
{
    public function __construct(
        public string $publicId,
        public string $status,
        public ?string $agendaNumber = null,
        public ?string $mailNumber = null,
        public ?string $revisionNote = null,
        public ?string $voidReason = null,
        public bool $hasDraft = false,
        public bool $hasLetter = false,
    ) {}
}
