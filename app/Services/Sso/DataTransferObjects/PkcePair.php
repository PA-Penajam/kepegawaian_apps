<?php

namespace App\Services\Sso\DataTransferObjects;

readonly class PkcePair
{
    public function __construct(
        public string $verifier,
        public string $challenge,
        public string $method = 'S256',
    ) {}
}
