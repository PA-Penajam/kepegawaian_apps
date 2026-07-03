<?php

namespace App\Keycloak\DataTransferObjects;

/**
 * DTO untuk claims yang diekstrak dari ID token JWT.
 *
 * Berisi informasi identitas pengguna termasuk NIP,
 * email, roles, permissions, dan metadata token.
 */
readonly class IdTokenClaims
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public string $sub,
        public string $nip,
        public string $email,
        public string $name,
        public array $roles,
        public array $permissions,
        public int $exp,
        public int $iat,
        public string $iss,
    ) {}
}
