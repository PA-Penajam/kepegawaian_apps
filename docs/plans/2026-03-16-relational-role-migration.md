# Relational Role Migration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace enum-based user roles with relational roles and permissions, then expand the admin UI for role assignment and permission management without breaking existing authorization behavior in production.

**Architecture:** Ship the migration in three phases. First, cut application reads over to relational auth behind compatibility helpers and a stable Inertia auth payload. Second, add the admin-facing User Management and role-permission management flows. Third, remove the legacy enum artifacts after the new system has survived verification.

**Tech Stack:** Laravel 12, Fortify, Inertia v2, React, TypeScript, Pest 4, Laravel Pint.

---

## Planning Defaults
- Use an isolated git worktree for execution because the current checkout is dirty.
- Keep one role per user.
- Add a unique immutable `key` column to `ref_roles` and use it as the canonical role identity.
- Keep `role:` middleware for compatibility in the first release, but migrate capability checks everywhere else to permissions.
- User Management lives under `Referensi` paths to avoid introducing a new top-level controller namespace.

## Must Have
- Relational role identity (`ref_roles.key`) and user foreign key (`users.ref_role_id`).
- Backfill for existing production users.
- Compatibility wrappers on `User` so existing callers do not all break at once.
- Permission-based policies, form requests, and frontend capability checks.
- Explicit Inertia auth payload with role and permission data.
- User Management CRUD with role assignment.
- Roles UI that can assign permissions.
- Full regression coverage and a final cleanup phase.

## Must Not Have
- Multi-role users.
- Direct user permissions.
- New dependencies.
- Manual-only QA.
- Big-bang removal of `users.role` in the same step that first introduces `ref_role_id`.

## Execution Gates
- Do not start implementation in the dirty primary worktree.
- Do not begin Task 8 or Task 9 until Tasks 1-7 pass.
- Do not run legacy cleanup until Task 10 acceptance criteria pass and the relational system is stable.

## Atomic Commit Strategy
1. `test(auth): pin relational role migration behavior`
2. `feat(auth): add relational role key and user backfill`
3. `refactor(auth): move authorization to relational helpers`
4. `feat(auth): stabilize Inertia auth payload and frontend gating`
5. `feat(referensi): add user management role assignment`
6. `feat(referensi): add role permission management`
7. `chore(auth): remove legacy enum role path`

## Task 0: Prepare isolated execution workspace

**Files:**
- None in repo if `.worktrees/` already exists and is ignored.
- Modify only if needed: `.gitignore`

**Step 1: Verify worktree location**
Run: `ls -d .worktrees 2>/dev/null || ls -d worktrees 2>/dev/null`
Expected: existing worktree directory found, or record that execution must choose one before continuing.

**Step 2: Verify ignore safety for project-local worktrees**
Run: `git check-ignore -q .worktrees || git check-ignore -q worktrees`
Expected: exit code `0` for the chosen directory.

**Step 3: Create worktree and branch for implementation**
Run: `git worktree add .worktrees/relational-role-migration -b feature/relational-role-migration`
Expected: new worktree created successfully.

**Step 4: Verify clean baseline inside the worktree**
Run: `git status --short`
Expected: no output.

**Step 5: Commit**
No commit for this task unless `.gitignore` must be changed.

## Task 1: Pin the target behavior with failing tests

**Files:**
- Create: `tests/Feature/Auth/RelationalRoleMigrationTest.php`
- Modify: `tests/Feature/Auth/RoleMiddlewareTest.php`
- Modify: `tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php`
- Modify as needed: `tests/Feature/SelfService/SelfServiceAccessTest.php`

**Step 1: Write the failing tests**
Add coverage for:
- backfilling `admin`, `operator`, and `viewer` into relational roles;
- denying access when `ref_role_id` is null;
- denying access when an assigned role is soft-deleted;
- preserving guest redirect behavior for `role:` middleware;
- authorizing the three form requests through relational helpers instead of enum casts.

**Step 2: Run tests to verify they fail**
Run: `php artisan test --compact tests/Feature/Auth/RelationalRoleMigrationTest.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php`
Expected: FAIL with missing relational auth helpers, schema, or backfill logic.

**Step 3: Keep failures focused**
Do not change production code yet. Only tighten tests until they describe the intended behavior precisely.

**Step 4: Re-run the same command**
Run: `php artisan test --compact tests/Feature/Auth/RelationalRoleMigrationTest.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php`
Expected: still FAIL, but only for the new intended behavior.

**Step 5: Commit**
Run:
```bash
git add tests/Feature/Auth/RelationalRoleMigrationTest.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php tests/Feature/SelfService/SelfServiceAccessTest.php
git commit -m "test(auth): pin relational role migration behavior"
```

## Task 2: Add canonical role identity and backfillable user foreign key

**Files:**
- Create via Artisan: `database/migrations/*_add_key_to_ref_roles_table.php`
- Create via Artisan: `database/migrations/*_add_ref_role_id_to_users_table.php`
- Modify: `database/seeders/RefRoleSeeder.php`
- Modify as needed: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Auth/RelationalRoleMigrationTest.php`

**Step 1: Write or extend the failing migration test**
Ensure the test asserts that seeded system roles have keys `admin`, `operator`, and `viewer`, and existing users are backfilled to a non-null `ref_role_id`.

**Step 2: Run the migration-focused test**
Run: `php artisan test --compact tests/Feature/Auth/RelationalRoleMigrationTest.php`
Expected: FAIL.

**Step 3: Implement the minimal schema and backfill**
- Add unique `key` to `ref_roles`.
- Add nullable `ref_role_id` to `users`.
- Backfill `users.ref_role_id` by mapping legacy `users.role` strings to system role keys.
- Keep `users.role` in place for now.
- Fail fast if required system roles are missing.

**Step 4: Run the migration-focused test again**
Run: `php artisan test --compact tests/Feature/Auth/RelationalRoleMigrationTest.php`
Expected: PASS.

**Step 5: Commit**
Run:
```bash
git add database/migrations database/seeders/RefRoleSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Auth/RelationalRoleMigrationTest.php
git commit -m "feat(auth): add relational role key and user backfill"
```

## Task 3: Introduce relational auth helpers on User and preserve factory compatibility

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/RefRole.php`
- Modify: `database/factories/UserFactory.php`
- Modify if needed: `database/factories/RefRoleFactory.php`
- Test: `tests/Feature/Auth/RoleMiddlewareTest.php`
- Test: `tests/Feature/Auth/RelationalRoleMigrationTest.php`

**Step 1: Add failing helper expectations**
Extend tests to require:
- `User::refRole()` relation,
- `User::hasRoleKey(string $key)`,
- `User::hasPermission(string $permission)`,
- wrapper methods `isAdmin()`, `isOperator()`, and `isViewer()` powered by the relational role key,
- factory states `admin()`, `operator()`, and `viewer()` still working.

**Step 2: Run the targeted tests**
Run: `php artisan test --compact tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Auth/RelationalRoleMigrationTest.php`
Expected: FAIL.

**Step 3: Implement the minimal auth surface**
- Add `refRole()` relation on `User`.
- Add permission helper methods on `User`.
- Reimplement wrapper role methods using `refRole.key`.
- Update `UserFactory` states to assign relational roles while preserving the old API.
- Prefer eager-loaded permission collection checks over repeated query calls where practical.

**Step 4: Re-run the targeted tests**
Run: `php artisan test --compact tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Auth/RelationalRoleMigrationTest.php`
Expected: PASS.

**Step 5: Commit**
Run:
```bash
git add app/Models/User.php app/Models/RefRole.php database/factories/UserFactory.php database/factories/RefRoleFactory.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Auth/RelationalRoleMigrationTest.php
git commit -m "refactor(auth): add relational user auth helpers"
```

## Task 4: Migrate middleware, policies, form requests, and controller checks

**Files:**
- Modify: `app/Http/Middleware/EnsureRole.php`
- Modify: `app/Policies/RefPolicy.php`
- Modify: `app/Policies/PegawaiPolicy.php`
- Modify: `app/Policies/RefRolePolicy.php`
- Modify: `app/Http/Requests/Kepegawaian/UpdatePenghargaanRequest.php`
- Modify: `app/Http/Requests/Kepegawaian/UpdateRiwayatJabatanRequest.php`
- Modify: `app/Http/Requests/Kepegawaian/UpdateRiwayatPangkatRequest.php`
- Modify as needed: `routes/web.php`
- Modify as needed: `app/Http/Controllers/Kepegawaian/RiwayatJabatanController.php`
- Modify as needed: `app/Http/Controllers/Kepegawaian/PenghargaanController.php`
- Modify as needed: the remaining five kepegawaian controllers that declare `new Middleware('role:admin,operator')`
- Test: `tests/Feature/Auth/RoleMiddlewareTest.php`
- Test: `tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php`
- Test: `tests/Feature/Kepegawaian/PegawaiControllerTest.php`

**Step 1: Expand tests to express permission-based capability rules**
Define exact expectations for:
- `pegawai.*` permissions,
- `referensi.*` permissions,
- admin-only destructive actions,
- operator access where currently allowed,
- viewer denial.

**Step 2: Run the authorization tests**
Run: `php artisan test --compact tests/Feature/Auth/RoleMiddlewareTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php tests/Feature/Kepegawaian/PegawaiControllerTest.php`
Expected: FAIL.

**Step 3: Implement the minimal migration**
- Make `EnsureRole` resolve against the relational role key.
- Convert policy and form request internals to `hasPermission()` or equivalent `User` helpers.
- Keep route-level `role:` checks only where the app still intentionally groups by system role.
- Do not broaden access beyond the current behavior.

**Step 4: Re-run the authorization tests**
Run: `php artisan test --compact tests/Feature/Auth/RoleMiddlewareTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php tests/Feature/Kepegawaian/PegawaiControllerTest.php`
Expected: PASS.

**Step 5: Commit**
Run:
```bash
git add app/Http/Middleware/EnsureRole.php app/Policies/RefPolicy.php app/Policies/PegawaiPolicy.php app/Policies/RefRolePolicy.php app/Http/Requests/Kepegawaian/UpdatePenghargaanRequest.php app/Http/Requests/Kepegawaian/UpdateRiwayatJabatanRequest.php app/Http/Requests/Kepegawaian/UpdateRiwayatPangkatRequest.php routes/web.php app/Http/Controllers/Kepegawaian/*.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php tests/Feature/Kepegawaian/PegawaiControllerTest.php
git commit -m "refactor(auth): move authorization to relational helpers"
```

## Task 5: Stabilize the Inertia auth payload and frontend authorization checks

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/auth.ts`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/types/referensi.ts`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/pages/kepegawaian/pegawai/index.tsx`
- Test: `tests/Feature/Auth/InertiaAuthPayloadTest.php`

**Step 1: Write the failing Inertia payload test**
Create a new feature test that asserts the shared auth payload contains:
- `auth.user.role.id`
- `auth.user.role.key`
- `auth.user.role.nama`
- `auth.user.permissions` as a string array
and does not require the frontend to parse the raw Eloquent model.

**Step 2: Run the backend auth payload test**
Run: `php artisan test --compact tests/Feature/Auth/InertiaAuthPayloadTest.php`
Expected: FAIL.

**Step 3: Implement the minimal payload and frontend updates**
- Return an explicit auth payload from `HandleInertiaRequests`.
- Update TS types to match that payload.
- Replace string role comparisons in the sidebar and Pegawai index page with permission checks or `role.key` only where identity is truly intended.

**Step 4: Run backend and frontend verification**
Run: `php artisan test --compact tests/Feature/Auth/InertiaAuthPayloadTest.php && npm run build`
Expected: tests PASS and build exits `0`.

**Step 5: Commit**
Run:
```bash
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/auth.ts resources/js/types/index.ts resources/js/types/referensi.ts resources/js/components/app-sidebar.tsx resources/js/pages/kepegawaian/pegawai/index.tsx tests/Feature/Auth/InertiaAuthPayloadTest.php
git commit -m "feat(auth): stabilize Inertia auth payload and frontend gating"
```

## Task 6: Run focused core-auth regression before expanding scope

**Files:**
- No new code unless regressions require targeted fixes.

**Step 1: Run the focused backend regression suite**
Run: `php artisan test --compact tests/Feature/Auth/RelationalRoleMigrationTest.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Auth/InertiaAuthPayloadTest.php tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php tests/Feature/Kepegawaian/PegawaiControllerTest.php tests/Feature/Monitoring/KgbMonitoringTest.php tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php tests/Feature/SelfService/SelfServiceAccessTest.php`
Expected: exit code `0`.

**Step 2: Run formatting**
Run: `vendor/bin/pint --dirty --format agent`
Expected: exit code `0`.

**Step 3: Run the frontend build**
Run: `npm run build`
Expected: exit code `0`.

**Step 4: Only fix failures that are direct regressions from Tasks 1-5**
Do not start User Management or role-permission UI until all three commands pass.

**Step 5: Commit**
Only commit if regression fixes were required. If so, use:
```bash
git add <fixed-files>
git commit -m "fix(auth): resolve relational role migration regressions"
```

## Task 7: Add User Management module for role assignment

**Files:**
- Create: `app/Http/Controllers/Referensi/UserController.php`
- Create: `app/Http/Requests/Referensi/StoreUserRequest.php`
- Create: `app/Http/Requests/Referensi/UpdateUserRequest.php`
- Modify: `app/Policies/RefPolicy.php` or create `app/Policies/UserPolicy.php` if separate policy is clearer
- Modify: `routes/web.php`
- Create: `resources/js/pages/referensi/users/index.tsx`
- Create: `resources/js/pages/referensi/users/create.tsx`
- Create: `resources/js/pages/referensi/users/edit.tsx`
- Modify as needed: `resources/js/components/app-sidebar.tsx`
- Test: `tests/Feature/Referensi/UserManagementTest.php`

**Step 1: Write the failing feature test**
Cover:
- admin can list users,
- admin can create a user with a role,
- admin can update a user's role,
- operator and viewer are forbidden,
- role dropdown only shows active roles.

**Step 2: Run the new test**
Run: `php artisan test --compact tests/Feature/Referensi/UserManagementTest.php`
Expected: FAIL.

**Step 3: Implement the minimal module**
- Restrict access to users with `rbac.manage`.
- Reuse existing Inertia CRUD conventions from `referensi` modules.
- Assign roles through `ref_role_id` only.
- Do not add password-reset workflows or bulk actions.

**Step 4: Re-run the feature test and build**
Run: `php artisan test --compact tests/Feature/Referensi/UserManagementTest.php && npm run build`
Expected: PASS and exit code `0`.

**Step 5: Commit**
Run:
```bash
git add app/Http/Controllers/Referensi/UserController.php app/Http/Requests/Referensi/StoreUserRequest.php app/Http/Requests/Referensi/UpdateUserRequest.php routes/web.php resources/js/pages/referensi/users resources/js/components/app-sidebar.tsx tests/Feature/Referensi/UserManagementTest.php
git commit -m "feat(referensi): add user management role assignment"
```

## Task 8: Enhance Roles module to manage permissions safely

**Files:**
- Modify: `app/Http/Controllers/Referensi/RefRoleController.php`
- Modify: `app/Http/Requests/Referensi/StoreRefRoleRequest.php`
- Modify: `app/Http/Requests/Referensi/UpdateRefRoleRequest.php`
- Modify: `app/Models/RefRole.php`
- Modify: `app/Models/RefPermission.php`
- Modify: `resources/js/pages/referensi/roles/create.tsx`
- Modify: `resources/js/pages/referensi/roles/edit.tsx`
- Modify: `resources/js/pages/referensi/roles/index.tsx`
- Create as needed: `resources/js/components/referensi/role-permission-form.tsx`
- Test: `tests/Feature/Referensi/RolePermissionManagementTest.php`

**Step 1: Write the failing feature test**
Cover:
- admin can assign permissions to non-system roles,
- admin can view system role permissions,
- system role keys cannot be changed,
- deleting a role assigned to users is forbidden,
- operator and viewer are forbidden.

**Step 2: Run the new test**
Run: `php artisan test --compact tests/Feature/Referensi/RolePermissionManagementTest.php`
Expected: FAIL.

**Step 3: Implement the minimal enhancement**
- Allow create/update forms to submit permission ids.
- Sync `ref_role_permission` on save.
- Protect immutable system-role identity.
- Block deleting assigned roles.
- Keep UI simple; no drag-and-drop or audit log.

**Step 4: Re-run the feature test and build**
Run: `php artisan test --compact tests/Feature/Referensi/RolePermissionManagementTest.php && npm run build`
Expected: PASS and exit code `0`.

**Step 5: Commit**
Run:
```bash
git add app/Http/Controllers/Referensi/RefRoleController.php app/Http/Requests/Referensi/StoreRefRoleRequest.php app/Http/Requests/Referensi/UpdateRefRoleRequest.php app/Models/RefRole.php app/Models/RefPermission.php resources/js/pages/referensi/roles resources/js/components/referensi/role-permission-form.tsx tests/Feature/Referensi/RolePermissionManagementTest.php
git commit -m "feat(referensi): add role permission management"
```

## Task 9: Verify the expanded admin surface

**Files:**
- No new code unless verification reveals regressions.

**Step 1: Run all role-management regression tests**
Run: `php artisan test --compact tests/Feature/Referensi/UserManagementTest.php tests/Feature/Referensi/RolePermissionManagementTest.php tests/Feature/Auth/RelationalRoleMigrationTest.php tests/Feature/Auth/RoleMiddlewareTest.php tests/Feature/Auth/InertiaAuthPayloadTest.php`
Expected: exit code `0`.

**Step 2: Run the broader regression suite**
Run: `php artisan test --compact tests/Feature/Kepegawaian tests/Feature/Monitoring tests/Feature/SelfService`
Expected: exit code `0`.

**Step 3: Run formatting and frontend build**
Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: exit code `0`.

**Step 4: Fix only proven regressions**
Record exact failing test names before changing code.

**Step 5: Commit**
Only if fixes were needed:
```bash
git add <fixed-files>
git commit -m "fix(referensi): resolve role management regressions"
```

## Task 10: Remove the legacy enum role path after stabilization

**Files:**
- Modify: `app/Models/User.php`
- Delete: `app/Enums/Role.php`
- Create via Artisan or manual migration: `database/migrations/*_drop_role_from_users_table.php`
- Modify: `database/factories/UserFactory.php`
- Modify any remaining tests still importing `App\Enums\Role`
- Test: all auth and affected feature tests

**Step 1: Add failing cleanup assertions**
Require:
- no production code imports `App\Enums\Role`,
- `users.role` is no longer read anywhere,
- factory states still pass without the enum,
- the cleanup migration safely drops the old column only after relational auth is stable.

**Step 2: Run targeted cleanup checks**
Run: `php artisan test --compact tests/Feature/Auth tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php`
Expected: FAIL until all enum dependencies are removed.

**Step 3: Implement the cleanup**
- Remove the enum cast and legacy imports.
- Remove the legacy column in a separate migration.
- Keep helper method names only if they are still intentionally part of the public `User` API; otherwise remove them and update callers.

**Step 4: Run final verification**
Run: `php artisan test --compact tests/Feature/Auth tests/Feature/Referensi tests/Feature/Kepegawaian tests/Feature/Monitoring tests/Feature/SelfService tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php && vendor/bin/pint --dirty --format agent && npm run build`
Expected: all commands exit `0`.

**Step 5: Commit**
Run:
```bash
git add app/Models/User.php app/Enums/Role.php database/migrations database/factories/UserFactory.php tests/Feature/Auth tests/Feature/Referensi tests/Feature/Kepegawaian tests/Feature/Monitoring tests/Feature/SelfService tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php
git commit -m "chore(auth): remove legacy enum role path"
```

## QA Matrix
- Happy path: admin accesses `/referensi/roles` and `/referensi/users`, assigns roles and permissions, and receives success responses proven by feature tests.
- Happy path: operator retains kepegawaian access but cannot access RBAC pages, proven by feature tests.
- Happy path: viewer retains self-service access and remains blocked from admin/operator areas, proven by feature tests.
- Failure path: guest is redirected from protected routes, proven by `tests/Feature/Auth/RoleMiddlewareTest.php`.
- Failure path: user with `ref_role_id = null` receives forbidden or unauthorized behavior defined by the tests.
- Failure path: assigned soft-deleted role does not accidentally grant access.
- Failure path: system role identity cannot be mutated through the role editor.
- Failure path: assigned roles cannot be deleted.

## Final Verification Commands
Run all of these before declaring success:

```bash
php artisan test --compact tests/Feature/Auth tests/Feature/Referensi tests/Feature/Kepegawaian tests/Feature/Monitoring tests/Feature/SelfService tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php
vendor/bin/pint --dirty --format agent
npm run build
```

Expected results:
- all test commands exit `0`
- no failed tests
- Pint exits `0`
- frontend build exits `0`
