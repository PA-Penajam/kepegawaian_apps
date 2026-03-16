# Relational Role Migration Design

## Goal
Migrate the application from the production enum-based `users.role` system to a relational `users.ref_role_id -> ref_roles -> ref_permissions` system without breaking existing authorization paths, locking users out of the application, or creating frontend/runtime contract drift.

## Current State

### Active production authorization path
- `users.role` is a string column added by `database/migrations/2026_03_15_023317_add_role_to_users_table.php`.
- `App\Enums\Role` is cast in `app/Models/User.php` and powers `isAdmin()`, `isOperator()`, and `isViewer()`.
- `app/Http/Middleware/EnsureRole.php` powers route strings like `role:admin,operator`.
- `app/Policies/RefPolicy.php`, `app/Policies/PegawaiPolicy.php`, and three update form requests call `isAdmin()` / `isOperator()` directly.
- Inertia currently shares the raw authenticated user model from `app/Http/Middleware/HandleInertiaRequests.php`, and the frontend assumes `auth.user.role` is a string literal.

### Existing but disconnected RBAC path
- `ref_roles`, `ref_permissions`, and `ref_role_permission` already exist.
- `RefRoleSeeder` seeds `Admin`, `Operator`, and `Viewer`.
- `RefPermissionSeeder` seeds 11 permissions and assigns them to the seeded roles.
- `RefRole` already has a `permissions()` relation and `hasPermission()` helper.
- Role CRUD UI exists, but there is no role assignment UI for users and no permission management UI.

## Recommended Architecture
Use a phased cutover with a stable compatibility layer:

1. Add a canonical immutable role key on `ref_roles`.
2. Add `users.ref_role_id` as nullable, backfill it from the legacy string role, and deploy application reads against the relational system.
3. Keep `users.role` and the legacy role helper method names temporarily as compatibility artifacts, but stop treating the enum column as the source of truth.
4. Move capability checks to permissions for policies, form requests, and frontend navigation.
5. Keep the `role:` middleware alias as a compatibility surface for existing coarse-grained route groups, but make it resolve against the canonical relational role key.
6. Delay User Management and role-permission management UI until the core auth cutover is stable.
7. Remove the enum and `users.role` only after the new system has survived a release cycle.

## Key Design Decisions

### 1. Introduce an immutable role identity
Do not use `ref_roles.nama` as the authorization key. It is currently editable and title-cased (`Admin`, `Operator`, `Viewer`), while the legacy enum values are lowercase (`admin`, `operator`, `viewer`).

Add a unique lowercase `key` column to `ref_roles`:
- `admin`
- `operator`
- `viewer`

`nama` remains display text. `key` becomes the stable authorization identity.

### 2. Keep single-role users
The target model remains one role per user via `users.ref_role_id`. This migration does not introduce multi-role users or direct per-user permissions.

### 3. Preserve helper method names temporarily
Keep `User::isAdmin()`, `User::isOperator()`, and `User::isViewer()` during the migration, but reimplement them on top of the relational role key. This limits blast radius while legacy callers are migrated.

### 4. Use permissions as the capability source of truth
Policies, form requests, and frontend capability checks should move to `hasPermission()` style checks. Role identity should only remain where the application intentionally wants coarse-grained route grouping or temporary compatibility.

### 5. Stop leaking the raw User model to Inertia
`HandleInertiaRequests` should return an explicit auth payload shape instead of serializing the raw model. Recommended shared payload:

```php
[
    'auth' => [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'pegawai_id' => $user->pegawai_id,
            'role' => [
                'id' => $user->refRole?->id,
                'key' => $user->refRole?->key,
                'nama' => $user->refRole?->nama,
                'is_system' => $user->refRole?->is_system,
            ],
            'permissions' => $user->permission_names,
        ],
    ],
]
```

This prevents the frontend from depending on accidental serialization details.

## Hidden Risks

### Canonical identity mismatch
The existing seeded role names do not match the legacy enum strings. Backfill logic that compares `users.role` to `ref_roles.nama` will fail.

### Authorization split-brain
Access rules currently exist in middleware, policies, form requests, controllers, factories, seeders, frontend type definitions, and many tests. Partial migration will create contradictory behavior.

### `role` attribute vs relationship confusion
If the old `role` column and a new `role()` relation coexist, the public contract becomes ambiguous. During transition, prefer a `refRole()` relation and an explicit shared auth payload.

### Soft deletes on roles
`RefRole` uses `SoftDeletes`. If assigned roles can be soft-deleted, `belongsTo` can resolve to `null` and silently deny access. The safer approach is to block deletion of assigned roles and add tests that prove the behavior.

### Test surface is larger than the obvious list
Role-dependent behavior exists in many feature tests, not just the middleware and form request tests. `UserFactory` state helpers are a major dependency and must remain stable until callers are migrated.

### Rollback complexity
If User Management and custom role assignment ship before the legacy system is removed, rolling back to the old enum-based application becomes unreliable unless dual-write is implemented. The safer sequencing is to cut over core auth first, then ship admin UIs.

## Assumptions Used For Planning
- One role per user.
- No per-user direct permissions.
- `admin`, `operator`, and `viewer` remain seeded system roles.
- System role `key` values are immutable.
- User Management will live under the existing `Referensi` namespace and navigation group to avoid creating a new top-level controller namespace.
- Core auth migration ships before User Management and role-permission management UI.

## Explicit Non-Goals
- Multi-role users.
- Direct user-to-permission assignments.
- New caching/invalidation infrastructure unless profiling proves it is needed.
- Replacing every route group with permission middleware in the first migration release.
- Building audit logging for role changes as part of this migration.

## Release Strategy

### Phase 1: Core auth cutover
- Add `ref_roles.key`.
- Add nullable `users.ref_role_id`.
- Backfill current users from `users.role`.
- Update `User` auth helpers to relational reads.
- Update middleware, policies, form requests, and controller checks.
- Update Inertia shared auth payload and frontend permission checks.
- Keep `users.role` and `App\Enums\Role` in place temporarily.

### Phase 2: Admin UI expansion
- Add User Management UI and backend for assigning roles.
- Enhance Roles UI to manage permissions.
- Add targeted tests around role assignment and permission editing.

### Phase 3: Legacy cleanup
- Remove `App\Enums\Role`.
- Remove `users.role`.
- Delete temporary compatibility wrappers only after all references are gone.

## Testing Strategy
TDD is mandatory. Every phase begins with failing tests and ends with targeted verification.

Minimum regression commands:
- `php artisan test --compact tests/Feature/Auth/RoleMiddlewareTest.php`
- `php artisan test --compact tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php`
- `php artisan test --compact tests/Feature/Kepegawaian/PegawaiControllerTest.php tests/Feature/SelfService/SelfServiceAccessTest.php tests/Feature/Monitoring/KgbMonitoringTest.php tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php`
- `npm run build`
- `vendor/bin/pint --dirty --format agent`

## Atomic Commit Strategy
1. `test(auth): pin relational role migration behavior`
2. `feat(auth): add relational role identity and user backfill`
3. `refactor(auth): migrate authorization checks to relational auth`
4. `feat(auth): stabilize shared auth payload and frontend permissions`
5. `feat(referensi): add user management for role assignment`
6. `feat(referensi): add role permission management`
7. `chore(auth): remove legacy enum role path`

## Approval Outcome
This document reflects the approved direction used to generate the implementation plan. Open questions remain, but the plan below assumes the safest defaults above so work can proceed without blocking.
