<?php

/**
 * Property-Based Tests untuk ConflictResolution service.
 *
 * Menguji properti universal dari conflict resolution:
 * - Property 12: Pegawai Wins Policy (Req 8.2, 8.3)
 * - Property 13: Conflict Detection Purity (Req 8.5)
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Services\ConflictResolution;
use App\Models\Pegawai;

beforeEach(function () {
    $this->resolver = new ConflictResolution;
});

// ============================================================
// Helper Functions untuk Property Testing
// ============================================================

/**
 * Menghasilkan email acak yang berbeda dari email Pegawai.
 */
function generateRandomDifferentEmail(string $pegawaiEmail): string
{
    $strategies = [
        fn () => fake()->unique()->safeEmail(),
        fn () => 'random'.bin2hex(random_bytes(4)).'@example.com',
        fn () => 'user'.random_int(1000, 9999).'@'.fake()->freeEmailDomain(),
    ];

    $email = $strategies[array_rand($strategies)]();

    // Pastikan email berbeda dari milik Pegawai
    return $email === $pegawaiEmail ? 'different_'.$email : $email;
}

/**
 * Menghasilkan array keycloakUser acak dengan konflik DataMismatch.
 */
function generateKeycloakUserWithDataMismatch(Pegawai $pegawai): array
{
    $namaParts = explode(' ', trim($pegawai->nama_lengkap), 2);
    $firstName = $namaParts[0];
    $lastName = $namaParts[1] ?? '';

    // Pilih setidaknya satu field yang berbeda
    $mismatchType = random_int(1, 7);

    $kcEmail = $pegawai->email;
    $kcFirstName = $firstName;
    $kcLastName = $lastName;

    if ($mismatchType & 1) {
        $kcEmail = generateRandomDifferentEmail($pegawai->email);
    }
    if ($mismatchType & 2) {
        $kcFirstName = fake()->firstName().'X';
    }
    if ($mismatchType & 4) {
        $kcLastName = fake()->lastName().'Y';
    }

    // Pastikan minimal satu field berbeda
    if ($kcEmail === $pegawai->email && $kcFirstName === $firstName && $kcLastName === $lastName) {
        $kcEmail = 'forced_diff_'.bin2hex(random_bytes(3)).'@test.com';
    }

    return [
        'id' => 'kc-'.bin2hex(random_bytes(8)),
        'username' => $pegawai->nip,
        'email' => $kcEmail,
        'firstName' => $kcFirstName,
        'lastName' => $kcLastName,
        'enabled' => $pegawai->status_pegawai === StatusPegawai::Aktif,
        'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
    ];
}

/**
 * Menghasilkan array keycloakUser acak dengan konflik StatusConflict.
 */
function generateKeycloakUserWithStatusConflict(Pegawai $pegawai): array
{
    $namaParts = explode(' ', trim($pegawai->nama_lengkap), 2);
    $isActive = $pegawai->status_pegawai === StatusPegawai::Aktif;

    return [
        'id' => 'kc-'.bin2hex(random_bytes(8)),
        'username' => $pegawai->nip,
        'email' => $pegawai->email,
        'firstName' => $namaParts[0],
        'lastName' => $namaParts[1] ?? '',
        'enabled' => ! $isActive, // Kebalikan dari status Pegawai
        'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
    ];
}

/**
 * Menghasilkan array keycloakUser acak dengan konflik RoleOverride.
 */
function generateKeycloakUserWithRoleOverride(Pegawai $pegawai): array
{
    $namaParts = explode(' ', trim($pegawai->nama_lengkap), 2);
    $pegawaiRoles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

    // Buat roles yang berbeda dari roles Pegawai
    $differentRoles = array_map(
        fn () => 'role_'.bin2hex(random_bytes(3)),
        range(1, random_int(1, 4))
    );

    // Pastikan roles memang berbeda
    sort($differentRoles);
    if ($differentRoles === $pegawaiRoles) {
        $differentRoles[] = 'extra_role_'.bin2hex(random_bytes(2));
    }

    return [
        'id' => 'kc-'.bin2hex(random_bytes(8)),
        'username' => $pegawai->nip,
        'email' => $pegawai->email,
        'firstName' => $namaParts[0],
        'lastName' => $namaParts[1] ?? '',
        'enabled' => $pegawai->status_pegawai === StatusPegawai::Aktif,
        'realmRoles' => $differentRoles,
    ];
}

/**
 * Menghasilkan array keycloakUser acak dengan konflik IdentifierChange.
 */
function generateKeycloakUserWithIdentifierChange(Pegawai $pegawai): array
{
    $namaParts = explode(' ', trim($pegawai->nama_lengkap), 2);

    // Username (NIP) yang berbeda dari NIP Pegawai
    $differentNip = str_pad((string) random_int(100000000000000000, 999999999999999999), 18, '0', STR_PAD_LEFT);
    if ($differentNip === $pegawai->nip) {
        $differentNip = str_pad((string) (random_int(100000000000000000, 999999999999999998) + 1), 18, '0', STR_PAD_LEFT);
    }

    return [
        'id' => 'kc-'.bin2hex(random_bytes(8)),
        'username' => $differentNip,
        'email' => $pegawai->email,
        'firstName' => $namaParts[0],
        'lastName' => $namaParts[1] ?? '',
        'enabled' => $pegawai->status_pegawai === StatusPegawai::Aktif,
        'realmRoles' => $pegawai->iamRoles->pluck('slug')->sort()->values()->all(),
    ];
}

/**
 * Menghasilkan array keycloakUser acak dengan berbagai konflik.
 */
function generateRandomConflictingKeycloakUser(Pegawai $pegawai): array
{
    $generators = [
        fn () => generateKeycloakUserWithDataMismatch($pegawai),
        fn () => generateKeycloakUserWithStatusConflict($pegawai),
        fn () => generateKeycloakUserWithRoleOverride($pegawai),
        fn () => generateKeycloakUserWithIdentifierChange($pegawai),
    ];

    return $generators[array_rand($generators)]();
}

// ============================================================
// Property 12: Pegawai Wins Policy
// **Validates: Requirements 8.2, 8.3**
// ============================================================

describe('Property 12: Pegawai Wins Policy', function () {
    test('resolvedData untuk DataMismatch SELALU berisi email/firstName/lastName dari Pegawai', function () {
        // UNTUK SEMUA conflict resolution DataMismatch,
        // resolvedData SELALU menggunakan data dari Pegawai (bukan Keycloak).
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);
            $pegawai->load('iamRoles');

            $keycloakUser = generateKeycloakUserWithDataMismatch($pegawai);

            $result = $this->resolver->resolve(ConflictType::DataMismatch, $pegawai, $keycloakUser);

            // Expected values dari Pegawai
            $namaParts = explode(' ', trim($pegawai->nama_lengkap), 2);
            $expectedFirstName = $namaParts[0];
            $expectedLastName = $namaParts[1] ?? '';

            // resolvedData HARUS berisi data Pegawai
            expect($result->resolvedData['email'])->toBe($pegawai->email)
                ->and($result->resolvedData['firstName'])->toBe($expectedFirstName)
                ->and($result->resolvedData['lastName'])->toBe($expectedLastName);
        }
    });

    test('resolvedData untuk StatusConflict SELALU berisi enabled sesuai status aktif Pegawai', function () {
        // UNTUK SEMUA conflict resolution StatusConflict,
        // resolvedData enabled SELALU sesuai dengan status aktif Pegawai.
        $statuses = StatusPegawai::cases();

        for ($i = 0; $i < 50; $i++) {
            $status = $statuses[array_rand($statuses)];
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => $status,
            ]);
            $pegawai->load('iamRoles');

            $keycloakUser = generateKeycloakUserWithStatusConflict($pegawai);

            $result = $this->resolver->resolve(ConflictType::StatusConflict, $pegawai, $keycloakUser);

            $expectedEnabled = $status === StatusPegawai::Aktif;

            // resolvedData enabled HARUS sesuai status aktif Pegawai
            expect($result->resolvedData['enabled'])->toBe($expectedEnabled);
        }
    });

    test('resolvedData untuk RoleOverride SELALU berisi roles dari Pegawai', function () {
        // UNTUK SEMUA conflict resolution RoleOverride,
        // resolvedData roles SELALU menggunakan roles dari Pegawai.
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);
            $pegawai->load('iamRoles');

            $keycloakUser = generateKeycloakUserWithRoleOverride($pegawai);

            $result = $this->resolver->resolve(ConflictType::RoleOverride, $pegawai, $keycloakUser);

            $expectedRoles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

            // resolvedData realmRoles HARUS sama dengan roles Pegawai
            expect($result->resolvedData['realmRoles'])->toBe($expectedRoles);
        }
    });

    test('resolvedData untuk IdentifierChange SELALU berisi username dari NIP Pegawai', function () {
        // UNTUK SEMUA conflict resolution IdentifierChange,
        // resolvedData username SELALU menggunakan NIP dari Pegawai.
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);
            $pegawai->load('iamRoles');

            $keycloakUser = generateKeycloakUserWithIdentifierChange($pegawai);

            $result = $this->resolver->resolve(ConflictType::IdentifierChange, $pegawai, $keycloakUser);

            // resolvedData username HARUS sama dengan NIP Pegawai
            expect($result->resolvedData['username'])->toBe($pegawai->nip)
                ->and($result->resolvedData['email'])->toBe($pegawai->email);
        }
    });

    test('resolvedData TIDAK PERNAH berisi nilai dari Keycloak saat ada konflik', function () {
        // UNTUK SEMUA jenis conflict resolution,
        // resolvedData SELALU berisi nilai dari Pegawai, BUKAN dari Keycloak.
        $conflictTypes = ConflictType::cases();

        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);
            $pegawai->load('iamRoles');

            $type = $conflictTypes[array_rand($conflictTypes)];

            // Buat keycloakUser yang sesuai dengan jenis konflik
            $keycloakUser = match ($type) {
                ConflictType::DataMismatch => generateKeycloakUserWithDataMismatch($pegawai),
                ConflictType::StatusConflict => generateKeycloakUserWithStatusConflict($pegawai),
                ConflictType::RoleOverride => generateKeycloakUserWithRoleOverride($pegawai),
                ConflictType::IdentifierChange => generateKeycloakUserWithIdentifierChange($pegawai),
            };

            $result = $this->resolver->resolve($type, $pegawai, $keycloakUser);

            // Verifikasi resolvedData mengandung nilai Pegawai
            $namaParts = explode(' ', trim($pegawai->nama_lengkap), 2);
            $expectedFirstName = $namaParts[0];
            $expectedLastName = $namaParts[1] ?? '';

            match ($type) {
                ConflictType::DataMismatch => expect($result->resolvedData)
                    ->toHaveKey('email', $pegawai->email)
                    ->toHaveKey('firstName', $expectedFirstName)
                    ->toHaveKey('lastName', $expectedLastName),
                ConflictType::StatusConflict => expect($result->resolvedData)
                    ->toHaveKey('enabled', true),
                ConflictType::RoleOverride => expect($result->resolvedData)
                    ->toHaveKey('realmRoles', $pegawai->iamRoles->pluck('slug')->sort()->values()->all()),
                ConflictType::IdentifierChange => expect($result->resolvedData)
                    ->toHaveKey('username', $pegawai->nip)
                    ->toHaveKey('email', $pegawai->email),
            };
        }
    });
});

// ============================================================
// Property 13: Conflict Detection Purity
// **Validates: Requirement 8.5**
// ============================================================

describe('Property 13: Conflict Detection Purity', function () {
    test('atribut model Pegawai TIDAK PERNAH dimodifikasi setelah detectConflicts', function () {
        // UNTUK SEMUA pemanggilan detectConflicts,
        // atribut Pegawai TIDAK PERNAH berubah setelah deteksi.
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => fake()->randomElement(StatusPegawai::cases()),
            ]);
            $pegawai->load('iamRoles');

            // Simpan snapshot atribut sebelum deteksi
            $originalNip = $pegawai->nip;
            $originalEmail = $pegawai->email;
            $originalNama = $pegawai->nama_lengkap;
            $originalStatus = $pegawai->status_pegawai;
            $originalRoles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

            $keycloakUser = generateRandomConflictingKeycloakUser($pegawai);

            // Jalankan deteksi konflik
            $this->resolver->detectConflicts($pegawai, $keycloakUser);

            // Verifikasi semua atribut tetap sama
            expect($pegawai->nip)->toBe($originalNip)
                ->and($pegawai->email)->toBe($originalEmail)
                ->and($pegawai->nama_lengkap)->toBe($originalNama)
                ->and($pegawai->status_pegawai)->toBe($originalStatus)
                ->and($pegawai->iamRoles->pluck('slug')->sort()->values()->all())->toBe($originalRoles);
        }
    });

    test('array keycloakUser TIDAK PERNAH dimodifikasi setelah detectConflicts', function () {
        // UNTUK SEMUA pemanggilan detectConflicts,
        // array keycloakUser TIDAK PERNAH berubah setelah deteksi.
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);
            $pegawai->load('iamRoles');

            $keycloakUser = generateRandomConflictingKeycloakUser($pegawai);

            // Simpan deep copy sebelum deteksi
            $originalKeycloakUser = $keycloakUser;

            // Jalankan deteksi konflik
            $this->resolver->detectConflicts($pegawai, $keycloakUser);

            // Verifikasi array keycloakUser tidak berubah
            expect($keycloakUser)->toBe($originalKeycloakUser);
        }
    });

    test('detectConflicts menghasilkan hasil yang SAMA untuk input yang SAMA (deterministic)', function () {
        // UNTUK SEMUA pemanggilan detectConflicts dengan input yang sama,
        // hasilnya SELALU identik (fungsi murni/pure function).
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => fake()->randomElement(StatusPegawai::cases()),
            ]);
            $pegawai->load('iamRoles');

            $keycloakUser = generateRandomConflictingKeycloakUser($pegawai);

            // Panggil detectConflicts beberapa kali dengan input yang sama
            $result1 = $this->resolver->detectConflicts($pegawai, $keycloakUser);
            $result2 = $this->resolver->detectConflicts($pegawai, $keycloakUser);
            $result3 = $this->resolver->detectConflicts($pegawai, $keycloakUser);

            // Semua hasil harus identik
            expect($result1)->toBe($result2)
                ->and($result2)->toBe($result3);
        }
    });
});
