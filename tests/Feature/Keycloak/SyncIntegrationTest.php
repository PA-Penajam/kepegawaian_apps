<?php

/**
 * Integration tests untuk operasi sinkronisasi Keycloak.
 *
 * Menguji end-to-end flow sinkronisasi data Pegawai ke Keycloak
 * dengan komponen asli (ConflictResolution, SyncAuditLogger) dan
 * hanya HTTP calls ke Keycloak yang di-mock via Http::fake().
 *
 * _Requirements: 6.1-6.9, 7.1-7.5, 8.1-8.6, 14.1-14.4_
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\ConflictResolutionInterface;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Keycloak\Models\KeycloakSyncState;
use App\Keycloak\Services\KeycloakSyncService;
use App\Keycloak\Services\SyncAuditLogger;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('keycloak.base_url', 'http://keycloak.test');
    config()->set('keycloak.realm', 'kepegawaian');
    config()->set('keycloak.service_account.client_id', 'kepegawaian-service');
    config()->set('keycloak.service_account.client_secret', 'test-secret');
    config()->set('keycloak.tokens.request_timeout_seconds', 5);
    config()->set('keycloak.sync.incremental_window_hours', 24);

    // Circuit breaker mock: selalu closed
    $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
    $this->circuitBreaker->shouldReceive('isOpen')->andReturn(false)->byDefault();
    $this->circuitBreaker->shouldReceive('getState')->andReturn('closed')->byDefault();
    $this->circuitBreaker->shouldReceive('getFailureCount')->andReturn(0)->byDefault();
    $this->circuitBreaker->shouldReceive('call')->andReturnUsing(function ($callback) {
        return $callback();
    })->byDefault();

    // Gunakan service asli (bukan mock) untuk integrasi
    $this->conflictResolver = app(ConflictResolutionInterface::class);
    $this->auditLogger = new SyncAuditLogger;

    $this->syncService = new KeycloakSyncService(
        $this->circuitBreaker,
        $this->conflictResolver,
        $this->auditLogger,
    );
});

// ============================================================
// Full Sync: creates/updates/skips correctly
// ============================================================

describe('Full sync end-to-end flow', function () {
    test('membuat user baru, update user dengan konflik, dan skip user yang sudah cocok dalam satu operasi', function () {
        // Pegawai 1: belum ada di Keycloak → create
        $pegawaiNew = Pegawai::factory()->create([
            'nama_lengkap' => 'Andi Pratama',
            'email' => 'andi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawaiNew->load('iamRoles');

        // Pegawai 2: sudah ada di Keycloak tapi email berbeda → conflict + update
        $pegawaiConflict = Pegawai::factory()->create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawaiConflict->load('iamRoles');
        $conflictRoles = $pegawaiConflict->iamRoles->pluck('slug')->sort()->values()->all();

        // Pegawai 3: sudah ada di Keycloak dengan data sama → skip
        $pegawaiMatch = Pegawai::factory()->create([
            'nama_lengkap' => 'Citra Dewi',
            'email' => 'citra@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawaiMatch->load('iamRoles');
        $matchRoles = $pegawaiMatch->iamRoles->pluck('slug')->sort()->values()->all();

        Http::fake(function ($request) use ($pegawaiConflict, $pegawaiMatch, $conflictRoles, $matchRoles) {
            $url = $request->url();
            $method = $request->method();

            // Token endpoint
            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            // Role mappings GET (per user)
            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                if (str_contains($url, 'kc-conflict-id')) {
                    return Http::response(array_map(fn ($r) => ['name' => $r], $conflictRoles));
                }
                if (str_contains($url, 'kc-match-id')) {
                    return Http::response(array_map(fn ($r) => ['name' => $r], $matchRoles));
                }

                return Http::response([]);
            }

            // Role mappings POST
            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            // User lookup
            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
                parse_str($queryString, $params);
                $nip = $params['username'] ?? '';

                if ($nip === $pegawaiConflict->nip) {
                    return Http::response([[
                        'id' => 'kc-conflict-id',
                        'username' => $pegawaiConflict->nip,
                        'email' => 'budi.lama@email.com', // Email berbeda → conflict
                        'firstName' => 'Budi',
                        'lastName' => 'Santoso',
                        'enabled' => true,
                    ]]);
                }

                if ($nip === $pegawaiMatch->nip) {
                    return Http::response([[
                        'id' => 'kc-match-id',
                        'username' => $pegawaiMatch->nip,
                        'email' => 'citra@pegawai.go.id',
                        'firstName' => 'Citra',
                        'lastName' => 'Dewi',
                        'enabled' => true,
                    ]]);
                }

                // Pegawai baru → user tidak ditemukan
                return Http::response([]);
            }

            // Update user (PUT)
            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            // Create user (POST)
            if (str_contains($url, '/users') && $method === 'POST') {
                return Http::response(null, 201, [
                    'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-andi',
                ]);
            }

            // Get realm roles
            if (str_contains($url, '/roles') && $method === 'GET') {
                return Http::response([
                    ['id' => 'role-viewer', 'name' => 'viewer'],
                ]);
            }

            return Http::response(null, 404);
        });

        $result = $this->syncService->fullSync();

        // Verifikasi hasil keseluruhan
        expect($result->success)->toBeTrue()
            ->and($result->created)->toBe(1)
            ->and($result->updated)->toBe(1)
            ->and($result->skipped)->toBe(1)
            ->and($result->conflicts)->toBeGreaterThanOrEqual(1)
            ->and($result->errors)->toBe(0)
            ->and($result->syncType)->toBe('full');

        // Verifikasi keycloak_synced_at: create dan update harus diupdate, skip tidak
        $pegawaiNew->refresh();
        $pegawaiConflict->refresh();
        $pegawaiMatch->refresh();

        expect($pegawaiNew->keycloak_synced_at)->not->toBeNull()
            ->and($pegawaiConflict->keycloak_synced_at)->not->toBeNull()
            ->and($pegawaiMatch->keycloak_synced_at)->toBeNull();

        // Verifikasi audit log: create dan conflict harus tercatat, skip tidak
        expect(KeycloakSyncAudit::where('nip', $pegawaiNew->nip)->where('event_type', 'create')->exists())->toBeTrue()
            ->and(KeycloakSyncAudit::where('nip', $pegawaiConflict->nip)->where('event_type', 'conflict')->exists())->toBeTrue()
            ->and(KeycloakSyncAudit::where('nip', $pegawaiMatch->nip)->exists())->toBeFalse();

        // Verifikasi sync state diupdate
        $syncState = KeycloakSyncState::first();
        expect($syncState)->not->toBeNull()
            ->and($syncState->last_sync_type)->toBe('full')
            ->and($syncState->total_synced)->toBe(2) // created + updated
            ->and($syncState->total_conflicts)->toBeGreaterThanOrEqual(1);
    });

    test('count invariant terpenuhi: processed = created + updated + skipped + errors', function () {
        // Buat campuran Pegawai aktif dan non-aktif
        Pegawai::factory()->count(3)->create(['status_pegawai' => StatusPegawai::Aktif]);
        Pegawai::factory()->count(2)->create(['status_pegawai' => StatusPegawai::Pensiun]);

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
            '*/role-mappings/realm' => Http::response(null, 204),
        ]);

        $result = $this->syncService->fullSync();

        $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;
        $expectedActive = Pegawai::where('status_pegawai', StatusPegawai::Aktif)->count();

        expect($totalProcessed)->toBe($expectedActive)
            ->and($result->created)->toBe(3);
    });
});

// ============================================================
// Conflict Detection dan Pegawai Wins Resolution
// ============================================================

describe('Conflict detection dan Pegawai Wins resolution', function () {
    test('DataMismatch conflict terdeteksi dan diresolvasi dengan data Pegawai', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Dewi Sartika',
            'email' => 'dewi.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');
        $roles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

        Http::fake(function ($request) use ($pegawai, $roles) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                return Http::response(array_map(fn ($r) => ['name' => $r], $roles));
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                return Http::response([[
                    'id' => 'kc-dewi-id',
                    'username' => $pegawai->nip,
                    'email' => 'dewi.lama@email.com', // Email berbeda
                    'firstName' => 'Dewi',
                    'lastName' => 'Lama', // LastName berbeda
                    'enabled' => true,
                ]]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                // Verifikasi data yang dikirim ke Keycloak menggunakan data Pegawai
                $data = $request->data();
                expect($data['email'])->toBe('dewi.baru@pegawai.go.id')
                    ->and($data['firstName'])->toBe('Dewi')
                    ->and($data['lastName'])->toBe('Sartika');

                return Http::response(null, 204);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            return Http::response(null, 404);
        });

        $result = $this->syncService->fullSync();

        expect($result->updated)->toBe(1)
            ->and($result->conflicts)->toBeGreaterThanOrEqual(1);

        // Verifikasi audit log menyimpan detail conflict
        $auditEntry = KeycloakSyncAudit::where('nip', $pegawai->nip)
            ->where('event_type', 'conflict')
            ->first();

        expect($auditEntry)->not->toBeNull()
            ->and($auditEntry->conflict_type)->toBe(ConflictType::DataMismatch->value)
            ->and($auditEntry->resolved_by)->toBe('system')
            ->and($auditEntry->pegawai_snapshot)->toBeArray()
            ->and($auditEntry->keycloak_snapshot)->toBeArray()
            ->and($auditEntry->resolution)->toBeArray();
    });

    test('StatusConflict terdeteksi ketika Pegawai aktif tapi Keycloak disabled', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Eko Prasetyo',
            'email' => 'eko@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');
        $roles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

        Http::fake(function ($request) use ($pegawai, $roles) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                return Http::response(array_map(fn ($r) => ['name' => $r], $roles));
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                [$firstName, $lastName] = explode(' ', trim($pegawai->nama_lengkap), 2);

                return Http::response([[
                    'id' => 'kc-eko-id',
                    'username' => $pegawai->nip,
                    'email' => $pegawai->email,
                    'firstName' => $firstName,
                    'lastName' => $lastName ?? '',
                    'enabled' => false, // Disabled di Keycloak → StatusConflict
                ]]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                $data = $request->data();
                // Pegawai Wins: enabled harus diset ke true
                expect($data['enabled'])->toBeTrue();

                return Http::response(null, 204);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            return Http::response(null, 404);
        });

        $result = $this->syncService->fullSync();

        expect($result->updated)->toBe(1)
            ->and($result->conflicts)->toBeGreaterThanOrEqual(1);

        // Verifikasi audit log conflict StatusConflict
        $auditEntry = KeycloakSyncAudit::where('nip', $pegawai->nip)
            ->where('event_type', 'conflict')
            ->where('conflict_type', ConflictType::StatusConflict->value)
            ->first();

        expect($auditEntry)->not->toBeNull();
    });

    test('RoleOverride conflict terdeteksi ketika role berbeda antara Pegawai dan Keycloak', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Fitri Handayani',
            'email' => 'fitri@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        Http::fake(function ($request) use ($pegawai) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                // Roles berbeda di Keycloak
                return Http::response([
                    ['name' => 'role-tidak-ada-di-pegawai'],
                    ['name' => 'super-admin'],
                ]);
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                [$firstName, $lastName] = explode(' ', trim($pegawai->nama_lengkap), 2);

                return Http::response([[
                    'id' => 'kc-fitri-id',
                    'username' => $pegawai->nip,
                    'email' => $pegawai->email,
                    'firstName' => $firstName,
                    'lastName' => $lastName ?? '',
                    'enabled' => true,
                ]]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            if (str_contains($url, '/roles') && $method === 'GET' && ! str_contains($url, 'role-mappings')) {
                $pegawaiRoles = $pegawai->iamRoles->pluck('slug')->all();

                return Http::response(
                    array_map(fn ($r) => ['id' => 'role-id-'.$r, 'name' => $r], $pegawaiRoles)
                );
            }

            return Http::response(null, 404);
        });

        $result = $this->syncService->fullSync();

        expect($result->updated)->toBe(1)
            ->and($result->conflicts)->toBeGreaterThanOrEqual(1);

        // Verifikasi audit log RoleOverride
        $auditEntry = KeycloakSyncAudit::where('nip', $pegawai->nip)
            ->where('event_type', 'conflict')
            ->where('conflict_type', ConflictType::RoleOverride->value)
            ->first();

        expect($auditEntry)->not->toBeNull()
            ->and($auditEntry->resolved_by)->toBe('system');
    });

    test('multiple conflicts pada satu user terdeteksi dan diselesaikan sekaligus', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Gita Rahayu',
            'email' => 'gita.new@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');

        Http::fake(function ($request) use ($pegawai) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                // Roles juga berbeda
                return Http::response([['name' => 'old-role']]);
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                return Http::response([[
                    'id' => 'kc-gita-id',
                    'username' => $pegawai->nip,
                    'email' => 'gita.old@email.com', // DataMismatch
                    'firstName' => 'Gita',
                    'lastName' => 'Lama', // DataMismatch
                    'enabled' => false, // StatusConflict
                ]]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            if (str_contains($url, '/roles') && $method === 'GET' && ! str_contains($url, 'role-mappings')) {
                $pegawaiRoles = $pegawai->iamRoles->pluck('slug')->all();

                return Http::response(
                    array_map(fn ($r) => ['id' => 'role-id-'.$r, 'name' => $r], $pegawaiRoles)
                );
            }

            return Http::response(null, 404);
        });

        $result = $this->syncService->fullSync();

        // Harus ada minimal 3 konflik: DataMismatch, StatusConflict, RoleOverride
        expect($result->updated)->toBe(1)
            ->and($result->conflicts)->toBeGreaterThanOrEqual(3);

        // Setiap jenis conflict harus memiliki audit entry tersendiri
        $auditEntries = KeycloakSyncAudit::where('nip', $pegawai->nip)
            ->where('event_type', 'conflict')
            ->get();

        expect($auditEntries->count())->toBeGreaterThanOrEqual(3);

        $conflictTypes = $auditEntries->pluck('conflict_type')->all();
        expect($conflictTypes)->toContain(ConflictType::DataMismatch->value)
            ->toContain(ConflictType::StatusConflict->value)
            ->toContain(ConflictType::RoleOverride->value);
    });
});

// ============================================================
// Incremental Sync Time Window Filtering
// ============================================================

describe('Incremental sync time window filtering', function () {
    test('hanya memproses Pegawai yang updated dalam 24 jam terakhir', function () {
        // Pegawai dalam window (baru diupdate)
        $recentPegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Hadi Suprapto',
            'email' => 'hadi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
            'updated_at' => now()->subHours(5),
        ]);

        // Pegawai di luar window (sudah lama)
        $oldPegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Indra Wijaya',
            'email' => 'indra@pegawai.go.id',
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
            '*/role-mappings/realm' => Http::response(null, 204),
        ]);

        $result = $this->syncService->incrementalSync();

        expect($result->success)->toBeTrue()
            ->and($result->created)->toBe(1)
            ->and($result->syncType)->toBe('incremental');

        // Hanya recent yang di-sync
        $recentPegawai->refresh();
        $oldPegawai->refresh();

        expect($recentPegawai->keycloak_synced_at)->not->toBeNull()
            ->and($oldPegawai->keycloak_synced_at)->toBeNull();
    });

    test('tidak memproses Pegawai non-aktif meskipun dalam time window', function () {
        Pegawai::factory()->create([
            'nama_lengkap' => 'Aktif Baru',
            'email' => 'aktif.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
            'updated_at' => now()->subHours(2),
        ]);

        Pegawai::factory()->create([
            'nama_lengkap' => 'Pensiun Baru',
            'email' => 'pensiun.baru@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Pensiun,
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
            '*/role-mappings/realm' => Http::response(null, 204),
        ]);

        $result = $this->syncService->incrementalSync();

        // Hanya 1 yang diproses (yang aktif)
        $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;
        expect($totalProcessed)->toBe(1);
    });

    test('incremental sync update sync state dengan tipe incremental', function () {
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
            '*/role-mappings/realm' => Http::response(null, 204),
        ]);

        $this->syncService->incrementalSync();

        $syncState = KeycloakSyncState::first();
        expect($syncState)->not->toBeNull()
            ->and($syncState->last_sync_type)->toBe('incremental')
            ->and($syncState->total_synced)->toBe(1);
    });
});

// ============================================================
// Sync Idempotency
// ============================================================

describe('Sync idempotency', function () {
    test('fullSync kedua dengan data tidak berubah menghasilkan semua skipped tanpa write calls', function () {
        $pegawai1 = Pegawai::factory()->create([
            'nama_lengkap' => 'Joko Widodo',
            'email' => 'joko@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai1->load('iamRoles');
        $roles1 = $pegawai1->iamRoles->pluck('slug')->sort()->values()->all();

        $pegawai2 = Pegawai::factory()->create([
            'nama_lengkap' => 'Kartini Putri',
            'email' => 'kartini@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai2->load('iamRoles');
        $roles2 = $pegawai2->iamRoles->pluck('slug')->sort()->values()->all();

        // Simulasikan data Keycloak yang sudah cocok dengan Pegawai
        Http::fake(function ($request) use ($pegawai1, $pegawai2, $roles1, $roles2) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                if (str_contains($url, 'kc-joko-id')) {
                    return Http::response(array_map(fn ($r) => ['name' => $r], $roles1));
                }
                if (str_contains($url, 'kc-kartini-id')) {
                    return Http::response(array_map(fn ($r) => ['name' => $r], $roles2));
                }

                return Http::response([]);
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
                parse_str($queryString, $params);
                $nip = $params['username'] ?? '';

                if ($nip === $pegawai1->nip) {
                    [$fn, $ln] = explode(' ', trim($pegawai1->nama_lengkap), 2);

                    return Http::response([[
                        'id' => 'kc-joko-id',
                        'username' => $pegawai1->nip,
                        'email' => $pegawai1->email,
                        'firstName' => $fn,
                        'lastName' => $ln ?? '',
                        'enabled' => true,
                    ]]);
                }

                if ($nip === $pegawai2->nip) {
                    [$fn, $ln] = explode(' ', trim($pegawai2->nama_lengkap), 2);

                    return Http::response([[
                        'id' => 'kc-kartini-id',
                        'username' => $pegawai2->nip,
                        'email' => $pegawai2->email,
                        'firstName' => $fn,
                        'lastName' => $ln ?? '',
                        'enabled' => true,
                    ]]);
                }

                return Http::response([]);
            }

            // Jika ada PUT atau POST ke users, ini seharusnya TIDAK terjadi
            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            if (str_contains($url, '/users') && $method === 'POST') {
                return Http::response(null, 201, [
                    'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new',
                ]);
            }

            return Http::response(null, 404);
        });

        // Eksekusi pertama
        $result1 = $this->syncService->fullSync();

        expect($result1->success)->toBeTrue()
            ->and($result1->created)->toBe(0)
            ->and($result1->updated)->toBe(0)
            ->and($result1->skipped)->toBe(2);

        // Eksekusi kedua (data tidak berubah)
        $result2 = $this->syncService->fullSync();

        expect($result2->success)->toBeTrue()
            ->and($result2->created)->toBe(0)
            ->and($result2->updated)->toBe(0)
            ->and($result2->skipped)->toBe(2)
            ->and($result2->errors)->toBe(0);

        // Verifikasi idempoten: tidak ada PUT/POST ke users
        Http::assertNotSent(function ($request) {
            $url = $request->url();
            $method = $request->method();

            // Tidak boleh ada POST create user
            if (str_contains($url, '/users') && $method === 'POST' && ! str_contains($url, 'token')) {
                return true;
            }

            // Tidak boleh ada PUT update user
            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return true;
            }

            return false;
        });
    });

    test('keycloak_synced_at tidak berubah untuk record yang di-skip', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Lina Mardiana',
            'email' => 'lina@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
            'keycloak_synced_at' => null,
        ]);
        $pegawai->load('iamRoles');
        $roles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

        // Data sudah cocok → skip
        Http::fake(function ($request) use ($pegawai, $roles) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                return Http::response(array_map(fn ($r) => ['name' => $r], $roles));
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                [$fn, $ln] = explode(' ', trim($pegawai->nama_lengkap), 2);

                return Http::response([[
                    'id' => 'kc-lina-id',
                    'username' => $pegawai->nip,
                    'email' => $pegawai->email,
                    'firstName' => $fn,
                    'lastName' => $ln ?? '',
                    'enabled' => true,
                ]]);
            }

            return Http::response(null, 404);
        });

        $result = $this->syncService->fullSync();

        expect($result->skipped)->toBe(1);

        // keycloak_synced_at harus tetap null
        $pegawai->refresh();
        expect($pegawai->keycloak_synced_at)->toBeNull();

        // Tidak ada audit entry
        expect(KeycloakSyncAudit::where('nip', $pegawai->nip)->exists())->toBeFalse();
    });

    test('sync idempoten: eksekusi berulang tidak menghasilkan audit entry ganda', function () {
        $pegawai = Pegawai::factory()->create([
            'nama_lengkap' => 'Mulyadi Putra',
            'email' => 'mulyadi@pegawai.go.id',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);
        $pegawai->load('iamRoles');
        $roles = $pegawai->iamRoles->pluck('slug')->sort()->values()->all();

        Http::fake(function ($request) use ($pegawai, $roles) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                return Http::response(array_map(fn ($r) => ['name' => $r], $roles));
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                [$fn, $ln] = explode(' ', trim($pegawai->nama_lengkap), 2);

                return Http::response([[
                    'id' => 'kc-mulyadi-id',
                    'username' => $pegawai->nip,
                    'email' => $pegawai->email,
                    'firstName' => $fn,
                    'lastName' => $ln ?? '',
                    'enabled' => true,
                ]]);
            }

            return Http::response(null, 404);
        });

        // Eksekusi 3 kali berturut-turut
        $this->syncService->fullSync();
        $this->syncService->fullSync();
        $this->syncService->fullSync();

        // Tidak boleh ada audit entry sama sekali (semua di-skip)
        expect(KeycloakSyncAudit::where('nip', $pegawai->nip)->count())->toBe(0);
    });
});
