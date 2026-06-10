# Requirements Document

## Introduction

Dokumen ini mendefinisikan persyaratan untuk migrasi sistem Identity and Access Management (IAM) dari custom implementation ke Keycloak 26.6.3 pada aplikasi Sistem Kepegawaian. Migrasi menggunakan pendekatan Big Bang (single phase deployment) dengan OIDC Authorization Code + PKCE flow untuk autentikasi, sinkronisasi data Pegawai ↔ Keycloak users, conflict resolution dengan kebijakan "Pegawai Wins", circuit breaker pattern untuk high availability, serta emergency bypass untuk admin access saat Keycloak tidak tersedia.

## Glossary

- **System**: Aplikasi Laravel 12 Sistem Kepegawaian yang terintegrasi dengan Keycloak
- **Keycloak_Server**: Keycloak 26.6.3 identity provider dengan realm `kepegawaian`
- **KeycloakClient**: Komponen yang menangani komunikasi OIDC dengan Keycloak via library jumbojett/openid-connect-php
- **TokenStorage**: Komponen yang mengelola penyimpanan dan lifecycle token dalam encrypted session
- **SyncService**: Komponen yang mengelola sinkronisasi data Pegawai ↔ Keycloak users
- **CircuitBreaker**: Komponen yang mengimplementasikan circuit breaker pattern untuk proteksi koneksi ke Keycloak
- **ConflictResolver**: Komponen yang mendeteksi dan menyelesaikan konflik data antara Pegawai dan Keycloak
- **EmergencyBypass**: Mekanisme akses admin darurat saat Keycloak tidak tersedia
- **Pegawai**: Entitas data pegawai yang menjadi source of truth dalam sistem
- **NIP**: Nomor Induk Pegawai (18 digit) yang digunakan sebagai identifier unik
- **PKCE**: Proof Key for Code Exchange (RFC 7636) untuk mengamankan authorization code flow
- **Token_Set**: Kumpulan access_token, refresh_token, dan id_token dari Keycloak
- **Middleware_Stack**: Kumpulan middleware Laravel yang memproses request terkait Keycloak

## Requirements

### Requirement 1: OIDC Authentication Flow

**User Story:** As a Pegawai, I want to authenticate via Keycloak using OIDC Authorization Code + PKCE flow, so that my identity is securely verified through a standards-compliant protocol.

#### Acceptance Criteria

1. WHEN a user initiates login, THE KeycloakClient SHALL generate a PKCE pair where code_challenge equals BASE64URL(SHA256(code_verifier)) with S256 method
2. WHEN a user initiates login, THE System SHALL generate a cryptographically random state parameter of at least 32 bytes of entropy and store it in the session with a 10-minute expiry
3. WHEN a user initiates login, THE System SHALL redirect the user to the Keycloak authorization endpoint with client_id, response_type=code, scope (including openid), redirect_uri, state, code_challenge, and code_challenge_method parameters
4. WHEN Keycloak redirects back with an authorization code, THE System SHALL validate that the returned state parameter matches the stored session state using constant-time comparison and remove the stored state from the session to prevent replay
5. IF the state parameter does not match or has expired, THEN THE System SHALL reject the callback with a 403 error, clear the stored OAuth state from the session, and redirect the user to the login page to restart the flow
6. WHEN the state is valid, THE KeycloakClient SHALL exchange the authorization code with the code_verifier at the Keycloak token endpoint to obtain a Token_Set
7. WHEN a Token_Set is received, THE KeycloakClient SHALL validate the ID token signature using the Keycloak public key from the JWKS endpoint, verify the issuer matches the configured Keycloak realm URL, and verify the token has not expired
8. IF the token exchange fails due to an invalid authorization code or Keycloak error response, THEN THE System SHALL reject the callback with an error indication that authentication failed and redirect the user to the login page
9. IF the ID token signature validation fails or the token claims are invalid, THEN THE System SHALL discard the Token_Set, reject the authentication with a 401 error, and redirect the user to the login page

### Requirement 2: User Identity Verification

**User Story:** As a system administrator, I want to ensure only registered Pegawai can access the system, so that unauthorized users are prevented from gaining access.

#### Acceptance Criteria

1. WHEN an ID token is validated, THE System SHALL extract the NIP claim from the JWT payload and verify it is an 18-digit numeric string
2. IF the NIP claim is missing from the JWT payload or is not a valid 18-digit numeric string, THEN THE System SHALL reject the login with an error message indicating the token contains invalid identity information
3. WHEN a NIP is extracted from the token, THE System SHALL verify that the NIP exists in the Pegawai table with status 'aktif'
4. IF the NIP from the token does not exist in the Pegawai table, THEN THE System SHALL reject the login with the error message "NIP tidak terdaftar dalam sistem kepegawaian"
5. IF the NIP from the token exists in the Pegawai table but the Pegawai status is not 'aktif', THEN THE System SHALL reject the login with an error message indicating the Pegawai account is inactive
6. WHEN a Pegawai is verified, THE System SHALL store user claims (sub, nip, email, name), permissions, and roles in the session

### Requirement 3: Session Security

**User Story:** As a security officer, I want login sessions to be protected against fixation and hijacking attacks, so that user sessions remain secure throughout their lifecycle.

#### Acceptance Criteria

1. WHEN a user successfully authenticates, THE System SHALL regenerate the session ID while preserving existing session data to prevent session fixation attacks
2. THE TokenStorage SHALL encrypt refresh tokens before storing them in the session using the application encryption key
3. WHEN tokens are stored in the session, THE TokenStorage SHALL record the access token expiry timestamp for proactive refresh tracking
4. WHEN a user logs out, THE System SHALL first invoke the Keycloak end-session endpoint using the stored refresh token to revoke the server-side session, then clear all Keycloak tokens from the local session, invalidate the session, and regenerate the CSRF token
5. IF the Keycloak end-session endpoint is unreachable during logout, THEN THE System SHALL proceed with local session invalidation and token clearing without blocking the logout operation

### Requirement 4: Token Lifecycle Management

**User Story:** As a Pegawai, I want my authentication tokens to be automatically refreshed before expiry, so that I maintain uninterrupted access to the system without re-authentication.

#### Acceptance Criteria

1. WHEN an access token is within 60 seconds of expiry, THE Middleware_Stack SHALL proactively request a new Token_Set using the refresh token
2. WHEN a token refresh succeeds, THE TokenStorage SHALL atomically store the new Token_Set and remove the previous access token, refresh token, and id token from the session
3. IF a token refresh fails due to an expired or revoked refresh token, THEN THE System SHALL clear all tokens from the session, invalidate the session, and redirect the user to the login page
4. IF a token refresh fails due to Keycloak unavailability and the access token has not yet reached its expiry timestamp, THEN THE System SHALL skip the refresh attempt, continue processing the current request using the existing access token, and retry the refresh on the next request
5. IF a token refresh fails due to Keycloak unavailability and the access token expiry timestamp has passed, THEN THE System SHALL clear all tokens from the session, invalidate the session, and redirect the user to the login page
6. IF multiple concurrent requests detect the access token within the 60-second refresh threshold simultaneously, THEN THE Middleware_Stack SHALL ensure only one refresh request is issued to Keycloak and subsequent requests SHALL wait for or use the result of the in-progress refresh

### Requirement 5: Circuit Breaker Pattern

**User Story:** As a system architect, I want a circuit breaker to protect the application when Keycloak is unavailable, so that the system degrades gracefully instead of cascading failures.

#### Acceptance Criteria

1. THE CircuitBreaker SHALL maintain three states: CLOSED (normal operation), OPEN (blocking requests), and HALF_OPEN (testing recovery)
2. WHEN 5 consecutive failures occur in CLOSED state, THE CircuitBreaker SHALL transition to OPEN state, where a failure is defined as a connection timeout, connection refused, or HTTP 5xx response from Keycloak
3. WHILE the CircuitBreaker is in OPEN state, THE CircuitBreaker SHALL immediately reject all requests to Keycloak without attempting connection by throwing a dedicated exception indicating service unavailability
4. WHEN 30 seconds have elapsed since the last failure in OPEN state, THE CircuitBreaker SHALL transition to HALF_OPEN state
5. WHILE the CircuitBreaker is in HALF_OPEN state, THE CircuitBreaker SHALL allow at most 1 request at a time to pass through to Keycloak as a probe while rejecting all other concurrent requests
6. WHEN 2 consecutive successes occur in HALF_OPEN state, THE CircuitBreaker SHALL transition to CLOSED state and reset the consecutive failure count to zero
7. WHEN 1 failure occurs in HALF_OPEN state, THE CircuitBreaker SHALL transition back to OPEN state and reset the consecutive success count to zero
8. THE CircuitBreaker SHALL track consecutive failure count, consecutive success count, last failure timestamp, and last success timestamp, all initialized to zero or null on instantiation
9. THE CircuitBreaker SHALL provide a reset method to manually transition to CLOSED state that also resets both consecutive failure count and consecutive success count to zero
10. THE CircuitBreaker SHALL consider a Keycloak request as failed if no response is received within 5 seconds (connection timeout)
11. WHEN the CircuitBreaker transitions from any state to another, THE CircuitBreaker SHALL reset the counter not relevant to the new state (failure count resets on transition to CLOSED, success count resets on transition to OPEN)

### Requirement 6: Full Sync Operation

**User Story:** As an administrator, I want to synchronize all active Pegawai data to Keycloak, so that all personnel have corresponding Keycloak accounts with correct attributes and roles.

#### Acceptance Criteria

1. WHEN a full sync is initiated, THE SyncService SHALL retrieve all Pegawai with active status from the database
2. WHEN a Pegawai does not have a corresponding Keycloak user (looked up by username=NIP), THE SyncService SHALL create a new Keycloak user with username=NIP, email, firstName, lastName, and set enabled=true
3. WHEN a new Keycloak user is created, THE SyncService SHALL assign realm roles based on the Pegawai role mappings
4. WHEN a Pegawai has a corresponding Keycloak user with conflicting data, THE SyncService SHALL invoke the ConflictResolver to detect and resolve conflicts
5. WHEN a Pegawai is successfully created or updated (including conflict resolution), THE SyncService SHALL update the pegawai.keycloak_synced_at timestamp
6. THE SyncService SHALL maintain the invariant that total processed equals created + updated + skipped + errors
7. WHEN a full sync completes, THE SyncService SHALL update the keycloak_sync_state table with last_sync_at, sync_type='full', total_synced (created + updated), and total_conflicts
8. IF a single Pegawai sync fails due to an exception, THEN THE SyncService SHALL record the error, log an audit entry with event_type='sync_failure', and continue processing the remaining Pegawai records
9. IF the CircuitBreaker transitions to OPEN state during a full sync, THEN THE SyncService SHALL abort processing remaining records and return a partial SyncResult with the counts accumulated so far and success=false

### Requirement 7: Incremental and Single Sync

**User Story:** As an administrator, I want to sync only recently changed Pegawai or a specific individual, so that I can efficiently update Keycloak without processing the entire dataset.

#### Acceptance Criteria

1. WHEN an incremental sync is initiated, THE SyncService SHALL process only Pegawai records with active status whose updated_at timestamp falls within the last 24 hours
2. WHEN a single sync is initiated for a specific NIP, THE SyncService SHALL sync only that Pegawai record to Keycloak following the same create-or-update logic as full sync (create if not exists, detect and resolve conflicts if exists)
3. IF a single sync is initiated for a NIP that does not exist in the Pegawai table or has inactive status, THEN THE SyncService SHALL return a failed SyncResult with an error indicating the Pegawai was not found or is inactive
4. WHEN a Pegawai record has status changed to inactive, THE SyncService SHALL set the corresponding Keycloak user enabled attribute to false
5. WHEN an incremental or single sync completes, THE SyncService SHALL update the keycloak_sync_state table with last_sync_at, sync_type (incremental or single), total_synced, and total_conflicts

### Requirement 8: Conflict Resolution

**User Story:** As a system architect, I want conflicts between Pegawai data and Keycloak user data to be automatically resolved with Pegawai as the source of truth, so that data integrity is maintained consistently.

#### Acceptance Criteria

1. WHEN comparing Pegawai and Keycloak user data, THE ConflictResolver SHALL detect four conflict types: DataMismatch (email, firstName, or lastName differs), StatusConflict (Pegawai active status differs from Keycloak enabled flag), RoleOverride (Pegawai role mappings differ from Keycloak realm role assignments), and IdentifierChange (NIP or email identifier in Pegawai differs from Keycloak username or email)
2. WHEN a conflict is detected, THE ConflictResolver SHALL apply the "Pegawai Wins" policy by overwriting the conflicting Keycloak field values with the corresponding Pegawai field values
3. WHEN a conflict is resolved, THE ConflictResolver SHALL update the Keycloak user attributes (username, email, firstName, lastName, enabled status, and realm role assignments) to match the current Pegawai data
4. WHEN a conflict is resolved, THE System SHALL log the conflict details with event_type='conflict', the specific conflict_type, pegawai_snapshot, keycloak_snapshot, resolution action taken, and resolved_by='system' to the keycloak_sync_audit table
5. THE ConflictResolver SHALL not produce side effects on the input Pegawai or Keycloak user data during detection
6. IF the Keycloak update fails during conflict resolution, THEN THE ConflictResolver SHALL leave the Pegawai data unchanged, log an audit entry with event_type='sync_failure' containing the error details and both snapshots, and propagate the error to the calling SyncService

### Requirement 9: Sync Audit Trail

**User Story:** As an administrator, I want all sync operations to be audited, so that I can track changes and troubleshoot issues.

#### Acceptance Criteria

1. WHEN a sync operation creates a user, THE System SHALL log an audit entry with event_type='create', pegawai_id, nip, pegawai_snapshot, caused_by (user ID or 'system' for scheduled sync), and caused_by_nip
2. WHEN a sync operation updates a user after conflict resolution, THE System SHALL log an audit entry with event_type='conflict', conflict_type, pegawai_snapshot, keycloak_snapshot, resolution, and resolved_by set to 'system' for automatic resolution or the admin NIP for manual resolution
3. WHEN a sync operation fails for a Pegawai, THE System SHALL log an audit entry with event_type='sync_failure', nip, pegawai_id, and an error_details string containing the exception message truncated to a maximum of 1000 characters
4. WHEN a sync operation updates a Keycloak user without conflict (data already matched after resolution), THE System SHALL log an audit entry with event_type='update', pegawai_id, nip, and pegawai_snapshot
5. THE keycloak_sync_audit table SHALL maintain indexes on (event_type, created_at) and (nip, created_at) for efficient querying
6. THE System SHALL store pegawai_snapshot and keycloak_snapshot as JSON objects with a maximum size of 64KB per field

### Requirement 10: Emergency Bypass Access

**User Story:** As a system administrator, I want emergency access to the admin panel when Keycloak is unavailable, so that I can still manage critical operations during an outage.

#### Acceptance Criteria

1. WHEN the CircuitBreaker is in OPEN state and emergency access is enabled in configuration, THE EmergencyBypass SHALL present the emergency login form and authenticate admin using preconfigured credentials stored in environment configuration
2. IF the CircuitBreaker is not in OPEN state, THEN THE EmergencyBypass SHALL redirect to normal Keycloak login with a message indicating that Keycloak is available and normal login should be used
3. IF emergency access is disabled in configuration, THEN THE EmergencyBypass SHALL return a 503 Service Unavailable response
4. WHEN emergency credentials are validated, THE EmergencyBypass SHALL create a session limited to the roles defined in the emergency allowed_roles configuration, with a 30-minute timeout from the moment of login
5. WHEN emergency credentials are validated, THE EmergencyBypass SHALL use constant-time comparison for username and hash verification for password
6. WHEN an emergency login succeeds or fails, THE System SHALL log the attempt in keycloak_emergency_login_log with hashed username, IP address, user_agent, timestamp, and outcome (success or failure)
7. IF emergency credentials are invalid, THEN THE EmergencyBypass SHALL reject the request and enforce rate limiting of a maximum of 5 attempts per IP address within a 15-minute window, blocking further attempts for 15 minutes after the limit is reached
8. IF an emergency session reaches its 30-minute timeout, THEN THE EmergencyBypass SHALL invalidate the session and redirect the administrator to the emergency login page

### Requirement 11: PKCE Generation

**User Story:** As a security engineer, I want PKCE parameters to comply with RFC 7636, so that the authorization code flow is protected against interception attacks.

#### Acceptance Criteria

1. THE System SHALL generate a unique code_verifier per authorization request with a length between 43 and 128 characters using a minimum of 32 cryptographically random bytes from the operating system CSPRNG
2. THE System SHALL encode the code_verifier using base64url encoding without padding (characters A-Z, a-z, 0-9, hyphen, underscore only, no '=' padding)
3. THE System SHALL compute code_challenge as BASE64URL(SHA256(code_verifier)) with method S256
4. THE System SHALL never use the 'plain' code_challenge_method
5. IF the CSPRNG source is unavailable or fails to generate random bytes, THEN THE System SHALL abort the login flow and return an error indicating that a secure login cannot be initiated

### Requirement 12: Database Schema

**User Story:** As a developer, I want the database schema to support Keycloak integration data, so that sync state, audit trails, and emergency access are properly persisted.

#### Acceptance Criteria

1. THE System SHALL create a keycloak_sync_audit table with columns: id (auto-increment primary key), event_type (string, max 50 characters, not null), pegawai_id (nullable foreign key referencing pegawai with nullOnDelete), nip (string, max 18 characters, not null, indexed), conflict_type (nullable string, max 50 characters), pegawai_snapshot (nullable JSON), keycloak_snapshot (nullable JSON), resolution (nullable JSON), resolved_by (string, max 50 characters, not null), caused_by (nullable foreign key referencing users with nullOnDelete), caused_by_nip (nullable string, max 18 characters), and timestamps
2. THE System SHALL create composite indexes on keycloak_sync_audit for (event_type, created_at) and (nip, created_at) to support efficient query filtering
3. THE System SHALL create a keycloak_sync_state table with columns: id (auto-increment primary key), last_sync_at (string, not null), last_sync_type (string, max 20 characters, not null), total_synced (integer, default 0), total_conflicts (integer, default 0), sync_metadata (nullable JSON), and timestamps
4. THE System SHALL create a keycloak_emergency_login_log table with columns: id (auto-increment primary key), user_id (nullable foreign key referencing users with nullOnDelete), username (string, max 255 characters, not null, stored as hashed value), ip_address (string, max 45 characters, not null), user_agent (nullable string, max 512 characters), logged_in_at (timestamp, not null), logged_out_at (nullable timestamp), and timestamps, with an index on logged_in_at
5. THE System SHALL add a nullable keycloak_id column (string, max 36 characters) with a unique index to the users table
6. THE System SHALL add nullable keycloak_synced_at (timestamp) and keycloak_user_id (string, max 36 characters) columns to the pegawai table

### Requirement 13: Middleware Integration

**User Story:** As a developer, I want middleware to handle token refresh, emergency bypass, and permission verification automatically, so that authentication concerns are handled transparently for all protected routes.

#### Acceptance Criteria

1. THE Middleware_Stack SHALL execute middleware in the following order: KeycloakTokenRefresh, EmergencyBypass, then VerifyIamPermission for every authenticated request
2. THE Middleware_Stack SHALL skip token refresh for routes matching keycloak/*, emergency/*, and _health paths
3. WHEN an authenticated request is processed and the access token is within 60 seconds of expiry, THE KeycloakTokenRefresh middleware SHALL proactively request a new Token_Set using the refresh token before passing the request to the next middleware
4. WHEN a request is processed, THE VerifyIamPermission middleware SHALL check that the user's session permissions array contains the permission string specified as the middleware parameter for the accessed route
5. IF the user lacks the required permission, THEN THE VerifyIamPermission middleware SHALL return a 403 Forbidden response without processing the request further
6. IF the user's session does not contain valid Keycloak session data (missing tokens or missing permissions array), THEN THE Middleware_Stack SHALL redirect the user to the login page
7. WHILE the CircuitBreaker is in OPEN state, THE EmergencyBypass middleware SHALL allow requests to emergency/* routes to proceed using the emergency session instead of requiring a valid Keycloak token

### Requirement 14: Sync Idempotency

**User Story:** As a system operator, I want sync operations to be idempotent, so that repeated execution with unchanged data produces the same result without side effects.

#### Acceptance Criteria

1. WHEN a full sync is executed 2 or more consecutive times with unchanged Pegawai data, THE SyncService SHALL produce identical user attributes (username, email, firstName, lastName, enabled status, and assigned realm roles) in Keycloak after each execution
2. WHEN a Pegawai record has no detected conflicts (DataMismatch, StatusConflict, RoleOverride, or IdentifierChange) with its corresponding Keycloak user, THE SyncService SHALL skip that record without making any write API calls to Keycloak
3. WHEN a record is skipped due to no conflicts, THE SyncService SHALL not update the pegawai.keycloak_synced_at timestamp and SHALL not create any audit entry in keycloak_sync_audit for that record
4. WHEN a full sync is re-executed with unchanged data after a previous successful full sync, THE SyncService SHALL return a SyncResult with created=0, updated=0, errors=0, and skipped equal to the total number of active Pegawai processed

### Requirement 15: Admin Panel for Sync Management

**User Story:** As an administrator, I want a Filament-based admin panel to manage sync operations, so that I can monitor sync status, trigger operations, and review audit logs.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a dashboard showing current sync state including last_sync_at (formatted as date-time), last_sync_type, total_synced, total_conflicts, and the current CircuitBreaker state (CLOSED, OPEN, HALF_OPEN)
2. THE Admin_Panel SHALL provide controls to trigger full sync and incremental sync operations, and a single pegawai sync control that accepts a valid 18-digit NIP as input
3. WHEN a sync operation is triggered, THE Admin_Panel SHALL display a confirmation notification indicating the operation result including counts of created, updated, skipped, conflicts, and errors within 5 seconds of completion
4. IF a sync operation fails to start due to CircuitBreaker being in OPEN state or validation error, THEN THE Admin_Panel SHALL display an error notification indicating the failure reason without starting the operation
5. THE Admin_Panel SHALL display the current CircuitBreaker state (CLOSED, OPEN, HALF_OPEN) with failure count and last failure timestamp, and provide a manual reset control to transition to CLOSED state
6. THE Admin_Panel SHALL provide a paginated view of keycloak_sync_audit records (maximum 50 records per page) that is filterable by event_type, conflict_type, and date range, and searchable by nip
7. THE Admin_Panel SHALL provide a paginated view of keycloak_emergency_login_log records (maximum 50 records per page) displaying ip_address, user_agent, logged_in_at, and logged_out_at columns
