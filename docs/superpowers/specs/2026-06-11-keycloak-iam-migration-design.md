# Keycloak IAM Migration Design

## Overview

Dokumen ini mendefinisikan desain migrasi sistem Identity and Access Management (IAM) dari custom implementation ke Keycloak menggunakan **Big Bang approach** dengan **Wrapper Pattern** untuk backward compatibility.

**Status:** Approved for Implementation
**Last Updated:** 2026-06-11

---

## Decisions Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Strategy | Big Bang | Clean, no hybrid state |
| Scope | Single Phase | Development, all components together |
| OIDC Library | jumbojett/openid-connect-php | Mature, stable, full OIDC support |
| User Creation | JIT + Seed Script | Greenfield development, data from PegawaiSeeder |
| Sync Modul | Admin Panel (Filament) | For non-developer admin staff |
| Role Mapping | Hierarchical | Keycloak composite roles for flexibility |
| 2FA | Full delegation to Keycloak | Single auth system, simpler |
| Degradation | Soft block + admin bypass | Emergency credentials for admins |
| Emergency Credentials | Create new | Clean separation, auditable |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                    KEEPEGAWAIIAN-APPS (Laravel 12)                   │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  KeycloakAuthService (Facade)                                │    │
│  │  ├── KeycloakClient      — OIDC via jumbojett library       │    │
│  │  ├── KeycloakSession     — Token storage & lifecycle        │    │
│  │  ├── KeycloakSync        — Pegawai ↔ Keycloak sync          │    │
│  │  └── KeycloakCircuitBreaker — High availability handling    │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                              │                                       │
│  ┌───────────────────────────┴───────────────────────────────┐      │
│  │  KeycloakAdminPanel (Filament Resource)                    │      │
│  │  ├── SyncController    — Manual sync trigger               │      │
│  │  ├── ConflictResolver  — UI untuk resolve conflicts        │      │
│  │  ├── SyncLogViewer     — Audit trail                       │      │
│  │  └── EmergencyLogin    — Admin bypass UI                   │      │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Updated Middleware                                          │    │
│  │  ├── VerifyIamPermission  — JWT claims dari session         │    │
│  │  ├── KeycloakTokenRefresh — Auto-refresh expired tokens     │    │
│  │  └── EmergencyBypass      — Admin bypass middleware        │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      KEYCLOAK 26.6.3                                 │
│                                                                      │
│  Realm: kepegawaian                                                 │
│  ├── Users (NIP as external_id)                                     │
│  ├── Realm Roles: admin, operator, pimpinan, pegawai, auditor,      │
│  │               viewer, validator                                  │
│  └── Composite Roles: kepegawaian-admin, kepegawaian-pimpinan,      │
│                       kepegawaian-operator, kepegawaian-auditor      │
│                                                                      │
│  Clients:                                                           │
│  ├── kepegawaian-apps      — User authentication (OIDC)            │
│  └── kepegawaian-service   — M2M authentication (Client Credentials)│
└─────────────────────────────────────────────────────────────────────┘
```

---

## 1. Keycloak Auth Flow

### 1.1 OIDC Authorization Code + PKCE Flow

```
┌──────────────────────────────────────────────────────────────────────┐
│                          LOGIN FLOW                                   │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  [User] → /login → [Laravel] → Generate PKCE (code_verifier,        │
│                              code_challenge, state)                   │
│                                    │                                  │
│                                    ▼                                  │
│                              [Laravel] → Store state in session      │
│                                    │                                  │
│                                    ▼                                  │
│                              [Browser] → Redirect to Keycloak         │
│                                   /auth?...                          │
│                                   &code_challenge=...                │
│                                   &state=...                          │
│                                                                       │
│  [Keycloak] → Login page → User enters credentials                   │
│                                    │                                  │
│                                    ▼                                  │
│  [Keycloak] → Redirect to /keycloak/callback?code=...&state=...      │
│                                    │                                  │
│                                    ▼                                  │
│  [Laravel Callback] → Validate state (CSRF)                          │
│                     → Exchange code + code_verifier → /token         │
│                     → Validate ID token (signature, claims)            │
│                     → Extract NIP from claims                         │
│                     → Verify NIP exists in Pegawai table             │
│                     → If exists: Create Laravel session              │
│                     → If not: Reject with error                       │
│                                    │                                  │
│                                    ▼                                  │
│                              [User] → Dashboard                       │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

### 1.2 Token Lifecycle

| Token | TTL | Storage | Notes |
|-------|-----|---------|-------|
| Access Token | 5 min | Session (encrypted) | Auto-refresh via middleware |
| Refresh Token | 8 hour | Session (encrypted) | Rotated every use |
| Laravel Session | - | Cookie | Regenerate on login |

### 1.3 Token Lifecycle Detail

#### Token Storage Strategy

```php
// app/Services/Keycloak/KeycloakTokenStorage.php

class KeycloakTokenStorage
{
    /**
     * Menyimpan token dengan encryption di Laravel session.
     *
     * Key points:
     * - Refresh token dienkripsi sebelum disimpan
     * - Access token tidak di-cache (di-validate per-request)
     * - Token di-rotate setiap refresh untuk security
     */
    public function storeTokens(AuthResult $authResult): void;
    public function getRefreshToken(): ?string;
    public function getAccessToken(): ?string;
    public function rotateTokens(TokenResult $newTokens): void;
    public function clearTokens(): void;
    public function isTokenValid(): bool;
}
```

#### Token Refresh Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│  REFRESH FLOW (automatic, saat access_token expired):                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. Middleware check: isAccessTokenExpired()?                          │
│                              │                                          │
│                              ▼                                          │
│  2. Call KeycloakAuth::refreshToken(refresh_token)                     │
│     POST /token { grant_type: refresh_token, refresh_token: ... }       │
│                              │                                          │
│                              ▼                                          │
│  3. Keycloak returns new { access_token, refresh_token, expires_in }   │
│                              │                                          │
│                              ▼                                          │
│  4. Rotate: store new tokens, invalidate old refresh_token              │
│     (Keycloak akan menolak old refresh_token)                           │
│                              │                                          │
│                              ▼                                          │
│  5. Continue dengan request + new access_token (claims cached)         │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### 1.4 Silent SSO Check

```
GET /realms/kepegawaian/protocol/openid-connect/auth?
    client_id=kepegawaian-apps
    &redirect_uri=...
    &response_type=code
    &scope=openid
    &prompt=none  ← Trigger silent check
    &state=...
    &code_challenge=...
```

---

## 2. Session Management

### 2.1 Session Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    SESSION MANAGEMENT ARCHITECTURE                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────┐      ┌─────────────┐      ┌─────────────────────────┐  │
│  │   Browser   │      │    Laravel     │      │      Keycloak           │  │
│  │   Client    │◄────►│    Session     │◄────►│      (IdP)              │  │
│  └─────────────┘      └─────────────┘      └─────────────────────────┘  │
│                                                                          │
│  Laravel Session = Source of Truth untuk app state                       │
│  Keycloak Session = Source of Truth untuk identity                       │
│                                                                          │
│  SYNC RULE:                                                              │
│  - Login: Create Laravel session + redirect ke Keycloak login           │
│  - Logout: Clear Laravel session + invalidate Keycloak session          │
│  - Token expired: Refresh via Keycloak, update Laravel session          │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Session Data Structure

```php
// session['keycloak'] structure
[
    'tokens' => [
        'access_token' => 'eyJ...',
        'refresh_token' => 'eyJ...', // encrypted
        'expires_at' => '2024-01-01T12:05:00Z',
    ],
    'user' => [
        'sub' => 'uuid-from-keycloak',
        'nip' => '198501152010011001',
        'email' => 'user@email.com',
        'name' => 'Nama Lengkap',
    ],
    'permissions' => ['cuti.view', 'cuti.create', ...],
    'roles' => ['operator', 'pegawai'],
]
```

### 2.3 Session Regeneration Hook Integration

```php
// app/Providers/KeycloakSessionServiceProvider.php

class KeycloakSessionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureSessionHooks();
    }

    private function configureSessionHooks(): void
    {
        // Hook saat SSO state di-set (setelah successful auth dari KC)
        $this->app->resolving('sso.state', function ($state, $app) {
            $state->onAuthenticated(function (Pegawai $pegawai) use ($app) {
                // Regenerate session setelah successful auth
                $app['session']->regenerate();

                // Store Keycloak session data
                $app->make(SessionManager::class)->createSession(
                    $app->make(KeycloakAuth::class)->getLastAuthResult()
                );
            });
        });
    }
}
```

---

## 3. PKCE Implementation

### 3.1 PKCE Generator

```php
// app/Services/Keycloak/PkceGenerator.php

class PkceGenerator
{
    /**
     * RFC 7636 PKCE Implementation
     *
     * code_verifier: high-entropy cryptographic random string
     * code_challenge: BASE64URL(SHA256(code_verifier))
     */
    public function generate(): PkcePair
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->computeCodeChallenge($codeVerifier);

        return new PkcePair(
            verifier: $codeVerifier,
            challenge: $codeChallenge,
            method: 'S256'
        );
    }

    private function generateCodeVerifier(): string
    {
        // Minimum 43 characters, maximum 128
        // Use base64url encoding (no + / =)
        $randomBytes = random_bytes(64);
        return rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
    }

    private function computeCodeChallenge(string $verifier): string
    {
        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public function validateState(string $state): bool
    {
        $storedState = session()->get('keycloak.oauth_state.state');
        return hash_equals($storedState ?? '', $state);
    }

    public function validateVerifier(string $verifier): bool
    {
        $storedChallenge = session()->get('keycloak.oauth_state.code_challenge');
        if (!$storedChallenge) return false;
        $computedChallenge = $this->computeCodeChallenge($verifier);
        return hash_equals($storedChallenge, $computedChallenge);
    }
}
```

### 3.2 Authorization Request Builder

```php
// app/Services/Keycloak/AuthorizationRequestBuilder.php

class AuthorizationRequestBuilder
{
    public function build(string $redirectUri): AuthorizationRequest
    {
        $pkce = app(PkceGenerator::class)->generate();
        $state = $this->generateState();

        // Store di session untuk later validation
        session()->put('keycloak.oauth_state', [
            'state' => $state,
            'code_verifier' => $pkce->verifier,
            'code_challenge' => $pkce->challenge,
            'redirect_uri' => $redirectUri,
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        $params = [
            'client_id' => config('keycloak.client_id'),
            'response_type' => 'code',
            'scope' => config('keycloak.scopes', ['openid', 'profile', 'email', 'roles']),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => $pkce->method,
        ];

        return new AuthorizationRequest(
            url: config('keycloak.base_url') . '/realms/' . config('keycloak.realm') . '/protocol/openid-connect/auth',
            params: $params
        );
    }
}
```

---

## 4. Keycloak Sync Modul

### 4.1 Sync Operations

| Operation | Trigger | Description |
|-----------|---------|-------------|
| **Full Sync** | Admin button / Artisan cmd | Sync semua Pegawai aktif |
| **Incremental Sync** | Scheduled (daily) | Sync Pegawai yang berubah dalam 24 jam |
| **Single Sync** | Per-Pegawai button | Sync satu Pegawai spesifik |
| **JIT Sync** | On login (optional) | Auto-create user saat first login |

### 4.2 Conflict Types & Resolution

| Conflict | Detection | Resolution |
|----------|-----------|-----------|
| **Email mismatch** | Pegawai.email ≠ Keycloak.email | Pegawai wins |
| **Disabled in KC** | Keycloak enabled = false | Block access, log |
| **Deleted in KC** | User not found in KC | Soft-disable Pegawai |
| **Role mismatch** | Roles different | Pegawai role mapping wins |

### 4.3 Conflict Resolution Detail

```php
// app/Services/Keycloak/ConflictResolution.php

enum ConflictType
{
    case DataMismatch;
    case RoleOverride;
    case IdentifierChange;
    case StatusConflict;
    case TimestampConflict;
}

class ConflictResolution
{
    /**
     * Resolution policy: siapa yang menang?
     * DEFAULT POLICY: "Pegawai Wins"
     *
     * Rationale: Pegawai adalah source of truth untuk employee data.
     * Keycloak hanya acting as identity provider, bukan master data.
     */
    const DEFAULT_POLICY = ConflictPolicy::PegawaiWins;

    public function resolve(
        ConflictType $type,
        Pegawai $pegawai,
        ?array $keycloakUser = null
    ): ConflictResult;
}
```

### 4.4 KeycloakSyncService Interface

```php
interface KeycloakSyncServiceInterface
{
    /**
     * Full sync semua Pegawai aktif ke Keycloak.
     */
    public function fullSync(): SyncResult;

    /**
     * Incremental sync (Pegawai berubah 24 jam terakhir).
     */
    public function incrementalSync(): SyncResult;

    /**
     * Sync single Pegawai.
     */
    public function syncPegawai(Pegawai $pegawai): SyncResult;

    /**
     * Disable user di Keycloak.
     */
    public function disableUser(string $nip): void;

    /**
     * Check if Pegawai exists in Keycloak.
     */
    public function userExists(string $nip): bool;

    /**
     * Get sync status/healthy check.
     */
    public function healthCheck(): HealthStatus;
}
```

---

## 5. Admin Panel (Filament)

### 5.1 Filament Resources

```
Navigation:
├── Keycloak
│   ├── Sync Users      — Trigger manual sync
│   ├── Sync Logs       — View audit trail
│   ├── Conflicts       — Resolve pending conflicts
│   └── Health Status   — Keycloak connection status
│
└── Emergency Access
    └── Login Bypass    — Emergency credentials management
```

### 5.2 Resource Details

| Resource | Pages | Actions |
|----------|-------|---------|
| **SyncUsersResource** | List, Sync | Trigger full sync, incremental sync, single sync |
| **SyncLogResource** | List, View | Filter by date, user, status |
| **ConflictResource** | List, Resolve | View diff, resolve with choice |
| **HealthStatusPage** | Dashboard | Health check, reconnect |

### 5.3 Emergency Login Configuration

```php
// config/keycloak.php
return [
    'emergency' => [
        'enabled' => env('KEYCLOAK_EMERGENCY_ENABLED', false),
        'username' => env('KEYCLOAK_EMERGENCY_USERNAME'),
        'password' => env('KEYCLOAK_EMERGENCY_PASSWORD'),
        'allowed_roles' => ['admin'],
        'session_timeout_minutes' => 30,
    ],
];
```

### 5.4 Emergency Bypass Flow

```
┌──────────────────────────────────────────────────────────────────────┐
│                    EMERGENCY BYPASS FLOW                             │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  [Admin] → Akses /admin/login → [Laravel]                           │
│                                    │                                 │
│                                    ▼                                 │
│                       Check Keycloak available?                       │
│                         │ │                             │
│                    Yes │              │ No │
│                         ▼              ▼                             │
│                  Normal KC Check emergency enabled?            │
│                  login flow │              │ │
│                                 Yes │          │ No                  │
│                                      ▼         ▼                     │
│                               Check creds   Block + show │
│                               match?        "Service unavailable"    │
│                               │ │
│                               │ Match │
│                               ▼                                          │
│                         Create session                                │
│                         + log emergency access                        │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 6. Middleware & Integration

### 6.1 Middleware Stack

```
Request Lifecycle:

[Request]
    │
    ▼
1. ShareAuth → Share user + permissions ke Inertia
    │
    ▼
2. KeycloakTokenRefresh → Auto-refresh expired tokens
    │
    ▼
3. EmergencyBypass → Check emergency bypass
    │
    ▼
4. VerifyIamPermission → Existing permission check
    │
    ▼
[Controller]
```

### 6.2 KeycloakTokenRefresh Middleware

```php
// app/Http/Middleware/KeycloakTokenRefresh.php

class KeycloakTokenRefresh
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if ($this->needsRefresh()) {
            $result = $this->refreshTokens();
            if (!$result->success) {
                return $this->handleRefreshFailure($result);
            }
        }

        return $next($request);
    }

    private function needsRefresh(): bool
    {
        $storage = app(KeycloakTokenStorage::class);
        $expiry = $storage->getAccessTokenExpiry();
        return $expiry && $expiry->diffInSeconds(now()) < config('keycloak.tokens.refresh_before_seconds');
    }
}
```

### 6.3 Updated VerifyIamPermission

```php
class VerifyIamPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->handleUnauthenticated($request);
        }

        // Get permissions dari session (di-set saat login via JWT claims)
        $userPermissions = session()->get('keycloak.permissions', []);

        // OR dari existing IamAuthorizationService (fallback untuk hybrid mode)
        if (empty($userPermissions)) {
            $userPermissions = $this->iamAuth->getUserPermissions(
                $user->id,
                $this->getAppId()
            );
        }

        // ... existing permission check logic
    }
}
```

### 6.4 Route Updates

```php
// routes/web.php additions
Route::prefix('keycloak')->group(function () {
    Route::get('login', [KeycloakAuthController::class, 'login'])->name('keycloak.login');
    Route::get('callback', [KeycloakAuthController::class, 'callback'])->name('keycloak.callback');
    Route::post('logout', [KeycloakAuthController::class, 'logout'])->name('keycloak.logout');
    Route::get('silent-check', [KeycloakAuthController::class, 'silentCheck'])->name('keycloak.silent-check');
});

Route::prefix('emergency')->middleware(['throttle:emergency'])->group(function () {
    Route::post('login', [EmergencyLoginController::class, 'login'])->name('emergency.login');
});
```

---

## 7. Error Handling & Circuit Breaker

### 7.1 Error Scenarios

```php
// app/Exceptions/Keycloak/

class KeycloakException extends Exception
{
    public const NETWORK_ERROR = 'KC_NETWORK_ERROR';
    public const INVALID_TOKEN = 'KC_INVALID_TOKEN';
    public const TOKEN_EXPIRED = 'KC_TOKEN_EXPIRED';
    public const USER_NOT_FOUND = 'KC_USER_NOT_FOUND';
    public const ACCESS_DENIED = 'KC_ACCESS_DENIED';
    public const CONFIGURATION_ERROR = 'KC_CONFIG_ERROR';
}
```

### 7.2 Circuit Breaker Pattern

```php
// app/Services/Keycloak/KeycloakCircuitBreaker.php

class KeycloakCircuitBreaker
{
    const STATE_CLOSED = 'closed';    // Normal operation
    const STATE_OPEN = 'open';        // Failing, reject requests
    const STATE_HALF_OPEN = 'half_open'; // Testing recovery

    const FAILURE_THRESHOLD = 5;      // Open after 5 failures
    const RECOVERY_TIMEOUT = 30;      // Try recovery after 30s
    const SUCCESS_THRESHOLD = 2;       // Close after 2 successes

    public function call(callable $operation): mixed
    {
        if ($this->isOpen()) {
            if ($this->shouldTryRecovery()) {
                $this->transitionToHalfOpen();
            } else {
                throw new KeycloakCircuitOpenException(
                    'Keycloak unavailable. Circuit is open.'
                );
            }
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (Exception $e) {
            $this->recordFailure($e);
            throw $e;
        }
    }
}
```

### 7.3 Graceful Degradation Strategy

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    ERROR HANDLING DECISION TREE                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Request masuk dengan Keycloak token                                     │
│                    │                                                    │
│                    ▼                                                    │
│         ┌─────────────────────┐                                         │
│         │ Keycloak reachable? │                                         │
│         └─────────────────────┘                                         │
│              │              │                                           │
│         No   │              │  Yes                                      │
│              ▼              ▼                                           │
│  ┌───────────────────┐  ┌─────────────────────┐                         │
│  │ Circuit open?     │  │ Validate token      │                         │
│  └───────────────────┘  └─────────────────────┘                         │
│       │                        │                                       │
│       │ Yes                    │ Invalid                                │
│       ▼                        ▼                                        │
│  ┌───────────────────┐  ┌─────────────────────┐                         │
│  │ Return 503        │  │ Check user in       │                         │
│  │ Service Unavailable│  │ Pegawai table       │                         │
│  └───────────────────┘  └─────────────────────┘                         │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘

FALLBACK MODE (Keycloak completely down):
1. Check if user already has valid Laravel session
   → If yes: allow request, but log degraded mode
2. For new logins:
   → Show "Maintenance" page or use cached permissions (if available)
3. Admin users:
   → Bypass Keycloak for admin role (emergency access)
   → Log all admin actions for audit
```

---

## 8. Database Schema Changes

### 8.1 New Tables

#### keycloak_sync_audit

```php
Schema::create('keycloak_sync_audit', function (Blueprint $table) {
    $table->id();
    $table->string('event_type'); // conflict, create, update, delete, sync_failure
    $table->foreignId('pegawai_id')->nullable()->constrained()->nullOnDelete();
    $table->string('nip')->index();
    $table->string('conflict_type')->nullable();
    $table->json('pegawai_snapshot')->nullable();
    $table->json('keycloak_snapshot')->nullable();
    $table->json('resolution')->nullable();
    $table->string('resolved_by'); // system, admin, user
    $table->foreignId('caused_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('caused_by_nip')->nullable();
    $table->timestamps();

    $table->index(['event_type', 'created_at']);
    $table->index(['nip', 'created_at']);
});
```

#### keycloak_sync_state

```php
Schema::create('keycloak_sync_state', function (Blueprint $table) {
    $table->id();
    $table->string('last_sync_at');
    $table->string('last_sync_type'); // full, incremental
    $table->integer('total_synced')->default(0);
    $table->integer('total_conflicts')->default(0);
    $table->json('sync_metadata')->nullable();
    $table->timestamps();
});
```

#### keycloak_emergency_login_log

```php
Schema::create('keycloak_emergency_login_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('username'); // hashed
    $table->string('ip_address');
    $table->string('user_agent')->nullable();
    $table->timestamp('logged_in_at');
    $table->timestamp('logged_out_at')->nullable();
    $table->timestamps();

    $table->index(['logged_in_at']);
});
```

### 8.2 Tables to Modify

| Table | Change | Reason |
|-------|--------|--------|
| `users` | Add `keycloak_id` column | Link Laravel user ke Keycloak user |
| `pegawai` | Add `keycloak_synced_at`, `keycloak_user_id` | Track sync status |

---

## 9. Implementation Phases

### Phase 1: Foundation (Backend Core)
- Install & configure jumbojett/openid-connect-php
- Create config/keycloak.php
- Implement KeycloakClient service
- Implement PKCE generator
- Implement token storage (encrypted session)
- Implement KeycloakAuthController (login route, callback, logout)
- Implement KeycloakTokenRefresh middleware
- Update routes

### Phase 2: Sync Service
- Implement KeycloakSyncService
- Create database migrations
- Create KeycloakSyncState model
- Implement conflict detection & resolution
- Implement Artisan commands (sync, health-check)

### Phase 3: Admin Panel (Filament)
- Install Filament
- Create KeycloakSyncResource
- Create SyncLogResource
- Create ConflictResource
- Create HealthStatusPage
- Create EmergencyLoginController & page

### Phase 4: Integration & Polish
- Update VerifyIamPermission middleware
- Implement emergency bypass middleware
- Update HandleInertiaRequests
- Circuit breaker implementation
- Error handling & logging
- Documentation

---

## 10. File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── KeycloakAuthController.php      ← NEW
│   │   └── EmergencyLoginController.php         ← NEW
│   └── Middleware/
│       ├── KeycloakTokenRefresh.php             ← NEW
│       └── EmergencyBypass.php                 ← NEW
│
├── Services/
│   └── Keycloak/
│       ├── KeycloakClient.php                  ← NEW
│       ├── KeycloakSession.php                 ← NEW
│       ├── KeycloakSyncService.php             ← NEW
│       ├── KeycloakCircuitBreaker.php          ← NEW
│       ├── KeycloakConfig.php                  ← NEW
│       ├── PkceGenerator.php                   ← NEW
│       └── KeycloakTokenStorage.php            ← NEW
│
├── Models/
│   ├── KeycloakSyncState.php                   ← NEW
│   ├── KeycloakSyncAudit.php                   ← NEW
│   └── KeycloakEmergencyLoginLog.php           ← NEW
│
├── Console/
│   └── Commands/
│       └── Keycloak/
│           ├── SyncUsersCommand.php            ← NEW
│           └── HealthCheckCommand.php          ← NEW
│
└── Filament/
    └── Resources/
        ├── KeycloakSyncResource.php            ← NEW
        ├── KeycloakSyncLogResource.php         ← NEW
        └── KeycloakConflictResource.php         ← NEW

config/
└── keycloak.php                                 ← NEW

database/migrations/
├── xxxx_create_keycloak_sync_audit_table.php   ← NEW
├── xxxx_create_keycloak_sync_state_table.php   ← NEW
├── xxxx_create_keycloak_emergency_login_table.php ← NEW
├── xxxx_add_keycloak_columns_to_users_table.php   ← NEW
└── xxxx_add_keycloak_columns_to_pegawai_table.php ← NEW
```

---

## 11. Configuration

### config/keycloak.php

```php
<?php

return [
    // Base URL Keycloak server
    'base_url' => env('KEYCLOAK_BASE_URL', 'http://localhost:9080'),

    // Realm configuration
    'realm' => env('KEYCLOAK_REALM', 'kepegawaian'),

    // Client untuk user authentication
    'client' => [
        'id' => env('KEYCLOAK_CLIENT_ID', 'kepegawaian-apps'),
        'secret' => env('KEYCLOAK_CLIENT_SECRET'),
    ],

    // Client untuk M2M authentication
    'service_account' => [
        'id' => env('KEYCLOAK_SERVICE_CLIENT_ID', 'kepegawaian-service'),
        'secret' => env('KEYCLOAK_SERVICE_CLIENT_SECRET'),
    ],

    // Token settings
    'tokens' => [
        'access_token_ttl' => env('KEYCLOAK_ACCESS_TOKEN_TTL', 300),
        'refresh_token_ttl' => env('KEYCLOAK_REFRESH_TOKEN_TTL', 28800),
        'refresh_before_seconds' => 60,
        'rotate_refresh_token' => true,
    ],

    // PKCE settings
    'pkce' => [
        'method' => 'S256',
        'state_ttl_minutes' => 10,
    ],

    // Scopes
    'scopes' => ['openid', 'profile', 'email', 'roles'],

    // Claims mapping
    'claims' => [
        'nip' => 'nip',
        'permissions' => 'permissions',
        'roles' => 'roles',
    ],

    // Circuit breaker
    'circuit_breaker' => [
        'failure_threshold' => env('KEYCLOAK_CIRCUIT_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('KEYCLOAK_CIRCUIT_RECOVERY_TIMEOUT', 30),
        'success_threshold' => 2,
    ],

    // Emergency bypass
    'emergency' => [
        'enabled' => env('KEYCLOAK_EMERGENCY_ENABLED', false),
        'username' => env('KEYCLOAK_EMERGENCY_USERNAME'),
        'password' => env('KEYCLOAK_EMERGENCY_PASSWORD'),
        'allowed_roles' => ['admin'],
        'session_timeout_minutes' => 30,
    ],
];
```

### Environment Variables

```env
# Keycloak Configuration
KEYCLOAK_BASE_URL=http://localhost:9080
KEYCLOAK_REALM=kepegawaian
KEYCLOAK_CLIENT_ID=kepegawaian-apps
KEYCLOAK_CLIENT_SECRET=<from Keycloak Admin Console>

# Service Account untuk M2M
KEYCLOAK_SERVICE_CLIENT_ID=kepegawaian-service
KEYCLOAK_SERVICE_CLIENT_SECRET=<from Keycloak Admin Console>

# Token Settings
KEYCLOAK_ACCESS_TOKEN_TTL=300
KEYCLOAK_REFRESH_TOKEN_TTL=28800

# Circuit Breaker
KEYCLOAK_CIRCUIT_FAILURE_THRESHOLD=5
KEYCLOAK_CIRCUIT_RECOVERY_TIMEOUT=30

# Emergency Bypass
KEYCLOAK_EMERGENCY_ENABLED=false
KEYCLOAK_EMERGENCY_USERNAME=
KEYCLOAK_EMERGENCY_PASSWORD=
```

---

## 12. Security Considerations

1. **PKCE Required** — Always use S256 method untuk Authorization Code flow
2. **JWT Validation** — Validate signature locally using Keycloak public key
3. **Token Storage** — Store refresh token encrypted di session
4. **NIP Validation** — Always validate NIP exists in Pegawai table
5. **Client Secrets** — Store di environment variables, never in code
6. **HTTPS Only** — Production must use HTTPS
7. **Circuit Breaker** — Prevent cascade failure saat Keycloak down
8. **Emergency Log** — All emergency logins must be logged and auditable

---

## 13. Testing Checklist

### Pre-Implementation Checklist

```markdown
## Pre-Implementation Checklist

### Token Management
- [ ] Install `jumbojett/openid-connect-php` dan verify PHP 8.4 compatibility
- [ ] Test token storage encryption dengan Laravel's encryption
- [ ] Verify token refresh flow dengan Keycloak test instance
- [ ] Test token rotation (old refresh token should be invalid after use)

### Session Management
- [ ] Verify SessionStart hook integration dengan existing SSO code
- [ ] Test session regeneration on login
- [ ] Verify silent SSO check dengan `prompt=none`
- [ ] Test session destruction on logout

### Conflict Resolution
- [ ] Create `keycloak_sync_audit` table migration
- [ ] Test each conflict type dengan mock data
- [ ] Verify Pegawai-wins policy dengan edge cases
- [ ] Test critical conflict (disabled in KC) → block access

### Error Handling
- [ ] Implement circuit breaker
- [ ] Test each error scenario dengan mock failures
- [ ] Verify graceful degradation (maintenance mode)
- [ ] Test admin bypass for emergency access

### PKCE Implementation
- [ ] Test code_verifier generation (43-128 chars)
- [ ] Test code_challenge computation (SHA256 + base64url)
- [ ] Verify state validation (CSRF protection)
- [ ] Test full authorization code flow
```

### Feature Tests

```php
// tests/Feature/Keycloak/

class KeycloakAuthenticationTest extends TestCase
{
    public function test_user_can_login_via_keycloak(): void;
    public function test_user_can_refresh_expired_token(): void;
    public function test_user_is_logged_out_when_token_expired_and_refresh_fails(): void;
    public function test_invalid_state_parameter_is_rejected(): void;
    public function test_user_not_in_pegawai_is_rejected(): void;
    public function test_keycloak_unreachable_returns_503(): void;
    public function test_silent_sso_works_when_user_has_keycloak_session(): void;
    public function test_logout_invalidates_both_sessions(): void;
}

class KeycloakSyncTest extends TestCase
{
    public function test_full_sync_creates_users_in_keycloak(): void;
    public function test_incremental_sync_only_syncs_changed_users(): void;
    public function test_conflict_resolution_uses_pegawai_wins_policy(): void;
    public function test_user_disabled_in_keycloak_is_blocked(): void;
}

class EmergencyBypassTest extends TestCase
{
    public function test_emergency_login_works_when_keycloak_down(): void;
    public function test_emergency_login_is_logged(): void;
    public function test_emergency_bypass_blocked_when_disabled(): void;
}
```

---

## 14. References

- [Keycloak Documentation](https://www.keycloak.org/guides)
- [jumbojett/openid-connect-php](https://packagist.org/packages/jumbojett/openid-connect-php)
- [OIDC RFC](https://openid.net/specs/openid-connect-core-1_0.html)
- [RFC 7636 - PKCE](https://datatracker.ietf.org/doc/html/rfc7636)