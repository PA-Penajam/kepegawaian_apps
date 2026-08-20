<?php

namespace App\Services\Sso\DataTransferObjects;

readonly class SsoUserInfo
{
    public function __construct(
        public ?string $sub,
        public ?string $nip,
        public ?string $name,
        public ?string $email,
        public ?string $department = null,
        public ?string $position = null,
        public ?string $securityLevel = null,
    ) {}

    /**
     * Membuat instance SsoUserInfo dari payload response JSON endpoint /api/user.
     *
     * @param  array{sub?: string|null, nip?: string|null, name?: string|null, email?: string|null, department?: string|null, position?: string|null, security_level?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $nip = $data['nip'] ?? $data['sub'] ?? null;

        return new self(
            sub: $data['sub'] ?? $nip,
            nip: $nip,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            department: $data['department'] ?? null,
            position: $data['position'] ?? null,
            securityLevel: $data['security_level'] ?? null,
        );
    }
}
