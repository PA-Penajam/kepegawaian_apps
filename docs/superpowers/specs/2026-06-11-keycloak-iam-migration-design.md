# Keycloak IAM Migration Design

## Overview

Dokumen ini mendefinisikan desain migrasi sistem Identity and Access Management (IAM) dari custom implementation ke Keycloak. Migration menggunakan **Big Bang approach** dengan **Wrapper Pattern** untuk backward compatibility.

## Decisions Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Strategy | Big Bang | Clean, no hybrid state |
| Admin UI | Keycloak Admin Console | No custom development |
| Data Source | Pegawai as source of truth | Keycloak as identity provider only |
| User Not Found | Block access | Security first |
| Permissions | Extract from JWT on login | Fast, no per-request calls |
| 2FA | Full delegation to Keycloak | Single auth system |
| M2M Auth | Keycloak Service Accounts | OAuth2 standard |
| PHP Library | jumbojett/openid-connect-php | 10M+ installs, mature, full OIDC |
| Architecture | Wrapper Pattern | Backward compatible interfaces |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    KEEPEGAWAIAN-APPS (Laravel 12)               │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  KeycloakService (Wrapper Pattern)                        │  │
│  │  ┌─────────────────┐ ┌─────────────────┐ ┌──────────────┐ │  │
│  │  │ KeycloakAuth    │ │ KeycloakUser   │ │ KeycloakM2M  │ │  │
│  │  │ (OIDC Flow)     │ │ (User Sync)    │ │ (Client Cred)│ │  │
│  │  └─────────────────┘ └─────────────────┘ └──────────────┘ │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                  │
│  ┌───────────────────────────┴───────────────────────────────┐  │
│  │  Existing Interfaces (Backward Compatible)                │  │
│  │  - VerifyIamPermission middleware                         │  │
│  │  - IamAuthorizationService                                │  │
│  │  - HandleInertiaRequests (permissions array)             │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         KEYCLOAK 26.6.3                          │
│                                                                 │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌──────────┐ │
│  │ Realm:      │ │ Clients     │ │ Roles &     │ │ Service  │ │
│  │ kepegawaian │ │ (apps)      │ │ Permissions │ │ Accounts │ │
│  └─────────────┘ └─────────────┘ └─────────────┘ └──────────┘ │
│                                                                 │
│  Database: PostgreSQL 17                                         │
└─────────────────────────────────────────────────────────────────┘
```

## Components

### 1. Directory Structure

```
app/
├── Services/
│   ├── Keycloak/
│   │   ├── KeycloakService.php          # Main wrapper
│   │   ├── KeycloakAuth.php             # OIDC authentication
│   │   ├── KeycloakUserService.php      # User sync (Pegawai → Keycloak)
│   │   ├── KeycloakM2MService.php       # Client credentials (M2M)
│   │   └── KeycloakTokenValidator.php    # JWT validation
│   └── IamAuthorizationService.php      # Existing (interface compatible)
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── KeycloakAuthController.php  # NEW: OIDC login/callback
│   │   └── SsoController.php              # MODIFY: use KeycloakAuth
│   ├── Middleware/
│   │   ├── VerifyIamSignature.php         # MODIFY: use KeycloakM2MService
│   │   └── VerifyIamPermission.php        # MODIFY: use KeycloakService
│   └── Responses/
│       └── SsoAwareLoginResponse.php       # MODIFY: Keycloak-aware
│
├── Console/Commands/
│   └── Keycloak/
│       ├── SyncUsersCommand.php           # Sync Pegawai → Keycloak users
│       ├── SyncRolesCommand.php           # Sync roles/permissions
│       └── ValidateSetupCommand.php       # Validate Keycloak configuration
│
└── Models/
    └── Pegawai.php                        # MODIFY: Keycloak user sync events
```

### 2. KeycloakService

Main wrapper class yang menyediakan interface unified untuk semua Keycloak operations.

```php
class KeycloakService
{
    public function __construct(
        private readonly KeycloakAuth $auth,
        private readonly KeycloakUserService $userService,
        private readonly KeycloakM2MService $m2m,
        private readonly KeycloakTokenValidator $tokenValidator,
    ) {}

    // OIDC Authentication
    public function getAuthorizationUrl(string $redirectUri, string $state): string;
    public function handleCallback(string $code): AuthResult;
    public function refreshToken(string $refreshToken): TokenResult;

    // User Management
    public function syncUser(Pegawai $pegawai): void;
    public function disableUser(string $nip): void;

    // M2M Authentication
    public function validateClientCredentials(string $clientId, string $clientSecret): ?Client;

    // Token Validation
    public function validateAccessToken(string $token): ?TokenClaims;
    public function getUserPermissions(string $nip): array;
}
```

### 3. Backward Compatible Interfaces

#### VerifyIamPermission

Middleware existing tetap pakai interface sama, implementasi内部换成 KeycloakService.

```php
class VerifyIamPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $userPermissions = keycloakService()->getUserPermissions($user->nip);
        
        // ... existing logic unchanged ...
    }
}
```

#### HandleInertiaRequests

Permissions di-extract dari JWT claims dan dishare ke React.

```php
class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = $user 
            ? keycloakService()->getUserPermissions($user->nip)
            : [];
        
        return [
            'auth' => [
                'user' => [..., 'permissions' => $permissions],
            ],
        ];
    }
}
```

## SSO Flow (OIDC Authorization Code + PKCE)

```
1. [User] → Click login / akses protected route
2. [App]  → Generate PKCE code_verifier + state
3. [App]  → Redirect to Keycloak /auth/endpoint
4. [KC]   → Show login page (atau SSO silent jika ada session)
5. [User] → Login credentials
6. [KC]   → Redirect to callback URL dengan code + state
7. [App]  → Exchange code untuk tokens via /token endpoint
8. [App]  → Validate ID token, extract NIP dari claims
9. [App]  → Check Pegawai exists dengan NIP tersebut
10. [App]  → Jika Pegawai tidak ada → reject dengan error
11. [App]  → Jika Pegawai ada → create session, extract perms
12. [User] → Login berhasil, permissions dari JWT claims
```

## M2M Authentication (Client Credentials Grant)

```
1. [External App] → POST /token dengan client_id + secret
2. [App]  → KeycloakM2MService::validateClientCredentials()
3. [KC]   → Validate client credentials
4. [KC]   → Return access_token dengan client_id di claims
5. [App]  → Validate JWT, extract client_id
6. [App]  → Map client_id ke aplikasi yang sesuai
7. [App]  → Inject iam_app ke request attributes
```

## User Sync (Pegawai → Keycloak)

```
PEGAWAI CREATE/UPDATE/DELETE
         │
    Model Observer
    (PegawaiObserver)
         │
    Artisan CMD / Queue Job
    (manual / async)
         │
    KeycloakUserService
    ┌─────────────────────┐
    │                      │
    ▼                      ▼
POST /users          PUT /users/{id}
DELETE /users/{id}   (disable)
    │
NIP sebagai external_id di Keycloak
```

## Configuration

File: `config/keycloak.php`

```php
return [
    'base_url' => env('KEYCLOAK_BASE_URL', 'http://localhost:9080'),
    'realm' => env('KEYCLOAK_REALM', 'kepegawaian'),
    'client_id' => env('KEYCLOAK_CLIENT_ID', 'kepegawaian-apps'),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    
    // Service Account untuk M2M
    'service_account' => [
        'client_id' => env('KEYCLOAK_SERVICE_CLIENT_ID', 'kepegawaian-service'),
        'client_secret' => env('KEYCLOAK_SERVICE_CLIENT_SECRET'),
    ],
    
    // Token settings
    'token_ttl' => env('KEYCLOAK_TOKEN_TTL', 28800), // 8 hours
    
    // PKCE settings
    'pkce_method' => 'S256',
    
    // Scopes
    'scopes' => ['openid', 'profile', 'email', 'roles'],
    
    // Claims mapping
    'claims' => [
        'nip' => 'nip',
        'permissions' => 'permissions',
        'roles' => 'roles',
    ],
];
```

## Environment Variables

```env
# Keycloak Configuration
KEYCLOAK_BASE_URL=http://localhost:9080
KEYCLOAK_REALM=kepegawaian
KEYCLOAK_CLIENT_ID=kepegawaian-apps
KEYCLOAK_CLIENT_SECRET=<from Keycloak Admin Console>

# Service Account untuk M2M
KEYCLOAK_SERVICE_CLIENT_ID=kepegawaian-service
KEYCLOAK_SERVICE_CLIENT_SECRET=<from Keycloak Admin Console>

# Token TTL (default 8 hours)
KEYCLOAK_TOKEN_TTL=28800
```

## Keycloak Setup Requirements

### 1. Realm: kepegawaian

- Enable
- Display name: Kepegawaian Apps
- User management enabled

### 2. Client: kepegawaian-apps

- Client ID: kepegawaian-apps
- Client Protocol: openid-connect
- Access Type: confidential
- Client Authentication: ON
- Authorization: ON
- Valid redirect URIs:
  - `http://localhost:8001/*`
- Web origins:
  - `http://localhost:8001`

### 3. Client: kepegawaian-service (M2M)

- Client ID: kepegawaian-service
- Client Protocol: openid-connect
- Access Type: confidential
- Client Authentication: ON
- Service Accounts Enabled: ON
- Standard Flow: OFF (no browser login needed)

### 4. Realm Roles

- admin
- operator
- pimpinan
- pegawai
- auditor
- viewer
- validator

### 5. Custom Claims Configuration

Di Keycloak, configure Client Scope untuk include custom claims:

- `permissions` - array of permission slugs
- `nip` - NIP dari Pegawai

## Rollback Plan

Karena Big Bang, rollback berarti revert semua code changes:

1. Git branch dengan semua perubahan sebelum merge
2. IAM tables tidak di-drop (soft deletes, atau rename ke *_backup)
3. Konfigurasi lama tetap ada di `.env.example`
4. Test di local sebelum push ke production

## Migration Steps

| Phase | Task | Description |
|-------|------|-------------|
| 1 | Setup Keycloak | Konfigurasi realm, client, roles di Admin Console |
| 2 | Install Library | `composer require jumbojett/openid-connect-php` |
| 3 | Create KeycloakService | Main wrapper dengan semua methods |
| 4 | Implement Auth Flow | OIDC login/callback controller |
| 5 | Implement M2M | Client credentials service |
| 6 | Implement User Sync | Pegawai → Keycloak sync |
| 7 | Update Middleware | VerifyIamSignature/Permission |
| 8 | Update Frontend | HandleInertiaRequests |
| 9 | Update Routes | Auth routes |
| 10 | Test End-to-End | Full SSO flow testing |
| 11 | Cleanup | Hapus IAM code yang tidak diperlukan |

## Dependencies

### Composer Packages

```json
{
    "require": {
        "jumbojett/openid-connect-php": "^1.0"
    }
}
```

### System Requirements

- PHP 8.4+
- ext-json
- ext-curl
- Keycloak 26.x
- PostgreSQL 17 (for Keycloak)

## Security Considerations

1. **PKCE Required** - Always use S256 method untuk Authorization Code flow
2. **JWT Validation** - Validate signature locally using Keycloak public key
3. **Token Storage** - Store refresh token encrypted di session
4. **NIP Validation** - Always validate NIP exists in Pegawai table
5. **Client Secrets** - Store di environment variables, never in code
6. **HTTPS Only** - Production must use HTTPS

## References

- Keycloak Documentation: https://www.keycloak.org/guides
- jumbojett/openid-connect-php: https://packagist.org/packages/jumbojett/openid-connect-php
- OIDC RFC: https://openid.net/specs/openid-connect-core-1_0.html
- Migration Requirements: `.kiro/specs/keycloak-iam-migration/requirements.md`
