<?php

/**
 * Property-Based Tests untuk KeycloakSyncService.
 *
 * Menguji properti universal dari sync service:
 * - Property 15: Sync Count Invariant (Req 6.6)
 * - Property 16: Active-Only Sync Filter (Req 6.1)
 * - Property 17: Incremental Sync Time Window (Req 7.1)
 * - Property 18: Sync Idempotency (Req 14.1, 14.2)
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\ConflictResolutionInterface;
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

// ============================================================
// Helper Functions untuk Property Testing
// ============================================================

/**
 * Setup Http::fake standar untuk sync operations dimana semua user baru (create).
 */
function fakeHttpForCreate(): void
{
    Http::fake([
        '*/protocol/openid-connect/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 300,
        ]),
        '*/admin/realms/kepegawaian/users?*' => Http::response([]),
        '*/admin/realms/kepegawaian/users' => Http::response(null, 201, [
            'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-'.bin2hex(random_bytes(4)),
        ]),
        '*/admin/realms/kepegawaian/roles' => Http::response([]),
        '*/role-mappings/realm' => Http::response(null, 204),
    ]);
}

// ============================================================
// Property 15: Sync Count Invariant
// **Validates: Requirement 6.6**
// ============================================================

describe('Property 15: Sync Count Invariant', function () {
    test('total processed SELALU sama dengan created + updated + skipped + errors pada fullSync', function () {
        // UNTUK SEMUA eksekusi fullSync dengan jumlah Pegawai acak,
        // total_processed (created + updated + skipped + errors) SELALU sama dengan
        // jumlah Pegawai aktif yang diproses.
        for ($i = 0; $i < 20; $i++) {
            // Buat jumlah Pegawai acak (1-5 aktif, 0-3 non-aktif)
            $activeCount = random_int(1, 5);
            $inactiveCount = random_int(0, 3);

            $nonActiveStatuses = [
                StatusPegawai::Pensiun,
                StatusPegawai::MutasiKeluar,
                StatusPegawai::Meninggal,
                StatusPegawai::Diberhentikan,
            ];

            Pegawai::factory()->count($activeCount)->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            for ($j = 0; $j < $inactiveCount; $j++) {
                Pegawai::factory()->create([
                    'status_pegawai' => $nonActiveStatuses[array_rand($nonActiveStatuses)],
                ]);
            }

            fakeHttpForCreate();

            $result = $this->syncService->fullSync();

            $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;

            // INVARIANT: total processed harus sama dengan jumlah Pegawai aktif
            expect($totalProcessed)->toBe($activeCount,
                "Iterasi {$i}: expected {$activeCount} processed, got {$totalProcessed} "
                ."(created={$result->created}, updated={$result->updated}, skipped={$result->skipped}, errors={$result->errors})"
            );

            // Cleanup untuk iterasi berikutnya
            Pegawai::query()->forceDelete();
        }
    });

    test('total processed SELALU sama dengan created + updated + skipped + errors pada incrementalSync', function () {
        // UNTUK SEMUA eksekusi incrementalSync,
        // invariant yang sama berlaku.
        for ($i = 0; $i < 20; $i++) {
            $recentCount = random_int(1, 4);
            $oldCount = random_int(0, 3);

            // Pegawai yang baru diupdate (dalam 24 jam)
            Pegawai::factory()->count($recentCount)->create([
                'status_pegawai' => StatusPegawai::Aktif,
                'updated_at' => now()->subHours(random_int(1, 23)),
            ]);

            // Pegawai yang lama tidak diupdate (> 24 jam)
            Pegawai::factory()->count($oldCount)->create([
                'status_pegawai' => StatusPegawai::Aktif,
                'updated_at' => now()->subDays(random_int(2, 10)),
            ]);

            fakeHttpForCreate();

            $result = $this->syncService->incrementalSync();

            $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;

            // INVARIANT: total processed harus sama dengan jumlah Pegawai aktif yang dalam window
            expect($totalProcessed)->toBe($recentCount,
                "Iterasi {$i}: expected {$recentCount} processed, got {$totalProcessed}"
            );

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });
});

// ============================================================
// Property 16: Active-Only Sync Filter
// **Validates: Requirement 6.1**
// ============================================================

describe('Property 16: Active-Only Sync Filter', function () {
    test('fullSync TIDAK PERNAH memproses Pegawai dengan status selain aktif', function () {
        // UNTUK SEMUA kombinasi status Pegawai,
        // fullSync HANYA memproses yang berstatus aktif.
        $allStatuses = StatusPegawai::cases();
        $nonActiveStatuses = array_filter($allStatuses, fn ($s) => $s !== StatusPegawai::Aktif);

        for ($i = 0; $i < 20; $i++) {
            // Buat tepat 1 Pegawai aktif
            $activePegawai = Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            // Buat beberapa Pegawai non-aktif dengan status acak
            $nonActiveCount = random_int(1, 4);
            $nonActivePegawai = [];
            for ($j = 0; $j < $nonActiveCount; $j++) {
                $nonActivePegawai[] = Pegawai::factory()->create([
                    'status_pegawai' => $nonActiveStatuses[array_rand($nonActiveStatuses)],
                ]);
            }

            fakeHttpForCreate();

            $result = $this->syncService->fullSync();

            // Total yang diproses HARUS hanya 1 (yang aktif)
            $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;
            expect($totalProcessed)->toBe(1,
                "Iterasi {$i}: hanya 1 Pegawai aktif, tetapi {$totalProcessed} diproses"
            );

            // Pegawai non-aktif TIDAK BOLEH memiliki keycloak_synced_at
            foreach ($nonActivePegawai as $p) {
                $p->refresh();
                expect($p->keycloak_synced_at)->toBeNull(
                    "Iterasi {$i}: Pegawai non-aktif {$p->nip} (status: {$p->status_pegawai->value}) seharusnya tidak di-sync"
                );
            }

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });

    test('fullSync memproses SEMUA Pegawai aktif tanpa kecuali', function () {
        // UNTUK SEMUA set Pegawai aktif,
        // fullSync SELALU memproses semuanya.
        for ($i = 0; $i < 15; $i++) {
            $activeCount = random_int(2, 6);

            Pegawai::factory()->count($activeCount)->create([
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            fakeHttpForCreate();

            $result = $this->syncService->fullSync();

            $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;

            // HARUS memproses semua Pegawai aktif
            expect($totalProcessed)->toBe($activeCount,
                "Iterasi {$i}: ada {$activeCount} Pegawai aktif, tetapi hanya {$totalProcessed} yang diproses"
            );

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });
});

// ============================================================
// Property 17: Incremental Sync Time Window
// **Validates: Requirement 7.1**
// ============================================================

describe('Property 17: Incremental Sync Time Window', function () {
    test('incrementalSync HANYA memproses Pegawai yang updated dalam 24 jam terakhir', function () {
        // UNTUK SEMUA kombinasi waktu update,
        // incrementalSync HANYA memproses yang updated_at dalam window 24 jam.
        for ($i = 0; $i < 20; $i++) {
            // Pegawai dalam window (updated dalam 24 jam)
            $inWindowCount = random_int(1, 4);
            $inWindowPegawai = [];
            for ($j = 0; $j < $inWindowCount; $j++) {
                $inWindowPegawai[] = Pegawai::factory()->create([
                    'status_pegawai' => StatusPegawai::Aktif,
                    'updated_at' => now()->subHours(random_int(0, 23))->subMinutes(random_int(0, 59)),
                ]);
            }

            // Pegawai di luar window (updated > 24 jam lalu)
            $outWindowCount = random_int(1, 3);
            $outWindowPegawai = [];
            for ($j = 0; $j < $outWindowCount; $j++) {
                $outWindowPegawai[] = Pegawai::factory()->create([
                    'status_pegawai' => StatusPegawai::Aktif,
                    'updated_at' => now()->subHours(random_int(25, 200)),
                ]);
            }

            fakeHttpForCreate();

            $result = $this->syncService->incrementalSync();

            $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;

            // HANYA Pegawai dalam window yang diproses
            expect($totalProcessed)->toBe($inWindowCount,
                "Iterasi {$i}: {$inWindowCount} dalam window, tetapi {$totalProcessed} diproses"
            );

            // Pegawai di luar window TIDAK BOLEH memiliki keycloak_synced_at
            foreach ($outWindowPegawai as $p) {
                $p->refresh();
                expect($p->keycloak_synced_at)->toBeNull(
                    "Iterasi {$i}: Pegawai {$p->nip} (updated {$p->updated_at}) di luar window, seharusnya tidak di-sync"
                );
            }

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });

    test('incrementalSync TIDAK memproses Pegawai non-aktif meskipun dalam time window', function () {
        // UNTUK SEMUA Pegawai non-aktif yang baru diupdate,
        // incrementalSync TIDAK PERNAH memproses mereka.
        $nonActiveStatuses = [
            StatusPegawai::Pensiun,
            StatusPegawai::MutasiKeluar,
            StatusPegawai::Meninggal,
            StatusPegawai::Diberhentikan,
        ];

        for ($i = 0; $i < 15; $i++) {
            // Buat 1 Pegawai aktif dalam window
            Pegawai::factory()->create([
                'status_pegawai' => StatusPegawai::Aktif,
                'updated_at' => now()->subHours(random_int(1, 12)),
            ]);

            // Buat beberapa non-aktif dalam window (seharusnya tidak diproses)
            $nonActiveCount = random_int(1, 3);
            for ($j = 0; $j < $nonActiveCount; $j++) {
                Pegawai::factory()->create([
                    'status_pegawai' => $nonActiveStatuses[array_rand($nonActiveStatuses)],
                    'updated_at' => now()->subHours(random_int(1, 12)),
                ]);
            }

            fakeHttpForCreate();

            $result = $this->syncService->incrementalSync();

            $totalProcessed = $result->created + $result->updated + $result->skipped + $result->errors;

            // Hanya 1 yang diproses (yang aktif)
            expect($totalProcessed)->toBe(1,
                "Iterasi {$i}: hanya 1 aktif dalam window, tetapi {$totalProcessed} diproses"
            );

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });
});

// ============================================================
// Property 18: Sync Idempotency
// **Validates: Requirements 14.1, 14.2**
// ============================================================

describe('Property 18: Sync Idempotency', function () {
    test('fullSync yang dijalankan 2x dengan data sama menghasilkan skipped pada eksekusi kedua', function () {
        // UNTUK SEMUA set Pegawai,
        // fullSync kedua (data tidak berubah) SELALU menghasilkan semua skipped.

        // Gunakan mutable reference agar Http::fake bisa diupdate per iterasi
        $currentData = [];

        Http::fake(function ($request) use (&$currentData) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                if (preg_match('/users\/([^\/]+)\/role-mappings/', $url, $matches)) {
                    $userId = $matches[1];
                    foreach ($currentData as $data) {
                        if ($data['id'] === $userId && isset($data['_roles'])) {
                            return Http::response(
                                array_map(fn ($r) => ['name' => $r], $data['_roles'])
                            );
                        }
                    }
                }

                return Http::response([]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
                parse_str($queryString, $params);
                $nip = $params['username'] ?? '';

                if (isset($currentData[$nip])) {
                    $userData = $currentData[$nip];
                    unset($userData['_roles']);

                    return Http::response([$userData]);
                }

                return Http::response([]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            if (str_contains($url, '/users') && $method === 'POST') {
                return Http::response(null, 201, [
                    'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-'.bin2hex(random_bytes(4)),
                ]);
            }

            if (str_contains($url, '/roles') && $method === 'GET') {
                return Http::response([]);
            }

            return Http::response(null, 404);
        });

        for ($i = 0; $i < 10; $i++) {
            $count = random_int(1, 4);

            for ($j = 0; $j < $count; $j++) {
                Pegawai::factory()->create([
                    'status_pegawai' => StatusPegawai::Aktif,
                ]);
            }

            // Query SEMUA Pegawai aktif dan buat data Keycloak yang cocok
            $allActivePegawai = Pegawai::where('status_pegawai', StatusPegawai::Aktif)
                ->with('iamRoles')
                ->get();

            $currentData = [];
            foreach ($allActivePegawai as $p) {
                $namaParts = explode(' ', trim($p->nama_lengkap), 2);
                $roles = $p->iamRoles->pluck('slug')->sort()->values()->all();
                $kcId = 'kc-'.bin2hex(random_bytes(4));

                $currentData[$p->nip] = [
                    'id' => $kcId,
                    'username' => $p->nip,
                    'email' => $p->email,
                    'firstName' => $namaParts[0],
                    'lastName' => $namaParts[1] ?? '',
                    'enabled' => true,
                    '_roles' => $roles,
                ];
            }

            $totalActive = $allActivePegawai->count();

            $result = $this->syncService->fullSync();

            // Semua harus di-skip karena data sudah cocok (Req 14.2)
            expect($result->created)->toBe(0,
                "Iterasi {$i}: expected 0 created (data sudah cocok), got {$result->created}"
            )
                ->and($result->updated)->toBe(0,
                    "Iterasi {$i}: expected 0 updated (data sudah cocok), got {$result->updated}"
                )
                ->and($result->skipped)->toBe($totalActive,
                    "Iterasi {$i}: expected {$totalActive} skipped, got {$result->skipped}"
                )
                ->and($result->errors)->toBe(0,
                    "Iterasi {$i}: expected 0 errors, got {$result->errors}"
                );

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });

    test('sync idempoten TIDAK membuat write API call ke Keycloak untuk data yang cocok', function () {
        // UNTUK SEMUA Pegawai yang datanya sudah cocok di Keycloak,
        // sync TIDAK PERNAH mengirim PUT/POST ke Keycloak (Req 14.2).

        $currentData = [];

        Http::fake(function ($request) use (&$currentData) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                if (preg_match('/users\/([^\/]+)\/role-mappings/', $url, $matches)) {
                    $userId = $matches[1];
                    foreach ($currentData as $data) {
                        if ($data['id'] === $userId && isset($data['_roles'])) {
                            return Http::response(
                                array_map(fn ($r) => ['name' => $r], $data['_roles'])
                            );
                        }
                    }
                }

                return Http::response([]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
                parse_str($queryString, $params);
                $nip = $params['username'] ?? '';

                if (isset($currentData[$nip])) {
                    $userData = $currentData[$nip];
                    unset($userData['_roles']);

                    return Http::response([$userData]);
                }

                return Http::response([]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            if (str_contains($url, '/users') && $method === 'POST') {
                return Http::response(null, 201, [
                    'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-'.bin2hex(random_bytes(4)),
                ]);
            }

            if (str_contains($url, '/roles') && $method === 'GET') {
                return Http::response([]);
            }

            return Http::response(null, 404);
        });

        for ($i = 0; $i < 10; $i++) {
            $count = random_int(1, 3);

            for ($j = 0; $j < $count; $j++) {
                Pegawai::factory()->create([
                    'status_pegawai' => StatusPegawai::Aktif,
                ]);
            }

            // Query SEMUA Pegawai aktif dan buat data Keycloak yang cocok
            $allActivePegawai = Pegawai::where('status_pegawai', StatusPegawai::Aktif)
                ->with('iamRoles')
                ->get();

            $currentData = [];
            foreach ($allActivePegawai as $p) {
                $namaParts = explode(' ', trim($p->nama_lengkap), 2);
                $roles = $p->iamRoles->pluck('slug')->sort()->values()->all();
                $kcId = 'kc-'.bin2hex(random_bytes(4));

                $currentData[$p->nip] = [
                    'id' => $kcId,
                    'username' => $p->nip,
                    'email' => $p->email,
                    'firstName' => $namaParts[0],
                    'lastName' => $namaParts[1] ?? '',
                    'enabled' => true,
                    '_roles' => $roles,
                ];
            }

            $result = $this->syncService->fullSync();

            // Verifikasi semua di-skip (tanpa write calls)
            expect($result->created)->toBe(0)
                ->and($result->updated)->toBe(0);

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });

    test('keycloak_synced_at TIDAK diupdate saat sync skip record (Req 14.3)', function () {
        // UNTUK SEMUA Pegawai yang di-skip oleh sync,
        // keycloak_synced_at TIDAK PERNAH diupdate.

        $currentData = [];

        Http::fake(function ($request) use (&$currentData) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'openid-connect/token')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'GET') {
                if (preg_match('/users\/([^\/]+)\/role-mappings/', $url, $matches)) {
                    $userId = $matches[1];
                    foreach ($currentData as $data) {
                        if ($data['id'] === $userId && isset($data['_roles'])) {
                            return Http::response(
                                array_map(fn ($r) => ['name' => $r], $data['_roles'])
                            );
                        }
                    }
                }

                return Http::response([]);
            }

            if (str_contains($url, 'role-mappings/realm') && $method === 'POST') {
                return Http::response(null, 204);
            }

            if (preg_match('/\/users\?/', $url) && $method === 'GET') {
                $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
                parse_str($queryString, $params);
                $nip = $params['username'] ?? '';

                if (isset($currentData[$nip])) {
                    $userData = $currentData[$nip];
                    unset($userData['_roles']);

                    return Http::response([$userData]);
                }

                return Http::response([]);
            }

            if (preg_match('/\/users\/[^\/\?]+$/', $url) && $method === 'PUT') {
                return Http::response(null, 204);
            }

            if (str_contains($url, '/users') && $method === 'POST') {
                return Http::response(null, 201, [
                    'Location' => 'http://keycloak.test/admin/realms/kepegawaian/users/kc-new-'.bin2hex(random_bytes(4)),
                ]);
            }

            if (str_contains($url, '/roles') && $method === 'GET') {
                return Http::response([]);
            }

            return Http::response(null, 404);
        });

        for ($i = 0; $i < 10; $i++) {
            $count = random_int(1, 3);

            for ($j = 0; $j < $count; $j++) {
                Pegawai::factory()->create([
                    'status_pegawai' => StatusPegawai::Aktif,
                    'keycloak_synced_at' => null,
                ]);
            }

            // Query SEMUA Pegawai aktif dan buat data Keycloak yang cocok
            $allActivePegawai = Pegawai::where('status_pegawai', StatusPegawai::Aktif)
                ->with('iamRoles')
                ->get();

            $currentData = [];
            foreach ($allActivePegawai as $p) {
                $namaParts = explode(' ', trim($p->nama_lengkap), 2);
                $roles = $p->iamRoles->pluck('slug')->sort()->values()->all();
                $kcId = 'kc-'.bin2hex(random_bytes(4));

                $currentData[$p->nip] = [
                    'id' => $kcId,
                    'username' => $p->nip,
                    'email' => $p->email,
                    'firstName' => $namaParts[0],
                    'lastName' => $namaParts[1] ?? '',
                    'enabled' => true,
                    '_roles' => $roles,
                ];
            }

            // Reset keycloak_synced_at untuk verifikasi
            Pegawai::where('status_pegawai', StatusPegawai::Aktif)
                ->update(['keycloak_synced_at' => null]);

            $result = $this->syncService->fullSync();

            // Semua Pegawai yang di-skip HARUS tetap memiliki keycloak_synced_at = null
            $allActivePegawai->each(function ($p) use ($i) {
                $p->refresh();
                expect($p->keycloak_synced_at)->toBeNull(
                    "Iterasi {$i}: Pegawai {$p->nip} di-skip tapi keycloak_synced_at diupdate"
                );
            });

            // Cleanup
            Pegawai::query()->forceDelete();
        }
    });
});
