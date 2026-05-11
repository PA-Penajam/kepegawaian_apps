# SIKEP Companion P1: Audit Cuti + KP 12 Periode + Workflow Usulan KP + Checklist Berkas

## TL;DR

> **Quick Summary**: Menutup gap Prioritas 1 dari spec administrasi kepegawaian: audit modul Cuti yang sudah ada, refactor monitoring Kenaikan Pangkat dari 2 periode (Apr/Okt) ke 12 periode bulanan sesuai Peraturan BKN 4/2025, bangun workflow usulan kenaikan pangkat lengkap sebagai companion SIKEP Mahkamah Agung, dan sistem checklist berkas administrasi generic (polimorfik, reusable lintas domain).
>
> **Deliverables**:
> - Audit verifikasi modul Cuti (CUTI-01..CUTI-10) dengan test scenario
> - Refactor `KenaikanPangkatMonitoringService` ke 12 periode bulanan (hard switch, hapus legacy April/Oktober)
> - Modul `UsulanKenaikanPangkat` lengkap: migrasi, state machine, controllers, UI, PDF surat pengantar (placeholder nomor surat)
> - Sistem `BerkasChecklist` generic polimorfik (reusable untuk cuti, KP, mutasi/pensiun masa depan)
> - `NomorSuratService` interface + placeholder impl (adapter-ready untuk integrasi aplikasi surat MA masa depan)
> - `SikepAdapter` interface + null implementation (adapter-ready untuk integrasi API SIKEP masa depan)
> - IAM permissions baru: `kenaikan-pangkat.usulan.*`, `checklist.template.*`, `checklist.submission.*`
> - Notifications + console command refactor ke 12 periode
> - UI: daftar eligible 12 bulan, form usulan KP, inbox approval, detail timeline, admin SK, admin checklist template
>
> **Estimated Effort**: Large (3-4 minggu untuk 1 dev fulltime)
> **Parallel Execution**: YES — 5 gelombang
> **Critical Path**: Refactor KP service (T7) → Model + States (T8) → Service (T13) → Controllers T16a/b/c paralel → T16d routes+wayfinder → UI (T17/19) → Auto-update riwayat (T22) → Final verification

---

## Context

### Original Request

User meminta analisa dan review menyeluruh dokumen `docs/spesifikasi-fitur-administrasi-kepegawaian-belum-tercover.md` untuk mengidentifikasi fitur yang belum ada pada project `kepegawaian-apps`.

### Interview Summary

**Key Discussions**:
- **Posisi aplikasi**: Companion/pendamping SIKEP Mahkamah Agung, BUKAN penerbit SK final. Aplikasi ini "cockpit" satuan kerja pengadilan untuk menyiapkan usulan, mengelola checklist berkas, tracking status, dan arsip SK yang turun dari Biro Kepegawaian MA.
- **Cakupan**: P1 saja dari dokumen spec. P2/P3 (mutasi, pensiun, dashboard, laporan) di-defer ke plan terpisah.
- **Integrasi SIKEP**: Belum ada API SIKEP. Arsitektur harus adapter-ready via interface `SikepAdapter` (null implementation sekarang).
- **Nomor surat**: Aplikasi surat MA masih dalam pengerjaan. Gunakan placeholder dengan format standar MA (`{kode_satker}/{nomor}/{klasifikasi}/{bulan_romawi}/{tahun}`). Interface `NomorSuratService` untuk future API replacement.
- **Penandatangan**: Konfigurable per jenis surat (Ketua untuk KP, Sekretaris untuk cuti).
- **Test strategy**: TDD wajib (RED-GREEN-REFACTOR) sesuai CLAUDE.md.
- **Refactor strategy**: Hard switch ke 12 periode (buang legacy April/Oktober).
- **Cuti**: Audit verifikasi saja — modul sudah lengkap.
- **Checklist**: Generic polimorfik (morphTo subject), bukan per-modul.

**Research Findings**:
- **Modul Cuti SUDAH LENGKAP**: 16 migrasi (`cuti_*`), 15 model di `app/Models/Cuti/`, controllers + API + UI pages, state machine, approval berjenjang, PDF, riwayat, konfigurasi PNS/PPPK. Secara struktural memenuhi CUTI-01..CUTI-10.
- **Monitoring KP masih 2 periode**: `app/Services/KenaikanPangkatMonitoringService.php` baris 42-46, 116-120, 132-150, 185-207 masih hard-coded April/Oktober. Tidak sesuai Peraturan BKN 4/2025.
- **Monitoring KGB sudah ada** sebagai pola reference (service + command + notification + test).
- **Infrastruktur reusable** sudah lengkap: `spatie/laravel-model-states` ^2.7, `spatie/laravel-activitylog` ^5.0, `spatie/laravel-pdf` ^1.5, `maatwebsite/excel` ^3.1, custom IAM, DB+Mail notifications.
- **Pegawai model**: ULID, ~40 kolom, enum casts, scope `aktif`/`byUnitKerja`/`byGolongan`. Kolom `tanggal_pensiun_bup` sudah ada. User table `pegawai_id` nullable.
- **RiwayatPangkat**: `pegawai_id`, `ref_pangkat_id`, `no_sk`, `tanggal_sk`, `tmt` (date), `pejabat_penetap`, `masa_kerja_tahun/bulan`, `gaji_pokok`, `is_aktif`. Eligibility existing: TMT + 4 tahun.
- **Pegawai aktif diperhitungkan KP**: selain status Pensiun/Meninggal/Diberhentikan. Service existing sudah filter benar.
- **Pola Cuti sebagai template workflow**: `cuti_pengajuan` + `cuti_pengajuan_approval_steps` + `cuti_pengajuan_approver_history` + `cuti_pengajuan_state_history` + `cuti_pengajuan_lampiran` + `cuti_pengajuan_pdf`.

### Self-Review Findings (Pengganti Metis Consultation)

**Questions yang teridentifikasi (di-handle via guardrails/defaults):**

| Topik | Default yang Diterapkan | Alasan |
|---|---|---|
| Jabatan Fungsional (JF) butuh DUPAK | **OUT OF SCOPE di P1**. KP JF di-defer ke plan terpisah. Filter service: hanya PNS struktural untuk P1. | DUPAK terpisah, butuh integrasi sistem penilaian angka kredit yang kompleks |
| Hukuman disiplin aktif memblokir eligibility | **YES**, pegawai dengan `hukuman_disiplin` aktif (belum expired) tidak muncul di daftar eligible | Standar BKN |
| KP reguler vs pilihan | **Hanya KP reguler** di P1 (berdasar TMT + 4 tahun). KP pilihan (prestasi, penghargaan khusus) di-defer | Mayoritas use case adalah reguler |
| Pegawai PPPK | **Dikecualikan** dari KP (PPPK tidak punya golongan, sistem KP hanya untuk PNS) | Standar ASN |
| Pegawai mutasi masuk (riwayat pangkat dari satker lama) | **Asumsi**: saat migrasi data, `riwayat_pangkat` sudah terisi dan `is_aktif=true` untuk yang terakhir. Jika belum, admin input manual dulu. Plan tidak handle import lintas-satker. | Skup satker, bukan lintas satker |
| Revisi setelah ditolak Biro MA | **YES**, state `PerluPerbaikan` mengembalikan ke draft → submit lagi | Standar workflow |
| Banding/keberatan | **OUT OF SCOPE di P1** | Bukan fitur operasional inti |

**Assumptions yang perlu divalidasi saat implementasi:**
1. IAM role "Ketua Pengadilan" belum ada — perlu seed permission baru
2. `RiwayatPangkat.is_aktif` invariant: hanya 1 aktif per `pegawai_id` (belum ada DB constraint, perlu di-validate di service)
3. Format NIP 18 digit sudah konsisten di semua pegawai
4. `Pegawai.ref_pangkat_id` sinkron dengan `RiwayatPangkat.is_aktif=true` — jika tidak, ada drift

---

## Work Objectives

### Core Objective

Transform `kepegawaian-apps` dari pure administrative database menjadi **companion workflow SIKEP MA** yang memungkinkan satuan kerja pengadilan: (1) audit kelengkapan modul cuti existing, (2) melakukan monitoring kenaikan pangkat sesuai Peraturan BKN 4/2025 (12 periode/tahun), (3) menjalankan workflow usulan kenaikan pangkat end-to-end dari eligible sampai SK turun, dan (4) mengelola checklist berkas administrasi secara generic-reusable.

### Concrete Deliverables

**Audit Cuti (1 task)**:
- Laporan audit `.sisyphus/evidence/cuti-audit/cuti-audit-report.md` berisi verifikasi CUTI-01..CUTI-10 dengan bukti file/endpoint/query per requirement

**Refactor KP Monitoring (3 tasks)**:
- `app/Services/KenaikanPangkatMonitoringService.php` tanpa logika April/Oktober
- `app/Notifications/KenaikanPangkatEligibleNotification.php` + command update
- `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx` UI filter 12 bulan

**Workflow Usulan KP (10 tasks)**:
- Migrations: `usulan_kenaikan_pangkat`, `usulan_kp_approval_steps`, `usulan_kp_approver_history`, `usulan_kp_state_history`, `usulan_kp_lampiran`, `usulan_kp_pdf`
- Model: `app/Models/UsulanKenaikanPangkat/UsulanKenaikanPangkat.php` + 5 submodel + 11 state class
- Service: `app/Services/UsulanKenaikanPangkatService.php`
- Controllers web + API: 5 controller files
- UI pages: `resources/js/pages/kenaikan-pangkat/{eligible,usulan,approval,detail,admin-sk}/*.tsx`
- Template PDF: `resources/views/pdf/kenaikan-pangkat/surat-pengantar.blade.php`

**Checklist Berkas Generic (3 tasks)**:
- Migrations: `berkas_checklist_templates`, `berkas_checklist_items`, `berkas_checklist_submissions` (morph), `berkas_checklist_submission_items`
- Models polimorfik + `app/Services/ChecklistBerkasService.php`
- UI: `resources/js/pages/admin/checklist-template/*.tsx` + integrated submission UI di halaman usulan KP

**Infrastructure (2 tasks)**:
- `app/Services/NomorSurat/NomorSuratService.php` (interface) + `PlaceholderNomorSuratService.php` (format MA) + binding via service provider
- `app/Services/Sikep/SikepAdapter.php` (interface) + `NullSikepAdapter.php` + binding

**IAM (1 task)**:
- Seeder `database/seeders/PermissionSikepP1Seeder.php` dengan permission baru

**Integration (3 tasks)**:
- Auto-update `riwayat_pangkat` + `pegawai.ref_pangkat_id` saat state transisi ke `SelesaiSKTerbit`
- Console command notifikasi deadline usulan KP (`php artisan sikep:notifikasi-deadline-kp`)
- Audit trail end-to-end (`LogsActivity` di semua model baru)

### Definition of Done

- [ ] `php artisan test --compact` → semua hijau, tidak ada skipped
- [ ] `vendor/bin/pint --test --format agent` → no style issues
- [ ] `npm run build` → sukses, tidak ada warning TypeScript
- [ ] Audit Cuti: dokumen `.sisyphus/evidence/cuti-audit/cuti-audit-report.md` ada dan semua 10 requirement `PASS`
- [ ] Monitoring KP: query `php artisan tinker --execute "app(KenaikanPangkatMonitoringService::class)->getKpStats(periodeBulan: 5, periodeTahun: 2026)"` return data valid untuk bulan 5/2026, dan loop 1..12 return 12 entry valid tanpa exception
- [ ] Workflow KP: QA scenario end-to-end (eligible → usulan → approve → upload SK → riwayat pangkat updated) pass dengan Playwright evidence
- [ ] Checklist: template CRUD + submission + validasi kelengkapan pass via Playwright
- [ ] Nomor surat placeholder: `NomorSuratService::generate('KP.01.1')` return format `W1-U1/{seq}/KP.01.1/{roman_month}/{year}`
- [ ] SikepAdapter: `app(SikepAdapter::class)->pushUsulan($usulan)` return null (default null impl, no throw)
- [ ] IAM permissions baru ter-seed dan middleware `EnsurePermission` block unauthorized user (HTTP 403)

### Must Have

- Hard switch 12 periode (buang logika April/Oktober)
- State machine usulan KP dengan 11 state terdokumentasi
- Checklist polimorfik via `morphTo subject`
- `NomorSuratService` + `SikepAdapter` sebagai interface (bisa di-swap di masa depan)
- Approval berjenjang (minimal 3 level): Kasubbag Kepegawaian → Sekretaris → Ketua Pengadilan
- Auto-update `riwayat_pangkat` saat SK terbit
- Audit trail lengkap (spatie/laravel-activitylog + state history table)
- PDF surat pengantar usulan KP dengan data pegawai + checklist
- Upload SK final (file PDF) ke storage
- Filter monitoring KP per bulan (12 opsi) + unit kerja + golongan

### Must NOT Have (Guardrails)

- **JANGAN** bangun modul mutasi, pensiun, atau P2 lainnya (defer ke plan terpisah)
- **JANGAN** bangun workflow KP Jabatan Fungsional (DUPAK/angka kredit) di P1
- **JANGAN** buat HTTP client konkret untuk SIKEP atau aplikasi surat — hanya interface + null/placeholder
- **JANGAN** modifikasi schema table `pegawai`, `riwayat_pangkat`, `ref_pangkat`, atau table Cuti — hanya tambah relasi via FK di table baru
- **JANGAN** refactor kode Cuti yang sudah berjalan (audit saja)
- **JANGAN** buat abstraction premature: tidak ada base class `BaseWorkflowPengajuan` atau trait workflow generic — setiap modul pengajuan punya model sendiri mengikuti pola Cuti
- **JANGAN** pakai `DB::raw()` atau query manual kecuali untuk kasus yang sudah ada di service existing
- **JANGAN** tulis kode sebelum test (TDD mandatory per CLAUDE.md)
- **JANGAN** skip audit trail — setiap state transition WAJIB logged
- **JANGAN** bangun dashboard monitoring khusus P1 — integrasi widget baru di dashboard existing saja
- **JANGAN** bangun laporan Excel/PDF cross-modul (defer ke P3)
- **JANGAN** bangun KP pilihan/prestasi (hanya KP reguler)
- **JANGAN** handle PPPK di modul KP (PPPK tidak naik pangkat di sistem PNS)
- **JANGAN** over-validation: ikut pola validasi modul Cuti existing, tidak perlu ≥10 rule per field
- **JANGAN** ubah format NIP (sudah 18 digit di existing, hanya validasi ulang)
- **JANGAN** pakai konstanta magic number untuk state — gunakan class state atau enum

---

## Verification Strategy (MANDATORY)

> **ZERO HUMAN INTERVENTION** — semua verifikasi agent-executed. No "user manually tests".

### Test Decision
- **Infrastructure exists**: YES (Pest 4 + pest-plugin-laravel sudah ada di `composer.json`)
- **Automated tests**: YES (TDD) — setiap task RED → GREEN → REFACTOR
- **Framework**: Pest 4 (`php artisan test --compact`)
- **Test filter pattern**: `php artisan test --compact --filter=UsulanKenaikanPangkat` atau `--filter=ChecklistBerkas`

### QA Policy
Setiap task wajib punya minimal 1 happy-path + 1 failure/edge scenario. Evidence ke `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Frontend/UI**: Playwright (playwright skill) — navigate, fill, click, assert DOM, screenshot
- **Backend API**: `curl` dengan `-H "Accept: application/json"`, assert HTTP status + response body fields
- **Service/Model**: `php artisan tinker --execute` untuk isolated check, atau feature test
- **Database**: `database-query` tool untuk verify row state sebelum/sesudah

### Verification Commands (Wave-level)

```bash
# Per task
php artisan test --compact --filter={TaskName}
vendor/bin/pint --dirty --format agent

# Per wave
php artisan test --compact

# Before final verification wave
php artisan test --compact && vendor/bin/pint --test --format agent && npm run build
```

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately — foundation, NO cross-dependencies):
├── T1:  Audit Cuti CUTI-01..CUTI-10                           [quick]
├── T2:  Migrasi berkas_checklist_* (4 tabel polimorfik)        [quick]
├── T3:  Migrasi usulan_kenaikan_pangkat_* (6 tabel + indices)  [quick]
├── T4:  NomorSuratService interface + placeholder impl + tests [quick]
├── T5:  SikepAdapter interface + null impl + tests             [quick]
├── T6:  Seed IAM permissions P1 (KP usulan + checklist)        [quick]
└── T7:  Refactor KenaikanPangkatMonitoringService 12 periode   [unspecified-high]

Wave 2 (After Wave 1 — domain logic, parallel):
├── T8:  Model + 11 State classes UsulanKenaikanPangkat      (deps: T3)    [deep]
├── T9:  Model Checklist + ChecklistBerkasService            (deps: T2)    [deep]
├── T10: Refactor KenaikanPangkatEligibleNotification + cmd  (deps: T7)    [quick]
├── T11: UI monitoring KP (filter 12 bulan + link usulan)    (deps: T7)    [visual-engineering]
└── T12: Template PDF surat pengantar usulan KP              (deps: T4)    [visual-engineering]

Wave 3 (After Wave 2 — business logic & API):
├── T13: UsulanKenaikanPangkatService (CRUD + transitions)   (deps: T8)    [deep]
├── T14: Integrate checklist ke usulan KP + gate validation  (deps: T9, T8)[deep]
├── T15: Form Requests + Policy UsulanKenaikanPangkat        (deps: T8, T6)[quick]
└── T16: Controllers web + API UsulanKenaikanPangkat         (deps: T13, T15) [unspecified-high]

Wave 4 (After Wave 3 — UI pages, MAX PARALLEL):
├── T17: UI eligible list + form usulan KP (dari monitoring) (deps: T16, T14) [visual-engineering]
├── T18: UI inbox approval KP + action approve/reject        (deps: T16)      [visual-engineering]
├── T19: UI detail usulan KP + timeline state + lampiran     (deps: T16, T14) [visual-engineering]
├── T20: UI admin daftar SK + upload SK form                 (deps: T16, T12) [visual-engineering]
└── T21: UI admin checklist template CRUD                    (deps: T9)       [visual-engineering]

Wave 5 (After Wave 4 — integration & polish):
├── T22: Auto-update riwayat_pangkat saat SKTerbit + E2E     (deps: T13, T16) [deep]
├── T23: Console command notifikasi deadline usulan KP       (deps: T13)      [unspecified-high]
└── T24: Audit trail verification + integration tests        (deps: T13, T14) [unspecified-high]

Wave FINAL (After ALL — 4 parallel reviews, then user okay):
├── F1: Plan compliance audit                                (oracle)
├── F2: Code quality review                                  (unspecified-high)
├── F3: Real manual QA end-to-end                            (unspecified-high + playwright)
└── F4: Scope fidelity check                                 (deep)
→ Present results → Wait for explicit user okay

Critical Path: T7 → T8 → T13 → T16 → T17 → T22 → F1-F4 → user okay
Parallel Speedup: ~65% faster than sequential (24 tasks in 5 waves vs 24 sequential)
Max Concurrent: 7 (Wave 1)
```

### Dependency Matrix

| Task | Depends On | Blocks | Wave |
|---|---|---|---|
| T1  | — | — | 1 |
| T2  | — | T9 | 1 |
| T3  | — | T8 | 1 |
| T4  | — | T12 | 1 |
| T5  | — | — | 1 |
| T6  | — | T15 | 1 |
| T7  | — | T10, T11 | 1 |
| T8  | T3 | T13, T14, T15 | 2 |
| T9  | T2 | T14, T21 | 2 |
| T10 | T7 | — | 2 |
| T11 | T7 | T17 | 2 |
| T12 | T4 | T20 | 2 |
| T13 | T8 | T16, T22, T23, T24 | 3 |
| T14 | T9, T8 | T17, T19, T24 | 3 |
| T15 | T8, T6 | T16 | 3 |
| T16 | T13, T15 | T17, T18, T19, T20, T22 | 3 |
| T17 | T16, T14, T11 | — | 4 |
| T18 | T16 | — | 4 |
| T19 | T16, T14 | — | 4 |
| T20 | T16, T12 | — | 4 |
| T21 | T9 | — | 4 |
| T22 | T13, T16 | F1 | 5 |
| T23 | T13 | — | 5 |
| T24 | T13, T14 | F1 | 5 |

### Agent Dispatch Summary

| Wave | Count | Dispatch |
|---|---|---|
| 1 | 7 | T1 → `quick`, T2 → `quick`, T3 → `quick`, T4 → `quick`, T5 → `quick`, T6 → `quick`, T7 → `unspecified-high` |
| 2 | 5 | T8 → `deep`, T9 → `deep`, T10 → `quick`, T11 → `visual-engineering`, T12 → `visual-engineering` |
| 3 | 4 | T13 → `deep`, T14 → `deep`, T15 → `quick`, T16 → `unspecified-high` |
| 4 | 5 | T17-T21 → `visual-engineering` |
| 5 | 3 | T22 → `deep`, T23 → `unspecified-high`, T24 → `unspecified-high` |
| FINAL | 4 | F1 → `oracle`, F2 → `unspecified-high`, F3 → `unspecified-high` (+ playwright skill), F4 → `deep` |

---

## TODOs

> Setiap task: Implementasi + Test = SATU task. WAJIB ada Recommended Agent Profile + Parallelization + QA Scenarios. Task tanpa QA scenario WILL BE REJECTED.

<!-- WAVE 1: FOUNDATION -->

- [x] 1. Audit Verifikasi Modul Cuti CUTI-01..CUTI-10

  **What to do**:
  - Verifikasi setiap requirement CUTI-01 sampai CUTI-10 dari dokumen spec terhadap implementasi existing
  - Untuk setiap requirement: identifikasi file/endpoint/table/UI page yang memenuhi, jalankan QA scenario, catat PASS/PARTIAL/FAIL dengan bukti
  - Periksa `cuti_jenis_master` seeded data untuk CUTI-03 (izin tidak masuk, izin keluar kantor, izin terlambat) — jika belum ada, catat sebagai gap MINOR
  - Tulis laporan audit markdown ke `.sisyphus/evidence/cuti-audit/cuti-audit-report.md`
  - Jalankan semua test existing di `tests/Feature/Cuti/*` dan `tests/Unit/Cuti/*` — pastikan hijau

  **Must NOT do**:
  - JANGAN modifikasi kode Cuti apapun — ini task audit saja
  - JANGAN tambah test baru di Cuti (out of scope)
  - JANGAN refactor master data Cuti

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Task read-only audit + run existing tests + write markdown report. Tidak ada implementasi baru.
  - **Skills**: `pest-testing`
    - `pest-testing`: Untuk verifikasi tests Cuti existing lulus tanpa error
  - **Skills Evaluated but Omitted**:
    - `inertia-react-development`: Audit tidak menulis page baru
    - `tailwindcss-development`: Tidak ada styling

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (dengan T2-T7)
  - **Blocks**: None
  - **Blocked By**: None (mulai immediately)

  **References**:

  **Pattern References**:
  - `app/Http/Controllers/Cuti/PengajuanController.php` - Endpoint pengajuan cuti (CUTI-01, CUTI-04)
  - `app/Http/Controllers/Cuti/ApprovalController.php` - Endpoint approval (CUTI-06, CUTI-07)
  - `app/Http/Controllers/Cuti/PdfController.php` - Generate surat cuti (CUTI-08)
  - `app/Http/Controllers/Cuti/SaldoController.php` - Validasi saldo (CUTI-05, CUTI-09)
  - `app/Http/Controllers/Cuti/AuditController.php` - Rekap cuti (CUTI-09)

  **API/Type References**:
  - `app/Models/Cuti/CutiJenisMaster.php` - Master jenis cuti (CUTI-02, CUTI-03)
  - `app/Models/Cuti/CutiJenisPerStatusPegawai.php` - Perbedaan PNS/PPPK (CUTI-10)
  - `app/Models/Cuti/CutiSaldoLedger.php` - Saldo cuti ledger (CUTI-05)
  - `app/Models/Cuti/CutiPengajuanApprovalStep.php` - Workflow approval (CUTI-06)
  - `app/Models/Cuti/CutiPengajuanApproverHistory.php` - Riwayat persetujuan (CUTI-07)
  - `app/Models/Cuti/CutiPengajuanLampiran.php` - Lampiran (CUTI-04)
  - `app/Models/Cuti/CutiPengajuanPdf.php` - Arsip PDF (CUTI-08)

  **Test References**:
  - `tests/Feature/Cuti/` dan `tests/Unit/Cuti/` - Jalankan semua, verifikasi hijau

  **External References**:
  - `docs/spesifikasi-fitur-administrasi-kepegawaian-belum-tercover.md` section 4.1.3 - Daftar CUTI-01..CUTI-10

  **WHY Each Reference Matters**:
  - Controllers di `app/Http/Controllers/Cuti/` adalah endpoint yang harus diverifikasi eksistensi dan fungsi
  - Model di `app/Models/Cuti/` adalah struktur data yang harus ada untuk setiap requirement
  - Test existing harus lulus sebagai bukti modul masih functional

  **Acceptance Criteria**:

  - [ ] File `.sisyphus/evidence/cuti-audit/cuti-audit-report.md` ada
  - [ ] Laporan berisi tabel 10 baris (CUTI-01..CUTI-10) dengan kolom: Kode, Requirement, Status (PASS/PARTIAL/FAIL), Bukti File/Endpoint, Catatan
  - [ ] `php artisan test --compact --filter=Cuti` → 100% hijau, 0 failed
  - [ ] Jika ada gap minor (contoh: `cuti_jenis_master` missing izin terlambat), tercatat di section "Gaps Ditemukan" dengan severity dan rekomendasi

  **QA Scenarios**:

  ```
  Scenario: Audit lengkap menghasilkan laporan valid
    Tool: Bash
    Preconditions: Branch bersih, DB ter-seed
    Steps:
      1. php artisan test --compact --filter=Cuti
      2. Read .sisyphus/evidence/cuti-audit/cuti-audit-report.md
      3. Grep "| CUTI-" report → count matches
    Expected Result: Test 100% pass. Report contains exactly 10 CUTI rows. Setiap row punya status PASS/PARTIAL/FAIL yang konkret.
    Failure Indicators: Test failing, report kurang dari 10 baris, status kosong/TBD
    Evidence: .sisyphus/evidence/task-1-audit-lengkap.txt (test output + grep result)

  Scenario: CUTI-02 master data berisi minimal jenis wajib
    Tool: Bash (php artisan tinker --execute)
    Preconditions: DB ter-seed
    Steps:
      1. php artisan tinker --execute "echo App\Models\Cuti\CutiJenisMaster::pluck('nama')->join(',');"
      2. Assert output contains: 'tahunan', 'sakit', 'melahirkan', 'alasan penting', 'besar'
    Expected Result: Minimal 5 jenis cuti wajib present. Jika kurang, flag sebagai gap PARTIAL di laporan.
    Evidence: .sisyphus/evidence/task-1-cuti-jenis-master.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-1-audit-lengkap.txt` — output test + grep report
  - [ ] `task-1-cuti-jenis-master.txt` — output tinker query jenis master
  - [ ] `cuti-audit-report.md` — laporan final

  **Commit**: YES
  - Message: `docs(cuti): audit report CUTI-01..CUTI-10 compliance`
  - Files: `.sisyphus/evidence/cuti-audit/cuti-audit-report.md`
  - Pre-commit: `php artisan test --compact --filter=Cuti`

- [x] 2. Migrasi `berkas_checklist_*` (4 Tabel Polimorfik)

  **What to do**:
  - Generate 4 migration via `php artisan make:migration --no-interaction`:
    - `create_berkas_checklist_templates_table` — kolom: `id` (ulid), `kode` (string, unique), `nama` (string), `domain` (string, nullable — untuk hint: `cuti`, `kenaikan_pangkat`, dll), `deskripsi` (text nullable), `aktif` (bool default true), timestamps, softDeletes
    - `create_berkas_checklist_items_table` — kolom: `id` (ulid), `berkas_checklist_template_id` (FK cascade), `kode` (string), `nama` (string), `deskripsi` (text nullable), `is_wajib` (bool default true), `urutan` (int), timestamps, unique composite `(template_id, kode)`
    - `create_berkas_checklist_submissions_table` — kolom: `id` (ulid), `berkas_checklist_template_id` (FK restrictOnDelete), `subject_type` (string), `subject_id` (string), `pegawai_id` (FK restrictOnDelete — pemohon), `status_kelengkapan` (string: `belum_lengkap`, `lengkap`), `persentase` (tinyint 0-100), timestamps. Index `(subject_type, subject_id)`
    - `create_berkas_checklist_submission_items_table` — kolom: `id` (ulid), `berkas_checklist_submission_id` (FK cascade), `berkas_checklist_item_id` (FK restrictOnDelete), `status` (string: `belum_ada`, `ada`, `valid`, `perlu_perbaikan`), `catatan` (text nullable), `file_path` (string nullable), `file_original_name` (string nullable), `file_mime` (string nullable), `file_size` (int nullable), `validated_by` (FK users nullable), `validated_at` (timestamp nullable), timestamps. Unique `(submission_id, item_id)`
  - Tulis test `tests/Feature/Migrations/BerkasChecklistMigrationTest.php` yang verify schema:
    - Table exists
    - Column types match
    - Foreign key constraints bekerja (cascade/restrict)
    - Unique constraints enforce
  - Jalankan `php artisan migrate:fresh` lalu `php artisan test --compact --filter=BerkasChecklistMigration`

  **Must NOT do**:
  - JANGAN bikin seeder data dulu (di task lain)
  - JANGAN bikin model dulu (di T9)
  - JANGAN pakai `bigIncrements` — project pakai ULID
  - JANGAN hardcode domain di kolom `domain` sebagai enum — biarkan string agar extensible

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Migration pure SQL-schema + schema test. Tidak ada business logic.
  - **Skills**: `pest-testing`
    - `pest-testing`: Untuk schema test

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (dengan T1, T3-T7)
  - **Blocks**: T9 (model checklist butuh table ini)
  - **Blocked By**: None

  **References**:

  **Pattern References**:
  - `database/migrations/2026_05_02_*_create_cuti_pengajuan_lampiran_table.php` - Pattern file upload table di project
  - `database/migrations/2026_03_15_*_create_dokumen_pegawai_table.php` - Pattern storage file path
  - `database/migrations/2026_03_15_024651_create_pegawai_table.php` - Pattern ULID + softDeletes

  **Test References**:
  - Project **belum punya direktori `tests/Feature/Migrations/`** — test migration akan jadi yang pertama dengan konvensi ini. Buat direktori baru.
  - Pattern alternatif (jika perlu referensi Pest schema test): gunakan `RefreshDatabase` trait + `Illuminate\Support\Facades\Schema` untuk assert `hasTable` / `hasColumn`. Tidak ada file template existing — buat pattern baru yang reusable untuk T2 dan T3.

  **External References**:
  - Laravel 12 migration docs — polymorphic: `$table->morphs('subject')` atau `$table->string('subject_type'); $table->string('subject_id');`

  **WHY Each Reference Matters**:
  - Cuti_pengajuan_lampiran adalah pattern file upload existing yang harus diikuti (ULID, kolom file_path, file_size, dll)
  - Pegawai migration adalah pattern ULID + softDeletes yang seragam di project

  **Acceptance Criteria**:
  - [ ] 4 file migration dibuat di `database/migrations/`
  - [ ] `php artisan migrate:fresh` → sukses, 0 error
  - [ ] `php artisan test --compact --filter=BerkasChecklistMigration` → 100% hijau
  - [ ] DB schema: run `php artisan db:show --counts` → 4 table baru listed
  - [ ] Foreign key cascade test: delete template → semua items ter-cascade; delete submission → submission_items ter-cascade
  - [ ] Unique composite constraint test: insert duplicate `(template_id, kode)` → throw

  **QA Scenarios**:

  ```
  Scenario: Migration fresh berhasil dan semua constraint aktif
    Tool: Bash (php artisan tinker --execute)
    Preconditions: Branch dengan migration baru, DB bersih
    Steps:
      1. php artisan migrate:fresh --force
      2. php artisan tinker --execute "echo Schema::hasTable('berkas_checklist_templates') ? 'YES' : 'NO';"
      3. Repeat untuk 4 table
      4. Insert 1 template + 2 items + 1 submission + 2 submission_items via tinker
      5. Delete template → verify items dan submission cascade
    Expected Result: 4 "YES", insert sukses, cascade delete bekerja (count items/submission_items = 0 setelah delete template)
    Evidence: .sisyphus/evidence/task-2-migration-schema.txt

  Scenario: Unique constraint enforce duplicate
    Tool: Bash (php artisan tinker --execute)
    Preconditions: Migration sukses, 1 template + 1 item ada
    Steps:
      1. Insert item dengan kode sama untuk template yang sama → expect throw
    Expected Result: QueryException thrown dengan message "UNIQUE constraint failed" atau "Duplicate entry"
    Evidence: .sisyphus/evidence/task-2-unique-constraint.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-2-migration-schema.txt` — output schema check + cascade test
  - [ ] `task-2-unique-constraint.txt` — output QueryException test

  **Commit**: YES
  - Message: `feat(checklist): add berkas checklist tables (polymorphic)`
  - Files: `database/migrations/*berkas_checklist*`, `tests/Feature/Migrations/BerkasChecklistMigrationTest.php`
  - Pre-commit: `php artisan migrate:fresh && php artisan test --compact --filter=BerkasChecklistMigration && vendor/bin/pint --dirty --format agent`

- [x] 3. Migrasi `usulan_kenaikan_pangkat_*` (6 Tabel + Indices)

  **What to do**:
  - Generate 6 migration via `php artisan make:migration --no-interaction`:
    - `create_usulan_kenaikan_pangkat_table` — kolom: `id` (ulid), `pegawai_id` (FK restrictOnDelete), `ref_pangkat_asal_id` (FK restrictOnDelete), `ref_pangkat_tujuan_id` (FK restrictOnDelete), `tmt_pangkat_asal` (date), `periode_usul_bulan` (tinyint 1-12), `periode_usul_tahun` (year), `nomor_usulan` (string nullable), `tanggal_usulan` (date nullable), `state` (string default `Draft`), `catatan_pengusul` (text nullable), `catatan_penolakan` (text nullable), `nomor_sk` (string nullable), `tanggal_sk` (date nullable), `sk_file_path` (string nullable), `sk_file_original_name` (string nullable), `submitted_at` (timestamp nullable), `finalized_at` (timestamp nullable), `created_by` (FK users nullable), timestamps, softDeletes. Index `(periode_usul_tahun, periode_usul_bulan, state)`, `(pegawai_id, state)`
    - `create_usulan_kp_approval_steps_table` — kolom: `id` (ulid), `usulan_kenaikan_pangkat_id` (FK cascade), `urutan` (tinyint), `role_required` (string — `kasubbag_kepegawaian`, `sekretaris`, `ketua_pengadilan`), `approver_user_id` (FK users nullable), `status` (string: `menunggu`, `disetujui`, `ditolak`, `perlu_perbaikan`), `catatan` (text nullable), `acted_at` (timestamp nullable), timestamps. Unique `(usulan_id, urutan)`
    - `create_usulan_kp_approver_history_table` — kolom: `id` (ulid), `usulan_kenaikan_pangkat_id` (FK cascade), `step_urutan` (tinyint), `user_id` (FK users restrictOnDelete), `action` (string: `setuju`, `tolak`, `minta_perbaikan`, `tanda_tangan`, `kirim_biro`, `upload_sk`, `finalize`), `catatan` (text nullable), `meta` (json nullable), `created_at` (timestamp). Index `(usulan_id, created_at)`
    - `create_usulan_kp_state_history_table` — kolom: `id` (ulid), `usulan_kenaikan_pangkat_id` (FK cascade), `from_state` (string nullable), `to_state` (string), `transitioned_by` (FK users nullable), `catatan` (text nullable), `created_at` (timestamp). Index `(usulan_id, created_at)`
    - `create_usulan_kp_lampiran_table` — kolom: `id` (ulid), `usulan_kenaikan_pangkat_id` (FK cascade), `jenis` (string: `sk_cpns`, `sk_pns`, `sk_pangkat_terakhir`, `sk_jabatan_terakhir`, `skp_2_tahun`, `sertifikat_diklat`, `lainnya`), `nama_file` (string), `file_path` (string), `file_original_name` (string), `file_mime` (string), `file_size` (int), `uploaded_by` (FK users), `catatan` (text nullable), timestamps. Index `(usulan_id, jenis)`
    - `create_usulan_kp_pdf_table` — kolom: `id` (ulid), `usulan_kenaikan_pangkat_id` (FK cascade), `jenis_pdf` (string: `surat_pengantar`, `berita_acara_verifikasi`), `nomor_surat` (string), `file_path` (string), `generated_by` (FK users), `generated_at` (timestamp), timestamps. Index `(usulan_id, jenis_pdf)`
  - Tulis test `tests/Feature/Migrations/UsulanKenaikanPangkatMigrationTest.php` yang verify: 6 table exists, FK bekerja, index terbentuk, composite unique enforce
  - Jalankan `php artisan migrate:fresh` lalu `php artisan test --compact --filter=UsulanKenaikanPangkatMigration`

  **Must NOT do**:
  - JANGAN bikin model dulu (di T8)
  - JANGAN seeder
  - JANGAN pakai enum database — pakai string untuk fleksibilitas
  - JANGAN ubah schema `pegawai`, `ref_pangkat`, atau `riwayat_pangkat`

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Migration pure schema. Logika transisi di task lain.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T8 (model butuh table ini)
  - **Blocked By**: None

  **References**:

  **Pattern References**:
  - `database/migrations/2026_05_02_*create_cuti_pengajuan_table.php` - Pattern pengajuan utama
  - `database/migrations/2026_05_02_*create_cuti_pengajuan_approval_steps_table.php` - Pattern approval steps
  - `database/migrations/2026_05_02_*create_cuti_pengajuan_approver_history_table.php` - Pattern approver history
  - `database/migrations/2026_05_02_*create_cuti_pengajuan_state_history_table.php` - Pattern state history
  - `database/migrations/2026_05_02_*create_cuti_pengajuan_lampiran_table.php` - Pattern lampiran
  - `database/migrations/2026_05_02_*create_cuti_pengajuan_pdf_table.php` - Pattern PDF output

  **WHY Each Reference Matters**:
  - 6 migrasi ini WAJIB ikut pola cuti_pengajuan_* agar konsisten. Review kolom, index, FK cascade/restrict untuk replikasi

  **Acceptance Criteria**:
  - [ ] 6 file migration ada di `database/migrations/`
  - [ ] `php artisan migrate:fresh` sukses, 0 error
  - [ ] `php artisan test --compact --filter=UsulanKenaikanPangkatMigration` 100% hijau
  - [ ] Composite index `(periode_usul_tahun, periode_usul_bulan, state)` terbentuk (verify: `SHOW INDEX FROM usulan_kenaikan_pangkat` atau `PRAGMA index_list` untuk sqlite)
  - [ ] FK `ref_pangkat_asal_id` dan `ref_pangkat_tujuan_id` punya restrictOnDelete (tidak boleh ter-cascade)
  - [ ] Delete usulan → cascade ke approval_steps, approver_history, state_history, lampiran, pdf

  **QA Scenarios**:

  ```
  Scenario: 6 tabel ter-create dengan FK dan index benar
    Tool: Bash
    Preconditions: Branch dengan migration baru
    Steps:
      1. php artisan migrate:fresh --force
      2. php artisan tinker --execute "foreach(['usulan_kenaikan_pangkat','usulan_kp_approval_steps','usulan_kp_approver_history','usulan_kp_state_history','usulan_kp_lampiran','usulan_kp_pdf'] as \$t) { echo \$t.':'.(Schema::hasTable(\$t)?'Y':'N').PHP_EOL; }"
    Expected Result: 6 "Y". Tidak ada migration error.
    Evidence: .sisyphus/evidence/task-3-tables-exist.txt

  Scenario: Cascade delete dari usulan ke child
    Tool: Bash (php artisan tinker --execute)
    Preconditions: Seed 1 usulan + 3 approval steps + 2 state history + 2 lampiran
    Steps:
      1. Hapus 1 usulan by id
      2. Count child table rows untuk usulan_id tersebut
    Expected Result: Semua child rows = 0 untuk usulan_id tersebut
    Evidence: .sisyphus/evidence/task-3-cascade-delete.txt

  Scenario: Restrict delete FK ref_pangkat
    Tool: Bash (php artisan tinker --execute)
    Preconditions: 1 usulan refer ref_pangkat_asal X
    Steps:
      1. Coba delete ref_pangkat X → expect QueryException
    Expected Result: Throw dengan message "FOREIGN KEY constraint failed"
    Evidence: .sisyphus/evidence/task-3-restrict-fk.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-3-tables-exist.txt`
  - [ ] `task-3-cascade-delete.txt`
  - [ ] `task-3-restrict-fk.txt`

  **Commit**: YES
  - Message: `feat(kp): add usulan kenaikan pangkat tables`
  - Files: `database/migrations/*usulan_kenaikan_pangkat*`, `database/migrations/*usulan_kp_*`, `tests/Feature/Migrations/UsulanKenaikanPangkatMigrationTest.php`
  - Pre-commit: `php artisan migrate:fresh && php artisan test --compact --filter=UsulanKenaikanPangkatMigration && vendor/bin/pint --dirty --format agent`

- [x] 4. `NomorSuratService` Interface + Placeholder Implementation

  **What to do**:
  - Buat interface `app/Services/NomorSurat/NomorSuratService.php` dengan method:
    - `generate(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): string` — untuk use case sederhana (reserve + confirm atomic)
    - `reserve(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): NomorSuratReservation` (object dengan nomor + id reservation)
    - `confirm(string $reservationId): void`
    - `release(string $reservationId): void`
  - Buat implementasi `app/Services/NomorSurat/PlaceholderNomorSuratService.php`:
    - Format: `{kode_satker}/{nomor_urut}/{klasifikasi}/{bulan_romawi}/{tahun}` — contoh: `W1-U1/123/KP.01.1/V/2026`
    - `kode_satker` dibaca dari config `config('sikep.kode_satker')` (default `W1-U1`)
    - **Sequence reset policy** (KEPUTUSAN EKSPLISIT, wajib dokumentasikan di PHPDoc method `generate`):
      - Nomor urut **reset per tahun per klasifikasi** (bukan per bulan). Contoh: `KP.01.1/2026` dimulai dari 1 pada Januari 2026, continue ke 124, 125, ... hingga Desember 2026, reset ke 1 pada Januari 2027.
      - Bulan romawi ikut nomor surat sebagai **bagian format** (bukan reset trigger). Sekuens continuous sepanjang tahun.
      - Alasan: Standar Mahkamah Agung untuk klasifikasi surat resmi (KP, HK, OT) pakai sekuens annual.
    - Nomor urut di-generate dari table `nomor_surat_sequences` (migration baru): kolom `id` ulid, `klasifikasi` string, `tahun` year, `next_number` int, `updated_at`; unique composite `(klasifikasi, tahun)` — **tidak ada kolom `bulan`** karena sequence per-tahun, bukan per-bulan.
    - Konversi bulan ke romawi via helper method `private function toRoman(int $n): string` (1=I, 2=II, ..., 12=XII)
    - `reserve()` increment `next_number` dan simpan ke table `nomor_surat_reservations` (kolom: `id` ulid, `nomor_urut` int, `nomor_lengkap` string, `klasifikasi`, `tahun`, `bulan`, `status` enum `reserved|confirmed|released`, `reserved_at`, `confirmed_at` nullable, `released_at` nullable)
    - `release()` **hanya tandai status=released**, tidak re-assign nomor yang sudah released (hole policy untuk audit trail).
    - Concurrent safety: wrap increment di DB transaction + `lockForUpdate()` pada row sequence.
  - Buat service provider binding di `app/Providers/AppServiceProvider.php` (register method): bind interface → placeholder impl
  - Buat config file `config/sikep.php` dengan struktur:
    ```php
    return [
        'kode_satker' => env('SIKEP_KODE_SATKER', 'W1-U1'),
        'adapter' => env('SIKEP_ADAPTER', 'null'),
        'kp' => [
            'lookahead_months' => env('SIKEP_KP_LOOKAHEAD_MONTHS', 6),
            'checklist_template_kode' => env('SIKEP_KP_CHECKLIST_KODE', 'checklist-kp-reguler'),
        ],
        'penandatangan' => [
            // Mapping jenis surat ke role IamRole yang berwenang tanda tangan
            'kenaikan_pangkat' => env('SIKEP_PENANDATANGAN_KP', 'ketua_pengadilan'),
            'cuti' => env('SIKEP_PENANDATANGAN_CUTI', 'sekretaris'),
        ],
    ];
    ```
  - Tulis test `tests/Unit/Services/NomorSurat/PlaceholderNomorSuratServiceTest.php`:
    - Format regex compliant
    - Sequence increment per klasifikasi per tahun (**bukan per bulan**)
    - Rollover tahun: generate pada 31 Des 2026 = nomor N, generate pada 1 Jan 2027 = nomor 1 (reset)
    - Generate Januari dan generate Desember dalam tahun sama → nomor urut continuous (bukan reset per bulan)
    - Reserve → confirm: nomor tidak bisa di-reuse
    - Reserve → release: nomor ditandai released, generate berikut dapat nomor +1 (hole policy)
    - Concurrent reserve: pakai DB transaction + `lockForUpdate` (simulasi via pcntl_fork atau assertion lock calls)
  - Tulis test `tests/Unit/Services/NomorSurat/PlaceholderNomorSuratServiceTest.php`:
    - Format regex compliant
    - Sequence increment per klasifikasi per bulan per tahun
    - Rollover tahun (increment dari tahun ke tahun tidak tumpang tindih)
    - Reserve → confirm: nomor tidak bisa di-reuse
    - Reserve → release: nomor bisa di-reuse (release balikin ke sequence? NO, cukup tandai released — tidak re-assign nomor yang sudah released)
    - Concurrent reserve: pakai DB transaction + lock

  **Must NOT do**:
  - JANGAN bikin HTTP client untuk aplikasi surat MA (future)
  - JANGAN hard-code `W1-U1` di service — dari config
  - JANGAN pakai `time()` atau `uniqid()` untuk nomor urut — pakai DB sequence
  - JANGAN expose internal state table ke controller

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Service kecil dengan unit test. Tidak ada UI.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T12 (PDF butuh nomor surat)
  - **Blocked By**: None

  **References**:

  **Pattern References**:
  - `app/Services/KgbMonitoringService.php` - Pattern service class
  - `app/Services/IamAuthorizationService.php` - Pattern service dengan interface
  - `config/auth.php` - Pattern config file

  **External References**:
  - Laravel docs: service provider binding, contextual binding
  - PHP DateTime untuk konversi bulan romawi

  **WHY Each Reference Matters**:
  - Service existing adalah pola yang harus diikuti untuk konsistensi (namespace, dependency injection, return types)
  - Config pattern agar `kode_satker` bisa di-env

  **Acceptance Criteria**:
  - [ ] Interface `NomorSuratService` ada dengan 4 method signature
  - [ ] Placeholder impl bind via service provider (`app(NomorSuratService::class)` return `PlaceholderNomorSuratService`)
  - [ ] Migration `nomor_surat_sequences` + `nomor_surat_reservations` sukses
  - [ ] Config `config/sikep.php` ada dengan keys: `kode_satker`, `adapter`, `kp.lookahead_months`, `kp.checklist_template_kode`, `penandatangan.kenaikan_pangkat`, `penandatangan.cuti`
  - [ ] `php artisan test --compact --filter=NomorSurat` 100% hijau
  - [ ] Format regex `^[A-Z0-9]+-[A-Z0-9]+\/\d+\/[A-Z0-9.]+\/[IVXLCDM]+\/\d{4}$` match output
  - [ ] 2 call berturut untuk klasifikasi sama dalam tahun sama → nomor urut berbeda (1, 2) regardless of bulan
  - [ ] Generate Januari 2026 = nomor 1, generate Juni 2026 = nomor 2 (continuous, **tidak reset per bulan**)
  - [ ] Generate Januari 2027 setelah ada nomor 50 di 2026 → nomor 1 (reset per tahun)
  - [ ] PHPDoc method `generate()` mendokumentasikan sequence reset policy eksplisit

  **QA Scenarios**:

  ```
  Scenario: Generate 3 kali untuk klasifikasi sama → urut incremental
    Tool: Bash (php artisan tinker --execute)
    Preconditions: DB fresh migrated
    Steps:
      1. php artisan tinker --execute "\$s=app(App\Services\NomorSurat\NomorSuratService::class); echo \$s->generate('KP.01.1').PHP_EOL; echo \$s->generate('KP.01.1').PHP_EOL; echo \$s->generate('KP.01.1').PHP_EOL;"
    Expected Result: 3 baris output, nomor urut 1, 2, 3 (bagian ke-2 dari format). Format match regex.
    Evidence: .sisyphus/evidence/task-4-generate-3x.txt

  Scenario: Konversi bulan romawi benar
    Tool: Bash
    Preconditions: Service binding aktif
    Steps:
      1. Untuk bulan 5 Mei → generate → part romawi harus "V"
      2. Untuk bulan 12 Desember → "XII"
    Expected Result: Romawi benar untuk semua 12 bulan
    Evidence: .sisyphus/evidence/task-4-roman.txt

  Scenario: Reserve tanpa confirm → release → nomor berikutnya tidak backfill
    Tool: Bash
    Preconditions: DB fresh
    Steps:
      1. Reserve KP.01.1 → dapat nomor 1
      2. Release reservation
      3. Generate KP.01.1 → dapat nomor 2 (bukan 1)
    Expected Result: Nomor 1 di-skip (hole policy), nomor berikut 2
    Evidence: .sisyphus/evidence/task-4-reserve-release.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-4-generate-3x.txt`
  - [ ] `task-4-roman.txt`
  - [ ] `task-4-reserve-release.txt`

  **Commit**: YES
  - Message: `feat(nomor-surat): add placeholder generator with MA format`
  - Files: `app/Services/NomorSurat/*`, `config/sikep.php`, `database/migrations/*nomor_surat*`, `tests/Unit/Services/NomorSurat/*`
  - Pre-commit: `php artisan test --compact --filter=NomorSurat && vendor/bin/pint --dirty --format agent`

- [x] 5. `SikepAdapter` Interface + Null Implementation

  **What to do**:
  - Buat interface `app/Services/Sikep/SikepAdapter.php` dengan method minimal (semua return nullable/union tipe):
    - `pushUsulan(?object $usulan): ?array` — future: POST ke SIKEP, sekarang return `null`
    - `pullStatusUsulan(string $externalId): ?string` — future: GET status, sekarang return `null`
    - `pullSkFinal(string $externalId): ?array` — future: GET SK file + metadata, sekarang return `null`
    - `isConfigured(): bool` — return `false` untuk null impl
  - Buat DTO sederhana `app/Services/Sikep/UsulanKenaikanPangkatDto.php` (readonly class) dengan properti yang akan di-push: `nip`, `nama_lengkap`, `pangkat_asal_kode`, `pangkat_tujuan_kode`, `tmt_pangkat_asal`, `periode_bulan`, `periode_tahun`, `nomor_usulan`
  - Buat implementasi `app/Services/Sikep/NullSikepAdapter.php`:
    - Semua method return `null` (atau `false` untuk bool)
    - Log info setiap kali dipanggil (via `Illuminate\Support\Facades\Log`) untuk future debugging
  - Bind via `AppServiceProvider::register()`: `$this->app->bind(SikepAdapter::class, NullSikepAdapter::class)`
  - Tambah config section di `config/sikep.php`: `'adapter' => env('SIKEP_ADAPTER', 'null')` untuk future switching
  - Tulis test `tests/Unit/Services/Sikep/NullSikepAdapterTest.php`:
    - `pushUsulan(null)` returns null tanpa throw
    - `pushUsulan($dto)` returns null tanpa throw
    - `isConfigured()` returns false
    - Log dipanggil dengan level info (pakai Log facade fake)

  **Must NOT do**:
  - JANGAN tulis HTTP client (Guzzle/Http client) di P1 — hanya null impl
  - JANGAN bikin queue job SIKEP sekarang
  - JANGAN hardcode credential atau URL SIKEP di kode
  - JANGAN throw exception dari null impl — harus silent (return null + log)
  - JANGAN tambah HTTP mock library ke composer

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Interface + null impl + unit test. Effort minimal.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: None (nanti dipakai di T13/T22 via DI, tapi null impl cukup untuk P1)
  - **Blocked By**: None

  **References**:

  **Pattern References**:
  - `app/Services/IamAuthorizationService.php` - Pattern service di project
  - `app/Providers/AppServiceProvider.php` - Lokasi binding interface

  **External References**:
  - Laravel docs: Interface binding, readonly class PHP 8.2+

  **WHY Each Reference Matters**:
  - Binding pattern harus ikut existing agar service provider tidak fragmented

  **Acceptance Criteria**:
  - [ ] Interface `SikepAdapter` ada dengan 4 method
  - [ ] Null impl ada + bind di AppServiceProvider
  - [ ] DTO readonly class ada
  - [ ] `app(SikepAdapter::class)` return instance `NullSikepAdapter`
  - [ ] `php artisan test --compact --filter=NullSikepAdapter` 100% hijau
  - [ ] Config `config/sikep.php` punya key `adapter`

  **QA Scenarios**:

  ```
  Scenario: Null impl tidak throw untuk semua method
    Tool: Bash (php artisan tinker --execute)
    Preconditions: Binding aktif
    Steps:
      1. php artisan tinker --execute "\$a=app(App\Services\Sikep\SikepAdapter::class); var_dump(\$a->pushUsulan(null)); var_dump(\$a->pullStatusUsulan('x')); var_dump(\$a->pullSkFinal('x')); var_dump(\$a->isConfigured());"
    Expected Result: NULL, NULL, NULL, bool(false). Tidak ada throw/warning.
    Evidence: .sisyphus/evidence/task-5-null-adapter.txt

  Scenario: Log dicatat saat dipanggil
    Tool: Bash (test feature)
    Preconditions: Log facade fake di test
    Steps:
      1. Panggil pushUsulan(null)
      2. Assert Log::shouldReceive('info')->once()
    Expected Result: Test hijau — log dipanggil
    Evidence: .sisyphus/evidence/task-5-log-called.txt (pest output)
  ```

  **Evidence to Capture**:
  - [ ] `task-5-null-adapter.txt`
  - [ ] `task-5-log-called.txt`

  **Commit**: YES
  - Message: `feat(sikep): add adapter interface with null impl`
  - Files: `app/Services/Sikep/*`, `config/sikep.php`, `tests/Unit/Services/Sikep/*`
  - Pre-commit: `php artisan test --compact --filter=NullSikepAdapter && vendor/bin/pint --dirty --format agent`

- [x] 6. Seed IAM Permissions P1 (KP Usulan + Checklist)

  **What to do**:
  - Buat seeder `database/seeders/PermissionSikepP1Seeder.php` yang insert/update permissions ke table `iam_permissions` (model `App\Models\IamPermission`). Catatan: table lama `ref_permissions` sudah di-drop di migrasi `2026_03_21_000003_drop_old_rbac_tables.php`, jadi hanya target `iam_permissions`.
  - Struktur kolom `iam_permissions`: `id` (ulid), `iam_application_id` (FK ke `iam_applications`), `nama`, `slug` (kolom identifier utama — **bukan `code`**), `group`, `keterangan`, timestamps, softDeletes. Unique composite: `(iam_application_id, slug)`.
  - Lookup `iam_application_id` via `IamApplication::where('slug', 'kepegawaian')->firstOrFail()` (seeded oleh `IamSeeder`).
  - Permission yang di-seed (isi kolom `slug` + `nama` human-readable + `group`):
    - **Group `kenaikan-pangkat-usulan`** (11 slug): `kenaikan-pangkat.usulan.view`, `kenaikan-pangkat.usulan.create`, `kenaikan-pangkat.usulan.update`, `kenaikan-pangkat.usulan.delete`, `kenaikan-pangkat.usulan.submit`, `kenaikan-pangkat.usulan.verifikasi-kasubbag`, `kenaikan-pangkat.usulan.verifikasi-sekretaris`, `kenaikan-pangkat.usulan.tanda-tangan-ketua`, `kenaikan-pangkat.usulan.kirim-biro`, `kenaikan-pangkat.usulan.upload-sk`, `kenaikan-pangkat.usulan.finalize`
    - **Group `monitoring`**: `kenaikan-pangkat.monitoring.view` (skip jika sudah ada dari seeder existing)
    - **Group `checklist-template`** (4 slug): `checklist.template.view`, `checklist.template.create`, `checklist.template.update`, `checklist.template.delete`
    - **Group `checklist-submission`** (3 slug): `checklist.submission.view`, `checklist.submission.update-item`, `checklist.submission.validate-item`
  - Upsert via `IamPermission::updateOrCreate(['iam_application_id' => $appId, 'slug' => $slug], ['nama' => ..., 'group' => ..., 'keterangan' => ...])` untuk idempotency.
  - Role mapping (project pakai `iam_role_permissions` pivot):
    - `kasubbag_kepegawaian` → usulan.view/create/update/submit/verifikasi-kasubbag + checklist.*
    - `sekretaris` → usulan.view/verifikasi-sekretaris + checklist.submission.validate-item
    - `ketua_pengadilan` → usulan.view/tanda-tangan-ketua + usulan.kirim-biro
    - `admin_kepegawaian` → semua permission P1 (kecuali admin sudah wildcard system-wide)
    - `pegawai` → usulan.view (own only, enforce di policy, bukan seeder)
  - Lookup role via `IamRole::where('slug', ...)->first()` lalu attach via `IamRolePermission::updateOrCreate(...)` untuk idempotency.
  - Register seeder di `DatabaseSeeder.php` (tambah call setelah `IamSeeder`, jangan hapus seeder existing).
  - Tulis test `tests/Feature/Seeders/PermissionSikepP1SeederTest.php`:
    - Jalankan seeder → semua permission exists di DB via `IamPermission::where('slug', ...)` query
    - Re-run seeder (idempotent) → count tidak berubah
    - Role mapping ter-assign benar via `IamRole::with('permissions')->where('slug', 'kasubbag_kepegawaian')->first()->permissions`

  **Must NOT do**:
  - JANGAN reference table `ref_permissions` — sudah di-drop
  - JANGAN pakai model `App\Models\RefPermission` — deprecated meskipun file class masih ada
  - JANGAN pakai kolom `code` — tidak ada di schema (pakai `slug`)
  - JANGAN hapus permission existing dari seeder lain
  - JANGAN ubah struktur table `iam_permissions`
  - JANGAN assign ke role `admin` jika sudah wildcard (cek eksistensi role dulu)
  - JANGAN hardcode `iam_application_id` literal — lookup by `slug = 'kepegawaian'`
  - JANGAN hardcode role_id — lookup by `slug` IamRole

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Seeder file + test idempotency.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T15 (Form Request/Policy butuh permission ini)
  - **Blocked By**: None

  **References**:

  **Pattern References**:
  - `database/seeders/IamSeeder.php` - Pattern seed `iam_permissions` + `iam_role_permissions` + lookup via slug (pattern aktif di project)
  - `app/Models/IamPermission.php` - Model aktif (fillable `iam_application_id`, `nama`, `slug`, `group`, `keterangan`)
  - `app/Models/IamApplication.php` + `app/Models/IamRole.php` + `app/Models/IamRolePermission.php` - Model dependencies
  - `app/Http/Middleware/VerifyIamPermission.php` (alias `iam.permission`) dan `app/Http/Middleware/EnsurePermission.php` (alias `permission`) - Konsumsi permission oleh middleware (registered di `bootstrap/app.php`)

  **Deprecated (JANGAN PAKAI)**:
  - `database/seeders/RefPermissionSeeder.php` — sudah tidak dipakai, table `ref_permissions` di-drop
  - `app/Models/RefPermission.php` — model deprecated meskipun file masih ada

  **WHY Each Reference Matters**:
  - `IamSeeder` adalah sumber kebenaran struktur permission aktif (nama kolom, relasi role-permission, pola upsert idempotent)
  - Middleware `VerifyIamPermission` / `EnsurePermission` meng-check permission via `slug` — format permission baru HARUS match pola slug yang diterima middleware (dot-separated)

  **Acceptance Criteria**:
  - [ ] Seeder `PermissionSikepP1Seeder.php` ada di `database/seeders/`
  - [ ] `php artisan db:seed --class=PermissionSikepP1Seeder --no-interaction` sukses, exit code 0
  - [ ] Re-run 2x → count permission sama (idempotent)
  - [ ] Query `SELECT COUNT(*) FROM iam_permissions WHERE slug LIKE 'kenaikan-pangkat.usulan.%'` return `11`
  - [ ] Query `SELECT COUNT(*) FROM iam_permissions WHERE slug LIKE 'checklist.%'` return `7`
  - [ ] Query `SELECT COUNT(*) FROM iam_permissions WHERE slug = 'kenaikan-pangkat.monitoring.view'` return `1` (created atau sudah ada)
  - [ ] Role `kasubbag_kepegawaian` memiliki permission `kenaikan-pangkat.usulan.verifikasi-kasubbag` via join `iam_role_permissions`
  - [ ] `php artisan test --compact --filter=PermissionSikepP1Seeder` 100% hijau

  **QA Scenarios**:

  ```
  Scenario: Seed pertama kali → permission baru muncul
    Tool: Bash + database-query
    Preconditions: DB fresh, IamSeeder sudah jalan (application `kepegawaian` + role `kasubbag_kepegawaian`, `sekretaris`, `ketua_pengadilan`, `admin_kepegawaian`, `pegawai` ada)
    Steps:
      1. php artisan db:seed --class=PermissionSikepP1Seeder --no-interaction
      2. database-query: SELECT COUNT(*) FROM iam_permissions WHERE slug LIKE 'kenaikan-pangkat.usulan.%'
      3. database-query: SELECT COUNT(*) FROM iam_permissions WHERE slug LIKE 'checklist.%'
    Expected Result: step 2 return 11, step 3 return 7
    Evidence: .sisyphus/evidence/task-6-seed-count.txt

  Scenario: Idempotency — run 2x tidak duplicate
    Tool: Bash
    Preconditions: Seeder sudah jalan 1x
    Steps:
      1. COUNT baseline
      2. Run seeder lagi
      3. COUNT after → sama dengan baseline
    Expected Result: Count sebelum dan sesudah identik
    Evidence: .sisyphus/evidence/task-6-idempotent.txt

  Scenario: Role mapping terpasang
    Tool: Bash (database-query)
    Preconditions: Seeder sukses
    Steps:
      1. database-query: SELECT p.slug FROM iam_permissions p JOIN iam_role_permissions rp ON rp.iam_permission_id = p.id JOIN iam_roles r ON r.id = rp.iam_role_id WHERE r.slug = 'kasubbag_kepegawaian'
    Expected Result: Return set termasuk `kenaikan-pangkat.usulan.verifikasi-kasubbag` dan `kenaikan-pangkat.usulan.submit`
    Evidence: .sisyphus/evidence/task-6-role-mapping.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-6-seed-count.txt`
  - [ ] `task-6-idempotent.txt`
  - [ ] `task-6-role-mapping.txt`

  **Commit**: YES
  - Message: `feat(iam): seed permissions for SIKEP P1`
  - Files: `database/seeders/PermissionSikepP1Seeder.php`, `database/seeders/DatabaseSeeder.php`, `tests/Feature/Seeders/PermissionSikepP1SeederTest.php`
  - Pre-commit: `php artisan db:seed --class=PermissionSikepP1Seeder && php artisan test --compact --filter=PermissionSikepP1Seeder && vendor/bin/pint --dirty --format agent`

- [x] 7. Refactor `KenaikanPangkatMonitoringService` ke 12 Periode Bulanan (BKN 4/2025)

  **What to do**:
  - Refactor `app/Services/KenaikanPangkatMonitoringService.php`:
    - Hapus method `resolvePeriodeUsulDanBatas()` logic April/Oktober di baris 185-207 — replace dengan logic 12 periode bulanan
    - Hapus `getPeriodeFilterSql()` yang masih pakai case `april`/`oktober` — ganti ke filter by bulan integer (1-12)
    - **Signature baru** (breaking change, hard switch):
      ```php
      public function getUpcomingKenaikanPangkat(
          ?int $periodeBulan = null,  // 1-12
          int $perPage = 15,
          ?string $unitKerjaId = null,
          ?string $golongan = null,
          ?int $periodeTahun = null,
      ): LengthAwarePaginator;

      public function getKpStats(
          ?int $periodeBulan = null,  // 1-12
          ?int $periodeTahun = null,
          ?string $unitKerjaId = null,
          ?string $golongan = null,
      ): array;

      public function getAllPeriodeBulanan(int $tahun): array; // method baru — stats untuk 12 bulan sekaligus
      ```
    - Untuk satu pegawai, `periode_usul` adalah bulan TMT pangkat berikutnya (TMT pangkat aktif + 4 tahun). Contoh: TMT 2022-05-15 → TMT KP berikutnya = 2026-05-15 → periode usul = Mei 2026
    - `batas_usul` = tanggal 1 bulan M-1 (bulan sebelum periode). Contoh: Mei 2026 → batas 1 April 2026
    - **Leap year policy** (eksplisit): gunakan `Carbon::parse($tmt)->addYears(4)` yang handle 29 Feb → 28 Feb (4 tahun berikutnya jika non-leap year, 29 Feb jika leap year). Periode usul ditentukan dari **bulan** hasil `addYears(4)` — untuk TMT 2020-02-29 → 2024-02-29 → periode usul = "Februari 2024". Dokumentasikan di PHPDoc method.
    - **Filter hukuman disiplin aktif** (MANDATORY dari guardrail): exclude pegawai yang punya `hukuman_disiplin` aktif (belum expired). Cek via relation `Pegawai->hukumanDisiplin()` atau kolom `tanggal_akhir_hukuman_disiplin > today`. Jika model/relation belum ada, dokumentasikan assumption + tambah `TODO` comment dengan issue tracker reference.
    - **Filter PPPK** (MANDATORY): exclude pegawai dengan `jenis_pegawai = 'pppk'` (PPPK tidak punya golongan, tidak eligible KP).
    - Pertahankan pattern `->when()` chain dan SQL driver branching (sqlite/mysql) yang sudah ada untuk cross-DB compatibility.
  - Refactor `app/Console/Commands/SendKenaikanPangkatNotification.php` untuk iterate per bulan (bukan hanya April/Oktober). Signature param `--bulan=` + `--tahun=` (lihat T10 untuk detail).
  - Update existing test `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php` dan file test terkait di `tests/Feature/Monitoring/`:
    - Hapus test case April/Oktober (string-based)
    - Tambah test case untuk 12 bulan: setup pegawai dengan TMT berbeda, assert `periode_usul` sesuai bulan TMT + 4 tahun
    - Test edge case: TMT 2022-12-15 → periode usul "Desember 2026"
    - Test leap year: TMT 2020-02-29 → periode usul "Februari 2024" (bulan hasil `addYears(4)`)
    - Test filter bulan spesifik return hanya pegawai dengan periode bulan itu
    - Test filter tahun
    - Test exclude pegawai dengan hukuman disiplin aktif
    - Test exclude pegawai PPPK

  **Must NOT do**:
  - JANGAN biarkan kode legacy (komentar // legacy April/Oktober) — hapus total
  - JANGAN ubah eligibility rule (tetap TMT + 4 tahun)
  - JANGAN ubah struktur return array `getKpStatus()` output keys (breaking change untuk notification) kecuali: `periode_usul` sekarang `{nama_bulan} {tahun}` yang sama dengan existing format (contoh "Mei 2026")
  - JANGAN panggil DB::raw() kompleks kalau bisa Eloquent Builder chain
  - JANGAN refactor method unrelated

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Refactor service dengan perubahan signature + logic bisnis, butuh ketelitian edge case (leap year, year rollover)
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T10 (notification), T11 (UI monitoring)
  - **Blocked By**: None

  **References**:

  **Pattern References**:
  - `app/Services/KenaikanPangkatMonitoringService.php:42-46,116-120,132-150,185-207` - Baris-baris legacy April/Oktober yang harus dihapus
  - `app/Services/KgbMonitoringService.php` - Pattern monitoring service lain (bisa jadi referensi struktur)

  **Test References**:
  - `tests/Feature/Services/KenaikanPangkatMonitoringServiceTest.php` - Test existing yang harus di-update
  - `tests/Feature/Notifications/KenaikanPangkatEligibleNotificationTest.php` - Test dependent

  **External References**:
  - Peraturan BKN Nomor 4 Tahun 2025 tentang periode kenaikan pangkat PNS — 12 periode/tahun (Januari-Desember)
  - Dokumen spec: `docs/spesifikasi-fitur-administrasi-kepegawaian-belum-tercover.md` section 4.4.4

  **WHY Each Reference Matters**:
  - Baris 42-46, 116-120, 132-150, 185-207 adalah target refactor utama (sudah di-identify dari review)
  - Service existing punya pattern `->when()` chain dan SQL driver branching (sqlite/mysql) yang harus dipertahankan untuk cross-DB compatibility

  **Acceptance Criteria**:
  - [ ] Tidak ada string `april` atau `oktober` di `KenaikanPangkatMonitoringService.php` (grep bersih, case-insensitive)
  - [ ] Signature method `getUpcomingKenaikanPangkat` dan `getKpStats` pakai `?int $periodeBulan` + `?int $periodeTahun` (bukan `?string $periode`)
  - [ ] Method baru `getAllPeriodeBulanan(int $tahun): array` ada dan return array 12 entries (bulan 1-12 dengan stats)
  - [ ] `periode_usul` output format: `{NamaBulan} {YYYY}` (contoh "Mei 2026") — format unchanged untuk backward-compat UI/notification
  - [ ] Filter per bulan 1-12 bekerja di SQL level (bukan collection filter setelah fetch)
  - [ ] Pegawai dengan PPPK status **tidak muncul** di hasil query (assert via test case)
  - [ ] Pegawai dengan hukuman disiplin aktif **tidak muncul** di hasil query (assert via test case; jika relation belum ada, skip assertion + log TODO)
  - [ ] Leap year TMT 2020-02-29 → `periode_usul` == "Februari 2024" (assert eksplisit)
  - [ ] `php artisan test --compact --filter=KenaikanPangkatMonitoring` 100% hijau (minimal 18 test cases termasuk 12 bulan + edge + PPPK + hukuman disiplin)
  - [ ] `vendor/bin/pint --test --format agent` pada file yang diubah → clean
  - [ ] Backward-compat: controller `MonitoringKenaikanPangkatController` masih berfungsi (update call site sekalian di T11)

  **QA Scenarios**:

  ```
  Scenario: Stats per bulan return data konsisten untuk 12 bulan
    Tool: Bash (php artisan tinker --execute)
    Preconditions: DB dengan test data (minimal 12 pegawai dengan TMT berbeda, 1 per bulan)
    Steps:
      1. php artisan tinker --execute "\$s=app(App\Services\KenaikanPangkatMonitoringService::class); for (\$b=1; \$b<=12; \$b++) { \$st=\$s->getKpStats(periodeBulan:\$b, periodeTahun:2026); echo \"bln \$b: total=\${st['total']}\".PHP_EOL; }"
    Expected Result: 12 baris output, masing-masing total >= 1 (sesuai test data). Tidak ada exception.
    Evidence: .sisyphus/evidence/task-7-stats-12-bulan.txt

  Scenario: TMT Desember → periode_usul Desember tahun +4
    Tool: Bash (tinker)
    Preconditions: Pegawai dengan riwayat_pangkat aktif TMT 2022-12-01
    Steps:
      1. Fetch status via getKpStatus()
      2. Assert periode_usul == "Desember 2026"
      3. Assert batas_usul == "2026-11-01"
    Expected Result: Match assertion
    Evidence: .sisyphus/evidence/task-7-tmt-desember.txt

  Scenario: Leap year 29 Februari → periode usul Februari tahun +4
    Tool: Bash (tinker)
    Preconditions: Pegawai dengan TMT 2020-02-29 (leap year)
    Steps:
      1. php artisan tinker --execute "\$p=App\Models\Pegawai::factory()->has(App\Models\RiwayatPangkat::factory()->state(['tmt' => '2020-02-29', 'is_aktif' => true]))->create(); \$s=app(App\Services\KenaikanPangkatMonitoringService::class); \$st=\$s->getKpStatus(\$p); echo \$st['periode_usul'];"
      2. Assert output == "Februari 2024"
      3. Assert tidak throw exception
    Expected Result: "Februari 2024" (hasil `Carbon::parse('2020-02-29')->addYears(4)` = 2024-02-29 karena 2024 juga leap year)
    Evidence: .sisyphus/evidence/task-7-leap-year.txt

  Scenario: Pegawai PPPK di-exclude dari eligible
    Tool: Bash (tinker)
    Preconditions: Seed 1 pegawai PNS + 1 pegawai PPPK dengan TMT sama
    Steps:
      1. Fetch getUpcomingKenaikanPangkat() tanpa filter
      2. Assert hanya PNS yang muncul (count == 1 untuk filter TMT tersebut)
    Expected Result: PPPK tidak muncul
    Evidence: .sisyphus/evidence/task-7-exclude-pppk.txt

  Scenario: Pegawai dengan hukuman disiplin aktif di-exclude
    Tool: Bash (tinker)
    Preconditions: Seed 2 pegawai PNS eligible, 1 dengan hukuman disiplin aktif (jika schema support)
    Steps:
      1. Fetch getUpcomingKenaikanPangkat()
      2. Assert pegawai dengan hukuman aktif tidak muncul
    Expected Result: 1 pegawai, bukan 2 (jika schema hukuman disiplin belum ada, skip scenario ini dan catat sebagai TODO di test)
    Evidence: .sisyphus/evidence/task-7-exclude-hukuman.txt

  Scenario: No legacy string di source
    Tool: Bash
    Preconditions: Refactor selesai
    Steps:
      1. grep -i 'april\|oktober' app/Services/KenaikanPangkatMonitoringService.php
    Expected Result: No matches (exit code 1 dari grep)
    Evidence: .sisyphus/evidence/task-7-no-legacy.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-7-stats-12-bulan.txt`
  - [ ] `task-7-tmt-desember.txt`
  - [ ] `task-7-leap-year.txt`
  - [ ] `task-7-exclude-pppk.txt`
  - [ ] `task-7-exclude-hukuman.txt` (atau skip note)
  - [ ] `task-7-no-legacy.txt`

  **Commit**: YES
  - Message: `refactor(kp-monitoring): hard switch to 12 monthly periods (BKN 4/2025)`
  - Files: `app/Services/KenaikanPangkatMonitoringService.php`, `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`, `app/Console/Commands/SendKenaikanPangkatNotification.php` (jika ada), `tests/Feature/Services/KenaikanPangkatMonitoringServiceTest.php`
  - Pre-commit: `php artisan test --compact --filter=KenaikanPangkat && vendor/bin/pint --dirty --format agent`

<!-- WAVE 2: DOMAIN LOGIC -->

- [x] 8. Model `UsulanKenaikanPangkat` + 11 State Classes

  **What to do**:
  - Buat 6 model di `app/Models/UsulanKenaikanPangkat/`:
    - `UsulanKenaikanPangkat.php` (model utama, extend Model, uses `HasUlids`, `SoftDeletes`, `LogsActivity`, `HasStates`)
      - Fillable: `pegawai_id`, `ref_pangkat_asal_id`, `ref_pangkat_tujuan_id`, `tmt_pangkat_asal`, `periode_usul_bulan`, `periode_usul_tahun`, `nomor_usulan`, `tanggal_usulan`, `state`, `catatan_pengusul`, `catatan_penolakan`, `nomor_sk`, `tanggal_sk`, `sk_file_path`, `sk_file_original_name`, `submitted_at`, `finalized_at`, `created_by`
      - Casts: `tmt_pangkat_asal => date`, `tanggal_usulan => date`, `tanggal_sk => date`, `submitted_at => datetime`, `finalized_at => datetime`, `periode_usul_bulan => int`, `periode_usul_tahun => int`, `state => UsulanKenaikanPangkatState::class`
      - Relationships: `pegawai() BelongsTo`, `pangkatAsal() BelongsTo RefPangkat`, `pangkatTujuan() BelongsTo RefPangkat`, `approvalSteps() HasMany`, `approverHistory() HasMany`, `stateHistory() HasMany`, `lampiran() HasMany`, `pdfs() HasMany`, `createdBy() BelongsTo User`, `checklistSubmission() MorphOne BerkasChecklistSubmission`
      - `getActivitylogOptions()`: `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()->useLogName('usulan_kenaikan_pangkat')`
    - `UsulanKpApprovalStep.php` (fillable: usulan_kenaikan_pangkat_id, urutan, role_required, approver_user_id, status, catatan, acted_at; cast acted_at datetime)
    - `UsulanKpApproverHistory.php` (fillable: usulan_kenaikan_pangkat_id, step_urutan, user_id, action, catatan, meta; cast meta array; no updated_at — immutable)
    - `UsulanKpStateHistory.php` (fillable: usulan_kenaikan_pangkat_id, from_state, to_state, transitioned_by, catatan; immutable)
    - `UsulanKpLampiran.php` (fillable: usulan_kenaikan_pangkat_id, jenis, nama_file, file_path, file_original_name, file_mime, file_size, uploaded_by, catatan)
    - `UsulanKpPdf.php` (fillable: usulan_kenaikan_pangkat_id, jenis_pdf, nomor_surat, file_path, generated_by, generated_at; cast generated_at datetime)
  - Buat factory `database/factories/UsulanKenaikanPangkat/UsulanKenaikanPangkatFactory.php` untuk testing
  - Buat 11 state class di `app/States/UsulanKenaikanPangkat/` mengikuti konvensi existing `app/States/Cuti/` (**suffix `State`** + property `$name` UPPERCASE):
    - `UsulanKenaikanPangkatState.php` (abstract base extend `Spatie\ModelStates\State`; define config dengan default `DraftState::class` + semua allowedTransitions via override method `config()`)
    - State concrete (masing-masing 1 file, semua **dengan suffix `State`**, `$name` value UPPERCASE):
      1. `DraftState.php` (`public static $name = 'DRAFT';`) — transisi: `DiajukanState`, `DibatalkanState`
      2. `DiajukanState.php` (`'DIAJUKAN'`) — transisi: `DiverifikasiKasubbagState`, `PerluPerbaikanState`, `DibatalkanState`
      3. `DiverifikasiKasubbagState.php` (`'DIVERIFIKASI_KASUBBAG'`) — transisi: `DiverifikasiSekretarisState`, `PerluPerbaikanState`, `DitolakState`
      4. `DiverifikasiSekretarisState.php` (`'DIVERIFIKASI_SEKRETARIS'`) — transisi: `DitandatanganiKetuaState`, `PerluPerbaikanState`, `DitolakState`
      5. `DitandatanganiKetuaState.php` (`'DITANDATANGANI_KETUA'`) — transisi: `DikirimBiroState`
      6. `DikirimBiroState.php` (`'DIKIRIM_BIRO'`) — transisi: `MenungguSkState`, `PerluPerbaikanState`
      7. `MenungguSkState.php` (`'MENUNGGU_SK'`) — transisi: `SelesaiSkTerbitState`, `PerluPerbaikanState`, `DitolakState`
      8. `SelesaiSkTerbitState.php` (`'SELESAI_SK_TERBIT'`) — transisi: none (terminal happy-path)
      9. `PerluPerbaikanState.php` (`'PERLU_PERBAIKAN'`) — transisi: `DraftState`, `DibatalkanState`
      10. `DitolakState.php` (`'DITOLAK'`) — transisi: none (terminal fail)
      11. `DibatalkanState.php` (`'DIBATALKAN'`) — transisi: none (terminal)
    - Setiap state class punya property `public static $name` (string UPPERCASE untuk DB serialization, konsisten dengan `app/States/Cuti/DraftState.php` yang pakai `'DRAFT'`) dan method `public function label(): string` yang return display Bahasa Indonesia (contoh: `'Draft'`, `'Diajukan'`, `'Diverifikasi Kasubbag'`, `'Selesai SK Terbit'`)
    - Override `UsulanKenaikanPangkatState::config(): StateConfig` dengan default `DraftState::class` dan semua `->allowTransition(FromState::class, ToState::class)` combinations
  - Tulis test `tests/Feature/Models/UsulanKenaikanPangkat/UsulanKenaikanPangkatTest.php`:
    - Create via factory
    - Default state = `DraftState` (via `expect($u->state)->toBeInstanceOf(DraftState::class)`)
    - Transisi valid: `$u->state->transitionTo(DiajukanState::class)` berhasil, DB `state` column == `'DIAJUKAN'`
    - Transisi invalid (`DraftState` → `SelesaiSkTerbitState`) throw `CouldNotPerformTransition`
    - Activity log ter-create saat update
    - Relationship chains bekerja (usulan → pegawai, lampiran, approvalSteps, stateHistory)
    - Checklist morphOne resolve ke `BerkasChecklistSubmission`
    - Factory variants: `state('diajukan')` set state ke `DiajukanState`, `state('sk_terbit')` ke `SelesaiSkTerbitState`

  **Must NOT do**:
  - JANGAN pakai `string` untuk state column tanpa state class — wajib pakai `spatie/laravel-model-states`
  - JANGAN commit state transition language mismatch (DB string = class `$name` property)
  - JANGAN panggil `save()` manual setelah transisi — state machine handle itu
  - JANGAN tambah kolom baru ke migration T3 — kalau perlu, buat migration baru di task ini (explicit, jangan silent)
  - JANGAN bikin base model abstract untuk workflow (cukup pakai trait `HasStates` + `LogsActivity` langsung)
  - JANGAN import `HasFactory` trait jika project pakai factory otomatis — cek existing pattern di `Pegawai.php`

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: 11 state class + 6 model + factory + test. Butuh pemahaman state machine pattern mendalam dan konsistensi transisi.
  - **Skills**: `pest-testing`
    - `pest-testing`: Untuk model test + state transition test

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (dengan T9, T10, T11, T12)
  - **Blocks**: T13, T14, T15 (semua butuh model + state)
  - **Blocked By**: T3 (migration)

  **References**:

  **Pattern References**:
  - `app/Models/Cuti/CutiPengajuan.php` - Pattern model utama dengan HasStates + LogsActivity
  - `app/Models/Cuti/CutiPengajuanStateHistory.php` - Pattern state history
  - `app/Models/Cuti/CutiPengajuanLampiran.php` - Pattern lampiran
  - `app/Models/Cuti/CutiPengajuanApprovalStep.php` - Pattern approval step
  - `app/States/Cuti/DraftState.php`, `app/States/Cuti/DiajukanState.php`, `app/States/Cuti/PengajuanState.php` - Pattern state class existing (suffix `State` + `$name` UPPERCASE)
  - `tests/Unit/Cuti/States/PengajuanStateTransitionTest.php` - Pattern test state transition

  **API/Type References**:
  - `app/Models/Pegawai.php` - Pattern ULID + casts enum + LogsActivity

  **Test References**:
  - `tests/Unit/Cuti/States/PengajuanStateTransitionTest.php` - Pattern test state transition existing
  - `tests/Unit/Cuti/CutiPengajuanPolicyTest.php` - Pattern test yang involve state

  **External References**:
  - `spatie/laravel-model-states` v2.7 docs — `StateConfig`, `allowTransition`, `CouldNotPerformTransition`
  - `spatie/laravel-activitylog` v5 docs — `LogOptions`, `useLogName`

  **WHY Each Reference Matters**:
  - CutiPengajuan adalah blueprint lengkap yang harus di-replikasi — setiap kolom/relation/trait harus mirror
  - Existing `app/States/Cuti/DraftState.php` menunjukkan **konvensi wajib** (suffix `State` di nama class, `public static $name = 'DRAFT'` uppercase untuk DB serialization, method `label()` untuk display) — plan HARUS mengikuti ini 1:1 untuk konsistensi project

  **Acceptance Criteria**:
  - [ ] 6 model file ada di `app/Models/UsulanKenaikanPangkat/`
  - [ ] 11 state class file ada di `app/States/UsulanKenaikanPangkat/` dengan nama file **suffix `State`** (contoh: `DraftState.php`, bukan `Draft.php`)
  - [ ] 1 file abstract base `UsulanKenaikanPangkatState.php` (parent class untuk 11 state)
  - [ ] Setiap state class punya property `public static $name` dengan value UPPERCASE (contoh: `'DRAFT'`, `'DIAJUKAN'`, `'SELESAI_SK_TERBIT'`)
  - [ ] Setiap state class punya method `label(): string` return Bahasa Indonesia display
  - [ ] Factory ada dan `UsulanKenaikanPangkat::factory()->create()` sukses
  - [ ] Default state = `DraftState` saat create (`expect($u->state)->toBeInstanceOf(DraftState::class)`)
  - [ ] `$u->state->transitionTo(DiajukanState::class)` sukses, DB kolom `state` update ke value `'DIAJUKAN'`
  - [ ] `$u->state->transitionTo(SelesaiSkTerbitState::class)` dari `DraftState` → throw `Spatie\ModelStates\Exceptions\CouldNotPerformTransition`
  - [ ] Activity log record dibuat saat update (check `$u->activities()->count() > 0`)
  - [ ] `php artisan test --compact --filter=UsulanKenaikanPangkat` 100% hijau (minimal 15 test cases: create, default state, 10 valid transitions, invalid transition, relationship, checklist morph, activity log, factory variants)
  - [ ] `vendor/bin/pint --dirty --format agent` → clean

  **QA Scenarios**:

  ```
  Scenario: Default state dan transisi happy-path lengkap
    Tool: Bash (php artisan tinker --execute)
    Preconditions: Migration + model ready, Pegawai factory tersedia
    Steps:
      1. Create usulan via factory
      2. Assert `get_class($u->state) === DraftState::class`
      3. transitionTo(DiajukanState) → DiverifikasiKasubbagState → DiverifikasiSekretarisState → DitandatanganiKetuaState → DikirimBiroState → MenungguSkState → SelesaiSkTerbitState
      4. Assert final state = SelesaiSkTerbitState
      5. Check state_history count = 7
    Expected Result: 7 transisi sukses tanpa throw. State history tercatat 7 rows.
    Evidence: .sisyphus/evidence/task-8-happy-path.txt

  Scenario: Invalid transition throws
    Tool: Bash (tinker)
    Preconditions: Usulan dengan state DraftState
    Steps:
      1. Try `$u->state->transitionTo(SelesaiSkTerbitState::class)` dari DraftState
      2. Expect CouldNotPerformTransition exception
    Expected Result: Exception thrown dengan class `Spatie\ModelStates\Exceptions\CouldNotPerformTransition`
    Evidence: .sisyphus/evidence/task-8-invalid-transition.txt

  Scenario: Activity log tercatat
    Tool: Bash (tinker)
    Preconditions: Factory create usulan, update catatan
    Steps:
      1. Create → activities count
      2. Update catatan_pengusul
      3. activities count setelah update
    Expected Result: Count bertambah minimal 1 setelah update
    Evidence: .sisyphus/evidence/task-8-activity-log.txt

  Scenario: Polymorphic checklist relationship resolve
    Tool: Bash (tinker)
    Preconditions: T9 done juga (atau stub)
    Steps:
      1. Usulan->checklistSubmission returns null (belum ada) atau relationship object
    Expected Result: Tidak throw, tipe relationship benar
    Evidence: .sisyphus/evidence/task-8-morph-checklist.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-8-happy-path.txt`
  - [ ] `task-8-invalid-transition.txt`
  - [ ] `task-8-activity-log.txt`
  - [ ] `task-8-morph-checklist.txt`

  **Commit**: YES
  - Message: `feat(kp): add UsulanKenaikanPangkat model + 11 state classes`
  - Files: `app/Models/UsulanKenaikanPangkat/*`, `app/States/UsulanKenaikanPangkat/*`, `database/factories/UsulanKenaikanPangkat/*`, `tests/Feature/Models/UsulanKenaikanPangkat/*`
  - Pre-commit: `php artisan test --compact --filter=UsulanKenaikanPangkat && vendor/bin/pint --dirty --format agent`

- [x] 9. Model Checklist Polimorfik + `ChecklistBerkasService`

  **What to do**:
  - Buat 4 model di `app/Models/BerkasChecklist/`:
    - `BerkasChecklistTemplate.php` (fillable: kode, nama, domain, deskripsi, aktif; scope `aktif()`, scope `byDomain($d)`; relations: `items() HasMany`, uses `LogsActivity`, `SoftDeletes`)
    - `BerkasChecklistItem.php` (fillable: berkas_checklist_template_id, kode, nama, deskripsi, is_wajib, urutan; relations: `template() BelongsTo`; scope `wajib()`, `ordered()`)
    - `BerkasChecklistSubmission.php` (fillable: berkas_checklist_template_id, subject_type, subject_id, pegawai_id, status_kelengkapan, persentase; relations: `subject() MorphTo`, `template() BelongsTo`, `pegawai() BelongsTo`, `items() HasMany BerkasChecklistSubmissionItem`; uses `LogsActivity`)
    - `BerkasChecklistSubmissionItem.php` (fillable: berkas_checklist_submission_id, berkas_checklist_item_id, status, catatan, file_path, file_original_name, file_mime, file_size, validated_by, validated_at; relations: `submission() BelongsTo`, `item() BelongsTo BerkasChecklistItem`, `validator() BelongsTo User`)
  - Buat service `app/Services/BerkasChecklist/ChecklistBerkasService.php`:
    - `createSubmission(BerkasChecklistTemplate $template, Model $subject, Pegawai $pegawai): BerkasChecklistSubmission` — buat submission + auto-create submission_items dari template items dengan status default `belum_ada`
    - `updateItemStatus(BerkasChecklistSubmissionItem $item, string $status, ?string $catatan = null): void` — validasi status enum, update, panggil recalculate
    - `uploadFile(BerkasChecklistSubmissionItem $item, UploadedFile $file): void` — store file di `storage/app/berkas-checklist/{submission_id}/`, update metadata, status otomatis ke `ada`
    - `validateItem(BerkasChecklistSubmissionItem $item, User $validator, string $newStatus, ?string $catatan = null): void` — `valid` / `perlu_perbaikan`, record `validated_by` + `validated_at`
    - `recalculatePersentase(BerkasChecklistSubmission $submission): void` — hitung `(count valid / count wajib) * 100`. Set `status_kelengkapan` = `lengkap` jika semua wajib `valid`, else `belum_lengkap`
    - `isComplete(BerkasChecklistSubmission $submission): bool` — gate method untuk workflow: return true hanya jika semua wajib `valid`
  - Buat factory untuk 4 model
  - Buat seeder default template: `database/seeders/ChecklistKenaikanPangkatSeeder.php` dengan template `checklist-kp-reguler` berisi items dari spec 4.5.4:
    - `sk_cpns` (wajib), `sk_pns` (wajib), `sk_pangkat_terakhir` (wajib), `sk_jabatan_terakhir` (wajib), `skp_2_tahun` (wajib), `sertifikat_diklat` (opsional), `dokumen_pendukung_lain` (opsional)
  - Tulis test `tests/Feature/Services/BerkasChecklist/ChecklistBerkasServiceTest.php`:
    - `createSubmission` auto-create all items
    - `updateItemStatus` valid status hanya accept enum values
    - `uploadFile` menyimpan file + update metadata
    - `validateItem` record validator + timestamp
    - `recalculatePersentase`: 0% / 50% / 100%
    - `isComplete` true hanya kalau semua wajib `valid`
    - Polymorphic `subject()` resolve ke `UsulanKenaikanPangkat` (stub model)

  **Must NOT do**:
  - JANGAN buat enum DB — status pakai string + validation constant
  - JANGAN hardcode domain `kenaikan_pangkat` di service — ambil dari template
  - JANGAN validasi file size/mime di service (itu tugas Form Request di T15)
  - JANGAN expose `BerkasChecklistSubmission` langsung ke controller tanpa lewat service

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: 4 model polimorfik + service dengan multi-method + file upload + rekalkulasi.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T14, T21
  - **Blocked By**: T2 (migration)

  **References**:

  **Pattern References**:
  - `app/Models/DokumenPegawai.php` - Pattern file upload metadata
  - `app/Models/Cuti/CutiPengajuanLampiran.php` - Pattern lampiran dengan metadata + storage
  - `app/Services/RiwayatPangkatService.php` - Pattern service class

  **API/Type References**:
  - Laravel `MorphTo` relationship docs
  - `Illuminate\Http\UploadedFile::store()`

  **WHY Each Reference Matters**:
  - DokumenPegawai dan CutiPengajuanLampiran adalah 2 pattern file upload existing — konsisten dengan style yang sama (nama kolom, path storage, metadata)

  **Acceptance Criteria**:
  - [ ] 4 model + factory + service + seeder ada
  - [ ] Run seeder: `php artisan db:seed --class=ChecklistKenaikanPangkatSeeder` → template + 7 items ter-create
  - [ ] `createSubmission` auto-create submission_items dengan count sama dengan template.items
  - [ ] `recalculatePersentase` logic: 0 valid dari 5 wajib → 0%. 3 valid dari 5 wajib → 60%. 5 dari 5 → 100% + status `lengkap`
  - [ ] `isComplete` false jika ada wajib belum valid
  - [ ] Upload file: path tersimpan di `storage/app/berkas-checklist/{submission_id}/{uuid}-{original_name}`, metadata (size, mime) accurate
  - [ ] `php artisan test --compact --filter=ChecklistBerkas` 100% hijau (minimal 12 test cases)
  - [ ] Polymorphic: `BerkasChecklistSubmission::factory()->for(UsulanKenaikanPangkat::factory(), 'subject')->create()` bekerja

  **QA Scenarios**:

  ```
  Scenario: Create submission auto-populate items
    Tool: Bash (tinker)
    Preconditions: Seeder checklist-kp-reguler sukses, Pegawai + UsulanKenaikanPangkat factory
    Steps:
      1. Template = find by kode 'checklist-kp-reguler'
      2. Service->createSubmission(template, usulan, pegawai)
      3. Assert submission.items count == 7
      4. Assert semua items status = 'belum_ada'
    Expected Result: Submission dengan 7 items, semua 'belum_ada'
    Evidence: .sisyphus/evidence/task-9-create-submission.txt

  Scenario: Persentase akurat + gate workflow
    Tool: Bash (tinker)
    Preconditions: Submission baru dengan 7 items (5 wajib, 2 opsional)
    Steps:
      1. Update 3 item wajib ke 'valid'
      2. recalculate
      3. Assert persentase == 60 (3/5)
      4. Assert status == 'belum_lengkap'
      5. Update 2 wajib remaining ke 'valid'
      6. recalculate
      7. Assert persentase == 100, status == 'lengkap', isComplete == true
    Expected Result: Assertion sesuai
    Evidence: .sisyphus/evidence/task-9-persentase.txt

  Scenario: File upload menyimpan file valid
    Tool: Bash (pest feature test pakai UploadedFile::fake())
    Preconditions: Submission + item target
    Steps:
      1. Fake PDF 100KB
      2. uploadFile(item, fakePdf)
      3. Assert Storage::disk('local')->exists(item->file_path)
      4. Assert item->file_mime == 'application/pdf'
      5. Assert item->status == 'ada'
    Expected Result: File ter-store, metadata terisi, status auto-update
    Evidence: .sisyphus/evidence/task-9-upload-file.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-9-create-submission.txt`
  - [ ] `task-9-persentase.txt`
  - [ ] `task-9-upload-file.txt`

  **Commit**: YES
  - Message: `feat(checklist): add polymorphic models + ChecklistBerkasService + default KP template seeder`
  - Files: `app/Models/BerkasChecklist/*`, `app/Services/BerkasChecklist/*`, `database/factories/BerkasChecklist/*`, `database/seeders/ChecklistKenaikanPangkatSeeder.php`, `tests/Feature/Services/BerkasChecklist/*`
  - Pre-commit: `php artisan test --compact --filter=ChecklistBerkas && vendor/bin/pint --dirty --format agent`

- [x] 10. Refactor Notification + Command `KenaikanPangkatEligible` untuk 12 Periode

  **What to do**:
  - Update `app/Notifications/KenaikanPangkatEligibleNotification.php`:
    - Accept `int $periodeBulan, int $periodeTahun` (bukan lagi `april`/`oktober`)
    - Toline message: "Anda eligible kenaikan pangkat periode {NamaBulan} {Tahun}. Batas usul: {tanggal}."
    - Channel: `['database', 'mail']`
  - Update / buat command `app/Console/Commands/SendKenaikanPangkatNotification.php`:
    - Signature: `sikep:notifikasi-kp {--bulan=} {--tahun=}`
    - Default: jalankan untuk bulan berjalan + 6 bulan ke depan (rolling)
    - Panggil `KenaikanPangkatMonitoringService::getUpcomingKenaikanPangkat()` dengan `periodeBulan` per bulan yang di-iterate
    - Filter hanya yang `status == 'Mendekati Eligible'` AND belum ada usulan KP untuk periode ini (avoid duplicate notification)
    - Send notification ke User yang link ke Pegawai (via `pegawai_id`)
  - Register command di `bootstrap/app.php` atau sudah auto-load (Laravel 12: auto)
  - Update `tests/Feature/Notifications/KenaikanPangkatEligibleNotificationTest.php`:
    - Test per 12 bulan
    - Test tidak kirim ke pegawai yang sudah punya usulan active
    - Test kirim ke DB + Mail channel

  **Must NOT do**:
  - JANGAN kirim notification untuk status `Sudah Eligible` yang belum buat usulan — hanya `Mendekati Eligible` (biar tidak spam)
  - JANGAN hardcode 6 bulan — config `sikep.kp.lookahead_months` (default 6)
  - JANGAN panggil query di command — delegasikan ke service

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Refactor notification + command existing. Tidak bikin file baru (kecuali config key).
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: None
  - **Blocked By**: T7

  **References**:

  **Pattern References**:
  - `app/Notifications/KgbJatuhTempoNotification.php` - Pattern notification serupa
  - `app/Console/Commands/SendKgbNotification.php` - Pattern console command

  **Test References**:
  - `tests/Feature/Notifications/KgbJatuhTempoNotificationTest.php` - Pattern test

  **WHY Each Reference Matters**:
  - KGB notification adalah twin pattern yang harus di-mirror (channel, test setup)

  **Acceptance Criteria**:
  - [ ] Notification signature accept periodeBulan + periodeTahun
  - [ ] Command `sikep:notifikasi-kp` bekerja: `php artisan sikep:notifikasi-kp --bulan=5 --tahun=2026`
  - [ ] Config key `sikep.kp.lookahead_months` ada
  - [ ] Idempotent: run 2x tidak kirim notification duplikat ke pegawai yang sama untuk periode yang sama
  - [ ] `php artisan test --compact --filter=KenaikanPangkatEligibleNotification` 100% hijau

  **QA Scenarios**:

  ```
  Scenario: Command kirim notification ke pegawai mendekati eligible
    Tool: Bash
    Preconditions: Seed 3 pegawai mendekati eligible untuk bulan Mei 2026
    Steps:
      1. Notification::fake()
      2. php artisan sikep:notifikasi-kp --bulan=5 --tahun=2026
      3. Notification::assertSentTo(3 pegawai)
    Expected Result: 3 notification sent
    Evidence: .sisyphus/evidence/task-10-cmd-send.txt

  Scenario: Skip pegawai yang sudah ada usulan active
    Tool: Bash
    Preconditions: 1 dari 3 pegawai sudah punya usulan KP state=Diajukan bulan Mei 2026
    Steps:
      1. Run command
      2. Assert only 2 notifications sent (skip 1)
    Expected Result: 2 sent
    Evidence: .sisyphus/evidence/task-10-skip-active.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-10-cmd-send.txt`
  - [ ] `task-10-skip-active.txt`

  **Commit**: YES
  - Message: `refactor(kp): notification + command support 12 monthly periods`
  - Files: `app/Notifications/KenaikanPangkatEligibleNotification.php`, `app/Console/Commands/SendKenaikanPangkatNotification.php`, `config/sikep.php`, `tests/Feature/Notifications/KenaikanPangkatEligibleNotificationTest.php`
  - Pre-commit: `php artisan test --compact --filter=KenaikanPangkat && vendor/bin/pint --dirty --format agent`

- [x] 11. UI Monitoring KP: Filter 12 Bulan + Link ke Form Usulan

  **What to do**:
  - Update page `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`:
    - Ganti filter dropdown `periode: april/oktober` menjadi dual-dropdown: `bulan` (1-12 dengan label nama bulan Bahasa Indonesia) + `tahun` (select tahun berjalan s/d +3)
    - Tambah kolom `Periode Usul` (format: "Mei 2026") dan `Batas Usul` di table
    - Tambah kolom `Status` dengan badge warna: `Sudah Eligible` (hijau), `Mendekati Eligible` (kuning), `Belum Eligible` (abu)
    - Tambah kolom `Aksi`: tombol `Buat Usulan` (link ke `/kenaikan-pangkat/usulan/create?pegawai_id={id}&bulan={b}&tahun={t}`) hanya jika status `Sudah Eligible` AND user punya permission `kenaikan-pangkat.usulan.create`
    - Tambah stat cards di atas table: `Total`, `Sudah Eligible`, `Mendekati Eligible`, `Belum Eligible`
  - Update controller `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`:
    - Pass data ke view: `list`, `stats`, `bulanOptions` (array 1→12 dengan label), `tahunOptions`
    - Terima query params `bulan` (1-12) dan `tahun`
    - Inject `KenaikanPangkatMonitoringService` via constructor
  - Tambah Wayfinder route functions (auto-generated oleh `@laravel/vite-plugin-wayfinder`) untuk `/monitoring/kenaikan-pangkat`
  - Gunakan komponen shadcn/ui existing: `Card`, `Table`, `Select`, `Badge`, `Button`
  - Style: Tailwind v4, dark mode support
  - Tulis test `tests/Feature/Http/Controllers/Monitoring/MonitoringKenaikanPangkatControllerTest.php`:
    - GET `/monitoring/kenaikan-pangkat` dengan auth → 200 + Inertia response
    - Filter `?bulan=5&tahun=2026` → data ter-filter
    - Tanpa permission → 403

  **Must NOT do**:
  - JANGAN gunakan `useState` untuk data table — ikut pola Inertia page (props dari server)
  - JANGAN fetch data via axios terpisah di frontend — pakai Inertia GET dengan query params
  - JANGAN pakai hardcode nama bulan — ambil dari array constant di frontend (atau dari props)
  - JANGAN ubah monitoring KGB page (scope terpisah)
  - JANGAN tambah chart/graph (out of scope P1)

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
    - Reason: Page Inertia + React + Tailwind dengan table filter interactive.
  - **Skills**: `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`
    - `inertia-react-development`: Page props, query param handling
    - `tailwindcss-development`: Styling table + cards + badges
    - `wayfinder-development`: Route function untuk form submit

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T17 (eligible list page reuse)
  - **Blocked By**: T7 (service signature berubah)

  **References**:

  **Pattern References**:
  - `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx` - Pattern monitoring page paling mirip
  - `resources/js/pages/kepegawaian/pegawai/index.tsx` - Pattern table dengan filter + pagination
  - `resources/js/components/ui/table.tsx` - Komponen table shadcn
  - `resources/js/components/ui/select.tsx` - Komponen select
  - `resources/js/components/ui/badge.tsx` - Komponen badge

  **API/Type References**:
  - `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php` - Controller yang di-update
  - Types di `resources/js/types/*.ts` - Definisi tipe (cari `KenaikanPangkat*` atau buat baru)

  **WHY Each Reference Matters**:
  - KGB page dan KP page share 90% struktur — jangan reinvent
  - Pegawai index adalah pola filter + pagination Inertia yang stabil

  **Acceptance Criteria**:
  - [ ] Dual dropdown bulan (1-12 label Bahasa Indonesia) + tahun render benar
  - [ ] Filter submit via Inertia GET → URL berubah, data ter-filter
  - [ ] Tombol `Buat Usulan` muncul hanya saat status `Sudah Eligible` AND user punya permission
  - [ ] Stat cards match nilai `stats` dari service
  - [ ] Dark mode support: tidak ada `bg-white` hardcoded (pakai `bg-background`, etc)
  - [ ] `npm run build` sukses, tidak ada TS error
  - [ ] `php artisan test --compact --filter=MonitoringKenaikanPangkat` 100% hijau

  **QA Scenarios**:

  ```
  Scenario: User buka monitoring dengan filter Mei 2026 → data ter-filter
    Tool: Playwright
    Preconditions: User login admin, seed 12 pegawai TMT berbeda per bulan
    Steps:
      1. Navigate to /monitoring/kenaikan-pangkat
      2. Assert page load, table render
      3. Select bulan = "Mei"
      4. Select tahun = 2026
      5. Click "Terapkan" atau submit otomatis
      6. Assert URL contains ?bulan=5&tahun=2026
      7. Assert table hanya menampilkan pegawai dengan periode_usul "Mei 2026"
      8. Screenshot
    Expected Result: Table filtered, URL berubah, screenshot sesuai
    Evidence: .sisyphus/evidence/task-11-filter-mei.png

  Scenario: Tombol Buat Usulan enabled hanya untuk Sudah Eligible
    Tool: Playwright
    Preconditions: User login dengan permission kenaikan-pangkat.usulan.create, seed 2 pegawai eligible + 1 mendekati
    Steps:
      1. Navigate ke monitoring
      2. Count tombol "Buat Usulan"
      3. Assert count == 2 (hanya untuk Sudah Eligible)
    Expected Result: 2 tombol
    Evidence: .sisyphus/evidence/task-11-tombol-usulan.png

  Scenario: Tanpa permission → 403
    Tool: curl
    Preconditions: User tanpa permission `kenaikan-pangkat.monitoring.view`
    Steps:
      1. curl -L --cookie authenticated_session /monitoring/kenaikan-pangkat
    Expected Result: HTTP 403 atau redirect ke dashboard dengan flash error
    Evidence: .sisyphus/evidence/task-11-no-permission.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-11-filter-mei.png`
  - [ ] `task-11-tombol-usulan.png`
  - [ ] `task-11-no-permission.txt`

  **Commit**: YES
  - Message: `feat(kp-monitoring): UI filter 12 periode + action buat usulan`
  - Files: `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`, `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`, `tests/Feature/Http/Controllers/Monitoring/*`
  - Pre-commit: `npm run build && php artisan test --compact --filter=MonitoringKenaikanPangkat && vendor/bin/pint --dirty --format agent`

- [x] 12. Template PDF Surat Pengantar Usulan Kenaikan Pangkat

  **What to do**:
  - Buat template Blade `resources/views/pdf/kenaikan-pangkat/surat-pengantar.blade.php`:
    - Header: Logo Mahkamah Agung (pakai placeholder jika asset belum ada) + nama satker + alamat
    - Body: `Nomor: {{ $nomorSurat }}` (placeholder dari T4), `Perihal: Usulan Kenaikan Pangkat a.n. {{ $pegawai->nama_lengkap }}`
    - Informasi pegawai (tabel): NIP, Nama, Pangkat Saat Ini, TMT, Pangkat Yang Diusulkan, Unit Kerja
    - Daftar berkas yang dilampirkan (dari checklist submission items yang sudah valid)
    - Footer: Tempat, tanggal, `{{ $pejabat->jabatan }}`, nama pejabat, NIP
  - Buat action class `app/Actions/UsulanKenaikanPangkat/GenerateSuratPengantarPdf.php`:
    - Method `handle(UsulanKenaikanPangkat $usulan, User $pejabatPenandatangan): UsulanKpPdf`
    - Reserve nomor surat via `NomorSuratService::reserve('KP.01.1', ...)`
    - Render PDF via `Spatie\LaravelPdf\Facades\Pdf::view(...)->save(...)`
    - Simpan file ke `storage/app/usulan-kp/surat-pengantar/{usulan_id}.pdf`
    - Simpan metadata ke `usulan_kp_pdf` (jenis_pdf='surat_pengantar')
    - Confirm nomor surat reservation
    - Return `UsulanKpPdf` instance
  - Pejabat penandatangan: ambil dari config `config('sikep.penandatangan.kenaikan_pangkat')` (default: role `ketua_pengadilan`). Lookup user via role.
  - Tulis test `tests/Feature/Actions/UsulanKenaikanPangkat/GenerateSuratPengantarPdfTest.php`:
    - Generate PDF sukses, file tersimpan
    - `usulan_kp_pdf` record ter-insert
    - Nomor surat ter-confirm (sequence increment)
    - PDF mengandung string penting: NIP, nama, nomor surat

  **Must NOT do**:
  - JANGAN hardcode logo image base64 — pakai path asset
  - JANGAN embed signature digital atau e-sign (out of scope)
  - JANGAN generate PDF untuk usulan belum `DitandatanganiKetua` — service layer cek state dulu
  - JANGAN ubah `NomorSuratService` interface — pakai signature existing

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
    - Reason: Blade template + styling PDF + integrasi PDF library.
  - **Skills**: `tailwindcss-development`
    - `tailwindcss-development`: Untuk styling PDF (Spatie PDF support Tailwind)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T20 (UI upload SK), T22 (auto-update riwayat)
  - **Blocked By**: T4 (NomorSuratService)

  **References**:

  **Pattern References**:
  - `resources/views/pdf/cuti/pengajuan.blade.php` - Pattern Blade PDF existing
  - `app/Http/Controllers/Cuti/PdfController.php` - Pattern PDF generation dengan Spatie

  **API/Type References**:
  - Spatie Laravel PDF docs — `Pdf::view()->save()`, page format A4
  - `app/Services/NomorSurat/NomorSuratService.php` - Generate nomor

  **WHY Each Reference Matters**:
  - Cuti PDF adalah pola yang harus dimirror (layout, save path, metadata pattern)

  **Acceptance Criteria**:
  - [ ] Template Blade ada dan render tanpa error
  - [ ] Action `GenerateSuratPengantarPdf` sukses generate file `storage/app/usulan-kp/surat-pengantar/{id}.pdf`
  - [ ] Record `usulan_kp_pdf` ter-insert dengan jenis_pdf='surat_pengantar'
  - [ ] Nomor surat unique per generate (tidak duplicate)
  - [ ] File PDF berisi NIP + nama + nomor surat (verify via text extract atau manual spot check)
  - [ ] `php artisan test --compact --filter=GenerateSuratPengantarPdf` 100% hijau

  **QA Scenarios**:

  ```
  Scenario: Generate PDF happy path
    Tool: Bash (pest feature test)
    Preconditions: Usulan state=DitandatanganiKetua, Pegawai lengkap, NomorSurat ready
    Steps:
      1. Action->handle(usulan, ketua)
      2. Assert file exists di storage/app/usulan-kp/surat-pengantar/{id}.pdf
      3. Assert UsulanKpPdf row ada
      4. pdftotext file → grep NIP pegawai
    Expected Result: File ada, row ada, teks NIP ditemukan
    Evidence: .sisyphus/evidence/task-12-pdf-generated.pdf (copy file)

  Scenario: Nomor surat unique antara 2 generate
    Tool: Bash
    Preconditions: 2 usulan berbeda
    Steps:
      1. Generate untuk usulan A → ambil nomor
      2. Generate untuk usulan B → ambil nomor
      3. Assert nomor berbeda
    Expected Result: Nomor urut A != nomor urut B
    Evidence: .sisyphus/evidence/task-12-nomor-unique.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-12-pdf-generated.pdf`
  - [ ] `task-12-nomor-unique.txt`

  **Commit**: YES
  - Message: `feat(kp): add surat pengantar PDF template + generator action`
  - Files: `resources/views/pdf/kenaikan-pangkat/surat-pengantar.blade.php`, `app/Actions/UsulanKenaikanPangkat/GenerateSuratPengantarPdf.php`, `config/sikep.php` (penandatangan section), `tests/Feature/Actions/UsulanKenaikanPangkat/*`
  - Pre-commit: `php artisan test --compact --filter=GenerateSuratPengantarPdf && vendor/bin/pint --dirty --format agent`

---

<!-- WAVE 3: BUSINESS LOGIC & API -->

- [ ] 13. `UsulanKenaikanPangkatService` (CRUD + State Transitions)

  **What to do**:
  - Buat service `app/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatService.php`:
    - `createDraft(array $data, User $actor): UsulanKenaikanPangkat` — validate `pegawai_id` aktif + bukan PPPK + tidak ada usulan active untuk periode sama; buat usulan state=Draft; buat 3 approval_steps default (kasubbag, sekretaris, ketua)
    - `submit(UsulanKenaikanPangkat $usulan, User $actor, ?BerkasChecklistSubmission $checklist = null): void` — gate: `ChecklistBerkasService::isComplete($checklist)`. Transisi Draft→Diajukan. Reserve nomor usulan sementara (placeholder). Record approver_history.
    - `verifikasiKasubbag(UsulanKenaikanPangkat $usulan, User $kasubbag, bool $setuju, ?string $catatan = null): void` — transisi sesuai. Update approval_step ke-1. Record history + state history.
    - `verifikasiSekretaris(UsulanKenaikanPangkat $usulan, User $sekretaris, bool $setuju, ?string $catatan = null): void` — sama
    - `tandaTanganKetua(UsulanKenaikanPangkat $usulan, User $ketua): UsulanKpPdf` — generate surat pengantar via `GenerateSuratPengantarPdf` action. Transisi ke DitandatanganiKetua.
    - `kirimBiro(UsulanKenaikanPangkat $usulan, User $actor, ?string $catatan = null): void` — transisi ke DikirimBiro. Optional: panggil `SikepAdapter::pushUsulan($dto)` (null impl no-op). Setelah itu transisi ke MenungguSK.
    - `uploadSkFinal(UsulanKenaikanPangkat $usulan, User $actor, UploadedFile $skFile, string $nomorSk, string $tanggalSk): void` — simpan file ke `storage/app/usulan-kp/sk/{id}.pdf`. Update `nomor_sk`, `tanggal_sk`, `sk_file_path`, `sk_file_original_name`. Transisi ke SelesaiSKTerbit.
    - `tolak(UsulanKenaikanPangkat $usulan, User $actor, string $alasan): void` — transisi ke Ditolak
    - `mintaPerbaikan(UsulanKenaikanPangkat $usulan, User $actor, string $catatan): void` — transisi ke PerluPerbaikan
    - `batalkan(UsulanKenaikanPangkat $usulan, User $actor, string $alasan): void` — hanya jika state Draft atau PerluPerbaikan. Transisi ke Dibatalkan.
    - Setiap method yang transisi state wajib: (a) record `state_history` row, (b) record `approver_history` row, (c) panggil `state->transitionTo()`, (d) DB transaction.
  - Buat custom exception classes:
    - `app/Exceptions/UsulanKenaikanPangkat/BerkasBelumLengkapException.php`
    - `app/Exceptions/UsulanKenaikanPangkat/DuplicateUsulanException.php` (untuk periode sama)
    - `app/Exceptions/UsulanKenaikanPangkat/PegawaiTidakEligibleException.php`
  - Tulis test `tests/Feature/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatServiceTest.php`:
    - Happy path end-to-end lengkap (11 state transitions valid flow)
    - createDraft: throw `DuplicateUsulanException` jika sudah ada usulan active
    - createDraft: throw `PegawaiTidakEligibleException` untuk PPPK
    - submit: throw `BerkasBelumLengkapException` jika checklist tidak complete
    - Invalid transition throw `CouldNotPerformTransition`
    - DB rollback jika exception mid-transaction
    - Activity log + state_history + approver_history ter-record per transisi

  **Must NOT do**:
  - JANGAN panggil `auth()->user()` di service — pass `User $actor` eksplisit sebagai parameter
  - JANGAN validate permission di service — delegasikan ke Policy (T15)
  - JANGAN generate nomor SK sendiri — nomor SK dari Biro Kepegawaian MA (input user saat upload)
  - JANGAN auto-update riwayat_pangkat di service ini (dipisah ke T22 untuk isolation)
  - JANGAN pakai model observer untuk history — explicit call di service (lebih testable)

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: 10 method business logic dengan state machine, DB transaction, exception handling, integrasi dengan 3 dependency (ChecklistService, SikepAdapter, GenerateSuratPengantarPdf).
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: NO (critical path)
  - **Parallel Group**: Wave 3
  - **Blocks**: T16, T22, T23, T24
  - **Blocked By**: T8

  **References**:

  **Pattern References**:
  - `app/Services/RiwayatPangkatService.php` - Pattern service
  - Service di modul Cuti (cari `app/Services/Cuti/` atau inline di controller) untuk pattern state transition + history

  **API/Type References**:
  - `app/States/UsulanKenaikanPangkat/*` - State classes dari T8
  - `app/Services/BerkasChecklist/ChecklistBerkasService.php` - `isComplete()` gate
  - `app/Services/Sikep/SikepAdapter.php` - push usulan interface
  - `app/Actions/UsulanKenaikanPangkat/GenerateSuratPengantarPdf.php` - PDF action

  **WHY Each Reference Matters**:
  - State classes dari T8 mendefinisikan valid transitions; service harus panggil `transitionTo()` sesuai
  - Checklist gate memastikan workflow compliant dengan spec BERKAS-07 (tidak lanjut jika belum lengkap)

  **Acceptance Criteria**:
  - [ ] Service class ada dengan 10 public method
  - [ ] 3 custom exception class ada
  - [ ] Happy path test: create → submit (with complete checklist) → verifikasi kasubbag → verifikasi sekretaris → tanda tangan ketua (PDF generated) → kirim biro → upload SK → state = SelesaiSKTerbit. Semua history tables ter-isi.
  - [ ] Negative test: submit tanpa checklist complete → throw BerkasBelumLengkapException, state tidak berubah
  - [ ] Duplicate usulan: create untuk pegawai + periode sama 2x → throw DuplicateUsulanException
  - [ ] PPPK: create untuk pegawai PPPK → throw PegawaiTidakEligibleException
  - [ ] DB rollback: simulate exception di tengah `tandaTanganKetua` (mock action throw) → state tidak berubah, tidak ada history orphan
  - [ ] `php artisan test --compact --filter=UsulanKenaikanPangkatService` 100% hijau (minimal 20 test cases)

  **QA Scenarios**:

  ```
  Scenario: Happy path 7 transitions
    Tool: Bash (pest feature test)
    Preconditions: Pegawai PNS eligible, template checklist seeded, 3 users (kasubbag, sekretaris, ketua) dengan role sesuai
    Steps:
      1. Service->createDraft([...], admin) → usulan.state == Draft
      2. Buat checklist submission, mark semua wajib valid
      3. Service->submit(usulan, admin, checklist) → state = Diajukan
      4. Service->verifikasiKasubbag(usulan, kasubbag, true) → DiverifikasiKasubbag
      5. Service->verifikasiSekretaris(usulan, sekretaris, true) → DiverifikasiSekretaris
      6. Service->tandaTanganKetua(usulan, ketua) → DitandatanganiKetua, PDF generated
      7. Service->kirimBiro(usulan, admin) → DikirimBiro → MenungguSK (auto)
      8. Service->uploadSkFinal(usulan, admin, fakeSk, 'NSK-001/2026', '2026-06-01') → SelesaiSKTerbit
      9. Assert state_history count == 7+
      10. Assert approver_history count == 7+
    Expected Result: Semua assertion pass
    Evidence: .sisyphus/evidence/task-13-happy-path.txt

  Scenario: Gate checklist — submit tanpa lengkap ditolak
    Tool: Bash (pest)
    Preconditions: Usulan Draft, checklist dengan 1 item wajib 'belum_ada'
    Steps:
      1. submit() → expect BerkasBelumLengkapException
      2. Assert state tetap Draft
    Expected Result: Exception thrown, state unchanged
    Evidence: .sisyphus/evidence/task-13-gate-checklist.txt

  Scenario: Rollback on mid-failure
    Tool: Bash (pest)
    Preconditions: Mock GenerateSuratPengantarPdf->handle() throw RuntimeException
    Steps:
      1. tandaTanganKetua() → catch exception
      2. Assert state tetap DiverifikasiSekretaris
      3. Assert tidak ada UsulanKpPdf record baru
    Expected Result: Rollback sukses, konsistensi terjaga
    Evidence: .sisyphus/evidence/task-13-rollback.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-13-happy-path.txt`
  - [ ] `task-13-gate-checklist.txt`
  - [ ] `task-13-rollback.txt`

  **Commit**: YES
  - Message: `feat(kp): UsulanKenaikanPangkatService with full state transitions + gates`
  - Files: `app/Services/UsulanKenaikanPangkat/*`, `app/Exceptions/UsulanKenaikanPangkat/*`, `tests/Feature/Services/UsulanKenaikanPangkat/*`
  - Pre-commit: `php artisan test --compact --filter=UsulanKenaikanPangkatService && vendor/bin/pint --dirty --format agent`

- [ ] 14. Integrasi Checklist ke Usulan KP + Gate Validation

  **What to do**:
  - Tambah method di `UsulanKenaikanPangkatService`:
    - `attachChecklist(UsulanKenaikanPangkat $usulan, BerkasChecklistTemplate $template, Pegawai $pegawai): BerkasChecklistSubmission` — panggil `ChecklistBerkasService::createSubmission($template, $usulan, $pegawai)`
  - Update method `createDraft` di T13: otomatis attach checklist default (template dengan `kode='checklist-kp-reguler'`) saat usulan dibuat
  - Modifikasi method `submit`: gate `isComplete($checklist)` sudah dilakukan di T13 — tambah defensive: jika `$usulan->checklistSubmission` null, throw `BerkasBelumLengkapException`
  - Tambah observer atau event listener: saat `BerkasChecklistSubmission` di-recalculate, kalau `status_kelengkapan` berubah, fire event `ChecklistKelengkapanBerubah($submission)` untuk notification/audit
  - Tulis integration test `tests/Feature/Integration/UsulanKpChecklistIntegrationTest.php`:
    - createDraft auto-attach checklist default, submission_items sesuai template
    - Submit dengan checklist incomplete → blocked
    - Upload file ke item wajib → persentase update → setelah semua valid → submit sukses

  **Must NOT do**:
  - JANGAN hardcode `checklist-kp-reguler` di service — ambil dari config `sikep.kp.checklist_template_kode`
  - JANGAN buat event handler berat di observer — hanya trigger, handler terpisah
  - JANGAN mix checklist logic ke controller — semua lewat service

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Integration antara 2 service existing, butuh pemahaman lifecycle event.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3
  - **Blocks**: T17, T19, T24
  - **Blocked By**: T8, T9

  **References**:

  **Pattern References**:
  - `app/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatService.php` (T13)
  - `app/Services/BerkasChecklist/ChecklistBerkasService.php` (T9)
  - `app/Models/UsulanKenaikanPangkat/UsulanKenaikanPangkat.php` (T8 — `checklistSubmission()` morphOne)

  **External References**:
  - Laravel events docs untuk custom event class

  **WHY Each Reference Matters**:
  - Service T13 dan T9 harus di-compose lewat method baru `attachChecklist` tanpa tight coupling

  **Acceptance Criteria**:
  - [ ] `createDraft` auto-attach checklist default (cek `$usulan->checklistSubmission` tidak null)
  - [ ] Config `sikep.kp.checklist_template_kode` ada (default `checklist-kp-reguler`)
  - [ ] Event `ChecklistKelengkapanBerubah` fired saat status kelengkapan transisi
  - [ ] `submit` tanpa checklist complete → `BerkasBelumLengkapException`, state tidak berubah
  - [ ] Integration test 100% hijau (`--filter=UsulanKpChecklistIntegration`)
  - [ ] Tidak ada circular dependency antar service

  **QA Scenarios**:

  ```
  Scenario: Integration flow: create draft → upload semua wajib → submit
    Tool: Bash (pest)
    Preconditions: Pegawai eligible, template checklist-kp-reguler seeded, 3 users role ready
    Steps:
      1. Service->createDraft([...], admin) → usulan.checklistSubmission is not null
      2. Assert items count = 7 (5 wajib + 2 opsional)
      3. Upload + validate 5 items wajib → persentase == 100
      4. Service->submit(usulan, admin, checklist) → state == Diajukan
    Expected Result: Flow sukses tanpa exception
    Evidence: .sisyphus/evidence/task-14-integration-flow.txt

  Scenario: Event fired saat kelengkapan berubah
    Tool: Bash (pest dengan Event::fake())
    Preconditions: Submission dengan 5 wajib, 3 valid
    Steps:
      1. Validate 2 wajib terakhir → status transit ke 'lengkap'
      2. Event::assertDispatched(ChecklistKelengkapanBerubah::class)
    Expected Result: Event dispatched sekali
    Evidence: .sisyphus/evidence/task-14-event-fired.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-14-integration-flow.txt`
  - [ ] `task-14-event-fired.txt`

  **Commit**: YES
  - Message: `feat(kp): integrate checklist into usulan workflow with gate validation`
  - Files: `app/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatService.php` (update), `app/Events/ChecklistKelengkapanBerubah.php`, `app/Services/BerkasChecklist/ChecklistBerkasService.php` (update untuk fire event), `config/sikep.php` (add kp.checklist_template_kode), `tests/Feature/Integration/UsulanKpChecklistIntegrationTest.php`
  - Pre-commit: `php artisan test --compact --filter=UsulanKpChecklist && vendor/bin/pint --dirty --format agent`

- [ ] 15. Form Requests + Policy `UsulanKenaikanPangkat`

  **What to do**:
  - Buat Form Requests di `app/Http/Requests/UsulanKenaikanPangkat/`:
    - `StoreUsulanKenaikanPangkatRequest.php` — rules: pegawai_id exists + aktif + jenis PNS, ref_pangkat_tujuan_id exists, periode_usul_bulan between 1-12, periode_usul_tahun >= current year, catatan_pengusul nullable|string|max:1000
    - `SubmitUsulanKenaikanPangkatRequest.php` — validate checklist_submission_id exists
    - `VerifikasiKasubbagRequest.php` — setuju:required|bool, catatan:required_if:setuju,false|string|max:500
    - `VerifikasiSekretarisRequest.php` — sama dengan kasubbag
    - `TandaTanganKetuaRequest.php` — no body fields, hanya authorize via Policy. **Alasan keep as class**: future extensibility (e.g. tambah `keterangan_ttd` nullable), dan konsisten dengan pattern 1 request per action. Rules: `[]` (empty), authorize via Policy.
    - `KirimBiroRequest.php` — catatan:nullable|string|max:500
    - `UploadSkFinalRequest.php` — sk_file:required|file|mimes:pdf|max:10240 (10MB), nomor_sk:required|string|max:100|unique:usulan_kenaikan_pangkat,nomor_sk, tanggal_sk:required|date|before_or_equal:today
    - `MintaPerbaikanRequest.php` — catatan:required|string|max:1000
    - `TolakUsulanRequest.php` — alasan:required|string|max:1000
    - `BatalkanUsulanRequest.php` — alasan:required|string|max:500
  - Buat Policy `app/Policies/UsulanKenaikanPangkatPolicy.php`:
    - `viewAny(User $user): bool` — check permission `kenaikan-pangkat.usulan.view`
    - `view(User $user, UsulanKenaikanPangkat $usulan): bool` — self (pegawai_id == user.pegawai_id) OR permission view
    - `create(User $user): bool` — `kenaikan-pangkat.usulan.create`
    - `update(User $user, UsulanKenaikanPangkat $usulan): bool` — state=Draft|PerluPerbaikan AND (self OR permission)
    - `delete(User $user, UsulanKenaikanPangkat $usulan): bool` — state=Draft AND self
    - `submit(User $user, UsulanKenaikanPangkat $usulan): bool` — state=Draft|PerluPerbaikan AND permission `kenaikan-pangkat.usulan.submit`
    - `verifikasiKasubbag(User $user, UsulanKenaikanPangkat $usulan): bool` — state=Diajukan AND permission `kenaikan-pangkat.usulan.verifikasi-kasubbag`
    - `verifikasiSekretaris(User $user, UsulanKenaikanPangkat $usulan): bool` — state=DiverifikasiKasubbag AND permission `.verifikasi-sekretaris`
    - `tandaTanganKetua(User $user, UsulanKenaikanPangkat $usulan): bool` — state=DiverifikasiSekretaris AND permission `.tanda-tangan-ketua`
    - `kirimBiro(User $user, UsulanKenaikanPangkat $usulan): bool` — state=DitandatanganiKetua AND permission
    - `uploadSk(User $user, UsulanKenaikanPangkat $usulan): bool` — state=MenungguSK AND permission `.upload-sk`
    - `batalkan(User $user, UsulanKenaikanPangkat $usulan): bool` — state=Draft|PerluPerbaikan AND self
  - Register policy di `app/Providers/AuthServiceProvider.php` (atau auto-discovery kalau project pakai)
  - Tulis test `tests/Feature/Policies/UsulanKenaikanPangkatPolicyTest.php`: test matrix role × action × state

  **Must NOT do**:
  - JANGAN validasi bisnis di Form Request (itu tugas service) — hanya data shape
  - JANGAN embed authorization di Form Request `authorize()` — return true, delegasikan ke Policy di Controller
  - JANGAN pakai `$this->user()->can('permission')` langsung di Controller — pakai `$this->authorize('action', $usulan)` via policy
  - JANGAN cek state di Form Request — itu tugas policy

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Form Request class + Policy class straightforward.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3
  - **Blocks**: T16
  - **Blocked By**: T8, T6

  **References**:

  **Pattern References**:
  - `app/Http/Requests/Cuti/SubmitPengajuanRequest.php` - Pattern Form Request workflow
  - `app/Http/Requests/Kepegawaian/ApprovePengajuanPerubahanDataRequest.php` - Pattern approve request
  - `app/Policies/PegawaiPolicy.php` - Pattern policy dengan permission check
  - `app/Policies/PengajuanPerubahanDataPolicy.php` - Pattern policy workflow

  **WHY Each Reference Matters**:
  - Cuti/PengajuanPerubahanData adalah workflow policy existing — pola authorization harus match

  **Acceptance Criteria**:
  - [ ] 10 Form Request class ada dengan rules lengkap
  - [ ] Policy class ada dengan 12 method
  - [ ] Policy matrix test coverage: role (5) × action (12) kombinasi relevan, tanpa regress
  - [ ] Unauthorized access → 403 (via `authorize()` call di controller)
  - [ ] `php artisan test --compact --filter=UsulanKenaikanPangkatPolicy` 100% hijau

  **QA Scenarios**:

  ```
  Scenario: Policy matrix — pegawai hanya view usulan sendiri
    Tool: Bash (pest)
    Preconditions: 2 pegawai A dan B, usulan milik A
    Steps:
      1. user(B).view(usulan A) → false
      2. user(A).view(usulan A) → true
      3. user(admin).view(usulan A) → true (punya permission)
    Expected Result: Sesuai assertion
    Evidence: .sisyphus/evidence/task-15-policy-view.txt

  Scenario: Action state-gated (verifikasi kasubbag hanya saat state Diajukan)
    Tool: Bash (pest)
    Preconditions: Usulan state=Draft, user kasubbag
    Steps:
      1. user(kasubbag).verifikasiKasubbag(usulan Draft) → false
      2. Transisi ke Diajukan
      3. user(kasubbag).verifikasiKasubbag(usulan Diajukan) → true
    Expected Result: State gate working
    Evidence: .sisyphus/evidence/task-15-policy-state-gate.txt

  Scenario: Form Request validates file upload SK
    Tool: Bash (pest http test)
    Preconditions: Usulan state=MenungguSK, user authorized
    Steps:
      1. POST upload SK tanpa file → 422 error 'sk_file'
      2. Upload file .docx → 422 error (only PDF)
      3. Upload PDF 15MB → 422 error (max 10MB)
      4. Upload PDF valid → 200
    Expected Result: 3 validation errors + 1 success
    Evidence: .sisyphus/evidence/task-15-form-request-upload.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-15-policy-view.txt`
  - [ ] `task-15-policy-state-gate.txt`
  - [ ] `task-15-form-request-upload.txt`

  **Commit**: YES
  - Message: `feat(kp): add form requests + policy for usulan kenaikan pangkat`
  - Files: `app/Http/Requests/UsulanKenaikanPangkat/*`, `app/Policies/UsulanKenaikanPangkatPolicy.php`, `tests/Feature/Policies/*`
  - Pre-commit: `php artisan test --compact --filter=UsulanKenaikanPangkatPolicy && vendor/bin/pint --dirty --format agent`

- [ ] 16. Controllers Web + API `UsulanKenaikanPangkat`

  **Sub-task granularity** (4 parallel sub-agents, split untuk reduce bottleneck):

  **T16a — UsulanKenaikanPangkatController (web)** [1 file, ~9 actions]:
  - Buat `app/Http/Controllers/UsulanKenaikanPangkat/UsulanKenaikanPangkatController.php`:
    - `index()` — list usulan dengan filter (state, periode, pegawai) + pagination → Inertia `kenaikan-pangkat/usulan/index`
    - `create(Request $request)` — form create, default pegawai_id dari query → Inertia `kenaikan-pangkat/usulan/form`
    - `store(StoreUsulanKenaikanPangkatRequest $req)` — `$this->authorize('create')` → service->createDraft → redirect ke show
    - `show(UsulanKenaikanPangkat $usulan)` — detail + timeline + lampiran + checklist → Inertia `kenaikan-pangkat/usulan/show`
    - `edit(UsulanKenaikanPangkat $usulan)` — form edit (hanya Draft/PerluPerbaikan) → Inertia `kenaikan-pangkat/usulan/form`
    - `update(StoreUsulanKenaikanPangkatRequest $req, UsulanKenaikanPangkat $usulan)` — update draft
    - `destroy(UsulanKenaikanPangkat $usulan)` — soft delete (hanya Draft)
    - `submit(SubmitUsulanKenaikanPangkatRequest $req, UsulanKenaikanPangkat $usulan)` — service->submit
    - `batalkan(BatalkanUsulanRequest $req, UsulanKenaikanPangkat $usulan)` — service->batalkan
  - Test: `tests/Feature/Http/Controllers/UsulanKenaikanPangkat/UsulanKenaikanPangkatControllerTest.php`

  **T16b — ApprovalController (web)** [1 file, ~7 actions]:
  - Buat `app/Http/Controllers/UsulanKenaikanPangkat/ApprovalController.php`:
    - `inbox()` — daftar usulan yang menunggu aksi user (berdasarkan state × role) → Inertia `kenaikan-pangkat/approval/inbox`
    - `verifikasiKasubbag(VerifikasiKasubbagRequest $req, UsulanKenaikanPangkat $usulan)`
    - `verifikasiSekretaris(VerifikasiSekretarisRequest $req, UsulanKenaikanPangkat $usulan)`
    - `tandaTanganKetua(TandaTanganKetuaRequest $req, UsulanKenaikanPangkat $usulan)` — generate PDF
    - `kirimBiro(KirimBiroRequest $req, UsulanKenaikanPangkat $usulan)`
    - `mintaPerbaikan(MintaPerbaikanRequest $req, UsulanKenaikanPangkat $usulan)`
    - `tolak(TolakUsulanRequest $req, UsulanKenaikanPangkat $usulan)`
  - Test: `tests/Feature/Http/Controllers/UsulanKenaikanPangkat/ApprovalControllerTest.php`

  **T16c — SkAdminController (web)** [1 file, ~4 actions]:
  - Buat `app/Http/Controllers/UsulanKenaikanPangkat/SkAdminController.php`:
    - `index()` — daftar usulan state=MenungguSK
    - `uploadSk(UploadSkFinalRequest $req, UsulanKenaikanPangkat $usulan)` — service->uploadSkFinal
    - `downloadSk(UsulanKenaikanPangkat $usulan)` — stream SK file
    - `downloadSuratPengantar(UsulanKpPdf $pdf)` — stream PDF pengantar
  - Test: `tests/Feature/Http/Controllers/UsulanKenaikanPangkat/SkAdminControllerTest.php`

  **T16d — API Controller + Routes + Wayfinder** [~3 files]:
  - Buat `app/Http/Controllers/Api/UsulanKenaikanPangkat/UsulanKenaikanPangkatApiController.php`:
    - `index()` — JSON list
    - `show()` — JSON detail
    - `stats()` — JSON stats per periode/state
  - Buat `app/Http/Resources/UsulanKenaikanPangkat/UsulanKenaikanPangkatResource.php`
  - Register **semua** routes di `routes/web.php` dengan group middleware `auth`, `verified`, dan permission check **per-action** (bukan wildcard): contoh `Route::post('/usulan/{u}/submit', ...)->middleware('iam.permission:kenaikan-pangkat.usulan.submit')`. Middleware `iam.permission` sudah ter-alias di `bootstrap/app.php` — tidak support wildcard, harus exact slug.
  - Register routes API di `routes/api.php` dengan middleware `auth:sanctum` + permission per-action
  - Generate Wayfinder types: `php artisan wayfinder:generate --no-interaction` — cek `resources/js/actions/App/Http/Controllers/UsulanKenaikanPangkat/*.ts` + `resources/js/routes/*` ter-generate
  - Test: `tests/Feature/Http/Controllers/Api/UsulanKenaikanPangkat/UsulanKenaikanPangkatApiControllerTest.php`

  **Dispatch strategy**: T16a, T16b, T16c bisa jalan paralel setelah T13 & T15 selesai. T16d butuh T16a+T16b+T16c selesai dulu (untuk register routes semua controller).

  **Aggregate test coverage** (gabungan 4 sub-task):
    - Index: auth → list, tanpa auth → 302 login
    - Store: valid data → create + redirect, invalid → 422
    - Submit: gate checklist → 422 (error BerkasBelumLengkap), valid → transisi
    - Approval inbox: hanya usulan yang perlu aksi user
    - Upload SK: valid PDF → 200, mime salah → 422
    - Policy denial → 403

  **Must NOT do**:
  - JANGAN embed business logic di controller — semua delegasikan ke service
  - JANGAN balik JSON dari controller web (pakai Inertia response)
  - JANGAN skip `$this->authorize(...)` di action sensitive
  - JANGAN manual serialize — pakai Eloquent API Resources untuk API controller
  - JANGAN tambah middleware baru ke controller — pakai `iam.permission` existing
  - JANGAN hardcode state label — pass dari state `label()` method

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high` per sub-task (T16a, T16b, T16c) dan `quick` untuk T16d (routes + wayfinder, no logic)
    - Reason: 4 controllers dengan total ~23 actions + route registration + API Resource + Wayfinder generation. Granular dispatch via 4 agents paralel.
  - **Category**: `unspecified-high`
    - Reason: 4 controller dengan ~25 action + route registration + API Resource.
  - **Skills**: `inertia-react-development`, `wayfinder-development`, `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES (within T16: 3 sub-tasks T16a/T16b/T16c paralel, T16d setelahnya)
  - **Parallel Group**: Wave 3 (T16a/b/c paralel dengan T14 opsional)
  - **Blocks**: T17, T18, T19, T20 (UI pages), T22 (event dispatch)
  - **Blocked By**: T13, T15 (semua sub-task); T16d tambahan blocked by T16a+T16b+T16c
  - **Can Run In Parallel**: NO (critical path)
  - **Parallel Group**: Wave 3
  - **Blocks**: T17, T18, T19, T20, T22
  - **Blocked By**: T13, T15

  **References**:

  **Pattern References**:
  - `app/Http/Controllers/Cuti/PengajuanController.php` - Pattern controller workflow
  - `app/Http/Controllers/Cuti/ApprovalController.php` - Pattern approval controller
  - `app/Http/Controllers/Api/Cuti/PengajuanController.php` - Pattern API controller
  - `routes/web.php` - Pattern route registration
  - `routes/api.php` - Pattern API route

  **API/Type References**:
  - `app/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatService.php` - Business logic source
  - `app/Http/Middleware/EnsurePermission.php` - Middleware permission yang dipakai di route

  **WHY Each Reference Matters**:
  - Cuti controllers adalah blueprint endpoint, routing pattern, dan Inertia response
  - Middleware `EnsurePermission` atau `iam.permission` adalah cara permission check di route — harus match

  **Acceptance Criteria**:
  - [ ] 4 controller class ada dengan total ~25 action method
  - [ ] Routes terdaftar, verifikasi via `php artisan route:list | grep kenaikan-pangkat` → minimal 20 route
  - [ ] Wayfinder types auto-generated: `resources/js/actions/` / `resources/js/routes/` update
  - [ ] Controller web return Inertia response (bukan JSON)
  - [ ] API controller return JSON via Resource
  - [ ] Policy enforcement: tanpa permission → 403
  - [ ] Middleware auth enforce: tanpa login → redirect login
  - [ ] `php artisan test --compact --filter=UsulanKenaikanPangkatController` 100% hijau (minimal 30 test cases)

  **QA Scenarios**:

  ```
  Scenario: Happy path end-to-end via HTTP
    Tool: Bash (pest http test)
    Preconditions: Factory pegawai + 3 users role, seed permissions
    Steps:
      1. POST /kenaikan-pangkat/usulan (as admin) → 302 redirect show (usulan.id)
      2. PATCH /kenaikan-pangkat/usulan/{id}/submit (dengan checklist complete) → 302 redirect, state=Diajukan
      3. POST /kenaikan-pangkat/approval/{id}/verifikasi-kasubbag (as kasubbag) → 302, state=DiverifikasiKasubbag
      4. POST /kenaikan-pangkat/approval/{id}/verifikasi-sekretaris (as sekretaris) → 302
      5. POST /kenaikan-pangkat/approval/{id}/tanda-tangan-ketua (as ketua) → 302, PDF generated
      6. POST /kenaikan-pangkat/approval/{id}/kirim-biro (as admin) → 302, state=MenungguSK
      7. POST /kenaikan-pangkat/admin-sk/{id}/upload-sk (dengan PDF file) → 302, state=SelesaiSKTerbit
    Expected Result: 7 HTTP 302, state akhir = SelesaiSKTerbit
    Evidence: .sisyphus/evidence/task-16-http-flow.txt

  Scenario: Policy 403 untuk user tanpa permission
    Tool: curl
    Preconditions: User pegawai login (tanpa permission verifikasi)
    Steps:
      1. curl POST /kenaikan-pangkat/approval/{id}/verifikasi-kasubbag
    Expected Result: HTTP 403
    Evidence: .sisyphus/evidence/task-16-policy-403.txt

  Scenario: API stats return JSON terstruktur
    Tool: curl
    Preconditions: Auth sanctum token, data seeded
    Steps:
      1. curl GET /api/kenaikan-pangkat/stats?tahun=2026 -H 'Accept: application/json'
    Expected Result: HTTP 200 + JSON dengan key `total`, `per_state`, `per_bulan`
    Evidence: .sisyphus/evidence/task-16-api-stats.json
  ```

  **Evidence to Capture**:
  - [ ] `task-16-http-flow.txt`
  - [ ] `task-16-policy-403.txt`
  - [ ] `task-16-api-stats.json`

  **Commit**: YES
  - Message: `feat(kp): web + API controllers for usulan kenaikan pangkat workflow`
  - Files: `app/Http/Controllers/UsulanKenaikanPangkat/*`, `app/Http/Controllers/Api/UsulanKenaikanPangkat/*`, `app/Http/Resources/UsulanKenaikanPangkat/*`, `routes/web.php`, `routes/api.php`, `tests/Feature/Http/Controllers/UsulanKenaikanPangkat/*`
  - Pre-commit: `php artisan route:list | grep kenaikan-pangkat | wc -l` ≥ 20 && `php artisan test --compact --filter=UsulanKenaikanPangkat` hijau && `vendor/bin/pint --dirty --format agent`

---

<!-- WAVE 4: UI PAGES (PARALLEL) -->

- [ ] 17. UI Eligible List + Form Usulan KP

  **What to do**:
  - Buat page `resources/js/pages/kenaikan-pangkat/eligible/index.tsx`:
    - Extend dari UI monitoring T11 (filter bulan/tahun/unit/golongan)
    - Tombol "Buat Usulan" di setiap row → navigate ke `/kenaikan-pangkat/usulan/create?pegawai_id={id}&bulan={b}&tahun={t}`
  - Buat page `resources/js/pages/kenaikan-pangkat/usulan/form.tsx`:
    - Form create dan edit (detect mode via `usulan` prop)
    - Field: pegawai (readonly, preselected dari query), pangkat_asal (auto dari riwayat aktif), pangkat_tujuan (select ref_pangkat dengan tingkat lebih tinggi), periode_bulan + periode_tahun, catatan_pengusul
    - Gunakan `useForm` dari `@inertiajs/react` + Wayfinder action `StoreUsulanKenaikanPangkatController.store`
    - Validation error display inline
    - Submit → redirect show page
  - Buat page `resources/js/pages/kenaikan-pangkat/usulan/index.tsx`:
    - Table list dengan kolom: Pegawai, Pangkat, Periode, State (badge warna per state), Progres checklist, Aksi (lihat/edit/batalkan)
    - Filter: state (multi-select), periode bulan/tahun, search pegawai
  - Register navigation di `resources/js/components/app-sidebar.tsx` atau nav existing dengan icon & permission check
  - Tulis test playwright/pest integration

  **Must NOT do**:
  - JANGAN fetch data dari API axios — pakai Inertia props
  - JANGAN ubah page cuti
  - JANGAN hardcode nama state — pakai label dari state class (pass via props)

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: None
  - **Blocked By**: T16, T14, T11

  **References**:
  - `resources/js/pages/cuti/pengajuan/create.tsx` - Pattern form pengajuan
  - `resources/js/pages/kepegawaian/pegawai/index.tsx` - Pattern list table + filter + pagination (alternatif untuk pola list karena `cuti/pengajuan/index.tsx` **tidak ada** di project — yang ada hanya `create.tsx` dan `show.tsx`)
  - `resources/js/pages/cuti/admin/audit.tsx` - Pattern admin list untuk Cuti
  - `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx` - UI T11
  - `resources/js/components/ui/*` - Komponen shadcn

  **WHY**: Cuti create/index adalah blueprint UI form + list yang konsisten dengan project.

  **Acceptance Criteria**:
  - [ ] 3 page file ada: eligible/index, usulan/form, usulan/index
  - [ ] Form submit sukses → redirect show
  - [ ] Validation error muncul inline
  - [ ] Filter list state + periode bekerja
  - [ ] Dark mode support
  - [ ] `npm run build` sukses

  **QA Scenarios**:

  ```
  Scenario: User buat usulan dari daftar eligible
    Tool: Playwright
    Preconditions: User admin login, pegawai eligible tersedia
    Steps:
      1. Navigate /kenaikan-pangkat/eligible
      2. Click "Buat Usulan" pada row pertama
      3. Assert URL mengandung ?pegawai_id=
      4. Form pre-populated dengan data pegawai
      5. Fill catatan, submit
      6. Assert redirect ke detail page, state badge = Draft
    Evidence: .sisyphus/evidence/task-17-buat-usulan.png

  Scenario: Validation error inline
    Tool: Playwright
    Preconditions: User admin login di form create
    Steps:
      1. Submit form kosong
      2. Assert error messages visible (pegawai required, pangkat_tujuan required)
    Evidence: .sisyphus/evidence/task-17-validation.png
  ```

  **Evidence to Capture**:
  - [ ] `task-17-buat-usulan.png`
  - [ ] `task-17-validation.png`

  **Commit**: YES
  - Message: `feat(kp-ui): eligible list + form usulan + list usulan pages`
  - Files: `resources/js/pages/kenaikan-pangkat/{eligible,usulan}/*.tsx`, `resources/js/components/app-sidebar.tsx`
  - Pre-commit: `npm run build`

- [ ] 18. UI Inbox Approval + Aksi Approve/Reject

  **What to do**:
  - Page `resources/js/pages/kenaikan-pangkat/approval/inbox.tsx`:
    - Tabs: "Verifikasi Kasubbag", "Verifikasi Sekretaris", "Tanda Tangan Ketua", "Kirim Biro" — masing-masing filter berdasar state + permission user
    - Table per tab: pegawai, pangkat, tanggal diajukan, aksi
    - Modal konfirmasi aksi: `Setuju` (hijau), `Perlu Perbaikan` (kuning), `Tolak` (merah). Field catatan muncul conditional.
    - Wayfinder actions: `ApprovalController.verifikasiKasubbag/Sekretaris/tandaTanganKetua/kirimBiro/mintaPerbaikan/tolak`
  - Gunakan `<Form>` component dari `@inertiajs/react`

  **Must NOT do**:
  - JANGAN tampilkan tab yang user tidak punya permission
  - JANGAN pakai alert() native — pakai toast (sonner) existing
  - JANGAN re-fetch setelah action — Inertia partial reload

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: None
  - **Blocked By**: T16

  **References**:
  - `resources/js/pages/cuti/approval/inbox.tsx` - Pattern inbox
  - `resources/js/components/ui/dialog.tsx`, `resources/js/components/ui/tabs.tsx`

  **WHY**: Cuti approval inbox adalah pola UI yang identik (tabs per state, action modal).

  **Acceptance Criteria**:
  - [ ] Tabs muncul sesuai permission user (kasubbag lihat tab kasubbag saja, dll)
  - [ ] Modal konfirmasi dengan validation catatan required_if
  - [ ] Action sukses → toast success, row hilang dari tab
  - [ ] `npm run build` sukses

  **QA Scenarios**:

  ```
  Scenario: Kasubbag verifikasi setuju
    Tool: Playwright
    Preconditions: User kasubbag login, 1 usulan state=Diajukan
    Steps:
      1. Navigate /kenaikan-pangkat/approval/inbox
      2. Click tab "Verifikasi Kasubbag"
      3. Click row pertama
      4. Click "Setuju"
      5. Submit modal
      6. Assert toast sukses, row hilang
    Evidence: .sisyphus/evidence/task-18-kasubbag-setuju.png

  Scenario: Minta perbaikan dengan catatan
    Tool: Playwright
    Preconditions: Sekretaris login, 1 usulan state=DiverifikasiKasubbag
    Steps:
      1. Inbox → tab Sekretaris
      2. Click "Perlu Perbaikan"
      3. Assert field catatan muncul required
      4. Submit tanpa catatan → error validation
      5. Isi catatan → submit sukses
    Evidence: .sisyphus/evidence/task-18-perlu-perbaikan.png
  ```

  **Evidence to Capture**:
  - [ ] `task-18-kasubbag-setuju.png`
  - [ ] `task-18-perlu-perbaikan.png`

  **Commit**: YES
  - Message: `feat(kp-ui): approval inbox with action modals`
  - Files: `resources/js/pages/kenaikan-pangkat/approval/*.tsx`
  - Pre-commit: `npm run build`

- [ ] 19. UI Detail Usulan + Timeline State + Checklist Panel

  **What to do**:
  - Page `resources/js/pages/kenaikan-pangkat/usulan/show.tsx`:
    - Header: nama pegawai, pangkat asal → tujuan, periode, state badge
    - Tab: `Ringkasan`, `Checklist Berkas`, `Lampiran`, `Timeline`, `Approver`
    - Tab Ringkasan: data usulan + tombol aksi (batalkan/edit jika state memungkinkan + policy allow)
    - Tab Checklist: integrasi komponen checklist dari T21 (reuse). Upload per item, status, catatan. Display persentase progress bar.
    - Tab Lampiran: list `usulan_kp_lampiran` + upload tambahan
    - Tab Timeline: list `state_history` + `approver_history` sorted chronologically dengan icon per action
    - Tab Approver: tabel 3 langkah approval (kasubbag/sekretaris/ketua) dengan status + timestamp
    - Tombol download: surat pengantar PDF (jika `ditandatangani_ketua`), SK final (jika `selesai_sk_terbit`)

  **Must NOT do**:
  - JANGAN edit checklist/lampiran di luar policy state
  - JANGAN fetch timeline via API terpisah — pass dari Inertia props
  - JANGAN hard-code state icon mapping — ambil dari props state class

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: None
  - **Blocked By**: T16, T14

  **References**:
  - `resources/js/pages/cuti/pengajuan/show.tsx` - Pattern detail + timeline
  - `resources/js/components/ui/tabs.tsx`, `progress.tsx`

  **WHY**: Cuti show adalah template lengkap untuk detail workflow.

  **Acceptance Criteria**:
  - [ ] 5 tab render dengan data sesuai
  - [ ] Timeline sorted chronologically, icon per action
  - [ ] Progress bar checklist update real-time setelah upload
  - [ ] Download buttons hanya muncul saat state memenuhi syarat
  - [ ] `npm run build` sukses

  **QA Scenarios**:

  ```
  Scenario: Tab checklist upload file → progress update
    Tool: Playwright
    Preconditions: Usulan Draft, checklist 5 items wajib
    Steps:
      1. Navigate /kenaikan-pangkat/usulan/{id}
      2. Click tab "Checklist Berkas"
      3. Upload file PDF ke item 1 → assert progress 20%
      4. Upload lagi item 2 → 40%
    Evidence: .sisyphus/evidence/task-19-checklist-progress.png

  Scenario: Timeline menampilkan semua state transition
    Tool: Playwright
    Preconditions: Usulan state=SelesaiSKTerbit (setelah end-to-end)
    Steps:
      1. Show page → tab Timeline
      2. Assert minimal 7 timeline entries
      3. Assert urutan chronological
    Evidence: .sisyphus/evidence/task-19-timeline.png
  ```

  **Evidence to Capture**:
  - [ ] `task-19-checklist-progress.png`
  - [ ] `task-19-timeline.png`

  **Commit**: YES
  - Message: `feat(kp-ui): detail usulan page with tabs, timeline, checklist`
  - Files: `resources/js/pages/kenaikan-pangkat/usulan/show.tsx`, komponen terkait
  - Pre-commit: `npm run build`

- [ ] 20. UI Admin Daftar SK + Upload SK Form

  **What to do**:
  - Page `resources/js/pages/kenaikan-pangkat/admin-sk/index.tsx`:
    - Table: pegawai, pangkat, tanggal kirim biro, umur (hari sejak kirim), aksi (upload SK)
    - Filter state: `MenungguSK`, `SelesaiSKTerbit`
    - Tombol Upload SK → modal upload (PDF, nomor SK, tanggal SK)
  - Page/modal upload SK: form dengan field sk_file (dropzone), nomor_sk, tanggal_sk
  - Table SK sudah terbit: kolom tambahan nomor_sk, tanggal_sk, download button
  - Gunakan Wayfinder action `SkAdminController.uploadSk`

  **Must NOT do**:
  - JANGAN izinkan upload untuk state != MenungguSK
  - JANGAN batch upload (1 SK per usulan)
  - JANGAN overwrite SK yang sudah ada tanpa konfirmasi eksplisit (out of scope P1: no overwrite)

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: None
  - **Blocked By**: T16, T12

  **References**:
  - `resources/js/pages/cuti/admin/audit/*.tsx` - Pattern admin page
  - `resources/js/components/ui/file-upload.tsx` (jika ada) atau `input[type=file]` custom

  **WHY**: Admin cuti audit adalah blueprint admin view dengan aksi.

  **Acceptance Criteria**:
  - [ ] Table list usulan MenungguSK render
  - [ ] Modal upload accept hanya PDF max 10MB
  - [ ] Upload sukses → row pindah ke section "SK Terbit"
  - [ ] Download button bekerja (streaming file)
  - [ ] `npm run build` sukses

  **QA Scenarios**:

  ```
  Scenario: Upload SK valid
    Tool: Playwright
    Preconditions: Admin login, usulan state=MenungguSK
    Steps:
      1. Navigate /kenaikan-pangkat/admin-sk
      2. Click Upload di row
      3. Drop PDF valid 500KB
      4. Fill nomor_sk, tanggal_sk
      5. Submit
      6. Assert toast sukses, row pindah ke tab SK Terbit
    Evidence: .sisyphus/evidence/task-20-upload-sk.png

  Scenario: Download SK
    Tool: curl
    Preconditions: Usulan state=SelesaiSKTerbit, file di storage
    Steps:
      1. curl -L download URL -o /tmp/sk.pdf
      2. Assert file exists dan > 0 bytes
      3. file /tmp/sk.pdf → PDF document
    Evidence: .sisyphus/evidence/task-20-download-sk.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-20-upload-sk.png`
  - [ ] `task-20-download-sk.txt`

  **Commit**: YES
  - Message: `feat(kp-ui): admin SK upload + list pages`
  - Files: `resources/js/pages/kenaikan-pangkat/admin-sk/*.tsx`
  - Pre-commit: `npm run build`

- [ ] 21. UI Admin Checklist Template CRUD

  **What to do**:
  - Buat controller `app/Http/Controllers/BerkasChecklist/ChecklistTemplateController.php` (CRUD): index, create, store, edit, update, destroy + manage items (add/remove/reorder)
  - Buat Form Requests: `StoreChecklistTemplateRequest`, `UpdateChecklistTemplateRequest`, `StoreChecklistItemRequest`
  - Buat Policy `ChecklistTemplatePolicy` dengan permission `checklist.template.*`
  - Register routes di `routes/web.php` dengan middleware `iam.permission:checklist.template.view`
  - Page `resources/js/pages/admin/checklist-template/index.tsx`: table list template dengan filter domain
  - Page `resources/js/pages/admin/checklist-template/form.tsx`:
    - Form template (kode, nama, domain, deskripsi)
    - Nested form items (add/remove/reorder via drag-drop atau up/down button). Field item: kode, nama, is_wajib, urutan
    - Submit via Wayfinder action
  - Tulis test controller + policy

  **Must NOT do**:
  - JANGAN hardcode domain list (cuti, kenaikan_pangkat, mutasi, pensiun) — ambil dari `config('sikep.checklist_domains')` atau enum
  - JANGAN izinkan hapus template yang sudah dipakai di `BerkasChecklistSubmission` (FK restrictOnDelete dari T2 sudah handle)
  - JANGAN izinkan edit kode template setelah created (unique constraint)

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: `inertia-react-development`, `tailwindcss-development`, `wayfinder-development`, `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4
  - **Blocks**: None
  - **Blocked By**: T9

  **References**:
  - `resources/js/pages/referensi/jenis-dokumen/*.tsx` - Pattern CRUD admin ref
  - `app/Http/Controllers/Referensi/RefJenisDokumenController.php` - Pattern controller CRUD ref

  **WHY**: Referensi JenisDokumen adalah pola CRUD admin existing yang sederhana dan konsisten.

  **Acceptance Criteria**:
  - [ ] Controller + policy + routes + pages ada
  - [ ] List filter by domain bekerja
  - [ ] Create template + items nested sukses
  - [ ] Edit template: kode disabled, lainnya editable
  - [ ] Delete template in-use → error message user-friendly
  - [ ] `php artisan test --compact --filter=ChecklistTemplate` 100% hijau
  - [ ] `npm run build` sukses

  **QA Scenarios**:

  ```
  Scenario: Admin buat template + 3 items
    Tool: Playwright
    Preconditions: Admin login
    Steps:
      1. Navigate /admin/checklist-template
      2. Click "Tambah Template"
      3. Fill kode, nama, domain=kenaikan_pangkat
      4. Add 3 items (2 wajib, 1 opsional)
      5. Submit
      6. Assert template muncul di list, items count = 3
    Evidence: .sisyphus/evidence/task-21-create-template.png

  Scenario: Cannot delete in-use template
    Tool: Playwright
    Preconditions: Template dipakai di 1 submission
    Steps:
      1. Click Delete pada template
      2. Confirm
      3. Assert error message "Template sedang dipakai, tidak dapat dihapus"
    Evidence: .sisyphus/evidence/task-21-delete-in-use.png
  ```

  **Evidence to Capture**:
  - [ ] `task-21-create-template.png`
  - [ ] `task-21-delete-in-use.png`

  **Commit**: YES
  - Message: `feat(checklist): admin CRUD for checklist templates`
  - Files: `app/Http/Controllers/BerkasChecklist/*`, `app/Http/Requests/BerkasChecklist/*`, `app/Policies/ChecklistTemplatePolicy.php`, `routes/web.php`, `resources/js/pages/admin/checklist-template/*.tsx`, `tests/Feature/Http/Controllers/BerkasChecklist/*`
  - Pre-commit: `php artisan test --compact --filter=ChecklistTemplate && npm run build && vendor/bin/pint --dirty --format agent`

---

<!-- WAVE 5: INTEGRATION & POLISH -->

- [ ] 22. Auto-Update `riwayat_pangkat` + `pegawai.ref_pangkat_id` saat `SelesaiSKTerbit` + E2E Test

  **What to do**:
  - Tambah migration baru untuk **invariant DB constraint**: unique partial index pada `riwayat_pangkat` — hanya 1 row dengan `is_aktif = true` per `pegawai_id`:
    ```php
    // database/migrations/YYYY_MM_DD_HHMMSS_add_unique_aktif_riwayat_pangkat.php
    Schema::table('riwayat_pangkat', function (Blueprint $table) {
        // Partial unique index; sqlite support WHERE clause, mysql 8+ pakai generated column atau trigger
        // Strategy: conditional berdasarkan driver DB
    });
    ```
    - Untuk **sqlite** (dev/test): `CREATE UNIQUE INDEX riwayat_pangkat_aktif_unique ON riwayat_pangkat(pegawai_id) WHERE is_aktif = 1`
    - Untuk **mysql 8+** (production): tambah generated column `aktif_unique` (`IF(is_aktif = 1, pegawai_id, NULL) STORED`) + unique index pada column tersebut
    - Gunakan `DB::connection()->getDriverName()` di dalam migration `up()` untuk branching
    - `down()`: drop index
  - Buat event + listener:
    - Event `app/Events/UsulanKenaikanPangkat/UsulanKpSkTerbit.php` (readonly class) — payload: `UsulanKenaikanPangkat $usulan`
    - Listener `app/Listeners/UsulanKenaikanPangkat/SinkronkanRiwayatPangkat.php` — method `handle(UsulanKpSkTerbit $event)`:
      - DB transaction (`DB::transaction(function () { ... })`):
        - Set existing `riwayat_pangkat->where('pegawai_id', $usulan->pegawai_id)->update(['is_aktif' => false])` (soft deactivate)
        - Insert `RiwayatPangkat` baru: `pegawai_id`, `ref_pangkat_id = usulan.ref_pangkat_tujuan_id`, `no_sk = usulan.nomor_sk`, `tanggal_sk = usulan.tanggal_sk`, `tmt = usulan.tanggal_sk`, `pejabat_penetap = 'Biro Kepegawaian Mahkamah Agung RI'`, `is_aktif = true`, `masa_kerja_tahun = 0`, `masa_kerja_bulan = 0`, `gaji_pokok = null`
        - Update `Pegawai->ref_pangkat_id = usulan.ref_pangkat_tujuan_id`
        - Log ke activity dengan description "Riwayat pangkat disinkronkan dari usulan KP {id}"
      - **Urutan critical**: deactivate old **SEBELUM** insert new (karena unique partial index akan reject jika ada 2 aktif bersamaan)
    - Register listener di `app/Providers/AppServiceProvider.php` method `boot()` via `Event::listen(UsulanKpSkTerbit::class, SinkronkanRiwayatPangkat::class)`. **Catatan Laravel 11/12**: file `app/Providers/EventServiceProvider.php` **tidak ada** di streamlined structure — event registration dilakukan di `AppServiceProvider::boot()` atau via auto-discovery (listener class dengan method `handle(EventClass $event)` akan otomatis discovered jika `$shouldDiscoverEvents` true).
  - Modifikasi `UsulanKenaikanPangkatService::uploadSkFinal` (T13) untuk `event(new UsulanKpSkTerbit($usulan))` setelah transisi state berhasil
  - Tulis test end-to-end `tests/Feature/Integration/UsulanKpE2ETest.php`:
    - Flow lengkap dari Draft sampai SelesaiSKTerbit via HTTP controller
    - Assert setelah selesai: (a) `pegawai->ref_pangkat_id` = pangkat tujuan, (b) `pegawai->riwayatPangkat()->where('is_aktif', true)->count()` = 1, (c) `riwayat_pangkat` baru punya `no_sk` = usulan.nomor_sk
    - Negative: event TIDAK fire jika state transition gagal
    - Idempotent: ulangi upload SK (harusnya di-block dari state machine, tapi pastikan listener tidak duplikat riwayat)
    - Constraint enforcement: coba insert 2 row `is_aktif=true` manual → throw QueryException (unique constraint violation)

  **Must NOT do**:
  - JANGAN update `riwayat_pangkat` di dalam service `uploadSkFinal` — pakai event untuk decoupling
  - JANGAN fire event 2x untuk 1 transisi (sekali saat state = SelesaiSkTerbit)
  - JANGAN panggil listener manual di controller — pakai dispatch event
  - JANGAN hapus riwayat_pangkat lama — hanya set `is_aktif = false`
  - JANGAN reference `app/Providers/EventServiceProvider.php` — file ini tidak ada di Laravel 11/12 streamlined structure, pakai `AppServiceProvider::boot()`
  - JANGAN insert new riwayat sebelum deactivate old — unique partial index akan throw

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Event + listener + integrity invariant (hanya 1 aktif) + DB transaction + E2E test.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 5
  - **Blocks**: F1 (final verification)
  - **Blocked By**: T13, T16

  **References**:

  **Pattern References**:
  - `app/Services/RiwayatPangkatService.php` - Service existing untuk manipulasi riwayat
  - `app/Providers/AppServiceProvider.php` - Event registration di method `boot()` (Laravel 11/12 pattern)
  - `bootstrap/app.php` - Alternative: event registration via `->withEvents()` jika dibutuhkan
  - `app/Models/RiwayatPangkat.php` - Model target

  **WHY Each Reference Matters**:
  - RiwayatPangkatService mungkin punya helper method yang bisa dipakai (tidak reinvent)
  - Laravel 11/12 **tidak punya** `EventServiceProvider.php` (streamlined structure) — event registration di `AppServiceProvider::boot()` via `Event::listen()` adalah pattern aktif project

  **Acceptance Criteria**:
  - [ ] Event class + listener class ada di `app/Events/UsulanKenaikanPangkat/` dan `app/Listeners/UsulanKenaikanPangkat/`
  - [ ] Migration unique partial index `riwayat_pangkat_aktif_unique` sukses (branch per DB driver)
  - [ ] Listener ter-register di `AppServiceProvider::boot()` via `Event::listen(...)` (**bukan** di `EventServiceProvider.php` — file ini tidak ada)
  - [ ] E2E test: setelah upload SK, `pegawai->riwayatPangkat()->where('is_aktif', true)->first()->ref_pangkat_id` == usulan.ref_pangkat_tujuan_id
  - [ ] Invariant test: `riwayatPangkat->where('is_aktif', true)->count()` tetap 1 (tidak ganda)
  - [ ] DB constraint test: insert manual 2 riwayat aktif untuk pegawai sama → throw `Illuminate\Database\QueryException` (unique violation)
  - [ ] Activity log ter-create dengan deskripsi "Riwayat pangkat disinkronkan dari usulan KP"
  - [ ] `php artisan test --compact --filter=UsulanKpE2E` 100% hijau (minimal 6 test cases termasuk constraint enforcement)

  **QA Scenarios**:

  ```
  Scenario: E2E lengkap → riwayat pangkat tersinkronisasi
    Tool: Bash (pest feature test)
    Preconditions: Pegawai dengan 1 riwayat_pangkat aktif (pangkat asal), factory users role lengkap
    Steps:
      1. Flow HTTP lengkap (store → submit → verifikasi → tanda tangan → kirim biro → upload SK)
      2. Refresh pegawai dari DB
      3. Assert pegawai->ref_pangkat_id == pangkat_tujuan
      4. Assert riwayat_pangkat aktif count = 1
      5. Assert riwayat pangkat lama is_aktif = false
      6. Assert riwayat pangkat baru punya no_sk = nomor_sk usulan
    Expected Result: Semua assertion pass
    Evidence: .sisyphus/evidence/task-22-e2e.txt

  Scenario: Tidak fire event jika state transition gagal
    Tool: Bash (pest dengan Event::fake)
    Preconditions: Usulan state=DitandatanganiKetua (belum MenungguSK)
    Steps:
      1. Call uploadSkFinal → expect CouldNotPerformTransition
      2. Event::assertNotDispatched(UsulanKpSkTerbit::class)
    Expected Result: Event tidak fire
    Evidence: .sisyphus/evidence/task-22-no-event-on-fail.txt

  Scenario: Invariant 1 aktif terjaga + DB constraint enforce
    Tool: Bash (tinker + pest)
    Preconditions: Pegawai dengan 3 riwayat (1 aktif, 2 tidak aktif — dari historical migration)
    Steps:
      1. Simulate E2E flow
      2. Assert aktif count = 1 setelah selesai
      3. Coba insert row manual `riwayat_pangkat` dengan `is_aktif=true` untuk pegawai yang sama tanpa deactivate existing → expect QueryException (unique partial index violation)
    Expected Result: Invariant terjaga + DB constraint reject duplicate aktif
    Evidence: .sisyphus/evidence/task-22-invariant.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-22-e2e.txt`
  - [ ] `task-22-no-event-on-fail.txt`
  - [ ] `task-22-invariant.txt`

  **Commit**: YES
  - Message: `feat(kp): auto-sync riwayat_pangkat on SK terbit via event listener + DB invariant`
  - Files: `app/Events/UsulanKenaikanPangkat/UsulanKpSkTerbit.php`, `app/Listeners/UsulanKenaikanPangkat/SinkronkanRiwayatPangkat.php`, `app/Providers/AppServiceProvider.php` (update `boot()`), `database/migrations/*add_unique_aktif_riwayat_pangkat.php`, `app/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatService.php` (update), `tests/Feature/Integration/UsulanKpE2ETest.php`
  - Pre-commit: `php artisan test --compact --filter=UsulanKp && vendor/bin/pint --dirty --format agent`

- [ ] 23. Console Command Notifikasi Deadline Usulan KP

  **What to do**:
  - Buat console command `app/Console/Commands/NotifikasiDeadlineUsulanKp.php`:
    - Signature: `sikep:notifikasi-deadline-kp {--threshold-days=14}`
    - Logic: query usulan state=Draft atau PerluPerbaikan, hitung hari sampai `batas_usul` dari monitoring service, jika <= threshold dan belum notif dalam 7 hari terakhir → kirim `KenaikanPangkatDeadlineNotification`
    - Pegawai pemohon dapat notification (DB + Mail)
  - Buat notification `app/Notifications/KenaikanPangkatDeadlineNotification.php` — channel DB + Mail, message berisi sisa hari + link ke usulan
  - Register command auto-discovery + scheduler di `routes/console.php`:
    - `Schedule::command('sikep:notifikasi-deadline-kp')->dailyAt('07:00');`
    - `Schedule::command('sikep:notifikasi-kp')->dailyAt('07:30');` (dari T10)
  - Tulis test `tests/Feature/Commands/NotifikasiDeadlineUsulanKpTest.php`:
    - Usulan dengan batas_usul 10 hari lagi → notif dikirim
    - Usulan dengan batas_usul 20 hari → skip
    - Usulan sudah state=Diajukan (bukan Draft) → skip
    - Idempotent: run 2x dalam 1 hari → 1 notif per pegawai

  **Must NOT do**:
  - JANGAN kirim ke user tanpa link pegawai (user.pegawai_id null)
  - JANGAN hardcode threshold
  - JANGAN spam: pakai table tracking last_sent_at atau cek notifications DB (query exists)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Command + notification + scheduler + idempotency logic.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 5
  - **Blocks**: None
  - **Blocked By**: T13

  **References**:
  - `app/Console/Commands/SendKgbNotification.php` - Pattern command notif
  - `app/Notifications/KgbJatuhTempoNotification.php` - Pattern notification
  - `routes/console.php` - Registration scheduler

  **WHY**: KGB notification adalah twin pattern deadline.

  **Acceptance Criteria**:
  - [ ] Command `sikep:notifikasi-deadline-kp` bisa dijalankan
  - [ ] Scheduler registered (verify via `php artisan schedule:list`)
  - [ ] Idempotent: run 2x sehari tidak duplikat
  - [ ] `php artisan test --compact --filter=NotifikasiDeadlineUsulanKp` 100% hijau

  **QA Scenarios**:

  ```
  Scenario: Kirim notif ke usulan mendekati deadline
    Tool: Bash
    Preconditions: Usulan Draft dengan batas_usul 10 hari lagi, user dengan pegawai_id
    Steps:
      1. Notification::fake()
      2. php artisan sikep:notifikasi-deadline-kp --threshold-days=14
      3. assertSentTo user, KenaikanPangkatDeadlineNotification
    Expected Result: 1 notification sent
    Evidence: .sisyphus/evidence/task-23-notif-deadline.txt

  Scenario: Idempotent
    Tool: Bash
    Preconditions: Command sudah jalan sekali hari ini
    Steps:
      1. Run command lagi
      2. Count notifications DB hari ini
    Expected Result: Count sama sebelum dan sesudah run kedua
    Evidence: .sisyphus/evidence/task-23-idempotent.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-23-notif-deadline.txt`
  - [ ] `task-23-idempotent.txt`

  **Commit**: YES
  - Message: `feat(kp): console command for usulan deadline notifications + scheduler`
  - Files: `app/Console/Commands/NotifikasiDeadlineUsulanKp.php`, `app/Notifications/KenaikanPangkatDeadlineNotification.php`, `routes/console.php`, `tests/Feature/Commands/*`
  - Pre-commit: `php artisan schedule:list && php artisan test --compact --filter=NotifikasiDeadline && vendor/bin/pint --dirty --format agent`

- [ ] 24. Audit Trail Verification + Integration Tests

  **What to do**:
  - Verify `LogsActivity` trait aktif di SEMUA model baru: `UsulanKenaikanPangkat`, `UsulanKpApprovalStep`, `UsulanKpLampiran`, `BerkasChecklistTemplate`, `BerkasChecklistItem`, `BerkasChecklistSubmission`, `BerkasChecklistSubmissionItem`
  - Setiap model punya `getActivitylogOptions()` yang benar (`useLogName`, `logFillable`, `logOnlyDirty`, `dontLogEmptyChanges`)
  - Tulis comprehensive integration test `tests/Feature/Integration/AuditTrailKpTest.php`:
    - Create usulan → 1 activity log entry `log_name='usulan_kenaikan_pangkat'`, event=`created`
    - Update usulan → entry dengan `attribute_changes` berisi old+new values
    - State transition → entry + row di `usulan_kp_state_history` (dua jalur: activity log + history table)
    - Approval action → row di `usulan_kp_approver_history`
    - Delete → soft delete log
  - Tulis endpoint `GET /kenaikan-pangkat/usulan/{id}/activity` yang return timeline gabungan:
    - Activity log entries (spatie)
    - State history
    - Approver history
    - Sorted by timestamp descending
  - UI sudah handle di T19 tab Timeline — pastikan endpoint provide data
  - Test end-to-end: 1 flow lengkap → count activity log entries >= 10 (create + 7 transitions + upload SK + checklist updates)

  **Must NOT do**:
  - JANGAN pakai custom logger — spatie/laravel-activitylog saja
  - JANGAN log secret (password) atau file path internal ke `properties`
  - JANGAN expose activity log mentah di API public tanpa filter (admin only via policy)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Verification across all new models + integration test + timeline endpoint.
  - **Skills**: `pest-testing`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 5
  - **Blocks**: F1, F3 (final verification butuh audit trail lengkap)
  - **Blocked By**: T13, T14

  **References**:

  **Pattern References**:
  - `app/Models/Cuti/CutiPengajuan.php` - Pattern `LogsActivity` dengan `getActivitylogOptions()`
  - `app/Http/Controllers/ActivityLogController.php` - Pattern endpoint activity log existing
  - AGENTS.md section `spatie/laravel-activitylog rules` - Convention project

  **API/Type References**:
  - `Spatie\Activitylog\Models\Activity` - Model entry log
  - `activity_log` table structure (dari existing migration)

  **WHY**: Cuti sudah punya pola lengkap audit trail yang harus dimirror untuk konsistensi audit MA.

  **Acceptance Criteria**:
  - [ ] Semua 7 model baru pakai `LogsActivity` + `getActivitylogOptions()` benar
  - [ ] Endpoint `GET /kenaikan-pangkat/usulan/{id}/activity` return JSON gabungan 3 source
  - [ ] Test integration: flow lengkap → count entries >= 10
  - [ ] State history table + approver history table sudah ter-populate (dari T13 sudah)
  - [ ] `php artisan test --compact --filter=AuditTrailKp` 100% hijau
  - [ ] Policy `ActivityLogPolicy` (atau equivalent) enforce: pegawai lihat usulan sendiri saja

  **QA Scenarios**:

  ```
  Scenario: Activity log lengkap setelah flow E2E
    Tool: Bash (pest integration)
    Preconditions: Flow E2E sukses
    Steps:
      1. $u = usulan
      2. $activities = $u->activities
      3. Assert count >= 10
      4. Assert log_name == 'usulan_kenaikan_pangkat' untuk semua
      5. Assert ada event 'created', 'updated' (multiple), 'deleted' (tidak, soft delete belum)
    Expected Result: Counter correct, log_name consistent
    Evidence: .sisyphus/evidence/task-24-activity-count.txt

  Scenario: Timeline endpoint gabungkan 3 source
    Tool: curl
    Preconditions: Auth admin, usulan dengan transitions
    Steps:
      1. curl GET /kenaikan-pangkat/usulan/{id}/activity -H 'Accept: application/json'
    Expected Result: JSON array sorted desc, entries dari activity_log + state_history + approver_history
    Evidence: .sisyphus/evidence/task-24-timeline-endpoint.json

  Scenario: Policy enforce — pegawai lain tidak bisa lihat
    Tool: curl
    Preconditions: User pegawai B login, usulan milik pegawai A
    Steps:
      1. curl GET /kenaikan-pangkat/usulan/{id_A}/activity (as B)
    Expected Result: 403
    Evidence: .sisyphus/evidence/task-24-policy-403.txt
  ```

  **Evidence to Capture**:
  - [ ] `task-24-activity-count.txt`
  - [ ] `task-24-timeline-endpoint.json`
  - [ ] `task-24-policy-403.txt`

  **Commit**: YES
  - Message: `feat(kp): comprehensive audit trail + timeline endpoint + tests`
  - Files: `app/Models/**/*.php` (verify LogsActivity), `app/Http/Controllers/UsulanKenaikanPangkat/UsulanKenaikanPangkatController.php` (add activity action), `routes/web.php`, `tests/Feature/Integration/AuditTrailKpTest.php`
  - Pre-commit: `php artisan test --compact --filter='AuditTrailKp|UsulanKenaikanPangkat' && vendor/bin/pint --dirty --format agent`

---


## Final Verification Wave (MANDATORY — after ALL implementation tasks)

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.
> **Do NOT auto-proceed after verification. Wait for user's explicit approval before marking work complete.**

- [ ] F1. **Plan Compliance Audit** — `oracle`

  Read plan end-to-end. Untuk setiap "Must Have": verifikasi implementasi ada (read file, run tinker command, check evidence). Untuk setiap "Must NOT Have": search codebase untuk forbidden patterns — reject dengan `file:line` jika ditemukan. Verifikasi file evidence di `.sisyphus/evidence/`. Bandingkan deliverables vs plan.

  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`

  Jalankan `php artisan test --compact` + `vendor/bin/pint --test --format agent` + `npm run build`. Review semua file yang diubah untuk: `as any`/`@ts-ignore`, `DB::raw()` yang tidak perlu, empty catch, `console.log`/`dd()` di production code, komentar commented-out, import tidak dipakai. Check AI slop: komentar berlebihan, over-abstraction, generic names (`data`, `result`, `item`, `temp`). Check compliance dengan AGENTS.md (Pint compliant, PHPDoc dalam Bahasa Indonesia, nama variabel English).

  Output: `Build [PASS/FAIL] | Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high` (+ `playwright` skill)

  Start dari state bersih. Eksekusi SETIAP QA scenario dari SETIAP task — ikuti step persis, capture evidence. Test integrasi cross-task: (a) monitoring KP 12 bulan → (b) buat usulan dari eligible → (c) approve berjenjang → (d) upload SK → (e) riwayat pangkat auto-update. Test edge cases: usulan dengan checklist belum lengkap (gate blocks), approver tanpa permission (403), pegawai PPPK (tidak muncul di eligible), hukuman disiplin aktif (filter). Save ke `.sisyphus/evidence/final-qa/`.

  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`

  Untuk setiap task: baca "What to do", baca actual diff (git log/diff). Verifikasi 1:1 — semua yang di spec ter-build (no missing), tidak ada di luar spec (no creep). Check "Must NOT do" compliance. Detect cross-task contamination: task N menyentuh file task M. Flag perubahan unaccounted (file diubah tapi tidak ada di task manapun). Verifikasi scope guardrails: tidak ada kode mutasi/pensiun/DUPAK di plan ini.

  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

Commit per task setelah verifikasi local pass. Format Conventional Commits, scope per modul.

- **T1**: `docs(cuti): audit report CUTI-01..CUTI-10 compliance` — `.sisyphus/evidence/cuti-audit/*`, `php artisan test --compact --filter=Cuti`
- **T2**: `feat(checklist): add berkas checklist tables (polymorphic)` — `database/migrations/*berkas_checklist*`, `php artisan migrate:fresh --seed`
- **T3**: `feat(kp): add usulan kenaikan pangkat tables` — `database/migrations/*usulan_kenaikan_pangkat*`, `php artisan migrate:fresh`
- **T4**: `feat(nomor-surat): add placeholder generator with MA format` — `app/Services/NomorSurat/*`, `php artisan test --compact --filter=NomorSurat`
- **T5**: `feat(sikep): add adapter interface with null impl` — `app/Services/Sikep/*`, test filter `Sikep`
- **T6**: `feat(iam): seed permissions for SIKEP P1` — `database/seeders/PermissionSikepP1Seeder.php`, `php artisan db:seed --class=PermissionSikepP1Seeder`
- **T7**: `refactor(kp-monitoring): hard switch to 12 monthly periods (BKN 4/2025)` — `app/Services/KenaikanPangkatMonitoringService.php`, `php artisan test --compact --filter=KenaikanPangkatMonitoring`
- **T8-T24**: Per-task commits dengan pattern `feat|refactor|test(scope): desc`
- **F1-F4**: No commit (review tasks only)

Pre-commit check tiap task:
```bash
php artisan test --compact --filter={TestPattern} && vendor/bin/pint --dirty --format agent
```

Group commit pada merge: gunakan branch `feat/sikep-p1-administrasi` dengan squash ke main setelah final verification user-approved.

---

## Success Criteria

### Verification Commands

```bash
# 1. All tests pass
php artisan test --compact
# Expected: 100% green, 0 skipped, 0 risky

# 2. No style issues
vendor/bin/pint --test --format agent
# Expected: "No style issues found."

# 3. Frontend build
npm run build
# Expected: built successfully, no TS errors

# 4. Monitoring 12 periode works
php artisan tinker --execute "
  \$s = app(App\Services\KenaikanPangkatMonitoringService::class);
  for (\$b = 1; \$b <= 12; \$b++) {
    \$stats = \$s->getKpStats(periodeBulan: \$b, periodeTahun: 2026);
    echo 'bulan '.\$b.': total='.\$stats['total'].PHP_EOL;
  }
"
# Expected: 12 periode return angka (tidak throw, tidak nol-semua kecuali data memang kosong)

# 5. State machine usulan KP bekerja
php artisan tinker --execute "
  \$u = App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat::factory()->create();
  \$u->state->transitionTo(App\States\UsulanKenaikanPangkat\DiajukanState::class);
  echo get_class(\$u->fresh()->state);
"
# Expected: "App\States\UsulanKenaikanPangkat\DiajukanState"

# 6. Nomor surat placeholder
php artisan tinker --execute "
  echo app(App\Services\NomorSurat\NomorSuratService::class)->generate(klasifikasi: 'KP.01.1');
"
# Expected regex: ^W\d+-U\d+\/\d+\/KP\.01\.1\/[IVXLCDM]+\/\d{4}$

# 7. SikepAdapter null impl
php artisan tinker --execute "
  \$a = app(App\Services\Sikep\SikepAdapter::class);
  var_dump(\$a->pushUsulan(null));
"
# Expected: NULL (no throw)

# 8. Audit trail tercatat
php artisan tinker --execute "
  \$u = App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat::latest()->first();
  echo \$u->activities()->count();
"
# Expected: > 0

# 9. Permission enforcement
curl -s -o /dev/null -w "%{http_code}" http://localhost/kenaikan-pangkat/usulan -H "Cookie: session_unauthorized"
# Expected: 302 (redirect login) or 403
```

### Final Checklist

- [ ] Semua 27 task (24 inti + T16 split jadi T16a/b/c/d) + 4 final verification complete
- [ ] All "Must Have" present (verified via commands)
- [ ] All "Must NOT Have" absent (verified via F4 scope check)
- [ ] All tests pass (Pest + frontend build)
- [ ] Evidence files in `.sisyphus/evidence/` exist per task
- [ ] F1-F4 all APPROVE
- [ ] User explicit okay diberikan setelah review F1-F4
