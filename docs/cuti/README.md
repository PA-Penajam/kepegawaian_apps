# Modul Cuti & Izin — Fase 1 MVP

> Dokumentasi teknis modul Cuti & Izin untuk Sistem Kepegawaian PA Penajam Paser Utara.

## Daftar Isi

- [Overview](#overview)
- [Arsitektur](#arsitektur)
- [State Machine](#state-machine)
- [Struktur File](#struktur-file)
- [Getting Started](#getting-started)
- [API Endpoints](#api-endpoints)
- [Scheduled Commands](#scheduled-commands)

---

## Overview

Modul Cuti & Izin Fase 1 MVP menyediakan fitur pengajuan, persetujuan, dan pencatatan cuti untuk pegawai PA Penajam Paser Utara. Jenis cuti yang didukung pada MVP:

| Kode | Nama | Saldo-Driven |
|------|------|:------------:|
| `CT` | Cuti Tahunan | Ya (12 hari/tahun, carry-over maks N-1) |
| `CS_TIER1` | Cuti Sakit Tier 1 (≤14 hari) | Tidak |
| `CS_TIER2` | Cuti Sakit Tier 2 (15-60 hari) | Tidak |
| `CAP` | Cuti Alasan Penting | Tidak |

### Workflow Persetujuan 4-Langkah

1. **Pegawai** membuat pengajuan (DRAFT → DIAJUKAN)
2. **Petugas Kepegawaian** memverifikasi (DIAJUKAN → DIVERIFIKASI)
3. **Atasan Langsung** menyetujui (DIVERIFIKASI → DISETUJUI_ATASAN)
4. **Pejabat Berwenang** memberikan persetujuan akhir (DISETUJUI_ATASAN → DISETUJUI)

### Pola Saldo: Pure Ledger + FIFO Multi-Bucket

Untuk jenis cuti yang bersifat `saldo_driven` (saat ini hanya CT), saldo dihitung dari `SUM(jumlah_hari)` pada tabel `cuti_saldo_ledger`. Tidak ada kolom saldo terdenormalisasi — saldo selalu derivable dari ledger.

Strategi FIFO multi-bucket digunakan untuk carry-over: saldo tahun N-1 dikonsumsi terlebih dahulu sebelum saldo tahun N. Carry-over otomatis dijalankan setiap 1 Januari.

**Referensi spesifikasi lengkap:** [`docs/superpowers/specs/2026-05-01-modul-cuti-design.md`](../superpowers/specs/2026-05-01-modul-cuti-design.md)

---

## Arsitektur

### Pola Desain Utama

| Pola | Penerapan |
|------|-----------|
| **Service Layer** | Semua business logic ada di `app/Services/Cuti/`, bukan di controller |
| **Spatie State Machine** | `spatie/laravel-model-states` mengelola transisi state pengajuan |
| **Pure Ledger Pattern** | Saldo CT dihitung dari append-only ledger, bukan kolom counter |
| **Transactional Outbox** | Event disimpan di `cuti_events` lalu di-dispatch ke consumer via scheduled command |
| **Rule Engine** | Validasi bisnis per jenis cuti melalui `CutiRuleEngine` + class per jenis |

### Keputusan Desain Kunci

- **`lockForUpdate()`** pada `cuti_alokasi_tahunan` dan `cuti_pengajuan` untuk mencegah race condition saldo
- **UNIQUE constraint** pada tabel alokasi (`pegawai_nip + jenis_cuti_kode + tahun_hak`) untuk idempotency
- **FIFO bucket** untuk carry-over: saldo tahun lama dipakai duluan
- **No cross-year leave**: pengajuan tidak boleh melintas tahun kalender (kebijakan PA Penajam)
- **Transactional outbox**: event dicatat dalam transaksi yang sama dengan perubahan state, lalu di-dispatch terpisah

### Aliran Data Saldo CT

```
kreditAlokasi()         → kredit              (+12 hari)
debitPendingFifo()      → debit_pending       (-N hari, FIFO dari bucket terlama)
commitConfirmed()       → debit_void          (+N) + debit_confirmed (-N)
voidPending()           → debit_void          (+N, untuk penolakan)
processRefund()         → kredit_refund       (+N, untuk pencabutan setelah disetujui)
penyesuaian()           → penyesuaian         (±N, adjustment manual admin)
```

---

## State Machine

### Diagram Transisi

```
                                  ┌───────────────────────┐
                                  │    DRAFT (default)     │
                                  └───────┬───────┬───────┘
                                          │       │
                                   submit │       │ cancel
                                          ▼       ▼
                                  ┌──────────┐  ┌──────────┐
                                  │ DIAJUKAN │  │DIBATALKAN│
                                  └────┬──┬──┘  └──────────┘
                                       │  │
                              verify   │  │ reject
                                       ▼  ▼
                              ┌─────────────┐  ┌──────────────────┐
                              │DIVERIFIKASI │  │DITOLAK_KEPEGAWAIAN│
                              └───┬──┬──────┘  └──────────────────┘
                                  │  │
                  approve_atasan  │  │ reject
                                  ▼  ▼
                    ┌─────────────────┐  ┌───────────────┐
                    │DISETUJUI_ATASAN │  │DITOLAK_ATASAN │
                    └───┬──┬──────────┘  └───────────────┘
                        │  │
        approve_pejabat │  │ reject
                        ▼  ▼
                ┌───────────┐  ┌────────────────┐
                │ DISETUJUI │  │DITOLAK_PEJABAT │
                └─────┬─────┘  └────────────────┘
                      │
                      │ cancel_after_approved
                      ▼
            ┌────────────────────────┐
            │DICABUT_SETELAH_DISETUJUI│
            └────────────────────────┘
```

### Konfigurasi State (Spatie)

Didefinisikan di [`app/States/Cuti/PengajuanState.php`](../../app/States/Cuti/PengajuanState.php):

```
DRAFT             → DIAJUKAN, DIBATALKAN
DIAJUKAN          → DIVERIFIKASI, DITOLAK_KEPEGAWAIAN
DIVERIFIKASI      → DISETUJUI_ATASAN, DITOLAK_ATASAN
DISETUJUI_ATASAN  → DISETUJUI, DITOLAK_PEJABAT
DISETUJUI         → DICABUT_SETELAH_DISETUJUI
```

State terminal (tidak ada transisi keluar): `DIBATALKAN`, `DITOLAK_*`, `DICABUT_SETELAH_DISETUJUI`.

---

## Struktur File

### Backend

```
app/
├── Models/Cuti/                          # 15 Eloquent models
│   ├── CutiJenisMaster.php               # Master jenis cuti (CT, CS_TIER1, dll.)
│   ├── CutiLiburMaster.php               # Hari libur nasional & cuti bersama
│   ├── CutiKonfigurasi.php               # Konfigurasi key-value
│   ├── CutiAlokasiTahunan.php            # Anchor row saldo per pegawai per tahun
│   ├── CutiPengajuan.php                 # Model utama pengajuan cuti
│   ├── CutiSaldoLedger.php               # Ledger append-only untuk saldo CT
│   ├── CutiPengajuanPeriode.php          # Periode tanggal pengajuan
│   ├── CutiPengajuanLampiran.php         # Lampiran dokumen
│   ├── CutiPengajuanApprovalStep.php     # Audit trail langkah approval
│   ├── CutiPengajuanApproverHistory.php  # Riwayat reassignment approver
│   ├── CutiPengajuanStateHistory.php     # Riwayat transisi state
│   ├── CutiPengajuanPdf.php              # Metadata file PDF yang digenerate
│   ├── CutiEvent.php                     # Outbox event
│   ├── CutiEventDelivery.php             # Delivery attempt ke consumer
│   └── CutiJenisPerStatusPegawai.php     # Mapping jenis cuti per status pegawai
│
├── Services/Cuti/                        # 8 service + 1 subdirectory
│   ├── PengajuanCutiService.php          # Orchestrator: create, submit pengajuan
│   ├── WorkflowService.php               # Transisi state + approval/rejection
│   ├── SaldoLedgerService.php            # CRUD ledger saldo CT
│   ├── HariKerjaCalculatorService.php    # Hitung hari kerja (exclude libur & weekend)
│   ├── ApproverResolverService.php       # Resolusi atasan & pejabat berwenang
│   ├── CarryOverProcessorService.php     # Proses carry-over saldo awal tahun
│   ├── EventDispatcherService.php        # Dispatch outbox events ke consumer webhook
│   ├── ConsumerRegistry.php              # Registry consumer webhook dari config
│   └── Rules/                            # Validasi bisnis per jenis cuti
│       ├── CutiRule.php                  # Interface rule
│       ├── CutiRuleEngine.php            # Dispatcher ke rule yang sesuai
│       ├── CutiTahunanRule.php           # Validasi CT (saldo, overlap, dll.)
│       ├── CutiSakitTier1Rule.php        # Validasi CS Tier 1
│       ├── CutiSakitTier2Rule.php        # Validasi CS Tier 2 (wajib lampiran)
│       └── CutiAlasanPentingRule.php     # Validasi CAP
│
├── States/Cuti/                          # 11 state classes (Spatie)
│   ├── PengajuanState.php                # Base state + konfigurasi transisi
│   ├── DraftState.php
│   ├── DiajukanState.php
│   ├── DiverifikasiState.php
│   ├── DisetujuiAtasanState.php
│   ├── DisetujuiState.php
│   ├── DibatalkanState.php
│   ├── DitolakKepegawaianState.php
│   ├── DitolakAtasanState.php
│   ├── DitolakPejabatState.php
│   └── DicabutSetelahDisetujuiState.php
│
├── Http/Controllers/Cuti/                # 5 controllers
│   ├── PengajuanController.php           # CRUD pengajuan
│   ├── ApprovalController.php            # Inbox, verify, approve, reject, reassign
│   ├── SaldoController.php               # Dashboard saldo, admin init, adjust
│   ├── PdfController.php                 # Generate & download PDF cuti
│   └── AuditController.php               # Halaman audit trail
│
├── Http/Requests/Cuti/                   # 4 form requests
│   ├── SubmitPengajuanRequest.php
│   ├── ApproveRequest.php
│   ├── RejectRequest.php
│   └── ReassignApproverRequest.php
│
├── Policies/Cuti/
│   └── CutiPengajuanPolicy.php           # Authorization policy
│
├── Notifications/Cuti/                   # 4 notifikasi
│   ├── PengajuanMenungguVerifikasi.php   # Notif ke petugas kepegawaian
│   ├── PengajuanMenungguApproval.php     # Notif ke atasan/pejabat
│   ├── PengajuanDisetujui.php            # Notif ke pemohon: disetujui
│   └── PengajuanDitolak.php              # Notif ke pemohon: ditolak
│
├── Exceptions/Cuti/                      # 7 custom exceptions
│   ├── SaldoTidakCukupException.php
│   ├── OverlapPengajuanException.php
│   ├── TransitionTidakValidException.php
│   ├── CancelTidakDiizinkanException.php
│   ├── CrossYearLeaveException.php
│   ├── SubmitTerlambatException.php
│   └── AlokasiTidakAdaException.php
│
└── Console/Commands/Cuti/                # 3 scheduled commands
    ├── ProcessCarryOverCommand.php       # cuti:carry-over
    ├── DispatchPendingEventsCommand.php  # cuti:dispatch-events
    └── ExpireOverdueDraftsCommand.php    # cuti:expire-drafts
```

### Frontend (Inertia + React 19)

```
resources/js/
├── pages/cuti/
│   ├── saldo/
│   │   ├── my-dashboard.tsx              # Dashboard saldo pegawai sendiri
│   │   ├── admin-index.tsx               # Admin: lihat saldo semua pegawai
│   │   └── admin-init.tsx                # Admin: inisialisasi saldo awal
│   ├── pengajuan/
│   │   ├── create.tsx                    # Form buat pengajuan baru
│   │   └── show.tsx                      # Detail pengajuan + timeline approval
│   ├── approval/
│   │   └── inbox.tsx                     # Inbox approval untuk atasan/pejabat
│   └── admin/
│       └── audit.tsx                     # Admin: halaman audit trail
│
└── components/cuti/
    ├── FormPengajuan.tsx                 # Form component pengajuan cuti
    ├── KartuSaldo.tsx                    # Card komponen tampilan saldo
    ├── TimelineApproval.tsx              # Timeline langkah-langkah approval
    ├── DialogApprove.tsx                 # Dialog konfirmasi approve
    ├── DialogReject.tsx                  # Dialog konfirmasi reject + alasan
    ├── DialogCancel.tsx                  # Dialog konfirmasi pembatalan
    └── DialogAdjustSaldo.tsx             # Dialog penyesuaian saldo admin
```

### Database Migrations

```
database/migrations/
├── 2026_05_02_000001_create_cuti_jenis_master_table.php
├── 2026_05_02_000002_create_cuti_libur_master_table.php
├── 2026_05_02_000003_create_cuti_konfigurasi_table.php
├── 2026_05_02_000004_create_cuti_alokasi_tahunan_table.php
├── 2026_05_02_000005_create_cuti_pengajuan_table.php
├── 2026_05_02_000006_create_cuti_saldo_ledger_table.php
├── 2026_05_02_000007_create_cuti_pengajuan_periode_table.php
├── 2026_05_02_000008_create_cuti_pengajuan_lampiran_table.php
├── 2026_05_02_000009_create_cuti_pengajuan_approval_steps_table.php
├── 2026_05_02_000010_create_cuti_pengajuan_approver_history_table.php
├── 2026_05_02_000011_create_cuti_pengajuan_state_history_table.php
├── 2026_05_02_000012_create_cuti_pengajuan_pdf_table.php
├── 2026_05_02_000013_create_cuti_events_table.php
├── 2026_05_02_000014_create_cuti_event_deliveries_table.php
└── 2026_05_02_000015_create_cuti_jenis_per_status_pegawai_table.php
```

### Konfigurasi & Seeders

```
config/cuti.php                                  # Consumer webhook config
database/seeders/
├── CutiJenisMasterSeeder.php                    # Seed 4 jenis cuti
├── CutiJenisPerStatusPegawaiSeeder.php          # Mapping jenis cuti per status
└── CutiPermissionSeeder.php                     # Permission IAM untuk cuti
```

---

## Getting Started

### Prasyarat

**Composer packages** (sudah ada di `composer.json`):
- `spatie/laravel-model-states ^2.7`
- `spatie/laravel-pdf ^1.5`
- `spatie/laravel-activitylog ^5.0`

**NPM packages** (sudah ada di `package.json`):
- `puppeteer` (untuk PDF generation via Browsershot)

### Instalasi

```bash
# 1. Install dependencies
composer install
npm install

# 2. Jalankan migrasi
php artisan migrate

# 3. Seed data master cuti
php artisan db:seed --class=CutiJenisMasterSeeder
php artisan db:seed --class=CutiJenisPerStatusPegawaiSeeder
php artisan db:seed --class=CutiPermissionSeeder

# 4. Build frontend
npm run build
```

### Menjalankan Test

```bash
# Semua test modul cuti
php artisan test --filter=Cuti

# Compact output
php artisan test --compact --filter=Cuti
```

Semua **97 test** harus PASS.

---

## API Endpoints

### Web Routes (Inertia)

| Method | URI | Name | Deskripsi |
|--------|-----|------|-----------|
| `GET` | `/cuti/saya` | `cuti.saya` | Dashboard saldo pegawai |
| `GET` | `/cuti/pengajuan/baru` | `cuti.pengajuan.create` | Form pengajuan baru |
| `POST` | `/cuti/pengajuan` | `cuti.pengajuan.store` | Simpan pengajuan |
| `GET` | `/cuti/pengajuan/{id}` | `cuti.pengajuan.show` | Detail pengajuan |
| `GET` | `/cuti/inbox` | `cuti.inbox` | Inbox approval |
| `POST` | `/cuti/pengajuan/{id}/verify` | `cuti.pengajuan.verify` | Verifikasi kepegawaian |
| `POST` | `/cuti/pengajuan/{id}/approve-atasan` | `cuti.pengajuan.approve-atasan` | Approve atasan langsung |
| `POST` | `/cuti/pengajuan/{id}/approve-pejabat` | `cuti.pengajuan.approve-pejabat` | Approve pejabat berwenang |
| `POST` | `/cuti/pengajuan/{id}/reject` | `cuti.pengajuan.reject` | Tolak pengajuan |
| `POST` | `/cuti/pengajuan/{id}/cancel` | `cuti.pengajuan.cancel` | Batalkan pengajuan |
| `POST` | `/cuti/pengajuan/{id}/reassign-approver` | `cuti.pengajuan.reassign` | Reassign approver |
| `GET` | `/cuti/pengajuan/{id}/pdf` | `cuti.pengajuan.pdf` | Download PDF surat cuti |

### Admin Web Routes

| Method | URI | Name | Deskripsi |
|--------|-----|------|-----------|
| `GET` | `/admin/cuti/saldo` | `admin.cuti.saldo.index` | Lihat saldo semua pegawai |
| `GET` | `/admin/cuti/saldo/init` | `admin.cuti.saldo.init` | Form inisialisasi saldo |
| `POST` | `/admin/cuti/saldo/init` | `admin.cuti.saldo.init.store` | Simpan inisialisasi saldo |
| `POST` | `/admin/cuti/saldo/adjust` | `admin.cuti.saldo.adjust` | Penyesuaian saldo manual |
| `GET` | `/admin/cuti/audit` | `admin.cuti.audit.index` | Halaman audit trail |

### API Routes (Sanctum, JSON)

| Method | URI | Name | Deskripsi |
|--------|-----|------|-----------|
| `GET` | `/api/cuti/pengajuan` | `api.cuti.pengajuan.index` | List pengajuan (paginasi) |
| `GET` | `/api/cuti/pengajuan/{id}` | `api.cuti.pengajuan.show` | Detail pengajuan JSON |
| `GET` | `/api/cuti/saldo/{nip}` | `api.cuti.saldo.show` | Ringkasan saldo pegawai |
| `GET` | `/api/cuti/saldo/{nip}/ledger` | `api.cuti.saldo.ledger` | Riwayat ledger saldo |

Semua API route dilindungi `auth:sanctum` dengan throttle 60 request/menit.

---

## Scheduled Commands

| Command | Jadwal | Deskripsi |
|---------|--------|-----------|
| `cuti:carry-over` | **1 Januari 00:05** | Proses carry-over saldo CT tahun lama ke tahun baru. Saldo N-1 yang tersisa dipindahkan sebagai bucket terpisah di tahun N. |
| `cuti:dispatch-events` | **Setiap menit** | Dispatch event dari outbox (`cuti_events`) ke consumer webhook yang terdaftar di `config/cuti.php`. Retry dengan backoff. |
| `cuti:expire-drafts` | **Setiap hari 00:30** | Expire pengajuan yang masih berstatus DRAFT lebih dari N hari (default 7 hari). |

### Registrasi di Scheduler

Command-command di atas perlu didaftarkan di `routes/console.php` atau `bootstrap/app.php`:

```php
Schedule::command('cuti:dispatch-events')->everyMinute();
Schedule::command('cuti:expire-drafts')->dailyAt('00:30');
Schedule::command('cuti:carry-over')->yearlyOn(1, 1, '00:05');
```

---

## Lisensi

Modul ini adalah bagian dari Sistem Kepegawaian PA Penajam Paser Utara. Hak cipta dilindungi.
