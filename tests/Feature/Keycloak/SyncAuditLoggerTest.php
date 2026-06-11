<?php

/**
 * Feature tests untuk SyncAuditLogger service.
 *
 * Menguji logging event create, update, conflict, dan sync_failure
 * sesuai Requirements 9.1, 9.2, 9.3, 9.4, 9.6.
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Keycloak\Services\SyncAuditLogger;
use App\Models\Pegawai;

beforeEach(function () {
    $this->logger = new SyncAuditLogger;
});

// === logCreate (Req 9.1) ===

describe('logCreate', function () {
    test('mencatat event create dengan data yang benar', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logCreate($pegawai);

        $audit = KeycloakSyncAudit::where('event_type', 'create')->first();

        expect($audit)->not->toBeNull()
            ->and($audit->event_type)->toBe('create')
            ->and($audit->pegawai_id)->toBe($pegawai->id)
            ->and($audit->nip)->toBe($pegawai->nip)
            ->and($audit->pegawai_snapshot)->toBeArray()
            ->and($audit->pegawai_snapshot['nip'])->toBe($pegawai->nip)
            ->and($audit->pegawai_snapshot['email'])->toBe('budi@pegawai.go.id')
            ->and($audit->resolved_by)->toBe('system');
    });

    test('mencatat caused_by ketika diberikan', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $causedBy = Pegawai::factory()->create([
            'nama_lengkap' => 'Admin User',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logCreate($pegawai, $causedBy);

        $audit = KeycloakSyncAudit::where('event_type', 'create')->first();

        expect($audit->caused_by)->toBe($causedBy->id)
            ->and($audit->caused_by_nip)->toBe($causedBy->nip);
    });

    test('caused_by null jika tidak diberikan', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logCreate($pegawai);

        $audit = KeycloakSyncAudit::where('event_type', 'create')->first();

        expect($audit->caused_by)->toBeNull()
            ->and($audit->caused_by_nip)->toBeNull();
    });
});

// === logUpdate (Req 9.4) ===

describe('logUpdate', function () {
    test('mencatat event update dengan data yang benar', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Siti Aminah',
            'email' => 'siti@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logUpdate($pegawai);

        $audit = KeycloakSyncAudit::where('event_type', 'update')->first();

        expect($audit)->not->toBeNull()
            ->and($audit->event_type)->toBe('update')
            ->and($audit->pegawai_id)->toBe($pegawai->id)
            ->and($audit->nip)->toBe($pegawai->nip)
            ->and($audit->pegawai_snapshot)->toBeArray()
            ->and($audit->pegawai_snapshot['nama_lengkap'])->toBe('Siti Aminah')
            ->and($audit->resolved_by)->toBe('system');
    });

    test('mencatat caused_by untuk update', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $causedBy = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logUpdate($pegawai, $causedBy);

        $audit = KeycloakSyncAudit::where('event_type', 'update')->first();

        expect($audit->caused_by)->toBe($causedBy->id)
            ->and($audit->caused_by_nip)->toBe($causedBy->nip);
    });
});

// === logConflict (Req 9.2) ===

describe('logConflict', function () {
    test('mencatat event conflict dengan semua field yang diperlukan', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Doni Pratama',
            'email' => 'doni@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $pegawaiSnapshot = [
            'nip' => $pegawai->nip,
            'email' => 'doni@pegawai.go.id',
            'firstName' => 'Doni',
            'lastName' => 'Pratama',
        ];

        $keycloakSnapshot = [
            'username' => $pegawai->nip,
            'email' => 'doni.old@email.com',
            'firstName' => 'Donny',
            'lastName' => 'Pratama',
        ];

        $resolution = [
            'email' => 'doni@pegawai.go.id',
            'firstName' => 'Doni',
            'lastName' => 'Pratama',
        ];

        $this->logger->logConflict(
            $pegawai,
            ConflictType::DataMismatch,
            $pegawaiSnapshot,
            $keycloakSnapshot,
            $resolution,
        );

        $audit = KeycloakSyncAudit::where('event_type', 'conflict')->first();

        expect($audit)->not->toBeNull()
            ->and($audit->event_type)->toBe('conflict')
            ->and($audit->pegawai_id)->toBe($pegawai->id)
            ->and($audit->nip)->toBe($pegawai->nip)
            ->and($audit->conflict_type)->toBe('data_mismatch')
            ->and($audit->pegawai_snapshot)->toBe($pegawaiSnapshot)
            ->and($audit->keycloak_snapshot)->toBe($keycloakSnapshot)
            ->and($audit->resolution)->toBe($resolution)
            ->and($audit->resolved_by)->toBe('system');
    });

    test('mencatat resolved_by admin NIP untuk manual resolution', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logConflict(
            $pegawai,
            ConflictType::StatusConflict,
            ['status' => 'aktif'],
            ['enabled' => false],
            ['enabled' => true],
            '198501152010011001',
        );

        $audit = KeycloakSyncAudit::where('event_type', 'conflict')->first();

        expect($audit->resolved_by)->toBe('198501152010011001');
    });
});

// === logSyncFailure (Req 9.3) ===

describe('logSyncFailure', function () {
    test('mencatat event sync_failure dengan error details', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Ani Rahayu',
            'email' => 'ani@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $errorMessage = 'Connection timeout: Keycloak server tidak merespon';

        $this->logger->logSyncFailure($pegawai, $errorMessage);

        $audit = KeycloakSyncAudit::where('event_type', 'sync_failure')->first();

        expect($audit)->not->toBeNull()
            ->and($audit->event_type)->toBe('sync_failure')
            ->and($audit->pegawai_id)->toBe($pegawai->id)
            ->and($audit->nip)->toBe($pegawai->nip)
            ->and($audit->resolution['error_details'])->toBe($errorMessage)
            ->and($audit->resolved_by)->toBe('system');
    });

    test('truncate error_details ke maksimal 1000 karakter', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        // Buat string lebih dari 1000 karakter
        $longError = str_repeat('Error detail yang sangat panjang. ', 100);
        expect(mb_strlen($longError))->toBeGreaterThan(1000);

        $this->logger->logSyncFailure($pegawai, $longError);

        $audit = KeycloakSyncAudit::where('event_type', 'sync_failure')->first();

        expect(mb_strlen($audit->resolution['error_details']))->toBeLessThanOrEqual(1000);
    });

    test('mencatat caused_by untuk sync_failure', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $causedBy = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $this->logger->logSyncFailure($pegawai, 'Test error', $causedBy);

        $audit = KeycloakSyncAudit::where('event_type', 'sync_failure')->first();

        expect($audit->caused_by)->toBe($causedBy->id)
            ->and($audit->caused_by_nip)->toBe($causedBy->nip);
    });
});

// === Snapshot size limit (Req 9.6) ===

describe('snapshot size limit', function () {
    test('truncate pegawai_snapshot jika melebihi 64KB', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Test User',
            'email' => 'test@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
            // Keterangan sangat panjang untuk memicu truncation
            'keterangan' => str_repeat('A', 70000),
        ]);

        // logCreate akan memanggil buildPegawaiSnapshot yang tidak include keterangan
        // Jadi kita test melalui logConflict dengan snapshot besar
        $largeSnapshot = ['data' => str_repeat('X', 70000)];

        $this->logger->logConflict(
            $pegawai,
            ConflictType::DataMismatch,
            $largeSnapshot,
            ['small' => 'data'],
            ['resolved' => true],
        );

        $audit = KeycloakSyncAudit::where('event_type', 'conflict')->first();

        // Snapshot yang melebihi batas harus di-truncate
        $json = json_encode($audit->pegawai_snapshot, JSON_UNESCAPED_UNICODE);
        expect(strlen($json))->toBeLessThanOrEqual(65536);
        expect($audit->pegawai_snapshot['_truncated'])->toBeTrue();
    });
});
