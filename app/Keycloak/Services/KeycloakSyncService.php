<?php

namespace App\Keycloak\Services;

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\ConflictResolutionInterface;
use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\DataTransferObjects\HealthStatus;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use App\Keycloak\Models\KeycloakSyncState;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk sinkronisasi data Pegawai dengan Keycloak users.
 *
 * Mengelola operasi CRUD terhadap Keycloak Admin API termasuk
 * full sync, incremental sync, single sync, disable user,
 * dan health check koneksi.
 */
class KeycloakSyncService implements KeycloakSyncServiceInterface
{
    /** Token akses service account untuk Admin API */
    private ?string $serviceToken = null;

    /** Timestamp expiry token service account */
    private ?int $tokenExpiresAt = null;

    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker,
        private readonly ConflictResolutionInterface $conflictResolver,
        private readonly SyncAuditLogger $auditLogger,
    ) {}

    /**
     * Full sync semua Pegawai aktif ke Keycloak.
     *
     * Mengambil seluruh Pegawai dengan status aktif dan melakukan
     * create/update di Keycloak. Konflik di-resolve menggunakan
     * kebijakan "Pegawai Wins".
     */
    public function fullSync(): SyncResult
    {
        // Abort jika circuit breaker open
        if ($this->circuitBreaker->isOpen()) {
            return new SyncResult(
                success: false,
                errorDetails: [['nip' => '', 'error' => 'Circuit breaker dalam state OPEN, sync dibatalkan']],
                syncType: 'full',
                completedAt: now(),
            );
        }

        $pegawaiList = Pegawai::query()
            ->where('status_pegawai', StatusPegawai::Aktif)
            ->with('iamRoles')
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $conflicts = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($pegawaiList as $pegawai) {
            // Cek circuit breaker sebelum setiap operasi (Req 6.9)
            if ($this->circuitBreaker->isOpen()) {
                $this->updateSyncState('full', $created + $updated, $conflicts);

                return new SyncResult(
                    success: false,
                    created: $created,
                    updated: $updated,
                    skipped: $skipped,
                    conflicts: $conflicts,
                    errors: $errors,
                    errorDetails: $errorDetails,
                    syncType: 'full',
                    completedAt: now(),
                );
            }

            try {
                $result = $this->syncSinglePegawai($pegawai);
                $created += $result['created'];
                $updated += $result['updated'];
                $skipped += $result['skipped'];
                $conflicts += $result['conflicts'];
            } catch (KeycloakCircuitOpenException $e) {
                // Circuit breaker terbuka saat sync → abort (Req 6.9)
                $this->updateSyncState('full', $created + $updated, $conflicts);

                return new SyncResult(
                    success: false,
                    created: $created,
                    updated: $updated,
                    skipped: $skipped,
                    conflicts: $conflicts,
                    errors: $errors,
                    errorDetails: $errorDetails,
                    syncType: 'full',
                    completedAt: now(),
                );
            } catch (\Throwable $e) {
                // Single Pegawai gagal → record error, lanjutkan sisanya (Req 6.8)
                $errors++;
                $errorDetails[] = [
                    'nip' => $pegawai->nip,
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                ];
                $this->auditLogger->logSyncFailure($pegawai, $e->getMessage());
            }
        }

        // Update sync state (Req 6.7)
        $this->updateSyncState('full', $created + $updated, $conflicts);

        return new SyncResult(
            success: $errors === 0,
            created: $created,
            updated: $updated,
            skipped: $skipped,
            conflicts: $conflicts,
            errors: $errors,
            errorDetails: $errorDetails,
            syncType: 'full',
            completedAt: now(),
        );
    }

    /**
     * Incremental sync: hanya Pegawai yang updated dalam 24 jam terakhir.
     *
     * Memproses Pegawai aktif dengan updated_at dalam rentang
     * waktu yang dikonfigurasi (default: 24 jam).
     */
    public function incrementalSync(): SyncResult
    {
        // Abort jika circuit breaker open
        if ($this->circuitBreaker->isOpen()) {
            return new SyncResult(
                success: false,
                errorDetails: [['nip' => '', 'error' => 'Circuit breaker dalam state OPEN, sync dibatalkan']],
                syncType: 'incremental',
                completedAt: now(),
            );
        }

        $windowHours = config('keycloak.sync.incremental_window_hours', 24);

        $pegawaiList = Pegawai::query()
            ->where('status_pegawai', StatusPegawai::Aktif)
            ->where('updated_at', '>=', now()->subHours($windowHours))
            ->with('iamRoles')
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $conflicts = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($pegawaiList as $pegawai) {
            if ($this->circuitBreaker->isOpen()) {
                $this->updateSyncState('incremental', $created + $updated, $conflicts);

                return new SyncResult(
                    success: false,
                    created: $created,
                    updated: $updated,
                    skipped: $skipped,
                    conflicts: $conflicts,
                    errors: $errors,
                    errorDetails: $errorDetails,
                    syncType: 'incremental',
                    completedAt: now(),
                );
            }

            try {
                $result = $this->syncSinglePegawai($pegawai);
                $created += $result['created'];
                $updated += $result['updated'];
                $skipped += $result['skipped'];
                $conflicts += $result['conflicts'];
            } catch (KeycloakCircuitOpenException $e) {
                $this->updateSyncState('incremental', $created + $updated, $conflicts);

                return new SyncResult(
                    success: false,
                    created: $created,
                    updated: $updated,
                    skipped: $skipped,
                    conflicts: $conflicts,
                    errors: $errors,
                    errorDetails: $errorDetails,
                    syncType: 'incremental',
                    completedAt: now(),
                );
            } catch (\Throwable $e) {
                $errors++;
                $errorDetails[] = [
                    'nip' => $pegawai->nip,
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                ];
                $this->auditLogger->logSyncFailure($pegawai, $e->getMessage());
            }
        }

        // Update sync state (Req 7.5)
        $this->updateSyncState('incremental', $created + $updated, $conflicts);

        return new SyncResult(
            success: $errors === 0,
            created: $created,
            updated: $updated,
            skipped: $skipped,
            conflicts: $conflicts,
            errors: $errors,
            errorDetails: $errorDetails,
            syncType: 'incremental',
            completedAt: now(),
        );
    }

    /**
     * Sync single Pegawai ke Keycloak.
     *
     * Melakukan sinkronisasi satu record Pegawai. Jika NIP tidak ditemukan
     * atau Pegawai tidak aktif, mengembalikan SyncResult gagal (Req 7.3).
     */
    public function syncPegawai(Pegawai $pegawai): SyncResult
    {
        // Abort jika circuit breaker open
        if ($this->circuitBreaker->isOpen()) {
            return new SyncResult(
                success: false,
                errorDetails: [['nip' => $pegawai->nip, 'error' => 'Circuit breaker dalam state OPEN, sync dibatalkan']],
                syncType: 'single',
                completedAt: now(),
            );
        }

        // Jika Pegawai tidak aktif → return failed (Req 7.3)
        if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
            // Jika status berubah ke inactive → disable di Keycloak (Req 7.4)
            try {
                $this->disableUser($pegawai->nip);
            } catch (\Throwable) {
                // Jika disable gagal, tetap return failed
            }

            return new SyncResult(
                success: false,
                errorDetails: [['nip' => $pegawai->nip, 'error' => 'Pegawai tidak aktif']],
                syncType: 'single',
                completedAt: now(),
            );
        }

        try {
            // Load relasi jika belum
            $pegawai->loadMissing('iamRoles');

            $result = $this->syncSinglePegawai($pegawai);

            // Update sync state (Req 7.5)
            $this->updateSyncState('single', $result['created'] + $result['updated'], $result['conflicts']);

            return new SyncResult(
                success: true,
                created: $result['created'],
                updated: $result['updated'],
                skipped: $result['skipped'],
                conflicts: $result['conflicts'],
                syncType: 'single',
                completedAt: now(),
            );
        } catch (\Throwable $e) {
            $this->auditLogger->logSyncFailure($pegawai, $e->getMessage());

            return new SyncResult(
                success: false,
                errors: 1,
                errorDetails: [['nip' => $pegawai->nip, 'error' => mb_substr($e->getMessage(), 0, 1000)]],
                syncType: 'single',
                completedAt: now(),
            );
        }
    }

    /**
     * Disable user di Keycloak (set enabled=false).
     *
     * Mencari user berdasarkan username=NIP dan mengubah
     * atribut enabled menjadi false (Req 7.4).
     */
    public function disableUser(string $nip): void
    {
        $keycloakUser = $this->findKeycloakUser($nip);

        if ($keycloakUser === null) {
            return;
        }

        $this->circuitBreaker->call(function () use ($keycloakUser): void {
            $token = $this->getServiceToken();
            $baseUrl = $this->getAdminBaseUrl();

            $response = Http::withToken($token)
                ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                ->put("{$baseUrl}/users/{$keycloakUser['id']}", [
                    'enabled' => false,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException(
                    "Gagal disable user {$nip} di Keycloak: HTTP {$response->status()}"
                );
            }
        });
    }

    /**
     * Cek apakah user dengan NIP tertentu ada di Keycloak.
     *
     * Melakukan pencarian user berdasarkan username=NIP.
     */
    public function userExists(string $nip): bool
    {
        return $this->findKeycloakUser($nip) !== null;
    }

    /**
     * Health check koneksi ke Keycloak.
     *
     * Mengembalikan status kesehatan termasuk circuit breaker state,
     * failure count, dan timestamps.
     */
    public function healthCheck(): HealthStatus
    {
        $circuitState = $this->circuitBreaker->getState();
        $failureCount = $this->circuitBreaker->getFailureCount();
        $isHealthy = $circuitState === 'closed';

        // Ambil timestamp dari circuit breaker
        $lastSuccessAt = null;
        $lastFailureAt = null;
        $lastError = null;

        if ($this->circuitBreaker instanceof KeycloakCircuitBreaker) {
            $lastSuccessTimestamp = $this->circuitBreaker->getLastSuccessAt();
            $lastFailureTimestamp = $this->circuitBreaker->getLastFailureAt();

            $lastSuccessAt = $lastSuccessTimestamp ? Carbon::createFromTimestamp($lastSuccessTimestamp) : null;
            $lastFailureAt = $lastFailureTimestamp ? Carbon::createFromTimestamp($lastFailureTimestamp) : null;
        }

        return new HealthStatus(
            isHealthy: $isHealthy,
            circuitState: $circuitState,
            failureCount: $failureCount,
            lastSuccessAt: $lastSuccessAt,
            lastFailureAt: $lastFailureAt,
            lastError: $lastError,
        );
    }

    /**
     * Sinkronisasi satu Pegawai ke Keycloak (internal logic).
     *
     * Mengembalikan array hasil operasi untuk diakumulasi oleh caller.
     *
     * @return array{created: int, updated: int, skipped: int, conflicts: int}
     */
    private function syncSinglePegawai(Pegawai $pegawai): array
    {
        $keycloakUser = $this->findKeycloakUser($pegawai->nip);

        if ($keycloakUser === null) {
            // User belum ada di Keycloak → create (Req 6.2)
            $keycloakUserId = $this->createKeycloakUser($pegawai);
            $this->assignRealmRoles($keycloakUserId, $pegawai);

            // Log audit create (Req 9.1)
            $this->auditLogger->logCreate($pegawai);

            // Update keycloak_synced_at (Req 6.5)
            $pegawai->update(['keycloak_synced_at' => now(), 'keycloak_user_id' => $keycloakUserId]);

            return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0];
        }

        // User sudah ada → check conflicts (Req 6.4)
        $keycloakUserWithRoles = $this->enrichKeycloakUserWithRoles($keycloakUser);
        $detectedConflicts = $this->conflictResolver->detectConflicts($pegawai, $keycloakUserWithRoles);

        if (empty($detectedConflicts)) {
            // Tidak ada konflik → skip (Req 14.2, 14.3)
            return ['created' => 0, 'updated' => 0, 'skipped' => 1, 'conflicts' => 0];
        }

        // Resolve semua konflik dengan Pegawai Wins policy
        foreach ($detectedConflicts as $conflictType) {
            $conflictResult = $this->conflictResolver->resolve($conflictType, $pegawai, $keycloakUserWithRoles);

            // Log audit conflict (Req 9.2)
            $this->auditLogger->logConflict(
                $pegawai,
                $conflictType,
                $conflictResult->pegawaiData,
                $conflictResult->keycloakData,
                $conflictResult->resolvedData,
            );
        }

        // Update Keycloak user dengan data resolved
        $this->updateKeycloakUser($keycloakUser['id'], $pegawai);

        // Assign ulang roles jika ada RoleOverride conflict
        if (in_array(ConflictType::RoleOverride, $detectedConflicts)) {
            $this->assignRealmRoles($keycloakUser['id'], $pegawai);
        }

        // Update keycloak_synced_at (Req 6.5)
        $pegawai->update(['keycloak_synced_at' => now(), 'keycloak_user_id' => $keycloakUser['id']]);

        return ['created' => 0, 'updated' => 1, 'skipped' => 0, 'conflicts' => count($detectedConflicts)];
    }

    /**
     * Cari user di Keycloak berdasarkan username=NIP.
     *
     * @return array<string, mixed>|null
     */
    private function findKeycloakUser(string $nip): ?array
    {
        try {
            return $this->circuitBreaker->call(function () use ($nip): ?array {
                $token = $this->getServiceToken();
                $baseUrl = $this->getAdminBaseUrl();

                $response = Http::withToken($token)
                    ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                    ->get("{$baseUrl}/users", [
                        'username' => $nip,
                        'exact' => 'true',
                    ]);

                if ($response->failed()) {
                    throw new \RuntimeException(
                        "Gagal mencari user {$nip} di Keycloak: HTTP {$response->status()}"
                    );
                }

                $users = $response->json();

                if (empty($users)) {
                    return null;
                }

                // Cari exact match berdasarkan username
                foreach ($users as $user) {
                    if (($user['username'] ?? '') === $nip) {
                        return $user;
                    }
                }

                return null;
            });
        } catch (KeycloakCircuitOpenException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning("Gagal mencari user di Keycloak: {$e->getMessage()}", ['nip' => $nip]);
            throw $e;
        }
    }

    /**
     * Buat user baru di Keycloak (Req 6.2).
     *
     * Mengembalikan Keycloak user ID dari header Location.
     */
    private function createKeycloakUser(Pegawai $pegawai): string
    {
        return $this->circuitBreaker->call(function () use ($pegawai): string {
            $token = $this->getServiceToken();
            $baseUrl = $this->getAdminBaseUrl();

            [$firstName, $lastName] = $this->splitNamaLengkap($pegawai->nama_lengkap);

            $response = Http::withToken($token)
                ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                ->post("{$baseUrl}/users", [
                    'username' => $pegawai->nip,
                    'email' => $pegawai->email,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'enabled' => true,
                ]);

            if ($response->status() === 409) {
                // User sudah ada (race condition), ambil ID-nya
                $existingUser = $this->findKeycloakUser($pegawai->nip);

                return $existingUser['id'] ?? throw new \RuntimeException(
                    "User {$pegawai->nip} sudah ada di Keycloak tapi tidak ditemukan saat lookup"
                );
            }

            if ($response->failed()) {
                throw new \RuntimeException(
                    "Gagal membuat user {$pegawai->nip} di Keycloak: HTTP {$response->status()} - {$response->body()}"
                );
            }

            // Ambil user ID dari Location header
            $locationHeader = $response->header('Location');

            if ($locationHeader) {
                return basename($locationHeader);
            }

            // Fallback: cari user yang baru dibuat
            $createdUser = $this->findKeycloakUser($pegawai->nip);

            return $createdUser['id'] ?? throw new \RuntimeException(
                "Gagal mendapatkan ID user setelah pembuatan: {$pegawai->nip}"
            );
        });
    }

    /**
     * Update user yang sudah ada di Keycloak.
     */
    private function updateKeycloakUser(string $keycloakUserId, Pegawai $pegawai): void
    {
        $this->circuitBreaker->call(function () use ($keycloakUserId, $pegawai): void {
            $token = $this->getServiceToken();
            $baseUrl = $this->getAdminBaseUrl();

            [$firstName, $lastName] = $this->splitNamaLengkap($pegawai->nama_lengkap);

            $response = Http::withToken($token)
                ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                ->put("{$baseUrl}/users/{$keycloakUserId}", [
                    'username' => $pegawai->nip,
                    'email' => $pegawai->email,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'enabled' => $pegawai->status_pegawai === StatusPegawai::Aktif,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException(
                    "Gagal update user {$pegawai->nip} di Keycloak: HTTP {$response->status()}"
                );
            }
        });
    }

    /**
     * Assign realm roles ke user di Keycloak (Req 6.3).
     */
    private function assignRealmRoles(string $keycloakUserId, Pegawai $pegawai): void
    {
        $roleSlugs = $pegawai->iamRoles->pluck('slug')->all();

        if (empty($roleSlugs)) {
            return;
        }

        $this->circuitBreaker->call(function () use ($keycloakUserId, $roleSlugs): void {
            $token = $this->getServiceToken();
            $baseUrl = $this->getAdminBaseUrl();

            // Ambil semua realm roles yang tersedia
            $response = Http::withToken($token)
                ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                ->get("{$baseUrl}/roles");

            if ($response->failed()) {
                throw new \RuntimeException('Gagal mengambil daftar realm roles dari Keycloak');
            }

            $availableRoles = $response->json();

            // Filter roles yang sesuai dengan slug Pegawai
            $rolesToAssign = array_filter($availableRoles, function ($role) use ($roleSlugs) {
                return in_array($role['name'], $roleSlugs);
            });

            if (empty($rolesToAssign)) {
                return;
            }

            // Assign roles ke user
            $rolePayload = array_map(fn ($role) => [
                'id' => $role['id'],
                'name' => $role['name'],
            ], array_values($rolesToAssign));

            $assignResponse = Http::withToken($token)
                ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                ->post("{$baseUrl}/users/{$keycloakUserId}/role-mappings/realm", $rolePayload);

            if ($assignResponse->failed()) {
                throw new \RuntimeException(
                    "Gagal assign roles ke user di Keycloak: HTTP {$assignResponse->status()}"
                );
            }
        });
    }

    /**
     * Enrich data Keycloak user dengan realm roles yang di-assign.
     *
     * @param  array<string, mixed>  $keycloakUser
     * @return array<string, mixed>
     */
    private function enrichKeycloakUserWithRoles(array $keycloakUser): array
    {
        try {
            $roles = $this->circuitBreaker->call(function () use ($keycloakUser): array {
                $token = $this->getServiceToken();
                $baseUrl = $this->getAdminBaseUrl();

                $response = Http::withToken($token)
                    ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
                    ->get("{$baseUrl}/users/{$keycloakUser['id']}/role-mappings/realm");

                if ($response->failed()) {
                    return [];
                }

                return array_column($response->json() ?? [], 'name');
            });

            $keycloakUser['realmRoles'] = $roles;
        } catch (\Throwable) {
            $keycloakUser['realmRoles'] = [];
        }

        return $keycloakUser;
    }

    /**
     * Dapatkan service account token (client_credentials grant).
     *
     * Token di-cache selama masa berlakunya untuk efisiensi.
     */
    private function getServiceToken(): string
    {
        // Gunakan cached token jika masih valid
        if ($this->serviceToken !== null && $this->tokenExpiresAt !== null && time() < $this->tokenExpiresAt) {
            return $this->serviceToken;
        }

        $baseUrl = config('keycloak.base_url');
        $realm = config('keycloak.realm');
        $clientId = config('keycloak.service_account.client_id');
        $clientSecret = config('keycloak.service_account.client_secret');

        $response = Http::asForm()
            ->timeout(config('keycloak.tokens.request_timeout_seconds', 5))
            ->post("{$baseUrl}/realms/{$realm}/protocol/openid-connect/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Gagal mendapatkan service account token: HTTP {$response->status()} - {$response->body()}"
            );
        }

        $data = $response->json();
        $this->serviceToken = $data['access_token'];
        // Cache token dengan buffer 30 detik sebelum expiry
        $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 300) - 30;

        return $this->serviceToken;
    }

    /**
     * Dapatkan base URL untuk Keycloak Admin API.
     */
    private function getAdminBaseUrl(): string
    {
        $baseUrl = config('keycloak.base_url');
        $realm = config('keycloak.realm');

        return "{$baseUrl}/admin/realms/{$realm}";
    }

    /**
     * Split nama_lengkap menjadi firstName dan lastName.
     *
     * @return array{0: string, 1: string}
     */
    private function splitNamaLengkap(?string $namaLengkap): array
    {
        if ($namaLengkap === null || $namaLengkap === '') {
            return ['', ''];
        }

        $parts = explode(' ', trim($namaLengkap), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Update tabel keycloak_sync_state setelah operasi sync.
     */
    private function updateSyncState(string $syncType, int $totalSynced, int $totalConflicts): void
    {
        KeycloakSyncState::query()->updateOrCreate(
            ['id' => 1],
            [
                'last_sync_at' => now()->toIso8601String(),
                'last_sync_type' => $syncType,
                'total_synced' => $totalSynced,
                'total_conflicts' => $totalConflicts,
            ]
        );
    }
}
