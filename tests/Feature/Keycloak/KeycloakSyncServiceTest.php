<?php

/**
 * Unit tests untuk KeycloakSyncService.
 *
 * Menguji fullSync, incrementalSync, syncPegawai, disableUser,
 * userExists, dan healthCheck.
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\ConflictResolutionInterface;
use App\Keycloak\DataTransferObjects\HealthStatus;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Keycloak\Models\KeycloakSyncState;
use App\Keycloak\Services\KeycloakSyncService;
use App\Keycloak\Services\SyncAuditLogger;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Konfigurasi keycloak
    config()->set('keycloak.base_url', 'http://keycloak.test');
    config()->set('keycloak.realm', 'kepegawaian');
    config()->set('keycloak.service_account.client_id', 'kepegawaian-service');
    config()->set('keycloak.service_account.client_secret', 'test-secret');
    config()->set('keycloak.tokens.request_timeout_seconds', 5);
    config()->set('keycloak.sync.incremental_window_hours', 24);

    // Mock circuit breaker yang selalu closed
    $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
    $this->circuitBreaker->shouldReceive('isOpen')->andReturn(false)->byDefault();
    $this->circuitBreaker->shouldReceive('getState')->andReturn('closed')->byDefault();
    $this->circuitBreaker->shouldReceive('getFailureCount')->andReturn(0)->byDefault();
    $this->circuitBreaker->shouldReceive('call')->andReturnUsing(function ($callback) {
        return $callback();
    })->byDefault();

    // Gunakan ConflictResolution asli
    $this->conflictResolver = app(ConflictResolutionInterface::class);

    // Gunakan SyncAuditLogger asli
    $this->auditLogger = new SyncAuditLogger;

    $this->syncService = new KeycloakSyncService(
        $this->circuitBreaker,
        $this->conflictResolver,
        $this->auditLogger,
    );
});

// === fullSync() ===

describe('fullSync', function () {
    test('mengembalikan SyncResult gagal ketika circuit breaker open', function () {
        $this->circuitBreaker->shouldReceive('isOpen')->andReturn(true);

        $result = $this->syncService->fullSync();

        expect($result)->toBeInstanceOf(SyncResult::class)
            ->and($result->success)->toBeFalse()
            ->and($result->syncType)->toBe('full');
    });

    test('membuat Keycloak user baru untuk Pegawai tanpa akun Keycloak (Req 6.2)', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        Http::fake([
            // Token request
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            // User lookup → kosong (user belum ada)
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            // Create user → berhasil
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-id',
            ]),
            // Get roles
            '*/admin/realms/kepegawaian/roles' => Http::response([
                ['id' => 'role-1', 'name' => 'viewer'],
            ]),
            // Assign roles
            '*/role-mappings/realm' => Http::response(null, 204),
        ]);

        $result = $this->syncService->fullSync();

        expect($result)->toBeInstanceOf(SyncResult::class)
            ->and($result->success)->toBeTrue()
            ->and($result->created)->toBe(1)
            ->and($result->updated)->toBe(0)
            ->and($result->skipped)->toBe(0)
            ->and($result->syncType)->toBe('full');

        // Verifikasi keycloak_synced_at diupdate (Req 6.5)
        $pegawai->refresh();
        expect($pegawai->keycloak_synced_at)->not->toBeNull();

        // Verifikasi audit log dibuat (Req 9.1)
        expect(KeycloakSyncAudit::where('nip', $pegawai->nip)->where('event_type', 'create')->exists())->toBeTrue();

        // Verifikasi sync state diupdate (Req 6.7)
        $syncState = KeycloakSyncState::first();
        expect($syncState)->not->toBeNull()
            ->and($syncState->last_sync_type)->toBe('full')
            ->and($syncState->total_synced)->toBe(1);
    });

    test('skip Pegawai ketika data sudah cocok di Keycloak (Req 14.2, 14.3)', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');
        $roles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            // User sudah ada dengan data sama
            '*/admin/realms/kepegawaian/users?*' => Http::response([[
                'id' => 'kc-existing-id',
                'username' => $pegawai->nip,
                'email' => 'budi@pegawai.go.id',
                'firstName' => 'Budi',
                'lastName' => 'Santoso',
                'enabled' => true,
            ]]),
            // Get user roles
            '*/role-mappings/realm' => Http::response(
                array_map(fn ($r) => ['name' => $r], $roles)
            ),
        ]);

        $result = $this->syncService->fullSync();

        expect($result->success)->toBeTrue()
            ->and($result->created)->toBe(0)
            ->and($result->updated)->toBe(0)
            ->and($result->skipped)->toBe(1);

        // Verifikasi keycloak_synced_at TIDAK diupdate saat skip (Req 14.3)
        $pegawai->refresh();
        expect($pegawai->keycloak_synced_at)->toBeNull();

        // Verifikasi TIDAK ada audit entry saat skip (Req 14.3)
        expect(KeycloakSyncAudit::where('nip', $pegawai->nip)->exists())->toBeFalse();
    });

    test('hanya memproses Pegawai aktif (Req 6.1)', function () {
        Pegawai::factory()->create([
            'nama_lengkap' => 'Pegawai Aktif',
            'email' => 'aktif@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        Pegawai::factory()->create([
            'nama_lengkap' => 'Pegawai Pensiun',
            'email' => 'pensiun@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Pensiun,
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-id-1',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([]),
        ]);

        $result = $this->syncService->fullSync();

        // Hanya 1 Pegawai aktif yang diproses
        expect($result->created)->toBe(1);
    });

    test('mencatat error dan melanjutkan ketika single Pegawai gagal (Req 6.8)', function () {
        $pegawai1 = Pegawai::factory()->create([
            'nama_lengkap' => 'Pegawai Satu',
            'email' => 'satu@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai2 = Pegawai::factory()->create([
            'nama_lengkap' => 'Pegawai Dua',
            'email' => 'dua@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $callCount = 0;
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
        ]);

        // Mock circuit breaker untuk gagal pada pegawai pertama, berhasil pada kedua
        $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $this->circuitBreaker->shouldReceive('isOpen')->andReturn(false);
        $this->circuitBreaker->shouldReceive('call')->andReturnUsing(function ($callback) use (&$callCount) {
            $callCount++;
            // Call pertama (lookup pegawai 1) → throw error
            if ($callCount === 1) {
                throw new RuntimeException('Keycloak API error');
            }

            return $callback();
        });

        $syncService = new KeycloakSyncService(
            $this->circuitBreaker,
            $this->conflictResolver,
            $this->auditLogger,
        );

        $result = $syncService->fullSync();

        // Minimal 1 error karena pegawai pertama gagal
        expect($result->errors)->toBeGreaterThanOrEqual(1)
            ->and($result->success)->toBeFalse()
            ->and($result->errorDetails)->not->toBeEmpty();
    });

    test('abort sync ketika circuit breaker terbuka selama operasi (Req 6.9)', function () {
        Pegawai::factory()->count(3)->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $callCount = 0;
        $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $this->circuitBreaker->shouldReceive('isOpen')->andReturnUsing(function () use (&$callCount) {
            $callCount++;

            // Setelah pegawai pertama berhasil, circuit open
            return $callCount > 2;
        });
        $this->circuitBreaker->shouldReceive('call')->andReturnUsing(function ($callback) {
            return $callback();
        });

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-id',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([]),
        ]);

        $syncService = new KeycloakSyncService(
            $this->circuitBreaker,
            $this->conflictResolver,
            $this->auditLogger,
        );

        $result = $syncService->fullSync();

        expect($result->success)->toBeFalse()
            ->and($result->syncType)->toBe('full');
    });

    test('mempertahankan invariant: processed = created + updated + skipped + errors (Req 6.6)', function () {
        Pegawai::factory()->count(3)->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-id',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([]),
        ]);

        $result = $this->syncService->fullSync();

        $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;
        $expectedTotal = Pegawai::where('status_pegawai', StatusPegawai::Aktif)->count();

        expect($totalProcessed)->toBe($expectedTotal);
    });
});

// === incrementalSync() ===

describe('incrementalSync', function () {
    test('mengembalikan SyncResult gagal ketika circuit breaker open', function () {
        $this->circuitBreaker->shouldReceive('isOpen')->andReturn(true);

        $result = $this->syncService->incrementalSync();

        expect($result->success)->toBeFalse()
            ->and($result->syncType)->toBe('incremental');
    });

    test('hanya memproses Pegawai yang diupdate dalam 24 jam terakhir (Req 7.1)', function () {
        // Pegawai yang baru diupdate
        $recentPegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Baru Update',
            'email' => 'baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
            'updated_at' => now()->subHours(2),
        ]);

        // Pegawai yang sudah lama tidak diupdate
        $oldPegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Lama Update',
            'email' => 'lama@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
            'updated_at' => now()->subDays(3),
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-id',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([]),
        ]);

        $result = $this->syncService->incrementalSync();

        // Hanya recentPegawai yang diproses
        expect($result->created)->toBe(1)
            ->and($result->syncType)->toBe('incremental');
    });

    test('update sync state setelah selesai (Req 7.5)', function () {
        Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
            'updated_at' => now()->subHours(1),
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-id',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([]),
        ]);

        $this->syncService->incrementalSync();

        $syncState = KeycloakSyncState::first();
        expect($syncState)->not->toBeNull()
            ->and($syncState->last_sync_type)->toBe('incremental');
    });
});

// === syncPegawai() ===

describe('syncPegawai', function () {
    test('mengembalikan SyncResult gagal untuk Pegawai non-aktif (Req 7.3)', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Pensiun,
        ]);

        // Mock: user tidak ada di Keycloak untuk disableUser
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
        ]);

        $result = $this->syncService->syncPegawai($pegawai);

        expect($result->success)->toBeFalse()
            ->and($result->syncType)->toBe('single')
            ->and($result->errorDetails[0]['error'])->toContain('tidak aktif');
    });

    test('mengembalikan SyncResult gagal ketika circuit breaker open', function () {
        $this->circuitBreaker->shouldReceive('isOpen')->andReturn(true);

        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $result = $this->syncService->syncPegawai($pegawai);

        expect($result->success)->toBeFalse()
            ->and($result->syncType)->toBe('single');
    });

    test('berhasil sync single Pegawai aktif ke Keycloak', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Rina Wati',
            'email' => 'rina@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-id',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([
                ['id' => 'role-1', 'name' => 'viewer'],
            ]),
            '*/role-mappings/realm' => Http::response(null, 204),
        ]);

        $result = $this->syncService->syncPegawai($pegawai);

        expect($result->success)->toBeTrue()
            ->and($result->created)->toBe(1)
            ->and($result->syncType)->toBe('single');

        // Verifikasi keycloak_synced_at diupdate
        $pegawai->refresh();
        expect($pegawai->keycloak_synced_at)->not->toBeNull();
    });

    test('update sync state setelah single sync (Req 7.5)', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Test Pegawai',
            'email' => 'test@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
            '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
                'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-id',
            ]),
            '*/admin/realms/kepegawaian/roles' => Http::response([]),
        ]);

        $this->syncService->syncPegawai($pegawai);

        $syncState = KeycloakSyncState::first();
        expect($syncState)->not->toBeNull()
            ->and($syncState->last_sync_type)->toBe('single');
    });
});

// === disableUser() ===

describe('disableUser', function () {
    test('set enabled=false di Keycloak (Req 7.4)', function () {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([[
                'id' => 'kc-user-id',
                'username' => '198501152010011001',
                'enabled' => true,
            ]]),
            '*/admin/realms/kepegawaian/users/kc-user-id' => Http::response(null, 204),
        ]);

        // Harus tidak throw exception
        $this->syncService->disableUser('198501152010011001');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/users/kc-user-id') && $request->method() === 'PUT') {
                return $request->data()['enabled'] === false;
            }

            return false;
        });
    });

    test('tidak melakukan apapun jika user tidak ditemukan', function () {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
        ]);

        // Harus tidak throw exception
        $this->syncService->disableUser('000000000000000000');

        // Tidak ada PUT request
        Http::assertNotSent(function ($request) {
            return $request->method() === 'PUT';
        });
    });
});

// === userExists() ===

describe('userExists', function () {
    test('return true jika user ada di Keycloak', function () {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([[
                'id' => 'kc-user-id',
                'username' => '198501152010011001',
            ]]),
        ]);

        expect($this->syncService->userExists('198501152010011001'))->toBeTrue();
    });

    test('return false jika user tidak ada di Keycloak', function () {
        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            '*/admin/realms/kepegawaian/users?*' => Http::response([]),
        ]);

        expect($this->syncService->userExists('000000000000000000'))->toBeFalse();
    });
});

// === healthCheck() ===

describe('healthCheck', function () {
    test('mengembalikan HealthStatus dengan data circuit breaker', function () {
        $this->circuitBreaker->shouldReceive('getState')->andReturn('closed');
        $this->circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);

        $result = $this->syncService->healthCheck();

        expect($result)->toBeInstanceOf(HealthStatus::class)
            ->and($result->isHealthy)->toBeTrue()
            ->and($result->circuitState)->toBe('closed')
            ->and($result->failureCount)->toBe(0);
    });

    test('mengembalikan isHealthy=false ketika circuit state bukan closed', function () {
        $this->circuitBreaker->shouldReceive('getState')->andReturn('open');
        $this->circuitBreaker->shouldReceive('getFailureCount')->andReturn(5);

        $result = $this->syncService->healthCheck();

        expect($result->isHealthy)->toBeFalse()
            ->and($result->circuitState)->toBe('open')
            ->and($result->failureCount)->toBe(5);
    });
});

// === Conflict resolution during sync ===

describe('sync dengan conflict resolution', function () {
    test('detect dan resolve conflict dengan Pegawai Wins policy (Req 6.4, 8.2)', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');
        $roles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

        Http::fake([
            '*/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 300,
            ]),
            // User sudah ada tapi email berbeda (DataMismatch)
            '*/admin/realms/kepegawaian/users?*' => Http::response([[
                'id' => 'kc-existing-id',
                'username' => $pegawai->nip,
                'email' => 'budi.lama@email.com',
                'firstName' => 'Budi',
                'lastName' => 'Santoso',
                'enabled' => true,
            ]]),
            // Get user roles
            '*/users/kc-existing-id/role-mappings/realm' => Http::response(
                array_map(fn ($r) => ['name' => $r], $roles)
            ),
            // Update user
            '*/admin/realms/kepegawaian/users/kc-existing-id' => Http::response(null, 204),
        ]);

        $result = $this->syncService->fullSync();

        expect($result->updated)->toBe(1)
            ->and($result->conflicts)->toBeGreaterThanOrEqual(1);

        // Verifikasi audit log conflict
        $auditConflict = KeycloakSyncAudit::where('nip', $pegawai->nip)
            ->where('event_type', 'conflict')
            ->first();
        expect($auditConflict)->not->toBeNull()
            ->and($auditConflict->conflict_type)->toBe(ConflictType::DataMismatch->value)
            ->and($auditConflict->resolved_by)->toBe('system');
    });
});
