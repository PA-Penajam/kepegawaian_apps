# Spesifikasi Desain: Modul Cuti & Izin

**Tanggal**: 2026-05-01
**Status**: Draft — Menunggu Review User
**Prioritas**: Tinggi (paling kritikal di gap analysis administrasi kepegawaian)
**Estimasi Effort**: ~40 hari kerja (~6-8 minggu) dengan subagent-driven development

---

## 1. Executive Summary

Modul Cuti & Izin adalah subsistem dari `kepegawaian-apps` yang menangani seluruh siklus pengajuan, persetujuan, pencatatan, dan pelaporan cuti pegawai (PNS dan PPPK) di Pengadilan Agama Penajam Paser Utara. Modul ini menjadi sistem-of-record (SoR) untuk seluruh data cuti, dan mempublikasikan event ke aplikasi konsumen (`attendance-qr-system`, `surat-app` di masa depan) melalui webhook signed.

Modul ini mengimplementasikan:
- 6 jenis cuti PNS (Tahunan, Sakit, Besar, Melahirkan, Alasan Penting, CLTN) + 4 jenis cuti PPPK
- Bucket-based saldo accounting (N/N-1/N-2 dengan FIFO + carry-over)
- Workflow 4-step (Pegawai → Petugas Kepegawaian → Atasan Langsung → Pejabat Berwenang) dengan dukungan Plh/Plt
- Workflow eksternal khusus Ketua PA (persetujuan via PTA)
- Pencabutan dengan refund proporsional
- Generate dokumen otomatis (form Lampiran II SE Sekma 13/2019 + SK Pejabat)
- PWA terbatas untuk pegawai portal
- Integrasi dengan custom IAM untuk role-permission

## 2. Goals & Non-Goals

### Goals

1. **Replace process kertas**: Pegawai ajukan cuti via aplikasi, output dokumen siap tanda tangan
2. **Saldo akurat & auditable**: Bucket FIFO mencegah hangus saldo yang tidak fair, audit trail lengkap
3. **Workflow patuh regulasi**: PP 11/2017, PP 17/2020, PP 49/2018, UU ASN 20/2023, SE Sekma 13/2019, PERMA 7/2015
4. **Integrasi attendance**: Event push ke `attendance-qr-system` agar status pegawai (sedang_cuti) sinkron
5. **Self-service**: Pegawai bisa cek saldo, ajukan, lihat status, batalkan tanpa harus ke ruang TU
6. **Reporting siap audit**: Export rekap saldo, riwayat pemakaian, monitoring per pegawai/jenis

### Non-Goals (di luar scope MVP)

1. **Multi-tenant**: Hanya untuk PA Penajam Paser Utara (multi-instansi adalah future work)
2. **Cuti sakit tier 3-4** (>14 hari): Flagged "manual handling" karena butuh proses tim penguji kesehatan + uji kelayakan jabatan
3. **Kompensasi piket cuti bersama**: Cuti bersama diperlakukan sebagai libur biasa (tidak memotong saldo, tidak ada modul kompensasi)
4. **Auto-completion ke status SELESAI**: Status `DISETUJUI` adalah terminal di kepegawaian-apps; expiry di-handle `attendance-qr-system` berdasarkan `tanggal_selesai` dari webhook
5. **Workflow eskalasi internal Ketua PA**: Untuk Ketua PA cuti, atasan internal kosong → output dokumen, upload ke PTA secara manual, scan SK PTA upload kembali ke sistem
6. **Mobile native app**: PWA cukup untuk pegawai portal
7. **Integrasi SIASN-MyASN BKN**: Future work
8. **Offline submit cuti**: Service worker hanya cache read-side (saldo, list)

## 3. Architecture Overview

### 3.1 Konteks Super-Apps

```
┌─────────────────────────────────────────────────────────────────┐
│                    Super-Apps Kepegawaian                       │
│                                                                 │
│  ┌─────────────────────┐    Master Data    ┌────────────────┐   │
│  │  kepegawaian-apps   │◄──────────────────│ Domain Apps:    │   │
│  │  (SoR + IdP)        │ ──── Webhook ────►│ - attendance    │   │
│  │  - Pegawai          │                   │ - surat (TBD)   │   │
│  │  - Cuti  ★          │                   │ - eval (TBD)    │   │
│  │  - IAM              │                   └────────────────┘   │
│  │  - Kinerja          │                                         │
│  └─────────────────────┘                                         │
└─────────────────────────────────────────────────────────────────┘
```

Modul Cuti adalah bagian dari kepegawaian-apps (master data + identity provider), bukan aplikasi terpisah. Decoupling ke domain app lain via API REST + webhook event.

### 3.2 Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Database | MySQL 8 dengan ULID PK |
| Frontend | Inertia.js v2, React 19, TypeScript, shadcn/ui, Tailwind v4 |
| State Machine | `spatie/laravel-model-states` |
| PDF Generation | `spatie/laravel-pdf` (Browsershot/Chromium) |
| Storage | Google Drive (`masbug/flysystem-google-drive-ext`) + local cache |
| Activity Log | `spatie/laravel-activitylog` |
| Excel | `maatwebsite/excel` (sudah ada) |
| Auth | Laravel Sanctum + Fortify (sudah ada) + custom IAM SSO |
| Queue | Redis (default Laravel) |
| PWA | `vite-plugin-pwa` (scope: `/cuti/saya`) |
| Testing | Pest/PHPUnit (Feature + Unit), Dusk (optional Browser) |

### 3.3 Prinsip Arsitektur

1. **Single Source of Truth**: Database adalah SoR; cache/index lain harus rebuild-able
2. **Event-Driven Decoupling**: Status berubah → INSERT outbox → async webhook delivery
3. **State Machine sebagai Authorization Boundary**: Permission check = "transition ini valid untuk role X di state Y?"
4. **YAGNI Strict**: Hanya implementasi yang dibutuhkan PA Penajam, bukan untuk skala BKN
5. **Audit-First**: Setiap state change + saldo manipulation + lampiran access ter-log

## 4. Aktor & Permissions

### 4.1 Aktor

| Aktor | Peran | Pre-existing Role |
|---|---|---|
| Pegawai | Pemohon cuti | `pegawai` |
| Petugas Kepegawaian | Pre-validator: lampiran, kelengkapan, saldo | `petugas_kepegawaian` |
| Atasan Langsung | Rekomendasi (per `atasan_langsung_id`, e-Kinerja pattern) | derived dari `pegawai.atasan_langsung_id` |
| Pejabat Berwenang | Final approval (umumnya Ketua PA) | `pejabat_berwenang` |
| Plh/Plt | Substitusi atasan/pejabat saat berhalangan | derived dari `penugasan_plh_plt` aktif |
| Admin Kepegawaian | Master data, saldo awal, override | `admin_kepegawaian` |
| System Service Account | API consumer (attendance-qr-system) | `system_attendance` |

### 4.2 Permissions (Tambahan untuk IAM)

```
cuti.submit               — Mengajukan cuti
cuti.cancel-own           — Membatalkan pengajuan sendiri
cuti.validate             — Validasi (Petugas Kepegawaian)
cuti.recommend            — Rekomendasi (Atasan Langsung)
cuti.approve              — Setujui/tolak (Pejabat Berwenang)
cuti.suspend              — Tangguhkan
cuti.handle-external      — Persetujuan eksternal (Ketua PA flow)
cuti.return-for-revision  — Kembalikan untuk revisi
cuti.view-all             — Monitoring semua cuti
cuti.admin                — Master data + saldo awal
cuti.api-read             — Read-only API access (untuk app lain)
```

## 5. Data Model

### 5.1 Modifikasi Tabel Existing

**`ref_jabatan`** (tambahan kolom):
- `jenis_jabatan` ENUM('struktural', 'fungsional_hakim', 'fungsional_kepaniteraan', 'fungsional_kesekretariatan', 'pelaksana')
- `atasan_jabatan_id` (FK self, nullable) — atasan struktural sesuai PERMA 7/2015

**`pegawai`** (tambahan kolom):
- `sub_unit_kerja_id` (FK ke `ref_sub_unit_kerja`, nullable) — penempatan operasional sesuai SK
- `atasan_struktural_id` (FK self, nullable) — sesuai PERMA 7/2015 (formal/dokumen)
- `atasan_langsung_id` (FK self, nullable) — sesuai e-Kinerja/SKP (untuk approval cuti)
- `gdrive_folder_id` (string 64, nullable) — folder GDrive khusus pegawai (lazy-created)

### 5.2 Tabel Baru (17 tabel)

#### Master Data

**`ref_sub_unit_kerja`** — sub unit di bawah unit utama (Sekretariat, Kepaniteraan)
- `id` ULID, `kode`, `nama`, `unit_induk` (enum), `parent_id` (self FK), `aktif`

**`ref_jenis_cuti`** — definisi 6 jenis cuti + aturan
- `id`, `kode` (CT/CS/CB/CM/CAP/CLTN), `nama`, `min_hari`, `max_hari`, `kuota_per_tahun`, `eligible_pns`, `eligible_pppk`, `butuh_lampiran`, `pejabat_berwenang_jabatan_id` (FK)

**`kalender_libur`** — libur nasional, cuti bersama, libur instansional
- `id`, `tanggal` (UNIQUE), `nama`, `jenis` (enum), `keppres_referensi`, `tahun`

#### Saldo

**`cuti_saldo_tahunan`** — bucket per pegawai per tahun
- `id`, `pegawai_id` (FK), `tahun` (UNIQUE composite), `kuota_n`, `sisa_n`, `sisa_n_minus_1`, `sisa_n_minus_2`, `kadaluarsa_at`

**`cuti_saldo_pemakaian`** — track tiap pemakaian per bucket (untuk FIFO + refund)
- `id`, `pengajuan_cuti_id` (FK), `pegawai_id` (FK), `tahun_bucket`, `jenis_bucket` (enum N-2/N-1/N), `jumlah_hari` (boleh negatif untuk refund), `urutan_alokasi`

#### Pengajuan

**`pengajuan_cuti`** (header) — 1 record per pengajuan logis
- `id`, `nomor_pengajuan` (UNIQUE, format `CT-YYYY-NNNNN`), `pegawai_id`, `jenis_cuti_id`, `tanggal_mulai`, `tanggal_selesai`, `jumlah_hari_kerja`, `alasan`, `alamat_selama_cuti`, `kontak_selama_cuti`, `status` (untuk spatie state)
- `butuh_persetujuan_eksternal` (bool), `instansi_atasan_eksternal`, `nomor_sk_eksternal`, `gdrive_file_id_sk_eksternal`, `tanggal_sk_eksternal`
- `tanggal_efektif_cabut`, `hari_dijalani`, `hari_dikembalikan` (untuk pencabutan)
- `submitted_at`, `decided_at`, `created_at`, `updated_at`

**`pengajuan_cuti_versi`** — snapshot per revisi (versioning)
- `id`, `pengajuan_cuti_id` (FK), `versi` (1, 2, 3...), `status_versi` (aktif/dikembalikan), `snapshot_data` (JSON), `created_at`

**`pengajuan_cuti_riwayat`** — timeline event per pengajuan
- `id`, `pengajuan_cuti_id` (FK), `aktor_id` (FK users), `aksi`, `dari_status`, `ke_status`, `catatan`, `acted_as_plh_for` (nullable), `created_at`

**`pengajuan_cuti_lampiran`** — files (surat dokter, dll)
- `id`, `pengajuan_cuti_id` (FK), `jenis_lampiran` (enum), `nama_file_asli`, `gdrive_file_id`, `gdrive_folder_path`, `mime_type`, `ukuran_bytes`, `checksum_sha256`, `uploaded_by`, `uploaded_at`, `deleted_at`

**`lampiran_access_log`** — audit access lampiran
- `id`, `lampiran_id` (FK), `accessed_by` (FK users), `access_type` (view/download), `ip_address`, `user_agent`, `accessed_at`

#### Plh/Plt

**`penugasan_plh_plt`** — substitusi atasan
- `id`, `pegawai_id` (FK, yang ditugaskan), `menggantikan_pegawai_id` (FK), `jenis` (plh/plt), `tanggal_mulai`, `tanggal_selesai`, `aktif`, `nomor_sk`, `tanggal_sk`

#### Outbox & Audit

**`cuti_event_log`** (outbox) — event yang akan/sudah dikirim ke webhook
- `id`, `pengajuan_cuti_id`, `event_type`, `event_version`, `payload` (JSON), `destination_app`, `destination_url`, `attempts`, `next_retry_at`, `delivered_at`, `last_response_code`, `last_response_body`, `failed_at`

**`cuti_audit_log`** — audit kustom (saldo manipulation, rollover, dll)
- `id`, `pegawai_id`, `event`, `detail` (JSON), `actor_id`, `created_at`

**`gdrive_folder_cache`** — cache folder GDrive per (pegawai, tahun)
- `id`, `pegawai_id` (FK), `tahun`, `gdrive_folder_id`, UNIQUE(pegawai_id, tahun)

**`iam_app_subscription`** — registry consumer event (untuk fan-out)
- `id`, `iam_application_id` (FK), `event_pattern` (e.g., `cuti.*`, `cuti.disetujui`), `webhook_url`, `shared_secret`, `aktif`

### 5.3 Storage GDrive

**Authentication**: OAuth2 via Google OAuth Playground (refresh token di `.env`)
- Akun: akun Google kantor yang sudah dipakai untuk integrasi API lain
- Library: `masbug/flysystem-google-drive-ext` sebagai disk Laravel (`Storage::disk('gdrive')`)

**Folder Structure**:
```
Kepegawaian-Cuti/
   ├── {NIP}/                    (folder per pegawai, lazy create)
   │   ├── {tahun}/              (sub-folder tahunan)
   │   │   ├── CT-{tahun}-{nourut}-{slug}-{timestamp}.{ext}
   │   │   └── ...
   │   └── ...
   └── _system/
       ├── _trash/               (soft delete, retention 90 hari)
       └── _orphan/              (file pegawai yang dihapus)
```

**File Access Strategy**: Hybrid Proxy + Local Cache
1. Pertama akses: Laravel stream dari GDrive, simpan ke `storage/app/cache/lampiran/{file_id}`
2. Akses berikutnya (TTL 24 jam): serve dari local cache
3. Cron daily: clean cache yang tidak diakses >24 jam
4. Permission check: policy berdasarkan relasi pegawai-atasan-approver
5. Audit: setiap akses tercatat di `lampiran_access_log`
6. Verify: SHA-256 checksum saat download dari GDrive

## 6. State Machine & Workflow

### 6.1 Status Enum

```
DRAFT                          — pegawai save draft, belum submit
DIAJUKAN                       — submit, menunggu Petugas Kepegawaian
DIVALIDASI                     — Petugas approve, menunggu next
DIREKOMENDASIKAN               — Atasan Langsung approve, menunggu Pejabat
PERLU_REVISI                   — dikembalikan ke pegawai
DISETUJUI                      — Pejabat approve (terminal de facto)
DITANGGUHKAN                   — Pejabat tunda
DITOLAK                        — Pejabat reject (TERMINAL)
DIBATALKAN                     — pegawai batal sebelum approve (TERMINAL)
MENUNGGU_PERSETUJUAN_EKSTERNAL — khusus Ketua PA, tunggu SK PTA
MENUNGGU_PENCABUTAN            — request cabut setelah disetujui
DICABUT                        — pencabutan disetujui (TERMINAL)
```

**Catatan**: Status `SELESAI` tidak ada — `DISETUJUI` adalah terminal di kepegawaian-apps. Expiry di-handle `attendance-qr-system` via `tanggal_selesai` dari webhook.

### 6.2 Implementation

Pakai `spatie/laravel-model-states`:
- 12 state class di `app/States/PengajuanCuti/`
- 15 transition class di `app/States/PengajuanCuti/Transitions/`
- Compile-time safety: transition tidak terdaftar throws `CouldNotPerformTransition`

### 6.3 Transition Rules

(Lihat tabel lengkap di Section 3.4 brainstorming notes; ringkasan kunci di sini)

**Workflow normal**: DRAFT → DIAJUKAN → DIVALIDASI → DIREKOMENDASIKAN → DISETUJUI

**Workflow eksternal (Ketua PA)**: DRAFT → DIAJUKAN → DIVALIDASI → MENUNGGU_PERSETUJUAN_EKSTERNAL → DISETUJUI (setelah upload SK PTA) atau DITOLAK

**Revisi**: Setiap step approver bisa return ke PERLU_REVISI; pegawai resubmit → versi baru di `pengajuan_cuti_versi`

**Pencabutan**:
- Sebelum mulai (`tanggal_mulai > now`): refund full
- Saat berjalan (`tanggal_mulai <= now <= tanggal_selesai`): refund proporsional (hari kerja sisa)
- Setelah selesai: tidak bisa cabut

**Plh/Plt Substitution**: Setiap transition yang butuh approval atasan/pejabat cek apakah aktor adalah atasan asli ATAU Plh/Plt aktif. Catat `acted_as_plh_for` di `pengajuan_cuti_riwayat`.

### 6.4 Edge Cases

1. Atasan langsung mutasi mid-flow → re-route ke atasan baru (lookup ulang saat approver buka)
2. Saldo habis saat approval (cuti lain di-approve duluan) → guard re-check, throw error
3. Tanggal lewat saat masih queue → cron auto-reject dengan alasan "Tanggal kadaluarsa"
4. Atasan = pemohon (Wakil Ketua) → atasan langsung adalah Ketua PA
5. Pemohon = Ketua PA → workflow eksternal (atasan internal kosong, output dokumen ke PTA manual)
6. Lampiran sakit di-reject Petugas → PERLU_REVISI dengan catatan re-upload
7. Cuti melahirkan overlap cuti tahunan disetujui → suggest cabut cuti tahunan dulu

## 7. Business Rules

### 7.1 Saldo Bucket FIFO + Carry-over

**Pengambilan**: FIFO dari bucket paling tua (N-2 → N-1 → N)
**Carry-over**: max 6 hari dari N ke N-1 (PP 11/2017 strict)
**Akumulasi N-2 + N-1**: max 18 hari
**Max ambil sekaligus**: 24 hari kerja
**Annual rollover**: 1 Januari cron — N-2 hangus, N-1 jadi N-2, N (cap 6) jadi N-1, N baru = 12 hari

**Refund FIFO Reverse**: Saat pencabutan, kembalikan ke bucket terakhir dipakai dulu (pakai compensating entry, bukan UPDATE — preserve audit trail)

### 7.2 Algoritma Hari Kerja

- Hari kerja: Senin-Jumat
- Skip: weekend, libur nasional, cuti bersama, libur instansional
- Cuti bersama TIDAK memotong saldo (diperlakukan seperti libur biasa)
- Lintas tahun: alokasi terpisah per bucket tahun (1 record `pengajuan_cuti`, multiple entries di `cuti_saldo_pemakaian`)

### 7.3 Cuti Tahunan (CT)

- Kuota 12 hari/tahun, carry max 6 ke N+1
- Min 1 hari, max ambil 24 hari sekaligus
- Saldo dipotong: ya
- Eligible: PNS + PPPK

### 7.4 Cuti Sakit (CS) — Tier MVP

| Tier | Durasi | Lampiran | Approver |
|---|---|---|---|
| 1 | 1-3 hari | Surat keterangan sendiri | Atasan Langsung |
| 2 | 4-14 hari | Surat dokter | Atasan + Pejabat |
| 3-4 | >14 hari | **Manual handling** (flag di sistem) | — |

- Saldo dipotong: tidak
- Eligible: PNS + PPPK

### 7.5 Cuti Besar (CB)

- 3 bulan, syarat masa kerja ≥6 tahun (dari TMT CPNS)
- Sisa cuti tahunan tahun berjalan **HANGUS** jika <3 bulan
- Eligible: PNS only (tidak untuk PPPK)

### 7.6 Cuti Melahirkan (CM)

- 3 bulan
- Eligible: PNS + PPPK perempuan, max anak ke-3
- Lampiran wajib: surat keterangan dokter (estimasi atau kelahiran)

### 7.7 Cuti Alasan Penting (CAP)

- Max 30 hari kerja, durasi tergantung alasan
- Eligible: PNS + PPPK
- Alasan wajib detail (validation min 50 karakter)

### 7.8 CLTN

- 1-3 tahun, syarat masa kerja ≥5 tahun
- Eligible: PNS only (tidak untuk PPPK — UU ASN 20/2023 belum jelas)
- Implikasi: tidak gajian, tidak masa kerja

### 7.9 Cuti Bersama

- BUKAN jenis cuti yang diajukan; di-set admin via `kalender_libur`
- Tidak memotong saldo
- Tidak ada modul kompensasi piket (di luar scope MVP)

### 7.10 Validasi Submit (Ringkas)

- Tanggal mulai >= today (kecuali konfigurasi advance day berlaku)
- Tanggal selesai >= tanggal mulai
- Saldo cukup (cuti tahunan)
- Tidak overlap dengan pengajuan aktif lain
- Lampiran sesuai tier
- PPPK eligibility check (whitelist 4 jenis)
- Atasan langsung ada (kecuali workflow eksternal)

### 7.11 Validasi Approval (Re-check)

- Saldo masih cukup (concurrent submission protection)
- Tanggal mulai belum lewat
- Pegawai masih aktif
- Approver masih punya wewenang (atau Plh/Plt aktif)

### 7.12 Saldo Awal saat Sistem Live

- Operator input via `/admin/cuti/saldo-awal`
- Form per pegawai + bulk Excel import (template downloadable)
- Field: NIP, tahun, sisa N-2, sisa N-1, sisa N

## 8. API & Events

### 8.1 Web Routes (Inertia)

Lihat detail di brainstorming notes Section 5.2. Pengelompokan:
- `/cuti/saya/*` (Pegawai Portal)
- `/cuti/approval/*` (Approval Center)
- `/cuti/{id}/lampiran/*` (Dokumen)
- `/cuti/monitoring/*` (Monitoring)
- `/admin/cuti/*` (Admin)

### 8.2 API Routes (REST untuk attendance-qr-system)

```
GET  /api/v1/cuti/aktif/{nip}        — cuti yang aktif hari ini
GET  /api/v1/cuti/saldo/{nip}        — saldo terkini
GET  /api/v1/cuti/riwayat/{nip}      — riwayat semua
POST /api/v1/cuti/cek-konflik         — cek konflik tanggal (untuk validasi attendance)
GET  /api/v1/cuti/aktif-bulk          — bulk untuk sync awal
```

Security: 4-layer (HMAC + Sanctum + Rate Limit + IP Whitelist) — pola yang sudah ada di `Pegawai API`.

### 8.3 Webhook Events

Events dipublikasikan ke consumer via outbox pattern:
- `cuti.disetujui` — status → DISETUJUI (workflow normal atau eksternal)
- `cuti.ditolak` — status → DITOLAK
- `cuti.dicabut` — status → DICABUT (refund proporsional)
- `cuti.ditangguhkan` — status → DITANGGUHKAN

**Tidak ada** `cuti.selesai` — handled di consumer berdasarkan `tanggal_selesai`.

**Payload format**: JSON dengan `event`, `version`, `event_id` (ULID, untuk idempotency), `issued_at`, `data`.

**Signature**: HMAC-SHA256 dengan shared secret per consumer (dari `iam_app_subscription`). Header `X-Kepegawaian-Signature`, `X-Kepegawaian-Timestamp`, `X-Kepegawaian-Event-Id`.

### 8.4 Outbox Pattern

```
Transaction (atomic):
  1. UPDATE pengajuan_cuti SET status = 'disetujui'
  2. INSERT INTO cuti_event_log (event, payload, ...)
COMMIT

Async:
  ProcessCutiOutboxJob (cron tiap 30 detik)
  → SELECT undelivered events
  → POST ke consumer URL with HMAC sign
  → Mark delivered/failed
  → Retry dengan exponential backoff (30s → 2m → 10m → 1h → 6h)
  → Max 5 attempts → alert admin
```

### 8.5 Multi-Consumer Fan-Out

Tabel `iam_app_subscription` registry: setiap consumer subscribe event pattern (`cuti.*`, `cuti.disetujui`, dll). Saat publish, fan-out ke semua subscriber yang match pattern. Add new consumer = INSERT row, no code change.

### 8.6 Authentication Strategy

**Web (UI)**: SSO via custom IAM (existing flow)
**API consumer**: Sanctum personal access token + HMAC + IP whitelist
**Webhook outbound**: HMAC sign payload + timestamp untuk replay protection

## 9. UI Flow

### 9.1 Halaman Utama

1. **`/cuti/saya/saldo`** — Dashboard saldo dengan visualisasi 3 bucket card
2. **`/cuti/ajukan`** — Form wizard 3-step (jenis+periode → detail+lampiran → konfirmasi)
3. **`/cuti/{id}`** — Detail dengan timeline workflow + action buttons kontekstual
4. **`/cuti/approval/persetujuan`** — Inbox per role + bulk approve + keyboard shortcuts (J/K/A/R)
5. **`/cuti/monitoring/kalender`** — Calendar view bulanan dengan event blocks per pegawai
6. **`/admin/cuti/saldo-awal`** — Form per pegawai + bulk Excel import

### 9.2 Komponen Reusable

```
resources/js/components/cuti/shared/
  CutiStatusBadge, CutiTimeline, SaldoBucketCard, KalenderCutiPicker,
  LampiranUploader, LampiranPreview, ActionButtons, HariKerjaIndicator
```

### 9.3 Notifikasi

- **In-app**: bell icon di header, database notification, polling 30 detik
- **Email**: queued via Laravel Mail
- **Multi-channel** via `Notification::send()`

### 9.4 PWA Scope

Service worker scope: `/cuti/saya` saja
- Cache: list pengajuan + saldo (NetworkFirst)
- Offline page: read-only, indikator "data offline"
- Submit dalam mode offline: TIDAK didukung (defer untuk v2)
- Approver/admin: desktop-only (no PWA)

### 9.5 Theme

Lanjutkan tema shadcn/ui existing project. No special branding.

## 10. File Structure & Implementation

### 10.1 Backend

```
app/
├── Models/Cuti/                  (12 models)
├── Models/Ref/                   (RefJenisCuti, RefSubUnitKerja - new)
├── States/PengajuanCuti/         (12 states + 15 transitions)
├── Http/Controllers/Cuti/        (8 controllers)
├── Http/Controllers/Cuti/Admin/  (5 controllers)
├── Http/Controllers/Api/Cuti/    (1 controller)
├── Services/Cuti/                (14 services + Storage subdir)
├── Http/Requests/Cuti/           (form requests + custom rules)
├── Policies/                     (3 policies)
├── Notifications/Cuti/           (9 notifications)
├── Jobs/Cuti/                    (7 jobs)
├── Events/Cuti/ + Listeners/Cuti/
└── Console/Commands/Cuti/        (4 commands)

config/cuti.php                   — settings centralized
config/filesystems.php            — disk gdrive

database/
├── migrations/                   (17 migrations)
└── seeders/Cuti/                 (5 seeders)
```

### 10.2 Frontend

```
resources/js/
├── pages/cuti/
│   ├── pegawai/                  (4 pages)
│   ├── approval/                 (4 pages)
│   ├── monitoring/               (5 pages)
│   └── admin/                    (5 pages)
├── components/cuti/
│   ├── shared/                   (8 reusable components)
│   ├── pegawai/                  (form wizard + steps)
│   ├── approval/                 (inbox + bulk + dialogs)
│   ├── monitoring/               (charts + calendar)
│   └── admin/                    (forms + import dialog)
├── hooks/cuti/                   (6 custom hooks)
└── types/cuti.ts                 (TypeScript types)

vite.config.ts                    — add vite-plugin-pwa with scope /cuti/saya
```

### 10.3 Tests

```
tests/
├── Feature/Cuti/                 (50+ tests)
│   ├── Submission/
│   ├── Workflow/
│   ├── Saldo/
│   ├── Cancellation/
│   ├── HariKerja/
│   ├── Storage/
│   ├── Notifications/
│   ├── Webhook/
│   └── Api/
├── Unit/Cuti/
│   ├── States/
│   ├── Services/
│   └── Rules/
└── Browser/Cuti/                 (optional, Dusk E2E)
```

**Strategi**: TDD ketat untuk Service & Business Logic, integration test untuk Workflow, smoke test untuk Controllers.

### 10.4 Documentation

```
docs/cuti/
├── README.md                     — modul overview
├── architecture.md
├── workflow-states.md
├── api-reference.md
├── webhook-contract.md
├── setup-gdrive.md
├── runbook-saldo-awal.md
├── runbook-rolover-tahunan.md
└── troubleshooting.md
```

### 10.5 Activity Log Integration

Pakai `spatie/laravel-activitylog` dengan `log_name = 'cuti'`:
- Semua state transition
- Saldo manipulation (manual override admin, rollover, refund)
- Lampiran access (sudah di `lampiran_access_log`, di-mirror untuk unified query)
- Plh/Plt switching

## 11. Estimasi Scope

| Kategori | Files | LoC | Effort |
|---|---|---|---|
| Migrations | 17 | ~800 | 2h |
| Models | 14 | ~1,500 | 2h |
| State Classes | 27 | ~1,200 | 3h |
| Controllers | 14 | ~1,800 | 3h |
| Services | 17 | ~3,000 | 6h |
| Form Requests | 17 | ~1,200 | 2h |
| Notifications | 9 | ~500 | 1h |
| Jobs | 7 | ~600 | 2h |
| Frontend Pages | 18 | ~2,500 | 5h |
| Frontend Components | 30+ | ~3,500 | 6h |
| Tests | 50+ | ~5,000 | 8h |
| **TOTAL** | **~220 files** | **~21,600 LoC** | **~40 hari kerja** |

(Estimasi konservatif; subagent-driven dev paralel bisa lebih cepat)

## 12. Implementation Approach

**MVP scope**: Semua section di spec ini, sekaligus, ~6-8 minggu.

**Approach**: Subagent-driven development dengan paralel task untuk independent units (e.g., parallel: schema migrations, state classes, frontend skeleton).

**Phasing internal** (untuk ordering, bukan release phasing):
1. **Foundation** (week 1): Migrations + Models + States + Config
2. **Saldo Layer** (week 2): BukuCutiService, HariKerjaCalculator, SaldoAwal (admin), tests
3. **Workflow Layer** (week 3-4): Controllers, Services, transitions, notifications, tests
4. **Frontend Layer** (week 5-6): Pages, components, wizard, approval inbox, PWA
5. **Integration & Polish** (week 7): Webhook + outbox, API endpoints, monitoring, calendar
6. **Testing & Documentation** (week 8): E2E test, runbooks, deployment guide

## 13. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| GDrive refresh token expire | Monitoring health daily; runbook regenerate; backup periodic ke local |
| Webhook delivery gagal terus-menerus | Outbox pattern dengan exp backoff + alert admin setelah max attempts |
| Saldo race condition (concurrent approval) | DB transaction + re-check saldo di approve guard |
| Plh/Plt salah resolve | Audit log mencatat `acted_as_plh_for` untuk forensic |
| Regulasi berubah (UU ASN turunan terbit) | Config-driven rules di `config/cuti.php` (kuota, threshold) |
| User confused dengan jenis cuti baru | UI dengan helper text + tooltip per jenis cuti |
| File GDrive corrupt | SHA-256 checksum verify saat download |
| State machine bug (transition tidak valid lolos) | Spatie state machine compile-time safety + comprehensive test |

## 14. Open Questions / Future Work

1. **Cuti sakit tier 3-4** (>14 hari): perlu integrasi dengan tim penguji kesehatan + uji kelayakan jabatan — defer ke v2
2. **Multi-tenant**: arsitektur sudah siap (instansi dari config), aktivasi defer
3. **SIASN-MyASN integration**: future feature setelah MA punya integration spec
4. **CLTN PPPK**: tunggu peraturan turunan UU ASN 20/2023 terbit
5. **Cuti bersama dengan kompensasi piket**: defer
6. **Mobile native app**: PWA cukup untuk MVP
7. **Offline submit cuti**: PWA hanya cache read-side; submit offline defer ke v2
8. **Auto-approve untuk Atasan Langsung yang juga pemohon**: kasus eselon paling atas — sementara pakai workflow eksternal

## 15. References (Regulasi)

| Regulasi | Detail |
|---|---|
| PP No. 11 Tahun 2017 | Manajemen PNS — definisi 7 jenis cuti |
| PP No. 17 Tahun 2020 | Amandemen PP 11/2017 — carry-over rules |
| Peraturan BKN No. 24 Tahun 2017 | Petunjuk teknis cuti PNS |
| Peraturan BKN No. 7 Tahun 2021 | Amandemen Peraturan BKN 24/2017 |
| PP No. 49 Tahun 2018 | Manajemen PPPK — 4 jenis cuti |
| Peraturan BKN No. 7 Tahun 2022 | Petunjuk teknis cuti PPPK |
| UU No. 20 Tahun 2023 | UU ASN — kesetaraan hak cuti PNS-PPPK |
| PP No. 94 Tahun 2021 | Disiplin PNS — sanksi tidak masuk kerja |
| SE Sekma No. 13 Tahun 2019 | Hierarki khusus kewenangan cuti hakim di MA — Lampiran II form template |
| PERMA No. 7 Tahun 2015 | Organisasi & tata kerja kepaniteraan-kesekretariatan PA |

## 16. Dependencies (Composer)

Tambahan ke `composer.json`:
```json
"spatie/laravel-model-states": "^2.0",
"spatie/laravel-pdf": "^1.5",
"masbug/flysystem-google-drive-ext": "^2.4"
```

Sudah ada (existing): `spatie/laravel-activitylog`, `maatwebsite/excel`, `laravel/sanctum`, `laravel/fortify`.

Tambahan ke `package.json`:
```json
"vite-plugin-pwa": "^0.20.0"
```

---

## Approval

- [ ] Reviewed by user (Moohard)
- [ ] Spec self-review passed
- [ ] Ready to invoke `superpowers:writing-plans` skill
