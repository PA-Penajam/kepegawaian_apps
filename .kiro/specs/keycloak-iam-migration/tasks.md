# Implementation Plan: Keycloak IAM Migration

## Overview

Implementasi migrasi IAM ke Keycloak 26.6.3 menggunakan Big Bang approach dengan Wrapper Pattern. Plan ini dibagi menjadi 4 fase: Foundation (backend core), Sync Service, Admin Panel (Filament), dan Integration & Polish. Setiap fase membangun di atas fase sebelumnya untuk memastikan incremental progress yang tervalidasi.

## Tasks

- [ ] 1. Phase 1: Foundation - Configuration & Core Interfaces
  - [-] 1.1 Create Keycloak configuration file and service provider
    - Buat file `config/keycloak.php` dengan semua konfigurasi (base_url, realm, client_id, client_secret, scopes, token settings, emergency settings, circuit breaker thresholds)
    - Buat `KeycloakServiceProvider` untuk register bindings interface ke implementasi
    - Update `.env.example` dengan variabel Keycloak yang diperlukan
    - _Requirements: 1.3, 5.2, 10.1_

  - [-] 1.2 Create data transfer objects and enums
    - Buat DTO classes: `TokenResult`, `IdTokenClaims`, `SyncResult`, `PkcePair`, `HealthStatus`, `AuthorizationRequest`, `ConflictResult`
    - Buat enum: `ConflictType` (DataMismatch, StatusConflict, RoleOverride, IdentifierChange), `ConflictPolicy`, `CircuitState`
    - Buat custom exceptions: `KeycloakException`, `KeycloakCircuitOpenException`
    - _Requirements: 5.1, 8.1_

  - [-] 1.3 Create core interfaces
    - Buat `KeycloakClientInterface` dengan methods: buildAuthorizationUrl, exchangeCode, refreshToken, validateIdToken, logout, silentCheck
    - Buat `KeycloakTokenStorageInterface` dengan methods: storeTokens, getAccessToken, getRefreshToken, getAccessTokenExpiry, rotateTokens, clearTokens, isTokenValid
    - Buat `KeycloakSyncServiceInterface` dengan methods: fullSync, incrementalSync, syncPegawai, disableUser, userExists, healthCheck
    - Buat `CircuitBreakerInterface` dengan methods: call, isOpen, getState, reset, getFailureCount
    - Buat `ConflictResolutionInterface` dengan methods: detectConflicts, resolve, getPolicy
    - _Requirements: 1.1, 3.2, 5.1, 6.1, 8.1_

  - [~] 1.4 Implement PkceGenerator
    - Buat class `PkceGenerator` yang menghasilkan PKCE pair sesuai RFC 7636
    - code_verifier: 43-128 karakter, base64url encoded dari minimum 32 random bytes
    - code_challenge: BASE64URL(SHA256(code_verifier)) dengan method S256
    - Validasi CSPRNG availability, abort jika tidak tersedia
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

  - [~] 1.5 Write property test for PKCE generation
    - **Property 1: PKCE Integrity**
    - **Validates: Requirements 1.1, 11.1, 11.2, 11.3, 11.4**

  - [~] 1.6 Implement KeycloakTokenStorage
    - Buat class yang menyimpan tokens di Laravel encrypted session
    - Encrypt refresh token menggunakan `Crypt::encryptString()` sebelum simpan
    - Track access token expiry timestamp untuk proactive refresh
    - Implementasi `rotateTokens()` untuk atomic token replacement
    - Implementasi `clearTokens()` untuk hapus semua data Keycloak dari session
    - _Requirements: 3.2, 3.3, 4.2_

  - [~] 1.7 Write property tests for TokenStorage
    - **Property 7: Token Encryption at Rest**
    - **Validates: Requirement 3.2**
    - **Property 10: Token Rotation Consistency**
    - **Validates: Requirements 4.2, 4.3**

  - [~] 1.8 Implement KeycloakClient (OIDC via jumbojett)
    - Buat class yang wraps `jumbojett/openid-connect-php` library
    - Implementasi `buildAuthorizationUrl()` dengan PKCE params dan state
    - Implementasi `exchangeCode()` untuk token exchange
    - Implementasi `refreshToken()` untuk token refresh
    - Implementasi `validateIdToken()` untuk JWT signature dan claims validation
    - Implementasi `logout()` untuk end-session endpoint call
    - _Requirements: 1.1, 1.3, 1.6, 1.7, 3.4_

  - [~] 1.9 Write property tests for authorization URL
    - **Property 3: Authorization URL Completeness**
    - **Validates: Requirement 1.3**

  - [~] 1.10 Implement KeycloakCircuitBreaker
    - Implementasi state machine: CLOSED, OPEN, HALF_OPEN
    - CLOSED→OPEN: setelah 5 consecutive failures
    - OPEN→HALF_OPEN: setelah 30s recovery timeout
    - HALF_OPEN→CLOSED: setelah 2 consecutive successes
    - HALF_OPEN→OPEN: setelah 1 failure
    - State disimpan di cache (configurable driver)
    - Timeout 5 detik untuk request ke Keycloak
    - Sediakan manual reset method
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 5.11_

  - [~] 1.11 Write property test for circuit breaker state machine
    - **Property 11: Circuit Breaker State Machine**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.6**

  - [~] 1.12 Implement KeycloakAuthController
    - Buat controller dengan methods: login, callback, logout
    - `login()`: generate PKCE, state, store di session, redirect ke Keycloak
    - `callback()`: validate state, exchange code, validate ID token, verify NIP di Pegawai, store session, regenerate session, Auth::login
    - `logout()`: invoke end-session, clear tokens, invalidate session, regenerate CSRF
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.1, 3.4, 3.5_

  - [~] 1.13 Write property tests for auth controller logic
    - **Property 2: State CSRF Protection**
    - **Validates: Requirements 1.4, 1.5**
    - **Property 4: NIP Verification Gate**
    - **Validates: Requirements 2.2, 2.3**
    - **Property 6: Session Regeneration on Login**
    - **Validates: Requirement 3.1**

  - [~] 1.14 Implement middleware stack
    - Buat `KeycloakTokenRefresh` middleware: proactive refresh 60s sebelum expiry, handle circuit open, handle refresh failure
    - Buat `EmergencyBypass` middleware: allow emergency routes saat circuit open
    - Buat `VerifyIamPermission` middleware: check permission dari session
    - Configure middleware order di `bootstrap/app.php`
    - Skip paths: keycloak/*, emergency/*, _health
    - _Requirements: 4.1, 4.3, 4.4, 4.5, 4.6, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7_

  - [~] 1.15 Write property tests for middleware
    - **Property 9: Proactive Token Refresh Trigger**
    - **Validates: Requirement 4.1**
    - **Property 22: Permission Enforcement**
    - **Validates: Requirements 13.3, 13.4**
    - **Property 23: Middleware Path Exclusion**
    - **Validates: Requirement 13.2**

  - [~] 1.16 Register Keycloak auth routes
    - Buat route file `routes/keycloak.php` dengan routes: GET /keycloak/login, GET /keycloak/callback, POST /keycloak/logout
    - Buat route untuk emergency: GET /emergency/login, POST /emergency/login
    - Register routes di `bootstrap/app.php`
    - _Requirements: 1.3, 10.1_

- [~] 2. Checkpoint - Ensure all Phase 1 tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 3. Phase 2: Sync Service & Database
  - [~] 3.1 Create database migrations
    - Migration: create `keycloak_sync_audit` table dengan semua kolom dan composite indexes
    - Migration: create `keycloak_sync_state` table
    - Migration: create `keycloak_emergency_login_log` table dengan index on `logged_in_at`
    - Migration: add `keycloak_id` (string, nullable, unique) ke `users` table
    - Migration: add `keycloak_synced_at` (timestamp, nullable) dan `keycloak_user_id` (string, nullable) ke `pegawai` table
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

  - [~] 3.2 Create Eloquent models for sync tables
    - Buat model `KeycloakSyncAudit` dengan relationships ke Pegawai dan User
    - Buat model `KeycloakSyncState`
    - Buat model `KeycloakEmergencyLoginLog` dengan relationships ke User
    - Update model `User` untuk menambahkan `keycloak_id` fillable dan cast
    - Update model `Pegawai` untuk menambahkan `keycloak_synced_at` dan `keycloak_user_id`
    - _Requirements: 12.1, 12.3, 12.5, 12.6_

  - [~] 3.3 Implement ConflictResolution service
    - Implementasi `detectConflicts()`: bandingkan email, firstName, lastName, enabled, roles
    - Deteksi 4 jenis konflik: DataMismatch, StatusConflict, RoleOverride, IdentifierChange
    - Implementasi `resolve()` dengan "Pegawai Wins" policy
    - Pastikan detection tidak memutasi input (pure function)
    - _Requirements: 8.1, 8.2, 8.3, 8.5_

  - [~] 3.4 Write property tests for conflict resolution
    - **Property 12: Pegawai Wins Policy**
    - **Validates: Requirements 8.2, 8.3**
    - **Property 13: Conflict Detection Purity**
    - **Validates: Requirement 8.5**

  - [~] 3.5 Implement KeycloakSyncService
    - Implementasi `fullSync()`: ambil semua Pegawai aktif, create/update di Keycloak, resolve conflicts
    - Implementasi `incrementalSync()`: hanya Pegawai updated dalam 24 jam terakhir
    - Implementasi `syncPegawai()`: sync single Pegawai by NIP
    - Implementasi `disableUser()`: set Keycloak user enabled=false
    - Implementasi `userExists()`: check user by username=NIP
    - Implementasi `healthCheck()`: return HealthStatus
    - Update `keycloak_sync_state` setelah setiap operasi sync
    - Update `pegawai.keycloak_synced_at` setelah sync berhasil
    - Abort sync jika circuit breaker open
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 7.1, 7.2, 7.3, 7.4, 7.5_

  - [~] 3.6 Write property tests for sync service
    - **Property 15: Sync Count Invariant**
    - **Validates: Requirement 6.6**
    - **Property 16: Active-Only Sync Filter**
    - **Validates: Requirement 6.1**
    - **Property 17: Incremental Sync Time Window**
    - **Validates: Requirement 7.1**
    - **Property 18: Sync Idempotency**
    - **Validates: Requirements 14.1, 14.2**

  - [~] 3.7 Implement sync audit logging
    - Buat `SyncAuditLogger` service untuk log create, update, conflict, sync_failure events
    - Log pegawai_snapshot dan keycloak_snapshot sebagai JSON (max 64KB)
    - Log conflict_type, resolution, dan resolved_by
    - Truncate error_details ke max 1000 karakter
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

  - [~] 3.8 Write property test for audit completeness
    - **Property 14: Sync Audit Completeness**
    - **Validates: Requirements 8.4, 9.1, 9.2, 9.3**

  - [~] 3.9 Implement Emergency Bypass authentication
    - Buat `EmergencyLoginController` dengan login form dan credential validation
    - Validate credentials dengan constant-time comparison (username) dan Hash::check (password)
    - Create limited session dengan 30-minute timeout
    - Log emergency access ke `keycloak_emergency_login_log`
    - Implement rate limiting: max 5 attempts per IP dalam 15 menit
    - Only allow access saat circuit breaker OPEN dan emergency enabled
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8_

  - [~] 3.10 Write property tests for emergency bypass
    - **Property 19: Emergency Access Guard**
    - **Validates: Requirements 10.1, 10.2, 10.3**
    - **Property 20: Emergency Session Timeout**
    - **Validates: Requirement 10.4**
    - **Property 21: Emergency Audit Trail**
    - **Validates: Requirement 10.6**

  - [~] 3.11 Create Artisan commands for sync operations
    - Buat `keycloak:sync` command dengan options: --type=full|incremental, --nip=
    - Buat `keycloak:health` command untuk check Keycloak connectivity dan circuit state
    - Buat `keycloak:circuit-reset` command untuk manual circuit breaker reset
    - _Requirements: 6.1, 7.1, 7.2, 5.9_

- [~] 4. Checkpoint - Ensure all Phase 2 tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Phase 3: Admin Panel (Filament)
  - [~] 5.1 Create Filament Sync Dashboard page
    - Buat Filament page yang menampilkan: last_sync_at, last_sync_type, total_synced, total_conflicts, circuit breaker state
    - Tampilkan circuit breaker state (CLOSED/OPEN/HALF_OPEN) dengan failure count dan last failure timestamp
    - Sediakan tombol manual reset circuit breaker
    - _Requirements: 15.1, 15.5_

  - [~] 5.2 Create Filament Sync Actions
    - Buat controls untuk trigger full sync dan incremental sync
    - Buat input field untuk single Pegawai sync by NIP (validate 18-digit)
    - Display confirmation notification dengan result counts (created, updated, skipped, conflicts, errors) dalam 5 detik
    - Tampilkan error notification jika circuit breaker OPEN atau validation error
    - _Requirements: 15.2, 15.3, 15.4_

  - [~] 5.3 Create Filament Sync Audit Log Resource
    - Buat Filament resource untuk `keycloak_sync_audit` dengan paginated table (max 50/page)
    - Filter by: event_type, conflict_type, date range
    - Search by: nip
    - Tampilkan kolom: event_type, nip, conflict_type, resolved_by, created_at
    - Detail view untuk pegawai_snapshot dan keycloak_snapshot
    - _Requirements: 15.6_

  - [~] 5.4 Create Filament Emergency Login Log Resource
    - Buat Filament resource untuk `keycloak_emergency_login_log` dengan paginated table (max 50/page)
    - Tampilkan kolom: ip_address, user_agent, logged_in_at, logged_out_at
    - _Requirements: 15.7_

  - [~] 5.5 Write feature tests for Filament admin pages
    - Test dashboard menampilkan sync state data
    - Test sync actions trigger operasi yang benar
    - Test audit log filtering dan pagination
    - _Requirements: 15.1, 15.2, 15.6, 15.7_

- [~] 6. Checkpoint - Ensure all Phase 3 tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Phase 4: Integration & Polish
  - [~] 7.1 Wire middleware into application bootstrap
    - Register middleware stack order di `bootstrap/app.php`: KeycloakTokenRefresh → EmergencyBypass → VerifyIamPermission
    - Apply middleware ke route groups yang tepat
    - Pastikan skip paths (keycloak/*, emergency/*, _health) berfungsi
    - _Requirements: 13.1, 13.2, 13.6, 13.7_

  - [~] 7.2 Implement concurrent token refresh protection
    - Implementasi mutex/lock untuk mencegah multiple concurrent refresh requests
    - Hanya satu refresh request yang dikirim ke Keycloak, request lain menunggu hasilnya
    - _Requirements: 4.6_

  - [~] 7.3 Implement graceful degradation error handling
    - Handle Keycloak unreachable: circuit breaker + degraded mode jika session masih valid
    - Handle token expired + refresh gagal: logout + redirect ke login
    - Handle invalid state: abort 403 + redirect ke login
    - Handle NIP tidak terdaftar: reject dengan error message spesifik
    - Handle circuit breaker open saat sync: abort dan return partial result
    - _Requirements: 1.5, 1.8, 1.9, 2.4, 4.3, 4.4, 4.5, 5.3, 6.9_

  - [~] 7.4 Write integration tests for complete auth flow
    - Test login → callback → session creation → dashboard access
    - Test token refresh cycle → session maintained
    - Test logout → session cleared → Keycloak notified
    - Test emergency bypass flow saat circuit open
    - _Requirements: 1.1-1.9, 2.1-2.6, 3.1-3.5, 4.1-4.6_

  - [~] 7.5 Write integration tests for sync operations
    - Test full sync creates/updates/skips correctly
    - Test conflict detection dan Pegawai Wins resolution
    - Test incremental sync time window filtering
    - Test sync idempotency (multiple runs same data)
    - _Requirements: 6.1-6.9, 7.1-7.5, 8.1-8.6, 14.1-14.4_

  - [~] 7.6 Write property test for session cleanup
    - **Property 8: Session Cleanup on Logout**
    - **Validates: Requirement 3.4**
    - **Property 5: User Claims Storage Completeness**
    - **Validates: Requirement 2.4**

- [~] 8. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Setiap task mereferensikan requirements spesifik untuk traceability
- Checkpoints memastikan validasi incremental di setiap akhir fase
- Property tests memvalidasi correctness properties universal dari design document
- Unit tests memvalidasi specific examples dan edge cases
- Gunakan `pestphp/pest` untuk semua testing (sesuai konvensi proyek)
- Library OIDC: `jumbojett/openid-connect-php` (sudah tersedia di design)
- Admin panel menggunakan Filament 3.x (sudah ada di proyek)
- Circuit breaker state disimpan di cache driver Laravel (configurable)

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["1.4", "1.6", "1.10"] },
    { "id": 2, "tasks": ["1.5", "1.7", "1.8", "1.11"] },
    { "id": 3, "tasks": ["1.9", "1.12", "1.14"] },
    { "id": 4, "tasks": ["1.13", "1.15", "1.16"] },
    { "id": 5, "tasks": ["3.1"] },
    { "id": 6, "tasks": ["3.2", "3.3"] },
    { "id": 7, "tasks": ["3.4", "3.5", "3.7", "3.9"] },
    { "id": 8, "tasks": ["3.6", "3.8", "3.10", "3.11"] },
    { "id": 9, "tasks": ["5.1", "5.3", "5.4"] },
    { "id": 10, "tasks": ["5.2", "5.5"] },
    { "id": 11, "tasks": ["7.1", "7.2"] },
    { "id": 12, "tasks": ["7.3"] },
    { "id": 13, "tasks": ["7.4", "7.5", "7.6"] }
  ]
}
```
