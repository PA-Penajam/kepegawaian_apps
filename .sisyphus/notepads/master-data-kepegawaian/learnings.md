# Learnings — master-data-kepegawaian

## [2026-03-15] Initialized

- Project: Laravel 12 + React 19 + Inertia v2 + Tailwind 4 + shadcn/ui
- Auth: Lengkap (Fortify), belum ada kode kepegawaian
- Test: Pest 4, 14 existing test files
- No repository pattern — direct Eloquent
- Middleware registration: bootstrap/app.php (bukan Kernel.php — Laravel 12)
- String-backed enums (bukan integer)
- SoftDeletes pada semua entity kepegawaian
- NIP nullable (honorer)
- User ↔ Pegawai: 1:1, users.pegawai_id nullable FK
- RBAC: Enum-based (Admin, Operator, Viewer), TIDAK pakai Spatie
- Seeder idempotent via updateOrCreate
