# Master Data Kepegawaian — Pengadilan Agama Penajam

## TL;DR

> **Quick Summary**: Membangun fondasi aplikasi master data kepegawaian untuk PA Penajam (Kelas II) yang melengkapi SIKEP. Mencakup reference tables, data pegawai lengkap (biodata, keluarga, riwayat), monitoring KGB & kenaikan pangkat, dashboard statistik, dan RBAC self-service untuk 40+ pegawai.
>
> **Deliverables**:
>
> - Reference tables (pangkat/golongan, jabatan, unit kerja, lookup tables) dengan seed data
> - Master data pegawai (biodata, keluarga, riwayat pangkat/jabatan/pendidikan/diklat, penghargaan, hukuman disiplin, dokumen)
> - Monitoring dashboard (KGB deadline 2 bulan sebelum TMT, kenaikan pangkat periode April/Oktober)
> - RBAC (admin, operator, self-service) dengan Pegawai-User linkage
> - Dashboard statistik kepegawaian
> - CRUD pages untuk semua entities dengan Inertia.js + React + shadcn/ui
>
> **Estimated Effort**: Large
> **Parallel Execution**: YES - 5 waves
> **Critical Path**: Task 1 (Enums) → Task 2 (Reference Tables) → Task 5 (Pegawai Model) → Task 8 (Riwayat Pangkat) → Task 16 (Monitoring KGB) → Task 18 (Dashboard) → Final Verification

---

## Context

### Original Request

Membangun fondasi awal master data kepegawaian untuk Sub Bagian Kepegawaian, Organisasi, dan Tata Laksana di Pengadilan Agama Penajam (PA Kelas II). Aplikasi ini melengkapi SIKEP (sistem pusat MA) dengan menyediakan monitoring lokal, deadline tracking, dan pelaporan yang tidak tersedia di SIKEP level satker.

### Interview Summary

**Key Discussions**:

- **Scope fondasi**: Master Data + Monitoring (KGB & Kenaikan Pangkat)
- **Users**: Semua pegawai (self-service) — 40+ orang, bukan hanya staf admin
- **Roles**: Admin (full access), Operator (CRUD), Viewer/Self-service (lihat data sendiri)
- **Data source**: Ada dokumen data awal yang sudah ada ("ada di docs data awal")
- **Prioritas tupoksi**: (1) Monitoring KGB, (2) Kenaikan Pangkat, (3) Data Pegawai & DUK, (4) Pensiun & Pemberhentian, (5) Disiplin & Hukuman, (6) Surat-surat Kepegawaian
- **Special note**: Surat izin keluar pegawai untuk kenaikan pangkat sudah tiap bulan mengikuti aturan terbaru per 2026
- **Test strategy**: TDD (RED-GREEN-REFACTOR) menggunakan Pest 4

**Research Findings**:

- PERMA 7/2015 Pasal 322 mendefinisikan 16 area kerja tupoksi kepegawaian
- SIKEP v3.1.0 menangani data pusat tapi TIDAK menyediakan: monitoring deadline, buku kendali digital, persiapan berkas, pelaporan lokal
- KGB harus diberitahukan 2 bulan sebelum TMT per SOP
- Kenaikan Pangkat 2 periode: April (usul Oktober sebelumnya) dan Oktober (usul April)
- Codebase: Laravel 12 + React 19 + Inertia v2 + Tailwind 4 + shadcn/ui, auth lengkap, belum ada kode kepegawaian

### Gap Analysis (Self-Review)

**Identified Gaps** (addressed):

- User ↔ Pegawai linkage: Resolved → 1:1 relationship, User.pegawai_id nullable (admin tanpa data pegawai)
- Soft delete: Resolved → Semua entity kepegawaian menggunakan SoftDeletes
- Audit trail: Resolved → Deferred ke fase berikutnya (di luar scope fondasi)
- Pegawai tanpa NIP (honorer): Resolved → NIP nullable, ada field status_kepegawaian (PNS, PPPK, Honorer)
- Pegawai mutasi keluar: Resolved → Field status_aktif dengan enum (Aktif, Mutasi Keluar, Pensiun, Meninggal, Diberhentikan)

---

## Work Objectives

### Core Objective

Membangun fondasi master data kepegawaian digital yang melengkapi SIKEP untuk PA Penajam, dengan fokus pada data pegawai lengkap dan monitoring deadline KGB/Kenaikan Pangkat yang saat ini masih manual (Excel/buku fisik).

### Concrete Deliverables

- 10+ database migrations untuk reference tables & core entities
- 10+ Eloquent models dengan relationships, factories, dan seeders
- Reference data seed (pangkat/golongan 17 level, jabatan struktural/fungsional, unit kerja PA)
- RBAC middleware dengan 3 roles (admin, operator, viewer)
- CRUD pages untuk Pegawai (list, create, show, edit) dengan React + Inertia
- Sub-pages: keluarga, riwayat pangkat, riwayat jabatan, riwayat pendidikan, riwayat diklat, penghargaan, hukuman disiplin
- Monitoring KGB: list pegawai mendekati TMT KGB, filter by status, notifikasi badge
- Monitoring Kenaikan Pangkat: list pegawai eligible, filter by periode (April/Oktober)
- Dashboard: statistik pegawai (by golongan, jabatan, unit kerja, jenis kelamin, pendidikan)
- Sidebar navigation terstruktur untuk menu kepegawaian
- Feature tests (Pest 4) untuk semua endpoints dan business logic

### Definition of Done

- [ ] `php artisan test --compact` → ALL PASS (0 failures)
- [ ] `vendor/bin/pint --dirty --format agent` → 0 issues
- [ ] `npm run build` → success (0 errors)
- [ ] Semua CRUD pages accessible via browser
- [ ] Dashboard menampilkan statistik yang benar
- [ ] Monitoring KGB menampilkan pegawai yang mendekati TMT
- [ ] RBAC: admin bisa akses semua, operator bisa CRUD, viewer hanya lihat data sendiri
- [ ] Reference data ter-seed dengan benar (17 level pangkat, jabatan PA, unit kerja)

### Must Have

- NIP sebagai identifier utama pegawai (nullable untuk honorer)
- Semua riwayat (pangkat, jabatan, pendidikan, diklat) tersimpan sebagai history, bukan overwrite
- Monitoring KGB menghitung otomatis dari data riwayat pangkat terakhir + masa kerja
- Tanggal TMT (Terhitung Mulai Tanggal) sebagai basis semua kalkulasi
- Status aktif pegawai (Aktif, Mutasi Keluar, Pensiun, Meninggal, Diberhentikan)
- SoftDeletes pada semua entity kepegawaian

### Must NOT Have (Guardrails)

- **TIDAK** build integrasi API langsung dengan SIKEP/SIASN/BKN
- **TIDAK** build fitur penggajian/tunjangan
- **TIDAK** build fitur presensi/absensi (sudah di SIKEP)
- **TIDAK** build fitur e-Kinerja/SKP (sudah ada sistem sendiri)
- **TIDAK** build multi-tenant/multi-satker architecture
- **TIDAK** build template generator surat (fase berikutnya)
- **TIDAK** build fitur pengelolaan cuti secara penuh (fase berikutnya)
- **TIDAK** menambahkan package/dependency baru tanpa kebutuhan jelas — leverage shadcn/ui & Laravel built-in
- **TIDAK** over-abstract: No repository pattern, no service layer kecuali business logic kompleks (monitoring calculations)
- **TIDAK** build data import tool (fase berikutnya — user akan seed data via seeder atau manual entry)

---

## Verification Strategy (MANDATORY)

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.
> Acceptance criteria requiring "user manually tests/confirms" are FORBIDDEN.

### Test Decision

- **Infrastructure exists**: YES (Pest 4 with 14 existing test files)
- **Automated tests**: TDD (RED-GREEN-REFACTOR)
- **Framework**: Pest 4 (via `php artisan test --compact`)
- **Each task**: Write failing test FIRST → minimal implementation → refactor → commit

### QA Policy

Every task MUST include agent-executed QA scenarios.
Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Frontend/UI**: Use Playwright (playwright skill) — Navigate, interact, assert DOM, screenshot
- **API/Backend**: Use Bash (curl / php artisan tinker) — Test endpoints, assert responses
- **Database**: Use Bash (php artisan tinker --execute) — Verify models, relationships, seeds

---

## Execution Strategy

### Parallel Execution Waves

> Waves are strictly ordered by the dependency matrix below.
> A task appears in a wave ONLY when ALL its dependencies are complete in prior waves.

```
Wave 1 (Start Immediately — no dependencies):
├── Task 1: PHP Enums & TypeScript types [quick]
├── Task 4: Sidebar navigation restructure [visual-engineering]

Wave 2 (After Wave 1 — depend on T1 only):
├── Task 2: Reference table migrations + models + seeders [unspecified-high]
├── Task 3: RBAC — Role enum, middleware, User model update [unspecified-high]

Wave 3 (After Wave 2 — depends on T1, T2, T3):
├── Task 5: Pegawai model, migration, factory, seeder [deep]

Wave 4 (After Wave 3 — depends on T5, MAX PARALLEL):
├── Task 6: Pegawai Controller + Form Requests + policies [unspecified-high]
├── Task 8: Riwayat Pangkat model + migration + CRUD [unspecified-high]
├── Task 9: Riwayat Jabatan model + migration + CRUD [unspecified-high]
├── Task 10: Riwayat Pendidikan model + migration + CRUD [unspecified-high]
├── Task 11: Riwayat Diklat model + migration + CRUD [unspecified-high]
├── Task 12: Data Keluarga model + migration + CRUD [unspecified-high]
├── Task 13: Penghargaan model + migration + CRUD [unspecified-high]
├── Task 14: Hukuman Disiplin model + migration + CRUD [unspecified-high]
├── Task 15: Dokumen Pegawai model + migration + CRUD [quick]

Wave 5 (After Wave 4 — depends on T4+T5+T6 / T8 / T8+T9 / T8-T15):
├── Task 7: Pegawai List page (React + Inertia) [visual-engineering] — depends: T4, T5, T6
├── Task 16: Monitoring KGB — service + controller + page [deep] — depends: T8
├── Task 17: Monitoring Kenaikan Pangkat — service + controller + page [deep] — depends: T8, T9
├── Task 19: Pegawai Show/Detail page (tabs: semua riwayat) [visual-engineering] — depends: T8-T15
├── Task 22: Search, filter, sort across all list pages [unspecified-high] — depends: T7 (note: T7 in same wave, so T22 starts after T7 completes OR moved to Wave 6 if strict)

Wave 6 (After Wave 5 — depends on T16+T17 / T19):
├── Task 18: Dashboard Statistik Kepegawaian [visual-engineering] — depends: T16, T17
├── Task 20: Pegawai Create/Edit pages (multi-step form) [visual-engineering] — depends: T5, T6, T19
├── Task 21: Self-service — pegawai view own data [unspecified-high] — depends: T3, T5, T6, T16, T17, T19

Wave FINAL (After ALL tasks — independent review, 4 parallel):
├── Task F1: Plan compliance audit (deep — dispatched via subagent_type='oracle')
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA (unspecified-high)
└── Task F4: Scope fidelity check (deep)

Critical Path: T1→T2→T5→T8→T16→T18→FINAL
                           └→T6→T7→T22
                           └→T8-T15→T19→T20,T21
Parallel Speedup: ~60% faster than sequential
Max Concurrent: 9 (Wave 4)
```

> **Note on T22**: T22 depends on T7 which is also in Wave 5. If strict wave-parallelism is enforced
> (all tasks in a wave start simultaneously), move T22 to Wave 6. However, since the executor processes
> tasks within a wave respecting individual dependencies, T22 can start as soon as T7 completes within Wave 5.

### Dependency Matrix

| Task  | Depends On     | Blocks                        | Wave  |
| ----- | -------------- | ----------------------------- | ----- |
| 1     | —              | 2,3,5,6,7,8-15,16-22          | 1     |
| 4     | —              | 7,18,19,20                    | 1     |
| 2     | 1              | 5,8,9,10,11,12,13,14          | 2     |
| 3     | 1              | 5,6,7,21                      | 2     |
| 5     | 1,2,3          | 6,7,8-15,16,17,18,19,20,21,22 | 3     |
| 6     | 5              | 7,19,20,21,22                 | 4     |
| 8     | 2,5            | 16,19                         | 4     |
| 9     | 2,5            | 17,19                         | 4     |
| 10    | 5              | 19                            | 4     |
| 11    | 5              | 19                            | 4     |
| 12    | 5              | 19                            | 4     |
| 13    | 5              | 19                            | 4     |
| 14    | 5              | 19                            | 4     |
| 15    | 5              | 19                            | 4     |
| 7     | 4,5,6          | 22                            | 5     |
| 16    | 8              | 18,21                         | 5     |
| 17    | 8,9            | 18,21                         | 5     |
| 19    | 8-15           | 20,21                         | 5     |
| 22    | 7              | —                             | 5\*   |
| 18    | 16,17          | —                             | 6     |
| 20    | 5,6,19         | —                             | 6     |
| 21    | 3,5,6,16,17,19 | —                             | 6     |
| F1-F4 | 1-22           | —                             | FINAL |

> \*T22 depends on T7, which is also Wave 5. The executor should start T22 after T7 completes within the wave.

### Agent Dispatch Summary

| Wave  | Tasks | Categories                                                                                        |
| ----- | ----- | ------------------------------------------------------------------------------------------------- |
| 1     | 2     | T1→`quick`, T4→`visual-engineering`                                                               |
| 2     | 2     | T2→`unspecified-high`, T3→`unspecified-high`                                                      |
| 3     | 1     | T5→`deep`                                                                                         |
| 4     | 9     | T6→`unspecified-high`, T8-T14→`unspecified-high`, T15→`quick`                                     |
| 5     | 5     | T7→`visual-engineering`, T16→`deep`, T17→`deep`, T19→`visual-engineering`, T22→`unspecified-high` |
| 6     | 3     | T18→`visual-engineering`, T20→`visual-engineering`, T21→`unspecified-high`                        |
| FINAL | 4     | F1→`deep` (subagent_type='oracle'), F2→`unspecified-high`, F3→`unspecified-high`, F4→`deep`       |

---

## TODOs

> Implementation + Test = ONE Task. Never separate.
> EVERY task MUST have: Recommended Agent Profile + Parallelization info + QA Scenarios.
> TDD: Write failing test FIRST → minimal implementation → refactor → commit.

- [x]   1. PHP Enums & TypeScript Types untuk Kepegawaian

    **What to do**:
    - Buat PHP Enums: `StatusPegawai` (Aktif, MutasiKeluar, Pensiun, Meninggal, Diberhentikan), `JenisKelamin` (LakiLaki, Perempuan), `StatusPerkawinan` (BelumKawin, Kawin, CeraiHidup, CeraiMati), `Agama` (Islam, Kristen, Katolik, Hindu, Buddha, Konghucu), `JenisJabatan` (Struktural, Fungsional, FungsionalUmum/Pelaksana), `GolonganDarah` (A, B, AB, O), `StatusKepegawaian` (PNS, PPPK, Honorer), `HubunganKeluarga` (Suami, Istri, Anak, AyahKandung, IbuKandung), `JenjangPendidikan` (SD, SMP, SMA, D1, D2, D3, D4, S1, S2, S3)
    - Setiap enum harus implement method `label(): string` yang mengembalikan nama display Indonesia
    - Buat TypeScript types di `resources/js/types/kepegawaian.ts` yang mirror PHP enums
    - TDD: Tulis test dulu untuk setiap enum (test label(), test values, test dari string)

    **Must NOT do**:
    - TIDAK membuat database tables untuk lookup enums — cukup PHP Enum
    - TIDAK menggunakan integer-backed enums — gunakan string-backed untuk readability di DB

    **Recommended Agent Profile**:
    - **Category**: `quick`
        - Reason: Hanya membuat enum files dan types — straightforward, no complex logic
    - **Skills**: [`pest-testing`]
        - `pest-testing`: Menulis Pest tests untuk enum behavior
    - **Skills Evaluated but Omitted**:
        - `wayfinder-development`: Belum perlu route generation di task ini

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 1 (with Task 4)
    - **Blocks**: Tasks 2, 3, 5, 6, 7, 8-15, 16-22
    - **Blocked By**: None (can start immediately)

    **References**:

    **Pattern References** (existing code to follow):
    - `app/Models/User.php` — Lihat bagaimana existing model di-setup untuk memahami namespace conventions

    **API/Type References**:
    - `resources/js/types/index.ts` — Existing TypeScript type definitions, ikuti pattern yang sama
    - `resources/js/types/auth.ts` — Contoh auth types yang sudah ada

    **Test References**:
    - `tests/Feature/Auth/AuthenticationTest.php` — Contoh test structure di proyek ini
    - `tests/Pest.php` — Pest configuration dan base test class

    **External References**:
    - PHP Enums: `https://www.php.net/manual/en/language.enumerations.backed.php`

    **WHY Each Reference Matters**:
    - `types/index.ts`: Ikuti pattern export dan naming convention TypeScript yang sudah dipakai
    - `User.php`: Pahami namespace `App\Models` dan folder structure
    - `Pest.php`: Pastikan test menggunakan correct base configuration

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file created: `tests/Unit/Enums/StatusPegawaiTest.php` (+ test files for each enum)
    - [ ] `php artisan test --compact --filter=Enum` → PASS (all enum tests)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Enum values match expected constants
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Enums created in app/Enums/
      Steps:
        1. Run: php artisan tinker --execute "echo implode(',', array_column(App\Enums\StatusPegawai::cases(), 'value'));"
        2. Assert output contains: "aktif,mutasi_keluar,pensiun,meninggal,diberhentikan"
        3. Run: php artisan tinker --execute "echo App\Enums\StatusPegawai::Aktif->label();"
        4. Assert output: "Aktif"
        5. Run: php artisan tinker --execute "echo App\Enums\Agama::Islam->label();"
        6. Assert output: "Islam"
      Expected Result: All enum values and labels return correctly
      Failure Indicators: Class not found error, missing cases, wrong labels
      Evidence: .sisyphus/evidence/task-1-enum-values.txt

    Scenario: TypeScript types file exists and exports correct types
      Tool: Bash
      Preconditions: TypeScript types created
      Steps:
        1. Run: cat resources/js/types/kepegawaian.ts
        2. Assert file contains: "export type StatusPegawai ="
        3. Assert file contains: "'aktif' | 'mutasi_keluar' | 'pensiun' | 'meninggal' | 'diberhentikan'"
        4. Run: npx tsc --noEmit resources/js/types/kepegawaian.ts
        5. Assert: exit code 0 (no TypeScript errors)
      Expected Result: Types file compiles without errors and contains all enum mirrors
      Failure Indicators: TypeScript compile error, missing type definitions
      Evidence: .sisyphus/evidence/task-1-typescript-types.txt
    ```

    **Commit**: YES
    - Message: `feat(kepegawaian): add PHP enums and TypeScript types for HR domain`
    - Files: `app/Enums/*.php`, `resources/js/types/kepegawaian.ts`, `tests/Unit/Enums/*.php`
    - Pre-commit: `php artisan test --compact --filter=Enum`

- [x]   2. Reference Table Migrations, Models, Factories, dan Seeders

    **What to do**:
    - Buat migration + model + factory + seeder untuk:
        - `ref_pangkat` (id, kode, nama, golongan, ruang, tingkat — e.g. "Penata Muda", "III/a") — 17 rows dari I/a sampai IV/e
        - `ref_jabatan` (id, kode, nama, jenis_jabatan enum, eselon nullable, kelas_jabatan nullable) — seed dengan jabatan di PA Kelas II (Ketua, Wakil Ketua, Hakim, Panitera, Sekretaris, Panitera Muda, Kasubbag, Jurusita, Panitera Pengganti, dll.)
        - `ref_unit_kerja` (id, kode, nama, parent_id nullable untuk hirarki) — seed dengan unit di PA Penajam (Kepaniteraan, Kesekretariatan, subbagian-subbagiannya)
        - `ref_jenis_diklat` (id, nama, keterangan) — seed: Prajabatan, Kepemimpinan, Teknis, Fungsional
        - `ref_jenis_penghargaan` (id, nama, keterangan) — seed: Satya Lencana 10/20/30 Tahun, dll.
        - `ref_jenis_hukuman_disiplin` (id, nama, tingkat — Ringan/Sedang/Berat, keterangan)
    - Semua model: SoftDeletes, fillable, proper casts
    - Semua seeders harus idempotent (updateOrCreate)
    - TDD: Test setiap model bisa di-create, seed berhasil, relationship (RefJabatan hasMany Pegawai)

    **Must NOT do**:
    - TIDAK buat CRUD pages untuk reference tables — admin seed saja untuk fondasi
    - TIDAK buat lookup tables untuk enum values (JenisKelamin, Agama, dll sudah di PHP Enum)
    - TIDAK buat reference table untuk data yang jarang berubah dan sudah jadi enum

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
        - Reason: Banyak files (6 migrations, 6 models, 6 factories, 6 seeders) dengan relasi dan seed data yang harus akurat
    - **Skills**: [`pest-testing`]
        - `pest-testing`: Menulis Pest tests untuk models dan seeders
    - **Skills Evaluated but Omitted**:
        - `wayfinder-development`: Tidak ada route di task ini

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 2 (with Task 3)
    - **Blocks**: Tasks 5, 8, 9, 10, 11, 12, 13, 14
    - **Blocked By**: Task 1 (needs enums for JenisJabatan in ref_jabatan)

    **References**:

    **Pattern References**:
    - `database/migrations/0001_01_01_000000_create_users_table.php` — Migration pattern yang dipakai proyek ini
    - `app/Models/User.php` — Model pattern (traits, fillable, casts, relationships)
    - `database/factories/UserFactory.php` — Factory pattern
    - `database/seeders/DatabaseSeeder.php` — Seeder entry point

    **API/Type References**:
    - `app/Enums/*.php` (dari Task 1) — Enum yang digunakan di ref_jabatan (JenisJabatan)

    **External References**:
    - Daftar Pangkat/Golongan PNS: I/a (Juru Muda) sampai IV/e (Pembina Utama) — 17 level standar
    - Struktur jabatan PA Kelas II per PERMA 7/2015: Ketua (IV), Wakil Ketua (IV), Hakim (III-IV), Panitera (III-IV), Sekretaris (III), Panitera Muda Permohonan/Gugatan/Hukum (III), Kasubbag Kepegawaian/Perencanaan/Umum (III), Jurusita (II-III), Panitera Pengganti (III)

    **WHY Each Reference Matters**:
    - Migration user table: Ikuti exact Laravel migration style (Schema::create pattern)
    - UserFactory: Ikuti Faker usage pattern yang sudah ada
    - Daftar pangkat: 17 level pangkat PNS adalah standar nasional, harus benar
    - Struktur PA Kelas II: Jabatan yang di-seed harus sesuai PERMA 7/2015

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test files created: `tests/Feature/Models/RefPangkatTest.php`, `tests/Feature/Models/RefJabatanTest.php`, etc.
    - [ ] `php artisan test --compact --filter=Ref` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Database seeds reference data correctly
      Tool: Bash (php artisan commands)
      Preconditions: Fresh database
      Steps:
        1. Run: php artisan migrate:fresh --seed
        2. Run: php artisan tinker --execute "echo App\Models\RefPangkat::count();"
        3. Assert output: "17"
        4. Run: php artisan tinker --execute "echo App\Models\RefPangkat::where('golongan','III')->where('ruang','a')->first()->nama;"
        5. Assert output: "Penata Muda"
        6. Run: php artisan tinker --execute "echo App\Models\RefJabatan::count();"
        7. Assert output: >= 10 (minimal 10 jabatan PA)
        8. Run: php artisan tinker --execute "echo App\Models\RefUnitKerja::count();"
        9. Assert output: >= 5 (minimal: Kepaniteraan, Kesekretariatan, + subbagian)
      Expected Result: All reference tables seeded with correct data
      Failure Indicators: Wrong count, wrong data, migration error
      Evidence: .sisyphus/evidence/task-2-seed-data.txt

    Scenario: Models have correct relationships
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Database seeded
      Steps:
        1. Run: php artisan tinker --execute "echo App\Models\RefUnitKerja::whereNull('parent_id')->count();"
        2. Assert output: >= 2 (top-level units: Kepaniteraan, Kesekretariatan)
        3. Run: php artisan tinker --execute "echo App\Models\RefUnitKerja::whereNotNull('parent_id')->count();"
        4. Assert output: >= 3 (child units: subbagian-subbagian)
      Expected Result: Hierarchical unit kerja relationships work
      Failure Indicators: Missing parent_id, broken relationship
      Evidence: .sisyphus/evidence/task-2-relationships.txt
    ```

    **Commit**: YES
    - Message: `feat(kepegawaian): add reference tables with seed data for PA Penajam`
    - Files: `app/Models/Ref*.php`, `database/migrations/*ref*`, `database/seeders/*`, `database/factories/Ref*`, `tests/Feature/Models/Ref*`
    - Pre-commit: `php artisan test --compact --filter=Ref`

- [x]   4. Sidebar Navigation Restructure untuk Menu Kepegawaian

    **What to do**:
    - **PERTAMA**: Install shadcn/ui components yang belum ada tapi dibutuhkan di tasks selanjutnya:
        ```bash
        npx shadcn@latest add table tabs progress pagination
        ```
        Ini akan membuat: `resources/js/components/ui/table.tsx`, `tabs.tsx`, `progress.tsx`, `pagination.tsx`
        Verifikasi: keempat file berhasil dibuat sebelum lanjut.
    - Update sidebar navigation structure untuk menampilkan menu kepegawaian terstruktur:
        ```
        Dashboard
        Kepegawaian
        ├── Data Pegawai
        ├── [placeholder menu items untuk future]
        Monitoring
        ├── KGB
        ├── Kenaikan Pangkat
        Settings
        ```
    - Update navigation types di TypeScript untuk mendukung grouped/nested menu
    - **CATATAN**: Semua menu items ditampilkan untuk semua user di task ini. Role-based menu visibility (hide /kepegawaian/\* dari viewer) akan diimplementasikan oleh Task 21 (Self-Service) setelah RBAC (Task 3) tersedia.
    - Update breadcrumb component jika ada untuk mendukung nested paths
    - TDD: `npm run build` sukses (TypeScript compiles). **TIDAK** test role-based rendering karena RBAC belum ada di Wave 1.

    **Must NOT do**:
    - TIDAK membuat semua menu active/linked — beberapa akan jadi placeholder dulu
    - TIDAK mengubah layout structure secara fundamental — hanya extend sidebar content
    - TIDAK menambahkan icon library baru — gunakan yang sudah ada (lucide-react dari shadcn/ui)

    **Recommended Agent Profile**:
    - **Category**: `visual-engineering`
        - Reason: Modifikasi UI sidebar component dengan React + shadcn/ui
    - **Skills**: [`wayfinder-development`]
        - `wayfinder-development`: Navigation menggunakan route names dari Laravel
    - **Skills Evaluated but Omitted**:
        - `pest-testing`: Tidak ada PHP test di task ini (frontend-only)

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 1 (with Task 1)
    - **Blocks**: Tasks 7, 18, 19, 20
    - **Blocked By**: None (can start immediately)

    **References**:

    **Pattern References**:
    - `resources/js/components/app-sidebar.tsx` — Existing sidebar component yang akan di-modify
    - `resources/js/components/nav-main.tsx` — Navigation items component
    - `resources/js/layouts/app-layout.tsx` — App layout yang menggunakan sidebar
    - `resources/js/layouts/app/app-sidebar-layout.tsx` — Sidebar layout wrapper

    **API/Type References**:
    - `resources/js/types/navigation.ts` — Navigation type definitions
    - `resources/js/types/index.ts` — Shared types including User (perlu check role field)

    **External References**:
    - shadcn/ui Sidebar component: https://ui.shadcn.com/docs/components/sidebar

    **WHY Each Reference Matters**:
    - `app-sidebar.tsx`: File UTAMA yang akan di-modify — harus baca dulu sebelum edit
    - `nav-main.tsx`: Pattern rendering nav items yang sudah ada
    - `navigation.ts`: Type structure untuk menu items — menentukan bagaimana menu didefinisikan

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `npm run build` → success (TypeScript compiles)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Sidebar shows kepegawaian menu groups
      Tool: Playwright (playwright skill)
      Preconditions: User logged in as admin
      Steps:
        1. Navigate to dashboard URL
        2. Wait for sidebar to render (selector: `[data-testid="app-sidebar"]` or `.sidebar`)
        3. Assert sidebar contains text "Kepegawaian"
        4. Assert sidebar contains text "Data Pegawai"
        5. Assert sidebar contains text "Monitoring"
        6. Assert sidebar contains text "KGB"
        7. Assert sidebar contains text "Kenaikan Pangkat"
        8. Take screenshot
      Expected Result: All menu items visible in sidebar with correct grouping
      Failure Indicators: Menu items missing, incorrect nesting, layout broken
      Evidence: .sisyphus/evidence/task-4-sidebar-menu.png

    Scenario: Navigation links render with correct href attributes
      Tool: Playwright (playwright skill)
      Preconditions: User logged in, app running
      Steps:
        1. Navigate to dashboard
        2. Locate sidebar link with text "Data Pegawai"
        3. Assert link element has href attribute containing "/kepegawaian/pegawai"
        4. Locate sidebar link with text "KGB"
        5. Assert link element has href attribute containing "/kepegawaian/monitoring/kgb"
        6. Take screenshot showing expanded sidebar menu
      Expected Result: Menu links exist in DOM with correct href values (routes may 404 since pages aren't built yet — that's OK, we only verify sidebar renders links correctly)
      Failure Indicators: Missing href, broken link elements, incorrect URL paths
      Evidence: .sisyphus/evidence/task-4-navigation-hrefs.png
    ```

    **Commit**: YES
    - Message: `feat(ui): restructure sidebar navigation for kepegawaian modules`
    - Files: `resources/js/components/app-sidebar*`, `resources/js/components/nav-main*`, `resources/js/types/navigation.ts`
    - Pre-commit: `npm run build`

- [x]   3. RBAC — Role Enum, Middleware, dan User Model Update

        **What to do**:
    - Buat `App\Enums\Role` enum: Admin, Operator, Viewer (string-backed)
    - Tambahkan `role` column (string, default 'viewer') ke users table via migration
    - Update `User` model: add `role` cast ke Role enum, add `isAdmin()`, `isOperator()`, `isViewer()` helper methods
    - Buat middleware `EnsureRole` yang check role dari user — usage: `->middleware('role:admin,operator')`
    - Register middleware di `bootstrap/app.php`
    - Update existing User factory untuk support role
    - TDD: Test role assignment, middleware blocking, role helpers

    **Must NOT do**:
    - TIDAK menggunakan Spatie Laravel Permission atau package RBAC lain — cukup simple enum-based
    - TIDAK membuat permissions table terpisah — 3 roles sudah cukup untuk MVP
    - TIDAK mengubah existing auth flow (login, register tetap sama)

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
        - Reason: Melibatkan middleware registration, model update, dan auth testing yang perlu hati-hati
    - **Skills**: [`pest-testing`, `fortify-development`]
        - `pest-testing`: Test RBAC behavior
        - `fortify-development`: Memahami existing auth setup untuk tidak break
    - **Skills Evaluated but Omitted**:
        - `wayfinder-development`: Middleware bukan route generation

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 2 (with Task 2)
    - **Blocks**: Tasks 5, 6, 7, 21
    - **Blocked By**: Task 1 (enum pattern consistency)

    **References**:

    **Pattern References**:
    - `app/Http/Middleware/HandleInertiaRequests.php` — Existing middleware pattern
    - `app/Http/Middleware/HandleAppearance.php` — Another middleware example
    - `bootstrap/app.php` — Where to register middleware aliases
    - `app/Models/User.php` — Model to extend with role

    **API/Type References**:
    - `app/Enums/*.php` (dari Task 1) — Follow same enum pattern for Role
    - `resources/js/types/index.ts` — User type needs role field added

    **Test References**:
    - `tests/Feature/Auth/AuthenticationTest.php` — Auth test patterns
    - `tests/Feature/DashboardTest.php` — Test protected routes pattern

    **External References**:
    - Laravel Middleware docs (via search-docs)
    - Laravel Enum Casting docs

    **WHY Each Reference Matters**:
    - `bootstrap/app.php`: KRITIS — di Laravel 12, middleware didaftarkan di sini, bukan Kernel.php
    - `HandleInertiaRequests.php`: Pattern response middleware yang sudah ada
    - `User.php`: Model yang akan di-modify, harus tahu state current-nya
    - Auth tests: Memastikan existing auth tidak break setelah role ditambahkan

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Auth/RoleMiddlewareTest.php`
    - [ ] `php artisan test --compact --filter=Role` → PASS
    - [ ] `php artisan test --compact --filter=Auth` → PASS (existing auth tests tidak break)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Role middleware blocks unauthorized access
      Tool: Bash (php artisan test or curl)
      Preconditions: User with role 'viewer' exists
      Steps:
        1. Run test yang membuat user dengan role 'viewer'
        2. Attempt access ke route yang require 'admin' role
        3. Assert: response status 403 Forbidden
        4. Change user role ke 'admin'
        5. Attempt access ke route yang sama
        6. Assert: response status 200 OK
      Expected Result: Middleware correctly blocks/allows based on role
      Failure Indicators: 403 not returned, or admin blocked
      Evidence: .sisyphus/evidence/task-3-role-middleware.txt

    Scenario: Existing auth tests still pass
      Tool: Bash
      Preconditions: Role migration applied
      Steps:
        1. Run: php artisan test --compact --filter=Authentication
        2. Assert: ALL PASS
        3. Run: php artisan test --compact --filter=Registration
        4. Assert: ALL PASS
      Expected Result: Zero regressions in auth functionality
      Failure Indicators: Any test failure in auth suite
      Evidence: .sisyphus/evidence/task-3-auth-regression.txt
    ```

    **Commit**: YES
    - Message: `feat(auth): add RBAC with role enum and middleware`
    - Files: `app/Enums/Role.php`, `app/Http/Middleware/EnsureRole.php`, `app/Models/User.php`, `bootstrap/app.php`, `database/migrations/*add_role*`, `database/factories/UserFactory.php`, `tests/Feature/Auth/RoleMiddlewareTest.php`
    - Pre-commit: `php artisan test --compact --filter=Role && php artisan test --compact --filter=Auth`

- [x]   5. Pegawai Model, Migration, Factory, dan Seeder

    **What to do**:
    - Buat migration `create_pegawai_table` dengan kolom:
        - `id` (ulid/uuid primary key)
        - `nip` (string, nullable, unique — nullable untuk honorer)
        - `nip_lama` (string, nullable — NIP format lama 9 digit)
        - `nama_lengkap` (string)
        - `tempat_lahir` (string)
        - `tanggal_lahir` (date)
        - `jenis_kelamin` (string, enum cast: JenisKelamin)
        - `agama` (string, enum cast: Agama)
        - `status_perkawinan` (string, enum cast: StatusPerkawinan)
        - `golongan_darah` (string nullable, enum cast: GolonganDarah)
        - `alamat` (text, nullable)
        - `no_telepon` (string, nullable)
        - `email` (string, nullable)
        - `status_kepegawaian` (string, enum cast: StatusKepegawaian — PNS/PPPK/Honorer)
        - `status_pegawai` (string, enum cast: StatusPegawai — Aktif/MutasiKeluar/Pensiun/dll)
        - `tmt_cpns` (date, nullable — TMT CPNS)
        - `tmt_pns` (date, nullable — TMT PNS)
        - `pendidikan_terakhir` (string, nullable — jenjang pendidikan terakhir saat ini)
        - `tanggal_masuk` (date — tanggal mulai bekerja di instansi)
        - `tanggal_pensiun_bup` (date, nullable — Batas Usia Pensiun calculated)
        - `ref_pangkat_id` (FK → ref_pangkat, nullable — pangkat saat ini)
        - `ref_jabatan_id` (FK → ref_jabatan, nullable — jabatan saat ini)
        - `ref_unit_kerja_id` (FK → ref_unit_kerja, nullable — unit kerja saat ini)
        - `no_karpeg` (string, nullable — Nomor Kartu Pegawai)
        - `no_karis_karsu` (string, nullable — Nomor Karis/Karsu)
        - `npwp` (string, nullable)
        - `no_bpjs_kesehatan` (string, nullable)
        - `no_bpjs_ketenagakerjaan` (string, nullable)
        - `no_taspen` (string, nullable)
        - `foto` (string, nullable — path to foto)
        - `keterangan` (text, nullable)
        - `timestamps`, `soft_deletes`
    - Tambahkan `pegawai_id` (FK nullable) ke `users` table via migration — link User ↔ Pegawai
    - Buat Pegawai model dengan:
        - Relationships: `belongsTo` RefPangkat, RefJabatan, RefUnitKerja; `hasOne` User; `hasMany` RiwayatPangkat, RiwayatJabatan, etc.
        - Casts: semua enum fields
        - Scopes: `scopeAktif()`, `scopeByUnitKerja($id)`, `scopeByGolongan($golongan)`
        - Accessor: `nama_pangkat_lengkap` (e.g. "Penata Muda - III/a")
    - Factory: generate realistic fake data menggunakan Faker Indonesia
    - Seeder: Import semua data dari `docs/data_pegawai.json` (29 records) lalu gunakan `PegawaiFactory` untuk generate records tambahan sehingga total ≥40 pegawai. Seeder HARUS menghasilkan ≥40 records secara konsisten.
    - TDD: Test model creation, relationships, scopes, casts, enum behavior

    **Must NOT do**:
    - TIDAK menyimpan riwayat pangkat/jabatan di pegawai table — itu di table terpisah
    - TIDAK auto-create User saat create Pegawai — linkage manual
    - TIDAK menambahkan foto upload logic — hanya kolom path dulu

    **Recommended Agent Profile**:
    - **Category**: `deep`
        - Reason: Core entity dengan banyak relationships, scopes, enum casts — butuh ketelitian tinggi
    - **Skills**: [`pest-testing`]
        - `pest-testing`: TDD untuk model + relationships + scopes
    - **Skills Evaluated but Omitted**:
        - `wayfinder-development`: Belum ada route/controller
        - `fortify-development`: Tidak ada auth logic

    **Parallelization**:
    - **Can Run In Parallel**: NO
    - **Parallel Group**: Wave 3 (sequential — sole task in wave)
    - **Blocks**: Tasks 6, 7, 8-15, 16, 17, 18, 19, 20, 21, 22
    - **Blocked By**: Tasks 1 (enums), 2 (reference tables), 3 (RBAC — User model update)

    **References**:

    **Pattern References**:
    - `app/Models/User.php` — Model traits, fillable, casts, factory usage
    - `database/migrations/0001_01_01_000000_create_users_table.php` — Migration pattern
    - `database/factories/UserFactory.php` — Factory pattern dan Faker usage
    - `app/Models/Ref*.php` (dari Task 2) — Reference model patterns

    **API/Type References**:
    - `app/Enums/*.php` (dari Task 1) — All enum types for casting
    - `app/Models/Ref*.php` (dari Task 2) — FK targets for relationships

    **External References**:
    - NIP format: 18 digit (TTTTMMDD TTTTMM X XXXX) — validasi format jika perlu
    - BUP Hakim: 65 tahun, BUP PNS umum: 58 tahun (per UU ASN)

    **WHY Each Reference Matters**:
    - `User.php`: Pattern utama untuk model — harus ikuti style yang sama
    - Ref models: FK targets yang harus sudah ada sebelum Pegawai di-create
    - NIP format: Validasi unik Indonesia — 18 digit bukan sembarang string
    - BUP: tanggal_pensiun_bup dihitung dari tanggal_lahir + usia BUP sesuai jabatan

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Models/PegawaiTest.php`
    - [ ] `php artisan test --compact --filter=Pegawai` → PASS
    - [ ] Tests cover: creation, relationships, scopes, enum casts, nullable fields

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Pegawai seeder imports data_pegawai.json + factory fills to ≥40
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Database migrated with ref tables seeded (Task 1, Task 2)
      Steps:
        1. Run: php artisan migrate:fresh --seed
        2. Run: php artisan tinker --execute "echo App\Models\Pegawai::count();"
        3. Assert output: ≥40
        4. Run: php artisan tinker --execute "
           \$p = App\Models\Pegawai::first();
           echo \$p->nama_lengkap . '|' . \$p->nip . '|' . \$p->status_pegawai->value;
           "
        5. Assert output contains: nama|nip|aktif (data from data_pegawai.json)
        6. Run: php artisan tinker --execute "
           \$factory = App\Models\Pegawai::factory()->make();
           echo \$factory->nama_lengkap . '|' . get_class(\$factory->status_pegawai);
           "
        7. Assert factory produces valid Pegawai with enum casts
      Expected Result: Seeder imports 29 records from JSON + factory-generated to reach ≥40; all casts work
      Failure Indicators: Count < 40, JSON import fails, enum cast error, factory definition error
      Evidence: .sisyphus/evidence/task-5-pegawai-create.txt

    Scenario: Pegawai relationships work correctly
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Pegawai created with ref_pangkat_id
      Steps:
        1. Run: php artisan tinker --execute "
           \$p = App\Models\Pegawai::with('pangkat','jabatan','unitKerja')->first();
           echo \$p->pangkat?->nama . '|' . \$p->jabatan?->nama . '|' . \$p->unitKerja?->nama;
           "
        2. Assert output contains valid names (not null for all three)
        3. Run: php artisan tinker --execute "echo App\Models\Pegawai::aktif()->count();"
        4. Assert output: count of active pegawai
      Expected Result: All belongsTo relationships and scopes function
      Failure Indicators: Null relationship, query error, scope not found
      Evidence: .sisyphus/evidence/task-5-pegawai-relations.txt

    Scenario: User-Pegawai linkage works
      Tool: Bash (php artisan tinker --execute)
      Preconditions: User and Pegawai exist
      Steps:
        1. Run: php artisan tinker --execute "
           \$user = App\Models\User::factory()->create();
           \$pegawai = App\Models\Pegawai::factory()->create();
           \$user->update(['pegawai_id' => \$pegawai->id]);
           echo \$user->fresh()->pegawai->nama_lengkap;
           "
        2. Assert output: nama_lengkap pegawai
      Expected Result: User->pegawai relationship returns linked Pegawai
      Failure Indicators: Relationship not defined, FK not added to users table
      Evidence: .sisyphus/evidence/task-5-user-pegawai-link.txt
    ```

    **Commit**: YES (groups with T6, T7)
    - Message: `feat(kepegawaian): add Pegawai model with relationships, factory, and seeder`
    - Files: `app/Models/Pegawai.php`, `database/migrations/*pegawai*`, `database/factories/PegawaiFactory.php`, `database/seeders/PegawaiSeeder.php`, `tests/Feature/Models/PegawaiTest.php`
    - Pre-commit: `php artisan test --compact --filter=Pegawai`

- [x]   6. Pegawai Controller, Form Requests, Policy, dan Routes

    **What to do**:
    - Buat `PegawaiController` (resource controller) dengan methods: index, create, store, show, edit, update, destroy
    - Buat Form Requests: `StorePegawaiRequest`, `UpdatePegawaiRequest` — validasi NIP unique, enum values, required fields
    - Buat `PegawaiPolicy` — admin: full CRUD; operator: full CRUD; viewer: DENIED (viewers cannot access /kepegawaian/\* routes — they use self-service instead, see Task 21)
    - Register routes di `routes/web.php`: `Route::resource('kepegawaian/pegawai', PegawaiController::class)->middleware(['auth', 'verified'])`
    - Controller index: paginated list dengan eager loading (pangkat, jabatan, unitKerja)
    - Controller show: load pegawai with all relationships
    - Register policy di `AuthServiceProvider` atau `AppServiceProvider`
    - Jalankan `php artisan wayfinder:generate` setelah routes dibuat
    - TDD: Test setiap endpoint (index returns paginated, store creates, show returns, update modifies, destroy soft-deletes)

    **Must NOT do**:
    - TIDAK membuat API routes — hanya web routes via Inertia
    - TIDAK membuat export/import functionality
    - TIDAK menambahkan complex filtering di controller — simple paginate dulu (filter di Task 22)

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
        - Reason: Standard resource controller tapi perlu policy, form requests, dan route registration yang benar
    - **Skills**: [`pest-testing`, `wayfinder-development`]
        - `pest-testing`: TDD untuk setiap controller action
        - `wayfinder-development`: Generate TypeScript route functions setelah routes di-define
    - **Skills Evaluated but Omitted**:
        - `fortify-development`: Tidak ada auth modification

    **Parallelization**:
    - **Can Run In Parallel**: NO (depends on Task 5)
    - **Parallel Group**: Wave 4 (with Tasks 8-15)
    - **Blocks**: Tasks 7, 19, 20, 21, 22
    - **Blocked By**: Task 5 (Pegawai model must exist)

    **References**:

    **Pattern References**:
    - `app/Http/Controllers/Settings/ProfileController.php` — Existing controller pattern
    - `app/Http/Controllers/Settings/SecurityController.php` — Another controller example (handles password & 2FA)
    - `routes/settings.php` — Route group pattern
    - `routes/web.php` — Main routes file

    **API/Type References**:
    - `app/Models/Pegawai.php` (dari Task 5) — Model yang di-CRUD
    - `app/Enums/*.php` (dari Task 1) — Validasi enum values di Form Request
    - `app/Http/Middleware/EnsureRole.php` (dari Task 3) — Role middleware

    **Test References**:
    - `tests/Feature/Settings/ProfileUpdateTest.php` — Controller test pattern di proyek ini
    - `tests/Feature/DashboardTest.php` — Authenticated route test pattern

    **WHY Each Reference Matters**:
    - `ProfileController.php`: Pattern exact untuk Inertia controller (return Inertia::render)
    - `settings.php`: Bagaimana route group di-register di proyek ini
    - `ProfileUpdateTest.php`: Pattern testing controller — actingAs, assert status, assert redirect

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Kepegawaian/PegawaiControllerTest.php`
    - [ ] `php artisan test --compact --filter=PegawaiController` → PASS
    - [ ] Tests: index 200, store creates record, show 200, update modifies, destroy soft-deletes, unauthorized returns 403

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Pegawai CRUD routes are registered
      Tool: Bash
      Preconditions: Routes registered
      Steps:
        1. Run: php artisan route:list --path=kepegawaian/pegawai
        2. Assert output contains: GET kepegawaian/pegawai (index)
        3. Assert output contains: POST kepegawaian/pegawai (store)
        4. Assert output contains: GET kepegawaian/pegawai/{pegawai} (show)
        5. Assert output contains: PUT/PATCH kepegawaian/pegawai/{pegawai} (update)
        6. Assert output contains: DELETE kepegawaian/pegawai/{pegawai} (destroy)
      Expected Result: All 7 resource routes registered
      Failure Indicators: Missing routes, wrong middleware
      Evidence: .sisyphus/evidence/task-6-routes.txt

    Scenario: Policy enforces role-based access
      Tool: Bash (php artisan test)
      Preconditions: PegawaiPolicy registered, EnsureRole middleware on /kepegawaian/* routes
      Steps:
        1. Test: viewer user cannot access GET /kepegawaian/pegawai (index) → 403
        2. Test: viewer user cannot access POST /kepegawaian/pegawai (store) → 403
        3. Test: viewer user cannot access GET /kepegawaian/pegawai/{id} (show) → 403
        4. Test: operator user can access GET /kepegawaian/pegawai (index) → 200
        5. Test: operator user can access POST /kepegawaian/pegawai (store) → 302
        6. Test: admin user can access all /kepegawaian/* endpoints → 200/302
      Expected Result: Viewer role is fully blocked from /kepegawaian/* routes (viewers use self-service only, Task 21)
      Failure Indicators: Viewer getting 200, wrong status code, policy not registered
      Evidence: .sisyphus/evidence/task-6-policy.txt
    ```

    **Commit**: YES (groups with T5, T7)
    - Message: `feat(kepegawaian): add Pegawai controller, form requests, policy, and routes`
    - Files: `app/Http/Controllers/Kepegawaian/PegawaiController.php`, `app/Http/Requests/Kepegawaian/*`, `app/Policies/PegawaiPolicy.php`, `routes/web.php`, `tests/Feature/Kepegawaian/PegawaiControllerTest.php`
    - Pre-commit: `php artisan test --compact --filter=PegawaiController`

- [x]   7. Pegawai List Page (React + Inertia)

    **What to do**:
    - Buat halaman list pegawai: `resources/js/pages/kepegawaian/pegawai/index.tsx`
    - Tampilkan table dengan kolom: NIP, Nama, Pangkat/Gol, Jabatan, Unit Kerja, Status, Aksi
    - Gunakan shadcn/ui `Table` component
    - Pagination menggunakan Inertia paginator (links dari Laravel)
    - Badge untuk status pegawai (warna berbeda: Aktif=hijau, Pensiun=abu, MutasiKeluar=kuning, dll)
    - Action buttons: View, Edit (based on role — gunakan shared props)
    - Empty state: tampilkan pesan "Belum ada data pegawai" dengan button "Tambah Pegawai"
    - TypeScript types: extend PageProps untuk Pegawai data
    - Buat Wayfinder route import untuk navigation
    - TDD: Controller test sudah di Task 6, di sini pastikan `npm run build` sukses

    **Must NOT do**:
    - TIDAK membuat server-side filtering/sorting (itu di Task 22)
    - TIDAK membuat inline editing — hanya list view
    - TIDAK menambahkan component library baru — gunakan shadcn/ui yang ada

    **Recommended Agent Profile**:
    - **Category**: `visual-engineering`
        - Reason: Halaman React dengan table, pagination, badges — fokus UI/UX
    - **Skills**: [`wayfinder-development`]
        - `wayfinder-development`: Import route functions dari Wayfinder untuk navigasi
    - **Skills Evaluated but Omitted**:
        - `pest-testing`: PHP test sudah di Task 6, ini frontend-only

    **Parallelization**:
    - **Can Run In Parallel**: NO (depends on Task 4 sidebar + Task 6 controller)
    - **Parallel Group**: Wave 5 (with Tasks 16, 17, 19, 22)
    - **Blocks**: Task 22 (search/filter)
    - **Blocked By**: Tasks 4 (sidebar navigation), 5 (Pegawai model), 6 (controller + routes)

    **References**:

    **Pattern References**:
    - `resources/js/pages/dashboard.tsx` — Existing page component pattern
    - `resources/js/pages/settings/profile.tsx` — Settings page with form pattern
    - `resources/js/layouts/app-layout.tsx` — Layout wrapper usage
    - `resources/js/components/ui/table.tsx` — shadcn/ui Table component (di-install oleh Task 4)
    - `resources/js/components/ui/badge.tsx` — shadcn/ui Badge for status display

    **API/Type References**:
    - `resources/js/types/kepegawaian.ts` (dari Task 1) — TypeScript types for enums
    - `resources/js/types/index.ts` — SharedProps, PageProps pattern
    - Wayfinder generated routes: `resources/js/actions/` or `resources/js/routes/`

    **External References**:
    - Inertia.js v2 pages: https://inertiajs.com/pages
    - shadcn/ui Table: https://ui.shadcn.com/docs/components/table

    **WHY Each Reference Matters**:
    - `dashboard.tsx`: Pattern EXACT untuk page component — layout, head, props
    - `table.tsx`: shadcn/ui table structure yang harus diikuti
    - `badge.tsx`: Cara menampilkan status badges yang konsisten dengan design system
    - Inertia docs: Pastikan pagination links di-render dengan benar

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `npm run build` → success (0 errors)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Pegawai list page renders with data
      Tool: Playwright (playwright skill)
      Preconditions: Database seeded with sample pegawai, user logged in as admin
      Steps:
        1. Navigate to /kepegawaian/pegawai
        2. Wait for page load (selector: `table` or `[data-testid="pegawai-table"]`)
        3. Assert table headers contain: "NIP", "Nama", "Pangkat", "Jabatan", "Unit Kerja", "Status"
        4. Assert table has >= 1 row of data
        5. Assert status badges are visible (look for Badge component)
        6. Take screenshot
      Expected Result: Table renders with pegawai data, proper columns, status badges
      Failure Indicators: Empty table, missing columns, no badge colors, JS error
      Evidence: .sisyphus/evidence/task-7-pegawai-list.png

    Scenario: Empty state shows when no pegawai data
      Tool: Playwright (playwright skill)
      Preconditions: Database migrated but no pegawai seeded, user logged in
      Steps:
        1. Navigate to /kepegawaian/pegawai
        2. Assert page contains text "Belum ada data pegawai" or similar empty message
        3. Assert "Tambah Pegawai" button/link is visible
        4. Take screenshot
      Expected Result: Friendly empty state with call-to-action
      Failure Indicators: Blank page, broken layout, no empty state message
      Evidence: .sisyphus/evidence/task-7-pegawai-empty-state.png
    ```

    **Commit**: YES (groups with T5, T6)
    - Message: `feat(kepegawaian): add pegawai list page with table, pagination, and status badges`
    - Files: `resources/js/pages/kepegawaian/pegawai/index.tsx`, `resources/js/types/kepegawaian.ts` (update)
    - Pre-commit: `npm run build`

- [x]   8. Riwayat Pangkat — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_riwayat_pangkat_table`:
        - `id`, `pegawai_id` (FK), `ref_pangkat_id` (FK), `no_sk` (string), `tanggal_sk` (date), `tmt` (date — Terhitung Mulai Tanggal), `pejabat_penetap` (string nullable), `masa_kerja_tahun` (integer), `masa_kerja_bulan` (integer), `gaji_pokok` (decimal nullable), `is_aktif` (boolean default false — hanya 1 yang aktif per pegawai), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Buat model `RiwayatPangkat` dengan: belongsTo Pegawai, belongsTo RefPangkat, SoftDeletes, scope aktif
    - Buat `RiwayatPangkatController` (nested under Pegawai): index (list per pegawai), store, update, destroy
    - Buat Form Request dengan validasi
    - Buat sub-page UI: `resources/js/pages/kepegawaian/pegawai/riwayat-pangkat.tsx` — tabel riwayat + form dialog (shadcn Dialog) untuk tambah/edit
    - Logic: saat riwayat baru ditambahkan dan is_aktif=true, set semua riwayat lain is_aktif=false, update Pegawai.ref_pangkat_id
    - Factory untuk testing
    - TDD: Test CRUD, test pangkat aktif sync logic

    **Must NOT do**:
    - TIDAK menghitung KGB di sini — itu di Task 16 (Monitoring)
    - TIDAK membuat standalone page — ini sub-page dari Pegawai detail

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
        - Reason: Model + controller + nested route + UI sub-page + sync logic
    - **Skills**: [`pest-testing`, `wayfinder-development`]
        - `pest-testing`: TDD untuk model dan controller
        - `wayfinder-development`: Nested route generation

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4 (with Tasks 6, 9-15)
    - **Blocks**: Tasks 16, 19
    - **Blocked By**: Tasks 2 (ref_pangkat), 5 (Pegawai model)

    **References**:

    **Pattern References**:
    - `app/Http/Controllers/Kepegawaian/PegawaiController.php` (dari Task 6) — Controller pattern
    - `app/Models/Pegawai.php` (dari Task 5) — Parent model dengan hasMany
    - `resources/js/pages/kepegawaian/pegawai/index.tsx` (dari Task 7) — Page pattern

    **API/Type References**:
    - `app/Models/RefPangkat.php` (dari Task 2) — FK target
    - `resources/js/components/ui/dialog.tsx` — shadcn Dialog untuk form modal

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Kepegawaian/RiwayatPangkatTest.php`
    - [ ] `php artisan test --compact --filter=RiwayatPangkat` → PASS
    - [ ] Tests: CRUD + pangkat aktif sync (adding new aktif unsets old aktif)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Adding riwayat pangkat syncs active pangkat
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Pegawai exists with one riwayat pangkat (is_aktif=true, pangkat III/a)
      Steps:
        1. Create new RiwayatPangkat for same pegawai with is_aktif=true, pangkat III/b
        2. Assert old riwayat is_aktif = false
        3. Assert new riwayat is_aktif = true
        4. Assert Pegawai.ref_pangkat_id = ref_pangkat for III/b
      Expected Result: Only one aktif riwayat per pegawai, Pegawai.ref_pangkat_id synced
      Failure Indicators: Multiple aktif records, Pegawai.ref_pangkat_id not updated
      Evidence: .sisyphus/evidence/task-8-pangkat-sync.txt

    Scenario: Riwayat pangkat CRUD API works end-to-end
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Pegawai and RefPangkat seeded
      Steps:
        1. Create RiwayatPangkat via controller store route (use test helper or tinker)
        2. Assert record created in database with correct pegawai_id, ref_pangkat_id, tmt, no_sk
        3. Update the riwayat (change keterangan)
        4. Assert updated value persisted
        5. Soft-delete the riwayat
        6. Assert record is soft-deleted (trashed) but still in DB
      Expected Result: Full CRUD lifecycle works, soft-delete preserves data
      Failure Indicators: Validation errors, missing FK, hard-delete instead of soft-delete
      Evidence: .sisyphus/evidence/task-8-riwayat-crud.txt

    Scenario: Riwayat pangkat sub-page component renders standalone
      Tool: Bash (npm run build)
      Preconditions: Sub-page component file created at resources/js/pages/kepegawaian/pegawai/riwayat-pangkat.tsx
      Steps:
        1. Run `npm run build`
        2. Assert build succeeds (exit code 0) — confirms TypeScript compiles and component is valid
        3. Run `php artisan route:list --path=kepegawaian` to confirm riwayat-pangkat routes registered
        4. Assert routes for index, store, update, destroy exist under pegawai/{pegawai}/riwayat-pangkat
      Expected Result: Component compiles, routes registered correctly
      Failure Indicators: TypeScript errors, missing routes, incorrect route patterns
      Evidence: .sisyphus/evidence/task-8-riwayat-pangkat-build.txt
    ```

    **Commit**: YES (groups with T9-T15)
    - Message: `feat(kepegawaian): add riwayat pangkat with active sync logic`
    - Files: `app/Models/RiwayatPangkat.php`, `app/Http/Controllers/Kepegawaian/RiwayatPangkatController.php`, `database/migrations/*riwayat_pangkat*`, `resources/js/pages/kepegawaian/pegawai/riwayat-pangkat.tsx`
    - Pre-commit: `php artisan test --compact --filter=RiwayatPangkat`

- [x]   9. Riwayat Jabatan — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_riwayat_jabatan_table`:
        - `id`, `pegawai_id` (FK), `ref_jabatan_id` (FK), `ref_unit_kerja_id` (FK nullable), `no_sk` (string), `tanggal_sk` (date), `tmt` (date), `pejabat_penetap` (string nullable), `is_aktif` (boolean default false), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model `RiwayatJabatan`: belongsTo Pegawai, RefJabatan, RefUnitKerja; SoftDeletes
    - Controller, Form Request, sub-page UI (same pattern as Task 8)
    - Sync logic: saat is_aktif=true, update Pegawai.ref_jabatan_id dan Pegawai.ref_unit_kerja_id
    - Factory, TDD

    **Must NOT do**:
    - TIDAK duplikasi logic yang sama persis dengan Task 8 — gunakan trait/concern jika pattern identical

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4 (with Tasks 6, 8, 10-15)
    - **Blocks**: Tasks 17, 19
    - **Blocked By**: Tasks 2 (ref_jabatan, ref_unit_kerja), 5 (Pegawai)

    **References**:

    **Pattern References**:
    - `app/Models/RiwayatPangkat.php` (dari Task 8) — Exact same pattern untuk riwayat model
    - `app/Http/Controllers/Kepegawaian/RiwayatPangkatController.php` — Controller pattern

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/RiwayatJabatanTest.php`
    - [ ] `php artisan test --compact --filter=RiwayatJabatan` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Riwayat jabatan sync logic and CRUD verified via tests
      Tool: Bash
      Preconditions: Migrations applied, ref tables seeded, Pegawai exists
      Steps:
        1. Run: php artisan test --compact --filter=RiwayatJabatan
        2. Assert: ALL PASS (0 failures) — tests cover:
           - Create riwayat jabatan with is_aktif=true → Pegawai.ref_jabatan_id updated
           - Create riwayat jabatan with is_aktif=true → Pegawai.ref_unit_kerja_id updated
           - Old aktif record set to is_aktif=false when new aktif added
           - CRUD (store, update, soft-delete) via controller routes
           - Validation: required fields, FK existence
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/riwayat-jabatan (GET, POST, PUT, DELETE)
      Expected Result: All RiwayatJabatan tests pass, routes registered
      Failure Indicators: Test failure, missing routes, sync logic not triggered
      Evidence: .sisyphus/evidence/task-9-jabatan-sync.txt

    Scenario: Riwayat jabatan sub-page compiles
      Tool: Bash
      Preconditions: Component file created at resources/js/pages/kepegawaian/pegawai/riwayat-jabatan.tsx
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0 (no TypeScript errors)
      Expected Result: Frontend compiles cleanly
      Failure Indicators: TypeScript error, missing import, type mismatch
      Evidence: .sisyphus/evidence/task-9-jabatan-build.txt
    ```

    **Commit**: YES (groups with T8, T10-T15)
    - Message: `feat(kepegawaian): add riwayat jabatan with active sync logic`
    - Pre-commit: `php artisan test --compact --filter=RiwayatJabatan`

- [x]   10. Riwayat Pendidikan — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_riwayat_pendidikan_table`:
        - `id`, `pegawai_id` (FK), `jenjang` (string, enum cast: JenjangPendidikan), `nama_sekolah` (string), `jurusan` (string nullable), `tahun_lulus` (year/integer), `no_ijazah` (string nullable), `tanggal_ijazah` (date nullable), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model, Controller (nested), Form Request, sub-page UI
    - Tidak ada sync logic (tidak ada "aktif" — semua riwayat tampil)
    - Factory, TDD

    **Must NOT do**:
    - TIDAK menambahkan file upload ijazah — hanya data text dulu (dokumen di Task 15)

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4
    - **Blocks**: Task 19
    - **Blocked By**: Task 5 (Pegawai)

    **References**:
    - Same pattern as Task 8/9

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/RiwayatPendidikanTest.php`
    - [ ] `php artisan test --compact --filter=RiwayatPendidikan` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Riwayat pendidikan CRUD verified via tests
      Tool: Bash
      Preconditions: Migrations applied, Pegawai seeded
      Steps:
        1. Run: php artisan test --compact --filter=RiwayatPendidikan
        2. Assert: ALL PASS — tests cover:
           - Create with jenjang enum (S1) → enum cast returns JenjangPendidikan::S1
           - CRUD lifecycle: store, update tahun_lulus, soft-delete
           - Validation: required nama_sekolah, valid jenjang enum value
           - Pegawai->riwayatPendidikan relationship returns collection
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/riwayat-pendidikan (GET, POST, PUT, DELETE)
      Expected Result: All tests pass, routes registered
      Failure Indicators: Enum cast failure, validation not enforced, routes missing
      Evidence: .sisyphus/evidence/task-10-pendidikan-crud.txt

    Scenario: Riwayat pendidikan sub-page compiles
      Tool: Bash
      Preconditions: Component file created
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0
      Expected Result: Frontend compiles
      Evidence: .sisyphus/evidence/task-10-pendidikan-build.txt
    ```

    **Commit**: YES (groups with T8-T9, T11-T15)
    - Pre-commit: `php artisan test --compact --filter=RiwayatPendidikan`

- [x]   11. Riwayat Diklat — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_riwayat_diklat_table`:
        - `id`, `pegawai_id` (FK), `ref_jenis_diklat_id` (FK), `nama_diklat` (string), `penyelenggara` (string), `tempat` (string nullable), `tanggal_mulai` (date), `tanggal_selesai` (date), `jam_pelajaran` (integer nullable — JP), `no_sertifikat` (string nullable), `tanggal_sertifikat` (date nullable), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model, Controller, Form Request, sub-page UI — same pattern
    - Factory, TDD

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4
    - **Blocks**: Task 19
    - **Blocked By**: Tasks 2 (ref_jenis_diklat), 5 (Pegawai)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/RiwayatDiklatTest.php` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Riwayat diklat CRUD and relationship verified via tests
      Tool: Bash
      Preconditions: Migrations applied, ref_jenis_diklat seeded, Pegawai seeded
      Steps:
        1. Run: php artisan test --compact --filter=RiwayatDiklat
        2. Assert: ALL PASS — tests cover:
           - Create with ref_jenis_diklat (Teknis) → jenisDiklat relationship loads correctly
           - Validation: tanggal_selesai >= tanggal_mulai enforced
           - CRUD lifecycle: store, update, soft-delete
           - Pegawai->riwayatDiklat relationship returns collection
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/riwayat-diklat (GET, POST, PUT, DELETE)
      Expected Result: All tests pass, date validation works, routes registered
      Failure Indicators: Date validation not enforced, relationship fails, routes missing
      Evidence: .sisyphus/evidence/task-11-diklat-crud.txt

    Scenario: Riwayat diklat sub-page compiles
      Tool: Bash
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0
      Expected Result: Frontend compiles
      Evidence: .sisyphus/evidence/task-11-diklat-build.txt
    ```

    **Commit**: YES (groups with T8-T10, T12-T15)
    - Pre-commit: `php artisan test --compact --filter=RiwayatDiklat`

- [x]   12. Data Keluarga — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_keluarga_table`:
        - `id`, `pegawai_id` (FK), `hubungan` (string, enum cast: HubunganKeluarga), `nama` (string), `tempat_lahir` (string nullable), `tanggal_lahir` (date nullable), `jenis_kelamin` (string nullable, enum: JenisKelamin), `pekerjaan` (string nullable), `pendidikan` (string nullable), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model, Controller (nested), Form Request, sub-page UI
    - Factory, TDD

    **Must NOT do**:
    - TIDAK menambahkan validasi complex (max anak, max istri) — input manual trustworthy

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4
    - **Blocks**: Task 19
    - **Blocked By**: Task 5 (Pegawai)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/KeluargaTest.php` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Data keluarga CRUD and enum cast verified via tests
      Tool: Bash
      Preconditions: Migrations applied, Pegawai seeded
      Steps:
        1. Run: php artisan test --compact --filter=Keluarga
        2. Assert: ALL PASS — tests cover:
           - Create keluarga with hubungan=Istri → enum cast returns HubunganKeluarga::Istri
           - Pegawai->keluarga relationship returns collection
           - CRUD lifecycle: store, update, soft-delete
           - Validation: required nama, valid hubungan enum value
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/keluarga (GET, POST, PUT, DELETE)
      Expected Result: All tests pass, enum casting works, routes registered
      Failure Indicators: Enum value not recognized, relationship error, routes missing
      Evidence: .sisyphus/evidence/task-12-keluarga-crud.txt

    Scenario: Data keluarga sub-page compiles
      Tool: Bash
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0
      Expected Result: Frontend compiles
      Evidence: .sisyphus/evidence/task-12-keluarga-build.txt
    ```

    **Commit**: YES (groups with T8-T11, T13-T15)
    - Pre-commit: `php artisan test --compact --filter=Keluarga`

- [x]   13. Penghargaan — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_penghargaan_table`:
        - `id`, `pegawai_id` (FK), `ref_jenis_penghargaan_id` (FK), `nama_penghargaan` (string), `no_sk` (string nullable), `tanggal_sk` (date nullable), `pejabat_penetap` (string nullable), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model, Controller, Form Request, sub-page UI — same riwayat pattern
    - Factory, TDD

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4
    - **Blocks**: Task 19
    - **Blocked By**: Tasks 2 (ref_jenis_penghargaan), 5 (Pegawai)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/PenghargaanTest.php` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Penghargaan CRUD with reference relationship verified via tests
      Tool: Bash
      Preconditions: Migrations applied, ref_jenis_penghargaan seeded, Pegawai seeded
      Steps:
        1. Run: php artisan test --compact --filter=Penghargaan
        2. Assert: ALL PASS — tests cover:
           - Create penghargaan with ref_jenis_penghargaan → jenisPenghargaan relationship loads
           - CRUD lifecycle: store, update, soft-delete
           - Validation: required fields enforced
           - Pegawai->penghargaan relationship returns collection
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/penghargaan (GET, POST, PUT, DELETE)
      Expected Result: All tests pass, FK relationship works, routes registered
      Failure Indicators: FK constraint error, validation not enforced, routes missing
      Evidence: .sisyphus/evidence/task-13-penghargaan-crud.txt

    Scenario: Penghargaan sub-page compiles
      Tool: Bash
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0
      Expected Result: Frontend compiles
      Evidence: .sisyphus/evidence/task-13-penghargaan-build.txt
    ```

    **Commit**: YES (groups with others in Wave 3)
    - Pre-commit: `php artisan test --compact --filter=Penghargaan`

- [x]   14. Hukuman Disiplin — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_hukuman_disiplin_table`:
        - `id`, `pegawai_id` (FK), `ref_jenis_hukuman_disiplin_id` (FK), `no_sk` (string), `tanggal_sk` (date), `tmt_berlaku` (date), `tmt_selesai` (date nullable — kapan hukuman berakhir), `pelanggaran` (text — deskripsi pelanggaran), `pejabat_penetap` (string nullable), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model, Controller, Form Request, sub-page UI
    - Scope: `scopeAktif()` — hukuman yang belum selesai (tmt_selesai null atau > now)
    - Factory, TDD

    **Must NOT do**:
    - TIDAK membuat workflow approval hukuman — input langsung oleh admin/operator

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4
    - **Blocks**: Task 19
    - **Blocked By**: Tasks 2 (ref_jenis_hukuman_disiplin), 5 (Pegawai)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/HukumanDisiplinTest.php` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Hukuman disiplin with active scope verified via tests
      Tool: Bash
      Preconditions: Migrations applied, ref_jenis_hukuman_disiplin seeded, Pegawai seeded
      Steps:
        1. Run: php artisan test --compact --filter=HukumanDisiplin
        2. Assert: ALL PASS — tests cover:
           - Create hukuman with tmt_selesai = future date → scopeAktif() includes it
           - Create hukuman with tmt_selesai = past date → scopeAktif() excludes it
           - Create hukuman with tmt_selesai = null → scopeAktif() includes it (ongoing)
           - CRUD lifecycle: store, update, soft-delete
           - Validation: required no_sk, tanggal_sk, pelanggaran
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/hukuman-disiplin (GET, POST, PUT, DELETE)
      Expected Result: All tests pass, scope correctly filters active hukuman, routes registered
      Failure Indicators: Scope includes expired hukuman, excludes null tmt_selesai, routes missing
      Evidence: .sisyphus/evidence/task-14-hukuman-scope.txt

    Scenario: Hukuman disiplin sub-page compiles
      Tool: Bash
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0
      Expected Result: Frontend compiles
      Evidence: .sisyphus/evidence/task-14-hukuman-build.txt
    ```

    **Commit**: YES (groups with others in Wave 3)
    - Pre-commit: `php artisan test --compact --filter=HukumanDisiplin`

- [x]   15. Dokumen Pegawai — Model, Migration, CRUD (Backend + Sub-page UI)

    **What to do**:
    - Buat migration `create_dokumen_pegawai_table`:
        - `id`, `pegawai_id` (FK), `jenis_dokumen` (string — e.g. "SK KGB", "SK Kenaikan Pangkat", "Ijazah", "Sertifikat", "Karpeg", "Karis/Karsu"), `nomor_dokumen` (string nullable), `tanggal_dokumen` (date nullable), `file_path` (string nullable — path ke file), `keterangan` (text nullable), `timestamps`, `soft_deletes`
    - Model, Controller, Form Request
    - Sub-page UI: list dokumen per pegawai, form tambah metadata (tanpa file upload di fondasi ini)
    - Factory, TDD

    **Must NOT do**:
    - TIDAK build file upload/storage — hanya metadata dan path placeholder
    - TIDAK membuat preview/viewer dokumen

    **Recommended Agent Profile**:
    - **Category**: `quick`
        - Reason: Model sederhana tanpa complex logic — hanya CRUD metadata
    - **Skills**: [`pest-testing`]

    **Parallelization**:
    - **Can Run In Parallel**: YES
    - **Parallel Group**: Wave 4
    - **Blocks**: Task 19
    - **Blocked By**: Task 5 (Pegawai)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/Kepegawaian/DokumenPegawaiTest.php` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Dokumen pegawai CRUD verified via tests
      Tool: Bash
      Preconditions: Migrations applied, Pegawai seeded
      Steps:
        1. Run: php artisan test --compact --filter=DokumenPegawai
        2. Assert: ALL PASS — tests cover:
           - Create dokumen with jenis_dokumen="SK KGB", nomor_dokumen="KGB/001/2024"
           - CRUD lifecycle: store, update nomor_dokumen, soft-delete
           - Pegawai->dokumen relationship returns collection
           - No file upload logic (metadata only)
        3. Run: php artisan route:list --path=kepegawaian/pegawai
        4. Assert output contains routes matching: pegawai/{pegawai}/dokumen (GET, POST, PUT, DELETE)
      Expected Result: All tests pass, metadata-only CRUD works, routes registered
      Failure Indicators: Unexpected file storage logic, routes missing, soft-delete broken
      Evidence: .sisyphus/evidence/task-15-dokumen-crud.txt

    Scenario: Dokumen pegawai sub-page compiles
      Tool: Bash
      Steps:
        1. Run: npm run build
        2. Assert: exit code 0
      Expected Result: Frontend compiles
      Evidence: .sisyphus/evidence/task-15-dokumen-build.txt
    ```

    **Commit**: YES (groups with others in Wave 3)
    - Pre-commit: `php artisan test --compact --filter=DokumenPegawai`

- [x]   16. Monitoring KGB — Service, Controller, dan Page

    **What to do**:
    - Buat `KgbMonitoringService` di `app/Services/`:
        - Method `getUpcomingKgb(int $months = 3): Collection` — menghitung pegawai yang KGB-nya akan jatuh tempo dalam N bulan
        - Logika: KGB diberikan setiap 2 tahun masa kerja pada golongan yang sama. TMT KGB berikutnya = TMT riwayat pangkat aktif terakhir + (masa_kerja_tahun \* 12 + masa_kerja_bulan + 24 bulan). Sederhananya: TMT terakhir + 2 tahun = TMT KGB berikutnya
        - Method `getKgbStatus(Pegawai $pegawai): array` — return: tanggal_kgb_berikutnya, sisa_hari, status (Sudah Jatuh Tempo, Segera (≤2 bulan), Mendekati (≤3 bulan), Aman)
    - Buat `MonitoringKgbController`:
        - index: list pegawai aktif dengan info KGB berikutnya, sortable by tanggal KGB terdekat
        - Props ke Inertia: pegawai list + kgb_info per pegawai
    - Buat page `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`:
        - Tabel: NIP, Nama, Pangkat, TMT Pangkat Terakhir, TMT KGB Berikutnya, Sisa Hari, Status
        - Status badges: Merah (Jatuh Tempo), Orange (≤2 bulan), Kuning (≤3 bulan), Hijau (Aman)
        - Filter: by status (semua, jatuh tempo, segera, mendekati)
        - Sorting by tanggal KGB terdekat (default: yang terdekat di atas)
    - Register routes: `Route::get('kepegawaian/monitoring/kgb', [MonitoringKgbController::class, 'index'])->name('monitoring.kgb.index')` — inside the `Route::middleware(['auth', 'role:admin,operator'])` group
    - TDD: Test service calculation logic extensively (edge cases: pegawai baru, pegawai tanpa riwayat pangkat, pegawai pensiun)

    **Must NOT do**:
    - TIDAK build email/notification system — hanya tampilan list/dashboard
    - TIDAK build print/export KGB list — fase berikutnya
    - TIDAK hardcode tanggal — semua kalkulasi dari data riwayat pangkat

    **Recommended Agent Profile**:
    - **Category**: `deep`
        - Reason: Business logic kalkulasi KGB yang harus akurat — butuh pemahaman domain kepegawaian
    - **Skills**: [`pest-testing`, `wayfinder-development`]
        - `pest-testing`: TDD extensive untuk business logic KGB
        - `wayfinder-development`: Route generation

    **Parallelization**:
    - **Can Run In Parallel**: YES (with Task 17)
    - **Parallel Group**: Wave 5 (with Tasks 7, 17, 19, 22)
    - **Blocks**: Task 18 (Dashboard needs KGB data)
    - **Blocked By**: Task 8 (RiwayatPangkat must exist for KGB calculation)

    **References**:

    **Pattern References**:
    - `app/Models/RiwayatPangkat.php` (dari Task 8) — Data source untuk kalkulasi KGB
    - `app/Http/Controllers/Kepegawaian/PegawaiController.php` (dari Task 6) — Controller pattern

    **API/Type References**:
    - `app/Models/Pegawai.php` — Pegawai with scopes
    - `app/Models/RefPangkat.php` — Pangkat reference

    **External References**:
    - SOP KGB PTA Jakarta: Proses dimulai 2 bulan sebelum TMT → notifikasi di status "Segera"
    - PP 7 Tahun 1977 tentang Gaji PNS (KGB setiap 2 tahun masa kerja pada golongan yang sama)

    **WHY Each Reference Matters**:
    - RiwayatPangkat: Data source utama — TMT dan masa_kerja_tahun/bulan
    - SOP KGB: Aturan 2 bulan sebelum TMT untuk memulai proses — basis threshold "Segera"
    - PP 7/1977: Basis legal KGB setiap 2 tahun — harus benar

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Monitoring/KgbMonitoringTest.php`
    - [ ] `php artisan test --compact --filter=KgbMonitoring` → PASS
    - [ ] Tests: calculation accuracy, edge cases (no riwayat, pensiunan excluded, new employee)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: KGB calculation returns correct next TMT
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Pegawai with RiwayatPangkat (tmt=2024-04-01, masa_kerja_tahun=10, masa_kerja_bulan=0)
      Steps:
        1. Create pegawai + riwayat pangkat with known TMT
        2. Call: KgbMonitoringService->getKgbStatus($pegawai)
        3. Assert tanggal_kgb_berikutnya = 2026-04-01 (TMT + 2 tahun)
        4. Assert sisa_hari calculated correctly from today
        5. Assert status matches threshold (based on current date vs TMT KGB)
      Expected Result: KGB date = TMT pangkat + 2 tahun, status accurate
      Failure Indicators: Wrong date, wrong status, calculation error
      Evidence: .sisyphus/evidence/task-16-kgb-calculation.txt

    Scenario: KGB monitoring page shows sorted list
      Tool: Playwright (playwright skill)
      Preconditions: Multiple pegawai with different KGB dates, user logged in
      Steps:
        1. Navigate to /kepegawaian/monitoring/kgb
        2. Assert table renders with columns: NIP, Nama, Pangkat, TMT Terakhir, KGB Berikutnya, Sisa Hari, Status
        3. Assert first row has smallest sisa_hari (sorted by urgency)
        4. Assert status badges are color-coded (check badge class/color)
        5. Take screenshot
      Expected Result: Monitoring page shows sorted list with correct color-coded statuses
      Failure Indicators: Wrong sort order, missing badges, calculation mismatch
      Evidence: .sisyphus/evidence/task-16-kgb-page.png

    Scenario: Pensiunan excluded from KGB monitoring
      Tool: Bash
      Steps:
        1. Create pegawai with status_pegawai = Pensiun
        2. Call getUpcomingKgb()
        3. Assert pensiunan NOT in results
      Expected Result: Only aktif pegawai shown
      Evidence: .sisyphus/evidence/task-16-kgb-exclude-pensiun.txt
    ```

    **Commit**: YES (groups with T17)
    - Message: `feat(monitoring): add KGB tracking with 2-year calculation and status badges`
    - Files: `app/Services/KgbMonitoringService.php`, `app/Http/Controllers/Monitoring/MonitoringKgbController.php`, `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`, `routes/web.php`, `tests/Feature/Monitoring/KgbMonitoringTest.php`
    - Pre-commit: `php artisan test --compact --filter=KgbMonitoring`

- [x]   17. Monitoring Kenaikan Pangkat — Service, Controller, dan Page

    **What to do**:
    - Buat `KenaikanPangkatMonitoringService` di `app/Services/`:
        - Method `getUpcomingKenaikanPangkat(string $periode = null): Collection`
        - Logika: Kenaikan Pangkat reguler membutuhkan minimal 4 tahun pada pangkat yang sama (atau 2 tahun untuk pilihan). TMT KP berikutnya = TMT pangkat saat ini + 4 tahun. Periode: April (usul paling lambat Oktober tahun sebelumnya) dan Oktober (usul paling lambat April)
        - Method `getKpStatus(Pegawai $pegawai): array` — return: eligible (boolean), tmt_kp_berikutnya, periode_usul, batas_usul, sisa_hari_usul, status (Sudah Eligible, Mendekati Eligible, Belum Eligible)
        - Filter by periode: April atau Oktober
    - Buat `MonitoringKenaikanPangkatController`
    - Buat page `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`:
        - Tabel: NIP, Nama, Pangkat Saat Ini, TMT Pangkat, TMT KP Berikutnya, Periode Usul, Status
        - Filter: by periode (April/Oktober), by status
        - Status badges: color-coded
    - Register routes: `Route::get('kepegawaian/monitoring/kenaikan-pangkat', [MonitoringKenaikanPangkatController::class, 'index'])->name('monitoring.kenaikan-pangkat.index')` — inside the `Route::middleware(['auth', 'role:admin,operator'])` group
    - TDD: Test calculation, edge cases

    **Must NOT do**:
    - TIDAK build surat izin keluar (meskipun user menyebutkan — itu surat-surat, bukan monitoring)
    - TIDAK build workflow pengusulan ke SIKEP/BKN — hanya monitoring
    - TIDAK handle kenaikan pangkat pilihan/pengabdian — fokus reguler dulu

    **Recommended Agent Profile**:
    - **Category**: `deep`
        - Reason: Business logic KP yang harus akurat — 4 tahun + periode April/Oktober
    - **Skills**: [`pest-testing`, `wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: YES (with Task 16)
    - **Parallel Group**: Wave 5
    - **Blocks**: Task 18
    - **Blocked By**: Tasks 8 (RiwayatPangkat), 9 (RiwayatJabatan)

    **References**:

    **Pattern References**:
    - `app/Services/KgbMonitoringService.php` (dari Task 16) — Service pattern yang sama
    - `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx` (dari Task 16) — Page pattern

    **External References**:
    - PP 12 Tahun 2002 tentang Kenaikan Pangkat PNS: reguler setiap 4 tahun pada pangkat yang sama
    - Periode KP: April (usul Oktober tahun sebelumnya) dan Oktober (usul April)
    - SE BKN: persyaratan KP reguler (PAK/DUPAK untuk fungsional, DP3/SKP, tidak ada hukuman disiplin)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php`
    - [ ] `php artisan test --compact --filter=KenaikanPangkatMonitoring` → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: KP calculation for reguler 4 tahun
      Tool: Bash (php artisan tinker --execute)
      Preconditions: Pegawai with RiwayatPangkat (tmt=2022-04-01, pangkat III/a)
      Steps:
        1. Call getKpStatus($pegawai)
        2. Assert tmt_kp_berikutnya = 2026-04-01 (TMT + 4 tahun)
        3. Assert periode_usul = "Oktober" (6 bulan sebelum April 2026)
        4. Assert eligible = true/false based on current date
      Expected Result: Correct TMT calculation and usul periode
      Evidence: .sisyphus/evidence/task-17-kp-calculation.txt

    Scenario: KP monitoring page with filter
      Tool: Playwright (playwright skill)
      Preconditions: Pegawai data seeded, user logged in
      Steps:
        1. Navigate to /kepegawaian/monitoring/kenaikan-pangkat
        2. Assert table renders
        3. Filter by periode "April"
        4. Assert only April-eligible pegawai shown
        5. Take screenshot
      Expected Result: Filter correctly shows only relevant periode
      Evidence: .sisyphus/evidence/task-17-kp-page.png
    ```

    **Commit**: YES (groups with T16)
    - Message: `feat(monitoring): add kenaikan pangkat tracking with periode filtering`
    - Pre-commit: `php artisan test --compact --filter=KenaikanPangkatMonitoring`

- [x]   18. Dashboard Statistik Kepegawaian

    **What to do**:
    - Update `DashboardController` (atau buat baru) untuk menyediakan data statistik:
        - Total pegawai aktif
        - Distribusi per golongan (I, II, III, IV) — bar chart data
        - Distribusi per jabatan (struktural, fungsional, pelaksana) — pie chart data
        - Distribusi per unit kerja — bar chart data
        - Distribusi per jenis kelamin — simple count
        - Distribusi per pendidikan terakhir — bar chart data
        - KGB yang akan jatuh tempo (count ≤2 bulan, ≤3 bulan) — alert badges
        - KP yang eligible (count per periode) — alert badges
    - Update `resources/js/pages/dashboard.tsx`:
        - Card summary: Total Pegawai, KGB Segera, KP Eligible, Pegawai Baru (bulan ini)
        - Chart/visual untuk distribusi (gunakan simple HTML/CSS bars atau shadcn/ui progress — TIDAK tambah chart library)
        - Quick links ke monitoring pages
    - TDD: Test controller returns correct aggregated data

    **Must NOT do**:
    - TIDAK menambahkan chart.js, recharts, atau chart library lain — gunakan simple bars/progress dari shadcn/ui
    - TIDAK membuat complex analytics — hanya statistik dasar
    - TIDAK mengubah dashboard untuk non-kepegawaian users (keep existing for non-linked users)

    **Recommended Agent Profile**:
    - **Category**: `visual-engineering`
        - Reason: Fokus UI dashboard dengan cards, progress bars, data visualization
    - **Skills**: [`wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: NO (depends on T16, T17 for monitoring data)
    - **Parallel Group**: Wave 6 (after T16, T17)
    - **Blocks**: None
    - **Blocked By**: Tasks 16 (KGB data), 17 (KP data)

    **References**:

    **Pattern References**:
    - `resources/js/pages/dashboard.tsx` — EXISTING dashboard yang akan di-update
    - `app/Http/Controllers/DashboardController.php` — Existing controller (jika ada) atau buat baru
    - `resources/js/components/ui/card.tsx` — shadcn Card component
    - `resources/js/components/ui/badge.tsx` — Badge for alerts
    - `resources/js/components/ui/progress.tsx` — Progress bar for distribution (di-install oleh Task 4)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] `tests/Feature/DashboardTest.php` (update existing) → PASS

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Dashboard shows correct statistics
      Tool: Playwright (playwright skill)
      Preconditions: Database seeded with pegawai data, user logged in as admin
      Steps:
        1. Navigate to /dashboard
        2. Assert card "Total Pegawai" shows number > 0
        3. Assert card "KGB Segera" shows number (could be 0)
        4. Assert distribusi golongan section visible
        5. Assert distribusi jabatan section visible
        6. Take screenshot
      Expected Result: Dashboard renders all statistics cards and distribution visuals
      Failure Indicators: Missing cards, zero values when data exists, layout broken
      Evidence: .sisyphus/evidence/task-18-dashboard.png

    Scenario: Dashboard data matches actual database
      Tool: Bash
      Preconditions: Known seed data
      Steps:
        1. Run: php artisan tinker --execute "echo App\Models\Pegawai::aktif()->count();"
        2. Compare with dashboard Total Pegawai value (via curl or test)
        3. Assert they match
      Expected Result: Dashboard numbers match actual database counts
      Evidence: .sisyphus/evidence/task-18-dashboard-accuracy.txt
    ```

    **Commit**: YES (groups with T19)
    - Message: `feat(dashboard): add kepegawaian statistics with distribution visuals`
    - Pre-commit: `php artisan test --compact --filter=Dashboard`

- [x]   19. Pegawai Show/Detail Page (Tabs untuk Semua Riwayat)

    **What to do**:
    - Buat halaman detail pegawai: `resources/js/pages/kepegawaian/pegawai/show.tsx`
    - Layout: Header (nama, NIP, foto placeholder, pangkat, jabatan, unit kerja) + Tabs
    - Tabs menggunakan shadcn Tabs component:
        - **Biodata**: data pribadi (dari Pegawai model)
        - **Keluarga**: sub-component list + form (reuse dari Task 12 UI)
        - **Riwayat Pangkat**: sub-component (reuse dari Task 8 UI)
        - **Riwayat Jabatan**: sub-component (reuse dari Task 9 UI)
        - **Riwayat Pendidikan**: sub-component (reuse dari Task 10 UI)
        - **Riwayat Diklat**: sub-component (reuse dari Task 11 UI)
        - **Penghargaan**: sub-component (reuse dari Task 13 UI)
        - **Hukuman Disiplin**: sub-component (reuse dari Task 14 UI)
        - **Dokumen**: sub-component (reuse dari Task 15 UI)
    - Controller show method harus eager-load semua relationships
    - Setiap tab lazy-loads data (atau prefetched via Inertia deferred props)
    - TDD: build check + controller test already exists (Task 6), verify show returns all data

    **Must NOT do**:
    - TIDAK membuat edit inline di show page — edit di halaman terpisah (Task 20)
    - TIDAK menambahkan tab component baru — gunakan shadcn/ui Tabs yang sudah ada

    **Recommended Agent Profile**:
    - **Category**: `visual-engineering`
        - Reason: Complex UI page dengan tabs, header, multiple sub-components
    - **Skills**: [`wayfinder-development`]

    **Parallelization**:
    - **Can Run In Parallel**: NO (needs ALL riwayat sub-pages)
    - **Parallel Group**: Wave 5 (after T8-T15)
    - **Blocks**: Task 20
    - **Blocked By**: Tasks 8-15 (all riwayat sub-pages)

    **References**:

    **Pattern References**:
    - `resources/js/pages/settings/profile.tsx` — Multi-section page pattern
    - `resources/js/components/ui/tabs.tsx` — shadcn Tabs component (di-install oleh Task 4)
    - All riwayat sub-pages dari Tasks 8-15

    **Acceptance Criteria**:

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Pegawai detail page shows all tabs
      Tool: Playwright (playwright skill)
      Preconditions: Pegawai with all riwayat data, user logged in
      Steps:
        1. Navigate to /kepegawaian/pegawai/{id}
        2. Assert header shows: nama, NIP, pangkat, jabatan
        3. Assert tabs visible: Biodata, Keluarga, Riwayat Pangkat, Riwayat Jabatan, Riwayat Pendidikan, Riwayat Diklat, Penghargaan, Hukuman Disiplin, Dokumen
        4. Click "Riwayat Pangkat" tab
        5. Assert riwayat pangkat table renders with data
        6. Click "Keluarga" tab
        7. Assert keluarga table renders
        8. Take screenshot
      Expected Result: All 9 tabs work and show correct data
      Failure Indicators: Missing tabs, empty data when data exists, tab switching broken
      Evidence: .sisyphus/evidence/task-19-pegawai-detail.png
    ```

    **Commit**: YES (groups with T18)
    - Message: `feat(kepegawaian): add pegawai detail page with tabbed riwayat views`
    - Pre-commit: `npm run build`

- [x]   20. Pegawai Create & Edit Pages — Multi-Step Form

    **What to do**:
    - Buat page `resources/js/pages/kepegawaian/pegawai/create.tsx`:
        - Multi-step form menggunakan state-based step navigation (step 1: Biodata, step 2: Kontak & Alamat, step 3: Kepegawaian)
        - Step 1 — Biodata: nama, NIP, NIP_lama (opsional), tempat_lahir, tanggal_lahir, jenis_kelamin (enum), agama (enum), golongan_darah (enum), status_perkawinan (enum)
        - Step 2 — Kontak & Alamat: alamat, telepon, email
        - Step 3 — Kepegawaian: status_pegawai (enum), status_kepegawaian (enum), tmt_cpns, tmt_pns, pendidikan_terakhir, unit_kerja=ref_unit_kerja_id (select dari RefUnitKerja), jabatan=ref_jabatan_id (select dari RefJabatan), pangkat=ref_pangkat_id (select dari RefPangkat), no_karpeg, no_karis_karsu, npwp, no_bpjs_kesehatan, no_bpjs_ketenagakerjaan, no_taspen
        - Navigasi antar step: tombol "Sebelumnya" dan "Selanjutnya", step indicator di atas (progress bar / numbered steps)
        - Validasi per-step sebelum next (client-side via Inertia form validation)
        - Submit di step terakhir via Inertia `router.post`
    - Buat page `resources/js/pages/kepegawaian/pegawai/edit.tsx`:
        - Sama seperti create tapi pre-filled dengan data existing
        - Submit via Inertia `router.put`
    - Buat `StorePegawaiRequest` dan `UpdatePegawaiRequest` Form Request classes:
        - Validasi lengkap: required fields, unique NIP, valid enum values, valid foreign keys
        - Custom error messages dalam Bahasa Indonesia
    - Update `PegawaiController` — tambah method `create()`, `store()`, `edit()`, `update()`
    - Buat komponen shared:
        - `resources/js/components/kepegawaian/multi-step-form.tsx` — Step container, progress indicator, navigation buttons
        - `resources/js/components/kepegawaian/enum-select.tsx` — Reusable select yang render options dari Enum values (dipass sebagai prop dari backend)
    - Update routes di `routes/web.php`: GET create, POST store, GET edit, PUT update
    - TDD: Test store, update, validasi gagal, unique NIP constraint

    **Must NOT do**:
    - TIDAK build upload foto pegawai (next phase)
    - TIDAK build inline riwayat creation dari form pegawai — riwayat di-manage terpisah dari detail page
    - TIDAK build form wizard library baru — cukup state management React sederhana
    - TIDAK over-validate fields opsional (no_karpeg, no_karis_karsu boleh null)

    **Recommended Agent Profile**:
    - **Category**: `visual-engineering`
        - Reason: Multi-step form UI yang kompleks membutuhkan UX yang baik (step indicator, validasi visual, transisi antar step)
    - **Skills**: [`pest-testing`, `wayfinder-development`]
        - `pest-testing`: TDD untuk Form Request validation dan controller store/update
        - `wayfinder-development`: Route generation untuk form action URLs
    - **Skills Evaluated but Omitted**:
        - `fortify-development`: Tidak relevan — ini form CRUD, bukan auth

    **Parallelization**:
    - **Can Run In Parallel**: NO
    - **Parallel Group**: Wave 6
    - **Blocks**: None (end-of-chain for form flow)
    - **Blocked By**: Tasks 5 (Pegawai model), 6 (PegawaiController), 19 (Detail page — untuk konsistensi layout)

    **References**:

    **Pattern References**:
    - `resources/js/pages/settings/profile.tsx` — Inertia form submission pattern (useForm, processing state, error display)
    - `resources/js/pages/auth/register.tsx` — Multi-field form layout pattern
    - `resources/js/components/ui/input.tsx`, `select.tsx`, `button.tsx`, `progress.tsx` — shadcn/ui form components
    - `app/Http/Requests/Settings/ProfileUpdateRequest.php` — Form Request class pattern
    - `app/Http/Controllers/Kepegawaian/PegawaiController.php` (dari Task 6) — Controller yang akan di-extend

    **API/Type References**:
    - `resources/js/types/kepegawaian.ts` (dari Task 5) — Pegawai TypeScript type, enum types
    - `app/Enums/` (dari Task 1) — Semua enum values untuk select options

    **External References**:
    - Inertia.js v2 docs: Forms & validation (useForm, processing, errors)

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Kepegawaian/PegawaiCreateTest.php`
    - [ ] Test file: `tests/Feature/Kepegawaian/PegawaiUpdateTest.php`
    - [ ] `php artisan test --compact --filter=PegawaiCreate` → PASS
    - [ ] `php artisan test --compact --filter=PegawaiUpdate` → PASS
    - [ ] Tests: successful create, successful update, validation fails (missing required fields), duplicate NIP rejected, invalid enum values rejected, unauthorized user blocked

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Create pegawai via multi-step form — happy path
      Tool: Playwright (playwright skill)
      Preconditions: Admin user logged in, ref tables seeded (RefPangkat, RefJabatan, RefUnitKerja)
      Steps:
        1. Navigate to /kepegawaian/pegawai/create
        2. Assert step indicator shows Step 1 of 3 as active
        3. Fill step 1: nama="Ahmad Fauzi", NIP="199001012020011001", tempat_lahir="Penajam", tanggal_lahir="1990-01-01", jenis_kelamin=select "Laki-laki", agama=select "Islam", status_perkawinan=select "Kawin"
        4. Click "Selanjutnya"
        5. Assert step 2 is active, step 1 fields preserved
        6. Fill step 2: alamat="Jl. Raya Penajam No. 1", telepon="08123456789", email="ahmad@test.com"
        7. Click "Selanjutnya"
        8. Assert step 3 is active
        9. Fill step 3: status_pegawai=select "Aktif", tmt_pns="2020-03-01", unit_kerja=select first option, pangkat=select first option
        10. Click "Simpan"
        11. Assert redirect to /kepegawaian/pegawai (list page)
        12. Assert success flash message visible
        13. Assert new pegawai "Ahmad Fauzi" appears in table
        14. Take screenshot
      Expected Result: Pegawai created successfully, redirected to list with success message
      Failure Indicators: Validation error on submit, no redirect, pegawai not in list
      Evidence: .sisyphus/evidence/task-20-create-happy-path.png

    Scenario: Validation errors shown per-step
      Tool: Playwright (playwright skill)
      Preconditions: Admin user logged in
      Steps:
        1. Navigate to /kepegawaian/pegawai/create
        2. Leave all fields empty, click "Selanjutnya"
        3. Assert validation error messages shown for: nama, NIP (required fields)
        4. Assert step does NOT advance to step 2
        5. Take screenshot
      Expected Result: Step 1 stays active with red validation messages under required fields
      Failure Indicators: Advances to step 2 without validation, no error messages shown
      Evidence: .sisyphus/evidence/task-20-create-validation-error.png

    Scenario: Edit pegawai pre-fills existing data
      Tool: Playwright (playwright skill)
      Preconditions: Admin logged in, pegawai "Ahmad Fauzi" exists (from create scenario)
      Steps:
        1. Navigate to /kepegawaian/pegawai/{id}/edit
        2. Assert step 1 fields pre-filled: nama="Ahmad Fauzi", NIP="199001012020011001"
        3. Change nama to "Ahmad Fauzi Hidayat"
        4. Navigate through step 2, step 3 without changes
        5. Click "Simpan"
        6. Assert redirect to pegawai detail or list
        7. Assert updated nama shown
      Expected Result: Form pre-filled, update successful
      Evidence: .sisyphus/evidence/task-20-edit-prefill.png

    Scenario: Duplicate NIP rejected on create
      Tool: Bash (php artisan test)
      Steps:
        1. Run: php artisan test --compact --filter=PegawaiCreate
        2. Assert test "duplicate NIP is rejected" passes
      Expected Result: 422 validation error with message "NIP sudah terdaftar"
      Evidence: .sisyphus/evidence/task-20-duplicate-nip.txt
    ```

    **Commit**: YES
    - Message: `feat(kepegawaian): add pegawai create/edit multi-step form with validation`
    - Files: `resources/js/pages/kepegawaian/pegawai/create.tsx`, `resources/js/pages/kepegawaian/pegawai/edit.tsx`, `resources/js/components/kepegawaian/multi-step-form.tsx`, `resources/js/components/kepegawaian/enum-select.tsx`, `app/Http/Requests/Kepegawaian/StorePegawaiRequest.php`, `app/Http/Requests/Kepegawaian/UpdatePegawaiRequest.php`, `tests/Feature/Kepegawaian/PegawaiCreateTest.php`, `tests/Feature/Kepegawaian/PegawaiUpdateTest.php`
    - Pre-commit: `php artisan test --compact --filter=Pegawai`

- [x]   21. Self-Service — Pegawai View Own Data

    **What to do**:
    - Buat middleware `EnsurePegawaiLinked` di `app/Http/Middleware/`:
        - Cek `auth()->user()->pegawai_id` tidak null
        - Jika null → redirect ke halaman pesan "Akun Anda belum dikaitkan dengan data pegawai. Hubungi administrator."
        - Register alias `pegawai.linked` di `bootstrap/app.php`
    - Buat `SelfServiceController` di `app/Http/Controllers/Kepegawaian/`:
        - `index()` — Render dashboard self-service (ringkasan data diri)
        - `show()` — Detail data pegawai sendiri (reuse layout dari Task 19 pegawai detail, tapi read-only dan tanpa action buttons)
        - Secara otomatis scope ke `auth()->user()->pegawai` — tidak ada route parameter {id}
    - Buat page `resources/js/pages/self-service/index.tsx`:
        - Card ringkasan: nama, NIP, pangkat/golongan, jabatan, unit kerja, foto placeholder
        - Quick info cards: KGB berikutnya (tanggal + sisa hari), KP berikutnya (tanggal + sisa hari), masa kerja
        - Link ke detail lengkap
    - Buat page `resources/js/pages/self-service/detail.tsx`:
        - Reuse pattern dari `pegawai/show.tsx` (Task 19) — tabbed view dengan semua riwayat
        - Tapi semua action buttons (create, edit, delete) di-hide — read-only view
        - Data di-scope ke pegawai sendiri di backend (controller), bukan di frontend
    - Register routes: `Route::middleware(['auth', 'pegawai.linked'])->prefix('self-service')->group()`
    - Update sidebar: tambahkan menu "Data Saya" yang visible untuk semua role. **Juga** implementasikan role-based sidebar visibility: hide menu Kepegawaian dan Monitoring dari viewer role (viewer hanya melihat Dashboard, Data Saya, dan Settings). Ini menggunakan shared auth props dari Inertia (user.role).
    - RBAC enforcement: viewer role HANYA bisa akses self-service routes, TIDAK bisa akses /kepegawaian/\*
    - TDD: Test self-service access, test unlinked user blocked, test viewer can't access admin routes

    **Must NOT do**:
    - TIDAK allow self-service users untuk edit data sendiri (admin/operator yang edit)
    - TIDAK build notification system untuk self-service
    - TIDAK duplicate komponen dari Task 19 — reuse/share components
    - TIDAK scope data di frontend (harus di backend controller/middleware untuk security)

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
        - Reason: Kombinasi middleware, RBAC enforcement, dan UI reuse membutuhkan pemahaman end-to-end tapi bukan UI-heavy
    - **Skills**: [`pest-testing`, `wayfinder-development`]
        - `pest-testing`: TDD untuk middleware logic, RBAC enforcement, route protection
        - `wayfinder-development`: Route generation untuk self-service URLs
    - **Skills Evaluated but Omitted**:
        - `fortify-development`: Auth sudah tersedia, ini RBAC bukan auth flow

    **Parallelization**:
    - **Can Run In Parallel**: YES (with Task 18, 20)
    - **Parallel Group**: Wave 6
    - **Blocks**: None
    - **Blocked By**: Tasks 3 (RBAC), 5 (Pegawai model), 6 (PegawaiController), 16 (KGB service — untuk quick info), 17 (KP service — untuk quick info), 19 (Pegawai detail — layout reuse)

    **References**:

    **Pattern References**:
    - `app/Http/Middleware/HandleInertiaRequests.php` — Existing middleware pattern
    - `bootstrap/app.php` — Where to register middleware alias (Laravel 12 pattern)
    - `resources/js/pages/kepegawaian/pegawai/show.tsx` (dibuat oleh Task 19) — Tabbed detail page to reuse
    - `resources/js/components/app-sidebar.tsx` — Sidebar where "Data Saya" menu will be added

    **API/Type References**:
    - `app/Models/User.php` — `pegawai_id` relationship dan role check
    - `app/Models/Pegawai.php` (dari Task 5) — Pegawai model
    - `app/Services/KgbMonitoringService.php` (dari Task 16) — KGB status method
    - `app/Services/KenaikanPangkatMonitoringService.php` (dari Task 17) — KP status method

    **External References**:
    - Laravel 12 middleware: Register alias di `bootstrap/app.php` via `$middleware->alias()`

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/SelfService/SelfServiceAccessTest.php`
    - [ ] `php artisan test --compact --filter=SelfServiceAccess` → PASS
    - [ ] Tests: linked user can access self-service, unlinked user redirected, viewer role cannot access /kepegawaian/\*, admin can still access self-service, self-service shows correct pegawai data (not another's)

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Linked pegawai sees own data on self-service dashboard
      Tool: Playwright (playwright skill)
      Preconditions: User with role=viewer, pegawai_id linked to pegawai "Siti Aminah" (NIP: 198505052010012001)
      Steps:
        1. Login as viewer user
        2. Navigate to /self-service
        3. Assert card shows nama="Siti Aminah", NIP="198505052010012001"
        4. Assert KGB info card shows tanggal and sisa hari (not empty)
        5. Assert KP info card shows tanggal and sisa hari (not empty)
        6. Click "Lihat Detail Lengkap"
        7. Assert navigated to /self-service/detail
        8. Assert tabs visible (Biodata, Keluarga, etc.)
        9. Assert NO edit/delete buttons visible anywhere
        10. Take screenshot
      Expected Result: Self-service shows own data read-only with KGB/KP monitoring info
      Failure Indicators: Wrong pegawai data, edit buttons visible, empty KGB/KP cards
      Evidence: .sisyphus/evidence/task-21-self-service-dashboard.png

    Scenario: Unlinked user sees informational page
      Tool: Playwright (playwright skill)
      Preconditions: User with role=viewer, pegawai_id=null
      Steps:
        1. Login as unlinked user
        2. Navigate to /self-service
        3. Assert redirected to info page (NOT /self-service dashboard)
        4. Assert message contains "belum dikaitkan" or "Hubungi administrator"
        5. Take screenshot
      Expected Result: Redirect to info page with clear message
      Failure Indicators: 500 error, shows empty dashboard, shows other's data
      Evidence: .sisyphus/evidence/task-21-unlinked-user.png

    Scenario: Viewer role cannot access admin kepegawaian routes
      Tool: Bash (php artisan test)
      Steps:
        1. Run: php artisan test --compact --filter=SelfServiceAccess
        2. Assert test "viewer cannot access kepegawaian routes" passes — 403 Forbidden
      Expected Result: Viewer gets 403 on /kepegawaian/pegawai, /kepegawaian/pegawai/create, etc.
      Evidence: .sisyphus/evidence/task-21-rbac-enforcement.txt
    ```

    **Commit**: YES
    - Message: `feat(self-service): add read-only self-service portal for employees`
    - Files: `app/Http/Middleware/EnsurePegawaiLinked.php`, `app/Http/Controllers/Kepegawaian/SelfServiceController.php`, `resources/js/pages/self-service/index.tsx`, `resources/js/pages/self-service/detail.tsx`, `bootstrap/app.php`, `routes/web.php`, `resources/js/components/app-sidebar.tsx`, `tests/Feature/SelfService/SelfServiceAccessTest.php`
    - Pre-commit: `php artisan test --compact --filter=SelfService`

- [x]   22. Search, Filter, Sort Across All List Pages

    **What to do**:
    - Buat trait `Filterable` di `app/Traits/Filterable.php`:
        - Method `scopeFilter(Builder $query, array $filters)` — generic filter dari request params
        - Method `scopeSearch(Builder $query, string $search, array $searchableColumns)` — LIKE search across multiple columns
        - Method `scopeSorted(Builder $query, string $sortBy, string $sortDir = 'asc')` — dynamic column sort
    - Apply trait ke model `Pegawai`
    - Update `PegawaiController@index`:
        - Accept query params: `search` (string), `golongan` (filter), `jabatan` (filter), `unit_kerja` (filter), `status_pegawai` (filter), `sort_by` (column name), `sort_dir` (asc/desc)
        - Pass filter options ke frontend: list golongan, jabatan, unit_kerja dari ref tables
        - Paginate results (15 per page default)
    - Update `resources/js/pages/kepegawaian/pegawai/index.tsx`:
        - Search bar: input text yang trigger search on Enter atau setelah 300ms debounce
        - Filter dropdowns: Golongan/Pangkat, Jabatan, Unit Kerja, Status Pegawai — menggunakan shadcn Select
        - Sort: clickable column headers (NIP, Nama, Pangkat, Jabatan) — toggle asc/desc
        - Clear all filters button
        - URL-based state: filters disimpan di URL query params (via Inertia router.get with preserveState)
        - Pagination: shadcn pagination component di bawah tabel
    - Buat reusable component `resources/js/components/kepegawaian/data-table-toolbar.tsx`:
        - Komposisi: search input + filter selects + clear button
        - Props: filterOptions (dynamic), onFilterChange, searchValue, onSearchChange
        - Bisa di-reuse untuk halaman list lain (riwayat, monitoring) di future
    - TDD: Test search by NIP, search by nama, filter by golongan, filter by unit_kerja, sort by nama ASC/DESC, combined search+filter, empty result

    **Must NOT do**:
    - TIDAK build advanced full-text search (LIKE cukup untuk 40 pegawai)
    - TIDAK build saved/bookmarked filters
    - TIDAK apply search/filter ke ALL list pages sekarang — hanya pegawai index dulu. Pattern-nya bisa di-reuse nanti
    - TIDAK build export/download dari filtered results (next phase)
    - TIDAK add database index untuk search (SQLite + 40 records tidak butuh)

    **Recommended Agent Profile**:
    - **Category**: `unspecified-high`
        - Reason: Backend trait + controller update + frontend interactivity — full-stack tapi bukan visual-heavy
    - **Skills**: [`pest-testing`, `wayfinder-development`]
        - `pest-testing`: TDD untuk Filterable trait, search accuracy, filter combinations
        - `wayfinder-development`: Route with query params
    - **Skills Evaluated but Omitted**:
        - `fortify-development`: Tidak relevan

    **Parallelization**:
    - **Can Run In Parallel**: YES (starts after T7 completes within Wave 5)
    - **Parallel Group**: Wave 5 (starts after T7 — see wave note)
    - **Blocks**: None
    - **Blocked By**: Task 7 (Pegawai list page — yang akan di-update)

    **References**:

    **Pattern References**:
    - `resources/js/pages/kepegawaian/pegawai/index.tsx` (dari Task 7) — Page yang akan di-enhance
    - `resources/js/components/ui/input.tsx`, `select.tsx`, `table.tsx` — shadcn/ui components
    - `app/Http/Controllers/Kepegawaian/PegawaiController.php` (dari Task 6) — Controller to update

    **API/Type References**:
    - `app/Models/Pegawai.php` (dari Task 5) — Model yang akan ditambah Filterable trait
    - `app/Models/RefPangkat.php`, `app/Models/RefJabatan.php`, `app/Models/RefUnitKerja.php` (dari Task 2) — Ref tables untuk filter options

    **External References**:
    - Inertia.js v2 docs: `router.get()` with `preserveState: true` untuk URL-based filtering tanpa full page reload
    - Laravel Query Builder: `when()` method untuk conditional filters

    **Acceptance Criteria**:

    **TDD:**
    - [ ] Test file: `tests/Feature/Kepegawaian/PegawaiSearchFilterTest.php`
    - [ ] `php artisan test --compact --filter=PegawaiSearchFilter` → PASS
    - [ ] Tests: search by NIP exact match, search by nama partial match, filter by golongan, filter by unit_kerja, filter by status_pegawai, sort by nama ASC, sort by nama DESC, combined search + filter returns correct subset, empty search returns all, no results scenario returns empty with message

    **QA Scenarios (MANDATORY):**

    ```
    Scenario: Search pegawai by NIP
      Tool: Playwright (playwright skill)
      Preconditions: Seeded 5+ pegawai, user logged in as admin
      Steps:
        1. Navigate to /kepegawaian/pegawai
        2. Type "199001" in search input
        3. Wait 500ms for debounce
        4. Assert table filtered — only pegawai with NIP containing "199001" shown
        5. Assert URL contains ?search=199001
        6. Clear search input
        7. Assert all pegawai shown again
        8. Take screenshot of filtered state
      Expected Result: Table dynamically filters by NIP substring
      Failure Indicators: No filtering, full page reload, URL not updated
      Evidence: .sisyphus/evidence/task-22-search-nip.png

    Scenario: Filter by golongan and unit kerja combined
      Tool: Playwright (playwright skill)
      Preconditions: Seeded pegawai with different golongan and unit kerja
      Steps:
        1. Navigate to /kepegawaian/pegawai
        2. Select golongan filter: "III/a"
        3. Assert table shows only III/a pegawai
        4. Select unit_kerja filter: "Kepaniteraan"
        5. Assert table shows only III/a pegawai in Kepaniteraan
        6. Assert URL contains ?golongan=III/a&unit_kerja=Kepaniteraan (or encoded)
        7. Click "Hapus Filter" / clear button
        8. Assert all pegawai shown, URL clean
        9. Take screenshot
      Expected Result: Filters combine (AND logic), URL reflects state, clear resets all
      Failure Indicators: OR logic instead of AND, URL not updated, clear doesn't reset
      Evidence: .sisyphus/evidence/task-22-filter-combined.png

    Scenario: Sort by nama column toggles direction
      Tool: Playwright (playwright skill)
      Preconditions: Seeded 5+ pegawai
      Steps:
        1. Navigate to /kepegawaian/pegawai
        2. Click column header "Nama"
        3. Assert table sorted A-Z (first row nama < last row nama alphabetically)
        4. Click column header "Nama" again
        5. Assert table sorted Z-A
        6. Assert sort indicator (arrow icon) changes direction
        7. Take screenshot
      Expected Result: Column header click toggles sort direction
      Failure Indicators: No sorting, wrong direction, sort indicator missing
      Evidence: .sisyphus/evidence/task-22-sort-nama.png

    Scenario: Empty search result shows message
      Tool: Playwright (playwright skill)
      Preconditions: Seeded pegawai
      Steps:
        1. Navigate to /kepegawaian/pegawai
        2. Type "XYZNONEXISTENT999" in search input
        3. Wait 500ms
        4. Assert table body shows "Tidak ada data yang ditemukan" or similar empty message
        5. Assert table has 0 data rows
        6. Take screenshot
      Expected Result: Empty state message shown, no error
      Failure Indicators: Blank table without message, error, loading spinner stuck
      Evidence: .sisyphus/evidence/task-22-empty-result.png
    ```

    **Commit**: YES
    - Message: `feat(kepegawaian): add search, filter, sort to pegawai list page`
    - Files: `app/Traits/Filterable.php`, `app/Models/Pegawai.php`, `app/Http/Controllers/Kepegawaian/PegawaiController.php`, `resources/js/pages/kepegawaian/pegawai/index.tsx`, `resources/js/components/kepegawaian/data-table-toolbar.tsx`, `tests/Feature/Kepegawaian/PegawaiSearchFilterTest.php`
    - Pre-commit: `php artisan test --compact --filter=PegawaiSearchFilter`

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks)

> 4 review agents run in PARALLEL. ALL must APPROVE. Rejection → fix → re-run.

- [ ] F1. **Plan Compliance Audit** — `deep` (dispatched via `subagent_type='oracle'`, bukan category-based dispatch)
      Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, curl endpoint, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in .sisyphus/evidence/. Compare deliverables against plan.
      Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
      Run `php artisan test --compact` + `vendor/bin/pint --dirty --format agent` + `npm run build`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names.
      Output: `Build [PASS/FAIL] | Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high` (+ `playwright` skill for UI)
      Start from clean state (`php artisan migrate:fresh --seed`). Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (create pegawai → add riwayat → check monitoring). Save to `.sisyphus/evidence/final-qa/`.
      Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
      For each task: read "What to do", read actual diff. Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT Have" compliance. Detect cross-task contamination.
      Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

| Wave | Commit  | Message                                                              | Files                                                                                   | Pre-commit                                       |
| ---- | ------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------------------- | ------------------------------------------------ |
| 1    | T1      | `feat(kepegawaian): add PHP enums and TypeScript types`              | app/Enums/\*.php, resources/js/types/kepegawaian.ts                                     | `php artisan test --compact --filter=Enum`       |
| 1    | T2      | `feat(kepegawaian): add reference tables with seed data`             | app/Models/Ref*.php, database/migrations/*ref*, database/seeders/*Ref\*                 | `php artisan test --compact --filter=Reference`  |
| 1    | T3      | `feat(auth): add RBAC roles and middleware`                          | app/Enums/Role.php, app/Http/Middleware/\*, app/Models/User.php                         | `php artisan test --compact --filter=Role`       |
| 1    | T4      | `feat(ui): restructure sidebar navigation for kepegawaian`           | resources/js/components/app-sidebar\*, resources/js/types/navigation.ts                 | `npm run build`                                  |
| 2    | T5-T7   | `feat(kepegawaian): add pegawai model, controller, and list page`    | app/Models/Pegawai.php, app/Http/Controllers/Pegawai*, resources/js/pages/kepegawaian/* | `php artisan test --compact --filter=Pegawai`    |
| 3    | T8-T15  | `feat(kepegawaian): add riwayat entities and keluarga data`          | app/Models/Riwayat\*.php, app/Models/Keluarga.php, etc.                                 | `php artisan test --compact`                     |
| 4    | T16-T17 | `feat(monitoring): add KGB and kenaikan pangkat tracking`            | app/Services/Monitoring*, resources/js/pages/kepegawaian/monitoring/*                   | `php artisan test --compact --filter=Monitoring` |
| 4    | T18-T19 | `feat(dashboard): add kepegawaian statistics and pegawai detail`     | resources/js/pages/dashboard*, resources/js/pages/kepegawaian/show*                     | `php artisan test --compact`                     |
| 5    | T20-T22 | `feat(kepegawaian): add create/edit forms, self-service, and search` | resources/js/pages/kepegawaian/create*, app/Http/Middleware/*                           | `php artisan test --compact`                     |

---

## Success Criteria

### Verification Commands

```bash
php artisan test --compact                    # Expected: ALL PASS (50+ tests, 0 failures)
vendor/bin/pint --dirty --format agent        # Expected: 0 files modified
npm run build                                 # Expected: success, 0 errors
php artisan migrate:fresh --seed              # Expected: seeded successfully
php artisan tinker --execute "echo App\Models\Pegawai::count();"  # Expected: ≥40 (29 dari data_pegawai.json + factory-generated)
php artisan tinker --execute "echo App\Models\RefPangkat::count();"  # Expected: 17
php artisan route:list --path=kepegawaian     # Expected: CRUD routes listed
php artisan route:list --path=monitoring      # Expected: monitoring routes listed
```

### Final Checklist

- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass (50+ tests)
- [ ] Reference data seeded correctly
- [ ] Monitoring KGB shows correct upcoming deadlines
- [ ] Dashboard statistics render accurately
- [ ] RBAC enforced on all routes
- [ ] Self-service users can only see own data
