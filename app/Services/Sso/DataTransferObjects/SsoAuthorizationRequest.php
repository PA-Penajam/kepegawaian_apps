<?php

namespace App\Services\Sso\DataTransferObjects;

readonly class SsoAuthorizationRequest
{
    public function __construct(
        public string $url,
        public string $state,
        public PkcePair $pkce,
    ) {}
}
