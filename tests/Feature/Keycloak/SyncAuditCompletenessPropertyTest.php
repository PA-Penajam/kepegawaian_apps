<?php

/**
 * Property-Based Tests untuk Sync Audit Completeness.
 *
 * Menguji properti universal dari audit logging:
 * - Property 14: Sync Audit Completeness (Req 8.4, 9.1, 9.2, 9.3)
 *
 * Memastikan bahwa UNTUK SETIAP operasi sync (create, conflict, failure),
 * audit log SELALU lengkap dengan event_type, NIP, dan data relevan.
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Keycloak\Services\SyncAuditLogger;
use App\Models\Pegawai;

beforeEach(function () {
    $this->logger = new SyncAuditLogger;
});

// ============================================================
// Helper Functions untuk Property Testing
// ============================================================

/**
 * Menghasilkan string error acak dengan panjang bervariasi.
 */
function generateRandomErrorDetails(): string
{
    $length = random_int(10, 2000);

    return fake()->realText($length);
}

/**
 * Menghasilkan conflict type acak.
 */
function generateRandomConflictType(): ConflictType
{
    $types = ConflictType::cases();

    return $types[array_rand($types)];
}

/**
 * Menghasilkan pegawai snapshot acak.
 *
 * @return array<string, mixed>
 */
function generateRandomPegawaiSnapshot(): array
{
    return [
        'nip' => fake()->numerify('##################'),
        'email' => fake()->safeEmail(),
        'nama_lengkap' => fake()->name(),
        'status' => fake()->randomElement(['aktif', 'non-aktif', 'pensiun']),
        'extra_field_'.bin2hex(random_bytes(3)) => fake()->sentence(),
    ];
}

/**
 * Menghasilkan keycloak snapshot acak.
 *
 * @return array<string, mixed>
 */
function generateRandomKeycloakSnapshot(): array
{
    return [
        'id' => 'kc-'.bin2hex(random_bytes(8)),
        'username' => fake()->numerify('##################'),
        'email' => fake()->safeEmail(),
        'firstName' => fake()->firstName(),
        'lastName' => fake()->lastName(),
        'enabled' => fake()->boolean(),
        'realmRoles' => array_map(
            fn () => 'role_'.bin2hex(random_bytes(3)),
            range(1, random_int(1, 4))
        ),
    ];
}

/**
 * Menghasilkan resolution data acak.
 *
 * @return array<string, mixed>
 */
function generateRandomResolution(): array
{
    return [
        'email' => fake()->safeEmail(),
        'firstName' => fake()->firstName(),
        'lastName' => fake()->lastName(),
        'enabled' => fake()->boolean(),
    ];
}

// ============================================================
// Property 14: Sync Audit Completeness
// **Validates: Requirements 8.4, 9.1, 9.2, 9.3**
// ============================================================

describe('Property 14: Sync Audit Completeness', function () {
    test('logCreate SELALU menghasilkan audit entry dengan event_type=create, pegawai_id, nip, dan pegawai_snapshot', function () {
        // **Validates: Requirements 1.2**
        // UNTUK SEMUA Pegawai yang random, logCreate SELALU menghasilkan
        // audit entry lengkap dengan semua field yang diperlukan (Req 9.1).
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => fake()->randomElement(StatusPegawai::cases()),
            ]);

            $this->logger->logCreate($pegawai);

            $audit = KeycloakSyncAudit::where('pegawai_id', $pegawai->id)
                ->where('event_type', 'create')
                ->latest('id')
                ->first();

            // Audit entry HARUS selalu ada
            expect($audit)->not->toBeNull()
                // event_type HARUS 'create'
                ->and($audit->event_type)->toBe('create')
                // pegawai_id HARUS sesuai
                ->and($audit->pegawai_id)->toBe($pegawai->id)
                // nip HARUS ada dan sesuai
                ->and($audit->nip)->toBe($pegawai->nip)
                ->and($audit->nip)->not->toBeEmpty()
                // pegawai_snapshot HARUS berupa array (bukan null)
                ->and($audit->pegawai_snapshot)->toBeArray()
                ->and($audit->pegawai_snapshot)->not->toBeEmpty()
                // Snapshot HARUS berisi NIP yang sesuai
                ->and($audit->pegawai_snapshot['nip'])->toBe($pegawai->nip)
                // resolved_by HARUS ada
                ->and($audit->resolved_by)->not->toBeEmpty();
        }
    });

    test('logCreate dengan causedBy SELALU menghasilkan caused_by dan caused_by_nip yang lengkap', function () {
        // **Validates: Requirements 1.2**
        // UNTUK SEMUA logCreate dengan causedBy, audit entry SELALU
        // mencatat siapa yang memicu operasi (Req 9.1: caused_by).
        for ($i = 0; $i < 30; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);
            $causedBy = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            $this->logger->logCreate($pegawai, $causedBy);

            $audit = KeycloakSyncAudit::where('pegawai_id', $pegawai->id)
                ->where('event_type', 'create')
                ->latest('id')
                ->first();

            // caused_by HARUS terisi sesuai ID Pegawai yang memicu
            expect($audit->caused_by)->toBe($causedBy->id)
                // caused_by_nip HARUS sesuai NIP Pegawai yang memicu
                ->and($audit->caused_by_nip)->toBe($causedBy->nip);
        }
    });

    test('logConflict SELALU menghasilkan audit entry lengkap dengan conflict_type, snapshots, resolution, dan resolved_by', function () {
        // **Validates: Requirements 1.2**
        // UNTUK SEMUA conflict event, audit entry SELALU memiliki
        // event_type='conflict', conflict_type, kedua snapshots, resolution,
        // dan resolved_by (Req 8.4, 9.2).
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => fake()->randomElement(StatusPegawai::cases()),
            ]);

            $conflictType = generateRandomConflictType();
            $pegawaiSnapshot = generateRandomPegawaiSnapshot();
            $keycloakSnapshot = generateRandomKeycloakSnapshot();
            $resolution = generateRandomResolution();
            $resolvedBy = fake()->randomElement(['system', fake()->numerify('##################')]);

            $this->logger->logConflict(
                $pegawai,
                $conflictType,
                $pegawaiSnapshot,
                $keycloakSnapshot,
                $resolution,
                $resolvedBy,
            );

            $audit = KeycloakSyncAudit::where('pegawai_id', $pegawai->id)
                ->where('event_type', 'conflict')
                ->latest('id')
                ->first();

            // Audit entry HARUS selalu ada
            expect($audit)->not->toBeNull()
                // event_type HARUS 'conflict'
                ->and($audit->event_type)->toBe('conflict')
                // pegawai_id dan nip HARUS sesuai
                ->and($audit->pegawai_id)->toBe($pegawai->id)
                ->and($audit->nip)->toBe($pegawai->nip)
                // conflict_type HARUS ada dan sesuai
                ->and($audit->conflict_type)->toBe($conflictType->value)
                ->and($audit->conflict_type)->not->toBeEmpty()
                // pegawai_snapshot HARUS ada (array)
                ->and($audit->pegawai_snapshot)->toBeArray()
                ->and($audit->pegawai_snapshot)->not->toBeEmpty()
                // keycloak_snapshot HARUS ada (array)
                ->and($audit->keycloak_snapshot)->toBeArray()
                ->and($audit->keycloak_snapshot)->not->toBeEmpty()
                // resolution HARUS ada (array)
                ->and($audit->resolution)->toBeArray()
                ->and($audit->resolution)->not->toBeEmpty()
                // resolved_by HARUS ada
                ->and($audit->resolved_by)->toBe($resolvedBy)
                ->and($audit->resolved_by)->not->toBeEmpty();
        }
    });

    test('logSyncFailure SELALU menghasilkan audit entry dengan event_type=sync_failure, nip, pegawai_id, dan error_details max 1000 karakter', function () {
        // **Validates: Requirements 1.2**
        // UNTUK SEMUA sync failure, audit entry SELALU memiliki
        // event_type='sync_failure', nip, pegawai_id, dan error_details
        // yang dipotong ke maks 1000 karakter (Req 9.3).
        for ($i = 0; $i < 50; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => fake()->randomElement(StatusPegawai::cases()),
            ]);

            $errorDetails = generateRandomErrorDetails();

            $this->logger->logSyncFailure($pegawai, $errorDetails);

            $audit = KeycloakSyncAudit::where('pegawai_id', $pegawai->id)
                ->where('event_type', 'sync_failure')
                ->latest('id')
                ->first();

            // Audit entry HARUS selalu ada
            expect($audit)->not->toBeNull()
                // event_type HARUS 'sync_failure'
                ->and($audit->event_type)->toBe('sync_failure')
                // pegawai_id HARUS sesuai
                ->and($audit->pegawai_id)->toBe($pegawai->id)
                // nip HARUS ada dan sesuai
                ->and($audit->nip)->toBe($pegawai->nip)
                ->and($audit->nip)->not->toBeEmpty()
                // error_details HARUS ada dalam resolution
                ->and($audit->resolution)->toBeArray()
                ->and($audit->resolution)->toHaveKey('error_details')
                ->and($audit->resolution['error_details'])->not->toBeEmpty()
                // error_details TIDAK BOLEH lebih dari 1000 karakter
                ->and(mb_strlen($audit->resolution['error_details']))->toBeLessThanOrEqual(1000);
        }
    });

    test('audit entry untuk SEMUA event type SELALU memiliki NIP yang valid (non-empty string)', function () {
        // **Validates: Requirements 1.2**
        // UNTUK SEMUA jenis operasi sync, NIP di audit log
        // SELALU valid dan non-empty.
        $operations = ['create', 'conflict', 'sync_failure'];

        for ($i = 0; $i < 30; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            // Jalankan semua operasi
            $this->logger->logCreate($pegawai);
            $this->logger->logConflict(
                $pegawai,
                generateRandomConflictType(),
                generateRandomPegawaiSnapshot(),
                generateRandomKeycloakSnapshot(),
                generateRandomResolution(),
            );
            $this->logger->logSyncFailure($pegawai, generateRandomErrorDetails());

            // Verifikasi semua audit entry memiliki NIP yang valid
            $audits = KeycloakSyncAudit::where('pegawai_id', $pegawai->id)->get();

            expect($audits)->toHaveCount(3);

            foreach ($audits as $audit) {
                expect($audit->nip)->toBe($pegawai->nip)
                    ->and($audit->nip)->not->toBeEmpty()
                    ->and(strlen($audit->nip))->toBeGreaterThan(0);
            }
        }
    });

    test('logConflict dengan resolved_by=system SELALU tersimpan sebagai system di audit', function () {
        // **Validates: Requirements 1.2**
        // UNTUK SEMUA conflict yang diselesaikan secara otomatis,
        // resolved_by SELALU 'system' (Req 8.4).
        for ($i = 0; $i < 30; $i++) {
            $pegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            $this->logger->logConflict(
                $pegawai,
                generateRandomConflictType(),
                generateRandomPegawaiSnapshot(),
                generateRandomKeycloakSnapshot(),
                generateRandomResolution(),
                'system',
            );

            $audit = KeycloakSyncAudit::where('pegawai_id', $pegawai->id)
                ->where('event_type', 'conflict')
                ->latest('id')
                ->first();

            expect($audit->resolved_by)->toBe('system');
        }
    });
});
