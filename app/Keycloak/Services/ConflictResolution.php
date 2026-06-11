<?php

namespace App\Keycloak\Services;

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\ConflictResolutionInterface;
use App\Keycloak\DataTransferObjects\ConflictResult;
use App\Keycloak\Enums\ConflictPolicy;
use App\Keycloak\Enums\ConflictType;
use App\Models\Pegawai;

/**
 * Implementasi service untuk deteksi dan resolusi konflik data
 * antara Pegawai (source of truth) dan Keycloak user.
 *
 * Menggunakan kebijakan "Pegawai Wins" dimana data Pegawai
 * selalu menjadi acuan utama saat terjadi perbedaan data.
 */
class ConflictResolution implements ConflictResolutionInterface
{
    /**
     * Deteksi konflik antara data Pegawai dan Keycloak user.
     *
     * Membandingkan email, firstName, lastName, enabled status, dan roles.
     * Method ini merupakan pure function yang tidak memutasi input.
     *
     * @param  array<string, mixed>  $keycloakUser
     * @return array<int, ConflictType>
     */
    public function detectConflicts(Pegawai $pegawai, array $keycloakUser): array
    {
        $conflicts = [];

        // DataMismatch: email, firstName, atau lastName berbeda
        if ($this->hasDataMismatch($pegawai, $keycloakUser)) {
            $conflicts[] = ConflictType::DataMismatch;
        }

        // StatusConflict: status aktif Pegawai berbeda dengan enabled flag Keycloak
        if ($this->hasStatusConflict($pegawai, $keycloakUser)) {
            $conflicts[] = ConflictType::StatusConflict;
        }

        // RoleOverride: role mappings Pegawai berbeda dengan realm roles Keycloak
        if ($this->hasRoleOverride($pegawai, $keycloakUser)) {
            $conflicts[] = ConflictType::RoleOverride;
        }

        // IdentifierChange: NIP berbeda dari username atau email berubah
        if ($this->hasIdentifierChange($pegawai, $keycloakUser)) {
            $conflicts[] = ConflictType::IdentifierChange;
        }

        return $conflicts;
    }

    /**
     * Resolve konflik dengan menerapkan kebijakan "Pegawai Wins".
     *
     * Menghasilkan ConflictResult berisi data Pegawai (source),
     * data Keycloak saat ini, dan data resolved yang harus ditulis ke Keycloak.
     *
     * @param  array<string, mixed>|null  $keycloakUser
     */
    public function resolve(ConflictType $type, Pegawai $pegawai, ?array $keycloakUser): ConflictResult
    {
        $pegawaiData = $this->buildPegawaiData($pegawai);
        $keycloakData = $this->buildKeycloakData($keycloakUser);
        $resolvedData = $this->buildResolvedData($type, $pegawai);

        return new ConflictResult(
            type: $type,
            pegawaiData: $pegawaiData,
            keycloakData: $keycloakData,
            resolvedData: $resolvedData,
            policy: $this->getPolicy(),
        );
    }

    /**
     * Dapatkan policy yang aktif (selalu Pegawai Wins).
     */
    public function getPolicy(): ConflictPolicy
    {
        return ConflictPolicy::PegawaiWins;
    }

    /**
     * Cek apakah terdapat perbedaan data (email, firstName, lastName).
     *
     * @param  array<string, mixed>  $keycloakUser
     */
    private function hasDataMismatch(Pegawai $pegawai, array $keycloakUser): bool
    {
        [$firstName, $lastName] = $this->splitNamaLengkap($pegawai->nama_lengkap);

        $emailMismatch = $pegawai->email !== ($keycloakUser['email'] ?? null);
        $firstNameMismatch = $firstName !== ($keycloakUser['firstName'] ?? null);
        $lastNameMismatch = $lastName !== ($keycloakUser['lastName'] ?? null);

        return $emailMismatch || $firstNameMismatch || $lastNameMismatch;
    }

    /**
     * Cek apakah status aktif Pegawai berbeda dari enabled flag Keycloak.
     *
     * @param  array<string, mixed>  $keycloakUser
     */
    private function hasStatusConflict(Pegawai $pegawai, array $keycloakUser): bool
    {
        $isActive = $pegawai->status_pegawai === StatusPegawai::Aktif;
        $isEnabled = $keycloakUser['enabled'] ?? false;

        return $isActive !== $isEnabled;
    }

    /**
     * Cek apakah role mappings Pegawai berbeda dari realm roles Keycloak.
     *
     * @param  array<string, mixed>  $keycloakUser
     */
    private function hasRoleOverride(Pegawai $pegawai, array $keycloakUser): bool
    {
        $pegawaiRoles = $this->getPegawaiRoleSlugs($pegawai);
        $keycloakRoles = $keycloakUser['realmRoles'] ?? [];

        sort($pegawaiRoles);
        sort($keycloakRoles);

        return $pegawaiRoles !== $keycloakRoles;
    }

    /**
     * Cek apakah NIP berbeda dari username Keycloak.
     *
     * @param  array<string, mixed>  $keycloakUser
     */
    private function hasIdentifierChange(Pegawai $pegawai, array $keycloakUser): bool
    {
        return $pegawai->nip !== ($keycloakUser['username'] ?? null);
    }

    /**
     * Bangun array data Pegawai untuk snapshot dalam ConflictResult.
     *
     * @return array<string, mixed>
     */
    private function buildPegawaiData(Pegawai $pegawai): array
    {
        [$firstName, $lastName] = $this->splitNamaLengkap($pegawai->nama_lengkap);

        return [
            'nip' => $pegawai->nip,
            'email' => $pegawai->email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'enabled' => $pegawai->status_pegawai === StatusPegawai::Aktif,
            'roles' => $this->getPegawaiRoleSlugs($pegawai),
        ];
    }

    /**
     * Bangun array data Keycloak saat ini untuk snapshot dalam ConflictResult.
     *
     * @param  array<string, mixed>|null  $keycloakUser
     * @return array<string, mixed>
     */
    private function buildKeycloakData(?array $keycloakUser): array
    {
        if ($keycloakUser === null) {
            return [];
        }

        return [
            'username' => $keycloakUser['username'] ?? null,
            'email' => $keycloakUser['email'] ?? null,
            'firstName' => $keycloakUser['firstName'] ?? null,
            'lastName' => $keycloakUser['lastName'] ?? null,
            'enabled' => $keycloakUser['enabled'] ?? false,
            'roles' => $keycloakUser['realmRoles'] ?? [],
        ];
    }

    /**
     * Bangun data resolved berdasarkan jenis konflik (Pegawai Wins).
     *
     * Data ini yang harus ditulis ke Keycloak setelah resolusi.
     *
     * @return array<string, mixed>
     */
    private function buildResolvedData(ConflictType $type, Pegawai $pegawai): array
    {
        [$firstName, $lastName] = $this->splitNamaLengkap($pegawai->nama_lengkap);

        return match ($type) {
            ConflictType::DataMismatch => [
                'email' => $pegawai->email,
                'firstName' => $firstName,
                'lastName' => $lastName,
            ],
            ConflictType::StatusConflict => [
                'enabled' => $pegawai->status_pegawai === StatusPegawai::Aktif,
            ],
            ConflictType::RoleOverride => [
                'realmRoles' => $this->getPegawaiRoleSlugs($pegawai),
            ],
            ConflictType::IdentifierChange => [
                'username' => $pegawai->nip,
                'email' => $pegawai->email,
            ],
        };
    }

    /**
     * Split nama_lengkap menjadi firstName dan lastName.
     *
     * Menggunakan kata pertama sebagai firstName dan sisanya sebagai lastName.
     * Jika hanya satu kata, lastName akan berupa string kosong.
     *
     * @return array{0: string, 1: string}
     */
    private function splitNamaLengkap(?string $namaLengkap): array
    {
        if ($namaLengkap === null || $namaLengkap === '') {
            return ['', ''];
        }

        $parts = explode(' ', trim($namaLengkap), 2);

        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        return [$firstName, $lastName];
    }

    /**
     * Ambil daftar role slugs dari Pegawai.
     *
     * @return array<int, string>
     */
    private function getPegawaiRoleSlugs(Pegawai $pegawai): array
    {
        return $pegawai->iamRoles->pluck('slug')->sort()->values()->all();
    }
}
