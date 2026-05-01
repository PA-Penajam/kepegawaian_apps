# Spesifikasi Desain: Modul Cuti & Izin

**Project**: kepegawaian-apps (Pengadilan Agama Penajam Paser Utara)
**Tanggal**: 2026-05-01
**Status**: Draft v7 (decision lock — open questions di-ACK user)
**Author**: Claude (Opus 4.7) bersama Moohard

---

## 1. Executive Summary

Modul Cuti & Izin adalah greenfield development di `kepegawaian-apps` untuk
menggantikan proses manual berbasis kertas dengan sistem digital
end-to-end. Modul ini mencakup pengajuan, approval berjenjang, manajemen
saldo cuti berbasis **pure ledger**, generasi PDF formulir, dan
integrasi dengan `attendance-qr-system` via webhook signed.

Modul dirilis bertahap dalam **3 fase** untuk mengelola risiko dan
mempercepat time-to-value:

- **Fase 1 (MVP, ~3-4 minggu)**: Cuti Tahunan, Cuti Sakit tier 1-2, CAP
- **Fase 2 (Lanjutan, ~3-4 minggu)**: Cuti Besar, Melahirkan, CLTN, Plh/Plt,
  workflow eksternal Ketua PA, GDrive, PWA, calendar
- **Fase 3 (Future)**: Tier sakit 3-4, integrasi SIASN, multi-tenant

Prioritas: Fase 1 menutupi ~80% volume cuti harian (tahunan + sakit
ringan + CAP) dengan kompleksitas rendah, lalu Fase 2 menambah fitur
spesifik PA (Plh/Plt, workflow eksternal Ketua).

---

## 2. Goals & Non-Goals

### Goals

1. Digitalisasi penuh proses cuti & izin di lingkungan PA Penajam Paser
   Utara, dari pengajuan sampai arsip.
2. Manajemen saldo cuti yang **akurat secara akuntansi** — tidak ada
   double-counting, tidak ada negative balance, audit trail per transaksi.
3. Workflow approval berjenjang yang sesuai dengan hierarki **e-Kinerja**
   (atasan langsung → pejabat berwenang) dan ketentuan PERMA 7/2015.
4. Integrasi 2-arah dengan `attendance-qr-system` untuk konsistensi
   data presensi vs cuti.
5. Compliance regulatif: PP 11/2017, PP 17/2020, PP 49/2018, Peraturan
   BKN 24/2017, Peraturan BKN 7/2021, UU ASN 20/2023, SE Sekma 13/2019.
6. Audit trail lengkap untuk semua perubahan state (siapa, kapan, kenapa).

### Non-Goals (Eksplisit)

1. **Bukan** menggantikan attendance-qr-system — modul ini hanya
   **memberitahu** attendance-system tentang periode cuti via webhook.
2. **Bukan** sistem payroll — perhitungan tunjangan/gaji selama cuti
   tidak masuk scope.
3. **Bukan** sistem disiplin — pelanggaran (alpha, terlambat tanpa izin)
   tidak masuk modul cuti, tetap di attendance-qr-system.
4. **Bukan** integrasi SIASN BKN di Fase 1 (defer ke Fase 3 setelah
   regulasi & API stabil).
5. **MVP tidak mendukung Plh/Plt, workflow eksternal Ketua PA, CLTN PPPK,
   dan tier 3-4 cuti sakit** — defer ke Fase 2/3.

---

## 3. Scope per Fase (REVISED)

### Fase 1 — MVP (3-4 minggu)

**Jenis cuti yang didukung**:
- Cuti Tahunan (CT) — PNS & PPPK
- Cuti Sakit Tier 1 (≤14 hari, surat dokter) — PNS & PPPK
- Cuti Sakit Tier 2 (15 hari - 1 bulan, surat dokter pemerintah) — PNS & PPPK
- Cuti Alasan Penting (CAP) — PNS & PPPK

**Workflow**: 4-step linear
1. Pegawai submit
2. Petugas Kepegawaian verifikasi (lengkap & saldo OK)
3. Atasan Langsung (kolom `atasan_langsung_id` — pola e-Kinerja) approve
4. Pejabat Berwenang (Ketua PA / Sekretaris) approve

**Reassign approver (MVP)**: Hanya **manual administratif** — admin
kepegawaian bisa memindahkan tanggung jawab approve dari pegawai A ke
pegawai B (mis. saat A mutasi keluar, atau A blocking inbox terlalu
lama). Wajib isi `alasan` yang masuk ke `cuti_pengajuan_approver_history`.
**TIDAK ADA auto-resolution Plh/Plt** di MVP — fitur tersebut (deteksi
otomatis approver yang sedang cuti & route ke Plh) di-defer ke Fase 2.

**Saldo**:
- Inisialisasi manual oleh admin (per pegawai per tahun)
- Carry-over otomatis tiap awal tahun via scheduled command
- Refund saldo CT (FIFO ke bucket asal) jika cuti dicabut saat berjalan
  — hanya hari kerja yang belum berjalan yang dikembalikan

**Storage**: Local disk (`storage/app/cuti/{NIP}/{tahun}/`)
**PDF**: Simple template (form sederhana, no signature pad)
**Notifikasi**: Database notifications + in-app inbox (no email blast)
**UI**: Single-page form (no wizard), inbox approval per role, audit
trail viewer
**Integrasi attendance**: 1-way webhook (cuti disetujui → notify
attendance) dengan outbox pattern

### Fase 2 — Lanjutan (3-4 minggu)

**Jenis cuti tambahan**:
- Cuti Besar
- Cuti Melahirkan (PNS) / Cuti Persalinan (PPPK)
- Cuti Karena Alasan Pribadi (CAP) extended
- CLTN (Cuti di Luar Tanggungan Negara) — PNS only

**Workflow tambahan**:
- Plh/Plt resolution untuk approver yang sedang cuti
- Workflow eksternal Ketua PA (status `MENUNGGU_PERSETUJUAN_EKSTERNAL`,
  upload SK PTA scan)
- Pencabutan multi-jenis dengan UI advanced (refund FIFO MVP sudah
  tersedia di Fase 1 untuk CT)

**Storage migrasi**: Local → GDrive (`Kepegawaian-Cuti/{NIP}/{tahun}/`)
**UI lanjutan**: Wizard 3-step, calendar bulanan, bulk approve,
keyboard shortcut, PWA scope `/cuti/saya`
**PDF lanjutan**: Template lengkap dengan logo, signature placeholder
**Notifikasi**: Email + WhatsApp (via 3rd party gateway)

### Fase 3 — Future (TBD)

- Cuti Sakit Tier 3 (1-6 bulan) & Tier 4 (>6 bulan)
- Integrasi SIASN MyASN BKN
- Multi-tenant (untuk PA lain di lingkungan PTA)
- Dashboard analytics & reporting (annual report cuti)

---

## 4. Architecture

### High-Level

```
┌─────────────────────────────────────────────────────────────┐
│                    kepegawaian-apps                         │
│  ┌────────────┐  ┌─────────────┐  ┌──────────────────────┐ │
│  │  Web (UI)  │  │  API REST   │  │  Webhook Outbox      │ │
│  │  Inertia   │  │  Sanctum    │  │  (events+deliveries) │ │
│  └─────┬──────┘  └──────┬──────┘  └──────────┬───────────┘ │
│        │                │                    │             │
│  ┌─────▼────────────────▼────────────────────▼──────────┐  │
│  │              Application Service Layer               │  │
│  │  PengajuanCutiService │ SaldoLedgerService           │  │
│  │  WorkflowService      │ EventDispatcherService       │  │
│  └─────┬────────────────────────────────────────────────┘  │
│        │                                                   │
│  ┌─────▼────────────────────────────────────────────────┐  │
│  │                 Domain Layer                         │  │
│  │  PengajuanCuti (state machine via spatie)           │  │
│  │  CutiSaldoLedger (pure ledger)                      │  │
│  │  Approver Snapshot Pattern                          │  │
│  └─────┬────────────────────────────────────────────────┘  │
│        │                                                   │
│  ┌─────▼────────────────────────────────────────────────┐  │
│  │              Persistence (MySQL)                     │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────┬────────────────────────────────────┘
                         │ HMAC-signed webhook
                         ▼
              ┌────────────────────────┐
              │  attendance-qr-system  │
              └────────────────────────┘
```

### Layer Responsibilities

- **UI (Inertia + React)**: Form pengajuan, inbox approval, calendar,
  audit viewer. **Pakai Inertia routes (`web.php`)** dengan
  `Inertia::render()` + session auth — bukan API-driven SPA. API REST
  hanya untuk integrasi eksternal & polling client (lihat decision
  Section 4.1).
- **API REST**: Endpoint untuk integrasi attendance-qr-system & client
  eksternal lain. Auth via Sanctum personal access token. Rate limit
  60/min.
- **Application Service**: Orchestrasi business logic (validation,
  ledger debit/credit, state transition, event dispatch).
- **Domain**: Aggregate root (`PengajuanCuti`), state machine,
  invariants.
- **Persistence**: MySQL via Eloquent. Critical writes wrapped in
  `DB::transaction()` dengan `lockForUpdate()` pada anchor row
  (`cuti_alokasi_tahunan`).

### Transactional Outbox Invariant (KRITIS)

State transition final, ledger write, dan insert ke `cuti_events`
**HARUS dalam satu `DB::transaction()`**. Jika salah satu gagal,
semuanya rollback. Worker delivery jalan terpisah membaca `cuti_events`
yang sudah committed.

```php
// CONTOH BENAR (lengkap dengan filter & idempotency guard)
DB::transaction(function () use ($pengajuanId) {
    // 1. Lock pengajuan + re-validate state
    $pengajuan = CutiPengajuan::where('id', $pengajuanId)
        ->lockForUpdate()
        ->firstOrFail();

    if (!$pengajuan->state->canTransitionTo(DisetujuiState::class)) {
        throw new TransitionTidakValidException();
    }

    // 2. Lock alokasi anchor (CT only, dengan filter eksplisit)
    if ($pengajuan->jenis_cuti_kode === 'CT') {
        CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', tahunHakDari($pengajuan))
            ->lockForUpdate()
            ->firstOrFail();
    }

    // 3. Ledger writes (CT only)
    if ($pengajuan->jenis_cuti_kode === 'CT') {
        CutiSaldoLedger::create([
            'pengajuan_id' => $pengajuan->id,
            'jenis_transaksi' => 'debit_void',
            'jumlah_hari' => +$pengajuan->jumlah_hari_kerja,
            'pegawai_nip' => $pengajuan->pegawai_nip,
            // ...
        ]);
        CutiSaldoLedger::create([
            'pengajuan_id' => $pengajuan->id,
            'jenis_transaksi' => 'debit_confirmed',
            'jumlah_hari' => -$pengajuan->jumlah_hari_kerja,
            // ...
        ]);
    }

    // 4. State transition
    $pengajuan->state->transitionTo(DisetujuiState::class);
    $pengajuan->approved_at = now();
    $pengajuan->save();

    // 5. Outbox event (atomic dengan #3 dan #4)
    $event = CutiEvent::create([
        'event_type' => 'cuti.disetujui',
        'aggregate_type' => 'PengajuanCuti',
        'aggregate_id' => $pengajuan->id,
        'payload' => $pengajuan->toEventPayload(),
    ]);

    // 6. Pre-create delivery rows per consumer (idempotent via UNIQUE)
    foreach (config('cuti.consumers') as $consumerId) {
        CutiEventDelivery::firstOrCreate(
            ['event_id' => $event->id, 'consumer_id' => $consumerId],
            ['status' => 'pending']
        );
    }
});

// Worker (terpisah) baca delivery 'pending' lalu HTTP POST ke consumer
```

**Anti-pattern (tolak di code review)**:
- Insert `CutiEvent` di luar transaction (event hilang jika txn rollback)
- Dispatch HTTP webhook langsung dari controller (sync, no retry, blocking)
- Pakai Laravel queue job tanpa outbox row (job hilang jika txn rollback
  sebelum job di-commit ke queue store)

### 4.1 Decision: Inertia for UI, REST API for Integration

Modul ini **TIDAK** memakai pola "stateless SPA via REST API" untuk UI
internal. Semua halaman web internal (form, inbox, detail) memakai
Inertia routes di `web.php` dengan `Inertia::render()`, mengikuti
konvensi Laravel + Inertia + React 19 yang sudah established di project
ini.

REST API di `api.php` hanya untuk:
- Webhook outbound ke `attendance-qr-system`
- Integrasi inbound dari sistem eksternal (jika ada)
- Endpoint download PDF (untuk consumer headless)

**Rationale**: Inertia sudah menyediakan SSR-like data fetching via
controller props — duplicating ke API endpoint hanya menambah surface
area tanpa benefit jelas untuk UI internal.

---

## 5. Aktor & Permissions

### Aktor

| Aktor | Role IAM | Scope |
|---|---|---|
| Pegawai | `pegawai` | Own data only |
| Petugas Kepegawaian | `petugas_kepegawaian` | All pegawai (verifikasi) |
| Atasan Langsung | dynamic (via `atasan_langsung_id`) | Subordinat |
| Pejabat Berwenang | `pejabat_berwenang_cuti` | All / per unit |
| Admin Kepegawaian | `admin_kepegawaian` | Full CRUD |

### Permissions (Spatie Permission)

```
cuti.pengajuan.create           — Pegawai submit cuti baru
cuti.pengajuan.view-own         — Lihat pengajuan sendiri
cuti.pengajuan.view-team        — Lihat pengajuan subordinat
cuti.pengajuan.view-all         — Lihat semua pengajuan
cuti.pengajuan.verify           — Petugas Kepegawaian verifikasi
cuti.pengajuan.approve-langsung — Atasan langsung approve
cuti.pengajuan.approve-pejabat  — Pejabat berwenang approve
cuti.pengajuan.cancel-own       — Cabut pengajuan sendiri (sebelum aktif)
cuti.pengajuan.cancel-any       — Admin cabut pengajuan
cuti.pengajuan.reassign         — Admin reassign approver (manual only, MVP)
cuti.saldo.view-own             — Lihat saldo sendiri
cuti.saldo.view-all             — Lihat saldo semua pegawai
cuti.saldo.adjust               — Admin penyesuaian saldo manual
cuti.audit.view                 — Lihat activity log
```

**Note penting**: State machine mem-validate **transisi** (e.g., dari
`DIAJUKAN` hanya boleh ke `DIVERIFIKASI` atau `DITOLAK`). **Authorization**
(siapa boleh melakukan transisi) di-enforce oleh **Policy/Gate Laravel**
yang mengecek permission Spatie. Dua hal terpisah; jangan campur.

---

## 6. Data Model

### Daftar Tabel (15 total untuk MVP)

1. `cuti_jenis_master` — referensi jenis cuti & aturan
2. `cuti_alokasi_tahunan` — **anchor row** untuk lock + hak inisial per pegawai/jenis/tahun
3. `cuti_saldo_ledger` — **pure ledger** (1 transaksi = 1 row, signed)
4. `cuti_pengajuan` — header pengajuan + approver snapshot/current
5. `cuti_pengajuan_periode` — detail periode (untuk lintas-bucket nanti)
6. `cuti_pengajuan_lampiran` — file pendukung (surat dokter, dll)
7. `cuti_pengajuan_approval_steps` — riwayat approval per step
8. `cuti_pengajuan_approver_history` — audit reassign approver
9. `cuti_pengajuan_state_history` — audit transisi state
10. `cuti_pengajuan_pdf` — referensi file PDF generated
11. `cuti_libur_master` — libur nasional & cuti bersama (untuk hitung hari kerja)
12. `cuti_events` — domain event (immutable, append-only)
13. `cuti_event_deliveries` — delivery state per consumer (idempotent)
14. `cuti_jenis_per_status_pegawai` — pivot jenis × status (PNS/PPPK)
15. `cuti_konfigurasi` — config per-tahun (max carry-over, dll)

### Schema Detail (MVP-Critical)

#### `cuti_alokasi_tahunan` (Lock Anchor)

Tabel ini bukan summary saldo — ini **anchor row** yang menjamin
exclusive lock saat baca/tulis ledger. Selalu ada **tepat 1 row** per
kombinasi `(pegawai_nip, jenis_cuti_kode, tahun_hak)` (di-init saat
admin set alokasi awal tahun, atau saat carry-over command jalan).

```sql
CREATE TABLE cuti_alokasi_tahunan (
    id CHAR(26) PRIMARY KEY,                    -- ULID
    pegawai_nip VARCHAR(20) NOT NULL,
    jenis_cuti_kode VARCHAR(10) NOT NULL,
    tahun_hak SMALLINT NOT NULL,
    hak_awal INT NOT NULL,                      -- hak inisial (e.g., 12 untuk CT)
    catatan VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_alokasi (pegawai_nip, jenis_cuti_kode, tahun_hak),
    FOREIGN KEY (pegawai_nip) REFERENCES pegawai(nip)
);
```

**Catatan**: `hak_awal` di-snapshot, **bukan** dihitung. Saldo terkini
tetap dihitung dari ledger via `SUM(jumlah_hari)`.

#### `cuti_saldo_ledger` (Pure Ledger Pattern)

```sql
CREATE TABLE cuti_saldo_ledger (
    id CHAR(26) PRIMARY KEY,                    -- ULID
    pegawai_nip VARCHAR(20) NOT NULL,
    jenis_cuti_kode VARCHAR(10) NOT NULL,       -- 'CT', 'CS', 'CB', dll
    tahun_hak SMALLINT NOT NULL,                -- tahun kepemilikan hak (untuk N/N-1)
    jenis_transaksi ENUM(
        'kredit',           -- alokasi awal tahun (positif)
        'debit_pending',    -- reservasi saat pengajuan submit (negatif)
        'debit_void',       -- batal reservasi (positif, netralkan debit_pending)
        'debit_confirmed',  -- konfirmasi saat disetujui (negatif)
        'kredit_refund',    -- refund FIFO ke bucket asal saat pencabutan (positif)
        'expire',           -- hangus akhir tahun (negatif)
        'penyesuaian'       -- adjustment manual admin (positif/negatif)
    ) NOT NULL,
    jumlah_hari INT NOT NULL,                   -- signed: positif=kredit, negatif=debit
    pengajuan_id CHAR(26) NULL,                 -- FK ke cuti_pengajuan jika applicable
    keterangan VARCHAR(500) NULL,
    aktor_pegawai_nip VARCHAR(20) NOT NULL,     -- siapa trigger transaksi (FK pegawai)
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pegawai_jenis_tahun (pegawai_nip, jenis_cuti_kode, tahun_hak),
    INDEX idx_pengajuan (pengajuan_id),
    FOREIGN KEY (pegawai_nip) REFERENCES pegawai(nip),
    FOREIGN KEY (aktor_pegawai_nip) REFERENCES pegawai(nip),
    FOREIGN KEY (pengajuan_id) REFERENCES cuti_pengajuan(id)
);
```

**Sign convention** (kritis untuk hindari double-debit):
- Setiap row signed (`jumlah_hari` boleh negatif).
- Saldo aktual = `SUM(jumlah_hari)` untuk pegawai+jenis+tahun_hak.
- **Pasangan transaksi**:
  - Submit: 1 row `debit_pending` = `-N`
  - Approve final: 2 row dalam 1 transaksi → `debit_void` = `+N`
    (netralkan pending) DAN `debit_confirmed` = `-N` (commit final)
  - Reject/cancel sebelum DISETUJUI: 1 row `debit_void` = `+N` saja
  - Pencabutan setelah DISETUJUI: 1+ row `kredit_refund` = `+M` (M ≤ N,
    sesuai sisa hari kerja yang belum berjalan; bisa multi-row jika
    pengajuan asli debit dari >1 bucket — refund FIFO ke bucket asal)
- Aturan ini menjamin `SUM` selalu konsisten — tidak ada path yang
  menambah dua debit tanpa void di antaranya.

**Tidak ada UPDATE** ke ledger — koreksi via row baru (`penyesuaian`).

**Concurrent safety**: lock **row alokasi tahunan** (bukan agregat
ledger) sebagai anchor sebelum baca saldo:

```php
// Submit pengajuan — protected by alokasi row lock
DB::transaction(function () use ($pengajuan) {
    // Lock anchor row (selalu ada 1 row per pegawai+jenis+tahun)
    $alokasi = CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
        ->where('jenis_cuti_kode', 'CT')
        ->where('tahun_hak', now()->year)
        ->lockForUpdate()
        ->firstOrFail();  // jika tidak ada → admin belum init alokasi

    // Setelah lock didapat, baca saldo agregat aman
    $saldo = CutiSaldoLedger::where('pegawai_nip', $pengajuan->pegawai_nip)
        ->where('jenis_cuti_kode', 'CT')
        ->where('tahun_hak', now()->year)
        ->sum('jumlah_hari');

    if ($saldo < $pengajuan->jumlah_hari_kerja) {
        throw new SaldoTidakCukupException();
    }

    CutiSaldoLedger::create([
        'jenis_transaksi' => 'debit_pending',
        'jumlah_hari' => -$pengajuan->jumlah_hari_kerja,
        'pengajuan_id' => $pengajuan->id,
        // ...
    ]);
});

// Approve final — void pending + commit confirmed dalam SATU transaksi
DB::transaction(function () use ($pengajuan) {
    $alokasi = CutiAlokasiTahunan::where(...)->lockForUpdate()->firstOrFail();

    // 1. Netralkan reservasi
    CutiSaldoLedger::create([
        'jenis_transaksi' => 'debit_void',
        'jumlah_hari' => +$pengajuan->jumlah_hari_kerja,
        'pengajuan_id' => $pengajuan->id,
        'keterangan' => 'Void pending menjelang konfirmasi final',
    ]);

    // 2. Commit final
    CutiSaldoLedger::create([
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -$pengajuan->jumlah_hari_kerja,
        'pengajuan_id' => $pengajuan->id,
    ]);

    // 3. State transition + cuti_events insert (juga di transaksi sama)
    $pengajuan->state->transitionTo(DisetujuiState::class);
    CutiEvent::create([...]);  // outbox: lihat Section 9 invariant
});
```

**Kenapa lock alokasi, bukan `SUM lockForUpdate`?** Query agregat tidak
menjamin lock pada "slot saldo" tertentu — terutama jika belum ada row
ledger sama sekali, dua transaksi paralel bisa sama-sama membaca
`SUM = 0`. Locking row stabil di `cuti_alokasi_tahunan` (selalu ada,
unique per kombinasi) memastikan serialization yang deterministik.

#### `cuti_pengajuan` (dengan Approver Snapshot Pattern)

```sql
CREATE TABLE cuti_pengajuan (
    id CHAR(26) PRIMARY KEY,                    -- ULID
    nomor_pengajuan VARCHAR(50) UNIQUE NOT NULL,
    pegawai_nip VARCHAR(20) NOT NULL,
    jenis_cuti_kode VARCHAR(10) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    jumlah_hari_kerja INT NOT NULL,             -- tidak menghitung weekend & libur
    alasan TEXT NOT NULL,
    alamat_selama_cuti TEXT NULL,
    nomor_telp_selama_cuti VARCHAR(30) NULL,

    -- State machine (spatie)
    state VARCHAR(50) NOT NULL DEFAULT 'DRAFT',

    -- Approver snapshot (immutable, captured at submit)
    petugas_kepegawaian_snapshot_nip VARCHAR(20) NULL,
    atasan_langsung_snapshot_nip VARCHAR(20) NULL,
    pejabat_berwenang_snapshot_nip VARCHAR(20) NULL,

    -- Current approver (mutable, may differ from snapshot if reassigned)
    petugas_kepegawaian_current_nip VARCHAR(20) NULL,
    atasan_langsung_current_nip VARCHAR(20) NULL,
    pejabat_berwenang_current_nip VARCHAR(20) NULL,

    submitted_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,                  -- timestamp DISETUJUI
    rejected_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pegawai (pegawai_nip),
    INDEX idx_state (state),
    INDEX idx_atasan_current (atasan_langsung_current_nip),
    INDEX idx_pejabat_current (pejabat_berwenang_current_nip),

    FOREIGN KEY (pegawai_nip) REFERENCES pegawai(nip),
    FOREIGN KEY (atasan_langsung_snapshot_nip) REFERENCES pegawai(nip)
);
```

**Snapshot vs Current**:
- **Snapshot** = value at submit time. Untuk audit/dokumen formal:
  "siapa yang seharusnya approve menurut struktur saat pengajuan
  disubmit". Tidak pernah berubah.
- **Current** = value sekarang. Saat atasan mutasi mid-flow,
  `current` di-update via reassign action. Yang menerima notifikasi
  adalah `current`.

#### `cuti_pengajuan_approver_history`

```sql
CREATE TABLE cuti_pengajuan_approver_history (
    id CHAR(26) PRIMARY KEY,
    pengajuan_id CHAR(26) NOT NULL,
    role ENUM('petugas_kepegawaian', 'atasan_langsung', 'pejabat_berwenang') NOT NULL,
    from_pegawai_nip VARCHAR(20) NULL,           -- NULL untuk initial assignment
    to_pegawai_nip VARCHAR(20) NOT NULL,
    alasan VARCHAR(500) NOT NULL,                -- kenapa di-reassign
    aktor_pegawai_nip VARCHAR(20) NOT NULL,      -- siapa yang trigger reassign
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pengajuan (pengajuan_id),
    FOREIGN KEY (pengajuan_id) REFERENCES cuti_pengajuan(id),
    FOREIGN KEY (from_pegawai_nip) REFERENCES pegawai(nip),
    FOREIGN KEY (to_pegawai_nip) REFERENCES pegawai(nip),
    FOREIGN KEY (aktor_pegawai_nip) REFERENCES pegawai(nip)
);
```

#### `cuti_events` (Domain Event)

```sql
CREATE TABLE cuti_events (
    id CHAR(36) PRIMARY KEY,                     -- UUID v4 (untuk distributed safety)
    aggregate_type VARCHAR(50) NOT NULL,         -- 'PengajuanCuti'
    aggregate_id CHAR(26) NOT NULL,              -- ULID pengajuan
    event_type VARCHAR(100) NOT NULL,            -- 'cuti.disetujui', 'cuti.dicabut', dll
    payload JSON NOT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_aggregate (aggregate_type, aggregate_id),
    INDEX idx_event_type (event_type),
    INDEX idx_occurred (occurred_at)
);
```

#### `cuti_event_deliveries` (Outbox Delivery State)

```sql
CREATE TABLE cuti_event_deliveries (
    id CHAR(26) PRIMARY KEY,
    event_id CHAR(36) NOT NULL,
    consumer_id VARCHAR(50) NOT NULL,            -- 'attendance-qr-system'
    status ENUM('pending', 'in_flight', 'delivered', 'failed', 'dead_letter') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    last_error TEXT NULL,
    next_retry_at TIMESTAMP NULL,                -- exponential backoff
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_event_consumer (event_id, consumer_id),  -- IDEMPOTENCY
    INDEX idx_status_retry (status, next_retry_at),
    FOREIGN KEY (event_id) REFERENCES cuti_events(id)
);
```

**Idempotency**: `UNIQUE(event_id, consumer_id)` mencegah duplicate
delivery row. Worker `INSERT IGNORE` saat dispatch event ke daftar
consumer.

---

## 7. State Machine

Pakai `spatie/laravel-model-states`. **Note**: Spatie melakukan validasi
**transisi at runtime** (bukan compile-time). Type safety hanya untuk
referensi class state, bukan untuk konstrain transisi.

### States MVP (10 total)

```
DRAFT ─────────────────→ DIBATALKAN
  ↓                       (pegawai cancel sebelum submit)
DIAJUKAN
  ↓
DIVERIFIKASI ─────────→ DITOLAK_KEPEGAWAIAN
  ↓
DISETUJUI_ATASAN ─────→ DITOLAK_ATASAN
  ↓
DISETUJUI ─────────────→ DICABUT_SETELAH_DISETUJUI
  ↑                       (refund FIFO ke bucket asal — CT/CAP only)
  └── DITOLAK_PEJABAT
```

**Catatan terminologi**:
- `DISETUJUI` adalah **status final approval sukses** — tidak ada step
  "selesai" karena masa cuti tracked di attendance-system via webhook.
  Tetapi `DISETUJUI` **bukan terminal absolut** — masih bisa transisi
  ke `DICABUT_SETELAH_DISETUJUI` jika pegawai membatalkan saat berjalan.
- `DIBATALKAN` adalah state untuk pengajuan yang dibatalkan **saat
  masih `DRAFT`** (belum disubmit). Tidak ada efek ke ledger
  (debit_pending belum pernah dibuat). Beda dengan
  `DICABUT_SETELAH_DISETUJUI` yang triggers refund.
- State terminal absolut: `DITOLAK_KEPEGAWAIAN`, `DITOLAK_ATASAN`,
  `DITOLAK_PEJABAT`, `DIBATALKAN`, `DICABUT_SETELAH_DISETUJUI`.

### Matriks Transisi per Jenis Cuti

| Dari → Ke | CT | CS Tier 1 | CS Tier 2 | CAP |
|---|---|---|---|---|
| DRAFT → DIAJUKAN | ✓ | ✓ | ✓ | ✓ |
| DIAJUKAN → DIVERIFIKASI | ✓ | ✓ | ✓ | ✓ |
| DIAJUKAN → DITOLAK_KEPEGAWAIAN | ✓ | ✓ | ✓ | ✓ |
| DIVERIFIKASI → DISETUJUI_ATASAN | ✓ | ✓ | ✓ | ✓ |
| DIVERIFIKASI → DITOLAK_ATASAN | ✓ | ✓ | ✓ | ✓ |
| DISETUJUI_ATASAN → DISETUJUI | ✓ | ✓ | ✓ | ✓ |
| DISETUJUI_ATASAN → DITOLAK_PEJABAT | ✓ | ✓ | ✓ | ✓ |
| DISETUJUI → DICABUT_SETELAH_DISETUJUI | ✓ | – | – | ✓ |
| DRAFT → DIBATALKAN | ✓ | ✓ | ✓ | ✓ |

**Catatan**: Cuti sakit (CS) tidak bisa dicabut setelah disetujui karena
sudah berjalan saat diajukan (surat dokter retroaktif).

### Authorization (terpisah dari state machine)

| Transisi | Authorization (Policy) | Efek Ledger (CT only) |
|---|---|---|
| → DIAJUKAN | `cuti.pengajuan.create` (pemilik own data) | insert `debit_pending` |
| DRAFT → DIBATALKAN | owner pengajuan (own) ATAU `cuti.pengajuan.cancel-any` | – (belum ada `debit_pending`) |
| → DIVERIFIKASI / DITOLAK_KEPEGAWAIAN | `cuti.pengajuan.verify` | DITOLAK: insert `debit_void` |
| → DISETUJUI_ATASAN / DITOLAK_ATASAN | `cuti.pengajuan.approve-langsung` AND `current_atasan_langsung_nip = $user->nip` | DITOLAK: insert `debit_void` |
| → DISETUJUI / DITOLAK_PEJABAT | `cuti.pengajuan.approve-pejabat` AND `current_pejabat_berwenang_nip = $user->nip` | DISETUJUI: `debit_void` + `debit_confirmed`. DITOLAK: `debit_void` |
| → DICABUT_SETELAH_DISETUJUI | `cuti.pengajuan.cancel-own` (own) atau `cuti.pengajuan.cancel-any` | CT only: insert `kredit_refund` FIFO ke bucket asal. CAP: tidak ada efek ledger |
| reassign approver (sub-action, bukan state transition) | `cuti.pengajuan.reassign` (admin only) | – |

**Efek ledger HANYA berlaku untuk CT (Cuti Tahunan)**. Untuk CS Tier 1,
CS Tier 2, dan CAP di MVP: **tidak ada** insert ke `cuti_saldo_ledger`
sama sekali (karena tidak ada `debit_pending` saat submit, jadi tidak
ada void/refund yang perlu ditulis). Service layer wajib check
`$pengajuan->jenis_cuti_kode === 'CT'` sebelum semua ledger write.

Policy class men-decide **siapa boleh**, state machine men-decide
**transisi mana yang valid**. Keduanya harus pass. Efek ledger
**selalu di transaksi yang sama** dengan state transition.

### Idempotency Mutation (KRITIS untuk concurrency)

Setiap mutation (verify/approve/reject/cancel) **wajib** dalam
transaksi yang melakukan **dua lock** dan **dua check**:

```php
// Pattern wajib untuk setiap state transition
DB::transaction(function () use ($pengajuanId, $action) {
    // 1. Lock row pengajuan dulu (prevent paralel mutation row sama)
    $pengajuan = CutiPengajuan::where('id', $pengajuanId)
        ->lockForUpdate()
        ->firstOrFail();

    // 2. Re-validate state SETELAH lock didapat
    //    (state mungkin sudah berubah oleh request lain yang menang race)
    if (!$pengajuan->state->canTransitionTo($targetStateClass)) {
        throw new TransitionTidakValidException(
            "State {$pengajuan->state->name} tidak bisa transisi ke {$targetStateClass}"
        );
    }

    // 3. Lock alokasi anchor jika akan write ledger (CT only)
    if ($pengajuan->jenis_cuti_kode === 'CT') {
        CutiAlokasiTahunan::where('pegawai_nip', $pengajuan->pegawai_nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', tahunHakDari($pengajuan))
            ->lockForUpdate()
            ->firstOrFail();
    }

    // 4. Lakukan action (state transition + ledger write atomic)
    // ...
});
```

**Guard layer kedua (defense-in-depth)**: Tambah constraint database
`UNIQUE(pengajuan_id, jenis_transaksi, tahun_hak)` di
`cuti_saldo_ledger`. Tahun_hak ikut sebagai key karena **satu
pengajuan CT bisa memakai > 1 bucket** (lihat 8.5 FIFO Split Bucket).

```sql
-- Per pengajuan + jenis_transaksi + tahun_hak → maksimal 1 row.
-- Mencegah duplicate akibat retry, sambil mengizinkan split lintas bucket
-- (mis. 1 pengajuan ambil 4 hari dari N-1 + 6 hari dari N → 2 row debit_pending
-- dengan tahun_hak berbeda).
ALTER TABLE cuti_saldo_ledger
ADD CONSTRAINT uk_pengajuan_transaksi_bucket_unique
UNIQUE (pengajuan_id, jenis_transaksi, tahun_hak);
```

**Catatan**: Untuk skenario yang butuh multi-row jenis_transaksi +
tahun_hak sama (mis. `penyesuaian` admin manual berulang), tabel
adjustment terpisah atau tambahkan `sequence_no` ke unique constraint.
Untuk MVP, scope `pengajuan_id` sebagai owning key sudah cukup karena
`penyesuaian` tidak punya `pengajuan_id` (NULL) — MySQL UNIQUE
mengizinkan multiple NULL di setiap kolom yang nullable.

#### Idempotency untuk Transaksi System-Generated (`pengajuan_id = NULL`)

UNIQUE constraint `(pengajuan_id, jenis_transaksi, tahun_hak)` **tidak
melindungi** transaksi system-generated dengan `pengajuan_id = NULL`,
yaitu:
- `kredit` — alokasi awal tahun (carry-over command)
- `expire` — hangus akhir tahun N-2 dan cap N-1 (carry-over command)
- `penyesuaian` — adjustment manual admin

Karena MySQL InnoDB mengizinkan **multiple NULL** di kolom unique
nullable, dua eksekusi command carry-over yang berjalan paralel atau
retry **tidak akan ditolak oleh database**. Idempotency untuk transaksi
ini dijaga **murni di service layer** dengan pola berikut:

```php
// Pattern wajib untuk command system-generated (kredit/expire/penyesuaian)
DB::transaction(function () use ($pegawai, $tahun) {
    // 1. Lock anchor row alokasi tahun terkait (mencegah paralel)
    $alokasi = CutiAlokasiTahunan::firstOrCreate(
        ['pegawai_nip' => $pegawai->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => $tahun],
        ['hak_total' => 12]
    );
    DB::table('cuti_alokasi_tahunan')
        ->where('id', $alokasi->id)
        ->lockForUpdate()
        ->first();

    // 2. Existence check: apakah row ledger sejenis sudah ada?
    $sudahAda = CutiSaldoLedger::where('pegawai_nip', $pegawai->nip)
        ->where('jenis_cuti_kode', 'CT')
        ->where('tahun_hak', $tahun)
        ->where('jenis_transaksi', 'kredit')
        ->whereNull('pengajuan_id')
        ->exists();

    if ($sudahAda) {
        return; // idempotent: skip, sudah pernah dijalankan
    }

    // 3. Insert ledger row
    CutiSaldoLedger::create([...]);
});
```

**Mengapa cukup untuk MVP**:
- Carry-over jalan via scheduled command 1× per tahun (1 Januari) —
  bukan endpoint user-triggered, jadi konkurensi rendah.
- `lockForUpdate()` di anchor row menyerialisasi eksekusi paralel.
- Existence check setelah lock dijamin akurat (READ COMMITTED + lock).
- `penyesuaian` admin di-trigger manual lewat UI dengan konfirmasi —
  tidak ada retry otomatis.

**Future hardening (Fase 2/3)**:
Jika butuh strong guarantee tanpa rely lock + check, tambahkan kolom
`idempotency_key VARCHAR(64)` ke `cuti_saldo_ledger` dengan UNIQUE
non-nullable per row system-generated (mis. `kredit_{nip}_{tahun}`,
`expire_n2_{nip}_{tahun}`). Tidak prioritas MVP karena overhead
schema vs risiko nyata kecil.

---

## 8. Business Rules

### 8.1 Carry-Over Cuti Tahunan (Pasal 313 PP 11/2017)

**Model interpretasi (DIPILIH untuk MVP)**: Carry-over hanya **1
tingkat** (N-1 saja). Sisa cuti N-2 **hangus per 1 Januari tahun N**
saat command carry-over jalan. Setelah command jalan, hanya bucket N-1
(max 6 hari) dan N (12 hari) yang aktif.

**Saldo aktif maksimum = 18 hari** sepanjang tahun (12 N + max 6 N-1).
Tidak ada window 24 hari yang usable di tahun N.

**Aturan**:
- Hak per tahun: **12 hari** (PNS & PPPK)
- Sisa N-1 yang tidak terpakai per 1 Januari tahun N: di-cap max **6
  hari**, sisanya hangus
- Sisa N-2 yang tidak terpakai per 1 Januari tahun N: **hangus
  sepenuhnya**
- Tidak ada bucket N-3, N-4, dst — semuanya sudah hangus di carry-over
  sebelumnya

**Catatan interpretasi alternatif** (TIDAK dipakai): Pasal 313 ayat (2)
PP 11/2017 bisa juga ditafsirkan bahwa "PNS yang tidak menggunakan cuti
tahunan dalam tahun bersangkutan, dapat digunakan tahun berikutnya
paling lama 18 hari kerja **termasuk hak cuti tahun berjalan**".
Interpretasi ini menghasilkan max 18 hari, sama dengan model yang
dipilih. Skenario "24 hari" yang sempat ditulis di v3 adalah salah
tafsir (membaca N-2 sebagai bucket aktif yang bisa di-debit) — sudah
dikoreksi di v4.

**FIFO debit (CT)**: Saat cuti CT diajukan, debit dari `tahun_hak` paling
lama dulu (N-1 → N). Karena N-2 sudah expired, hanya 2 bucket aktif.

Implementasi (`ProcessCarryOverCommand`, jalan tiap 1 Januari 00:05):

```php
// Pseudocode lengkap — handle N-2 expire (sweeper) + N-1 cap + N kredit
DB::transaction(function () use ($pegawai) {
    $tahunBaru = now()->year;       // N
    $tahunLalu = $tahunBaru - 1;    // N-1
    $tahunDuaLalu = $tahunBaru - 2; // N-2 (sweeper untuk data migration / bug recovery)

    // Step 1: ensure anchor row ada (idempotent)
    foreach ([$tahunDuaLalu, $tahunLalu, $tahunBaru] as $th) {
        CutiAlokasiTahunan::firstOrCreate(
            ['pegawai_nip' => $pegawai->nip, 'jenis_cuti_kode' => 'CT', 'tahun_hak' => $th],
            ['hak_awal' => $th === $tahunBaru ? 12 : 0]
        );
    }

    // Step 2: ACQUIRE lock — query ulang dengan lockForUpdate
    // (firstOrCreate tidak bisa di-chain dengan lockForUpdate)
    foreach ([$tahunDuaLalu, $tahunLalu, $tahunBaru] as $th) {
        CutiAlokasiTahunan::where('pegawai_nip', $pegawai->nip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', $th)
            ->lockForUpdate()
            ->firstOrFail();
    }

    // === STEP 1: Expire bucket N-2 sepenuhnya ===
    // (sesuai PP 11/2017: sisa N-2 hangus per 31 Desember tahun ke-N-1)
    $sisaN2 = ledgerSum($pegawai->nip, 'CT', $tahunDuaLalu);
    if ($sisaN2 > 0) {
        CutiSaldoLedger::create([
            'jenis_transaksi' => 'expire',
            'jumlah_hari' => -$sisaN2,
            'tahun_hak' => $tahunDuaLalu,
            'keterangan' => "Hangus akhir tahun {$tahunDuaLalu} (carry-over expired)",
        ]);
    }

    // === STEP 2: Cap bucket N-1 max 6 hari ===
    // Sisa di atas 6 → expire. Sisa ≤ 6 → biarkan (jadi N-1 carry-over)
    $sisaN1 = ledgerSum($pegawai->nip, 'CT', $tahunLalu);
    if ($sisaN1 > 6) {
        CutiSaldoLedger::create([
            'jenis_transaksi' => 'expire',
            'jumlah_hari' => -($sisaN1 - 6),
            'tahun_hak' => $tahunLalu,
            'keterangan' => "Cap carry-over N-1 max 6 hari",
        ]);
    }
    // Note: jika $sisaN1 ≤ 0 (over-debit edge case), tidak ada aksi

    // === STEP 3: Kredit hak tahun N ===
    // Idempotency: skip jika sudah ada kredit untuk tahun N
    $sudahKredit = CutiSaldoLedger::where('pegawai_nip', $pegawai->nip)
        ->where('jenis_cuti_kode', 'CT')
        ->where('tahun_hak', $tahunBaru)
        ->where('jenis_transaksi', 'kredit')
        ->exists();

    if (!$sudahKredit) {
        CutiSaldoLedger::create([
            'jenis_transaksi' => 'kredit',
            'jumlah_hari' => 12,
            'tahun_hak' => $tahunBaru,
            'keterangan' => "Hak cuti tahunan {$tahunBaru}",
        ]);
    }
});
```

**Hasil setelah command jalan** (1 Januari 00:05 tahun N):
- Bucket N-2: saldo **0** (semua sisa di-expire)
- Bucket N-1: saldo **min(sisa, 6)** — sisanya di-expire
- Bucket N: saldo **12** (kredit hak baru, idempotent)
- **Total saldo aktif = 12 + min(sisa-N-1, 6) ≤ 18 hari**

**Detail penting**:
- Bucket N-2 tetap di-process oleh sweeper sebagai **safety net**
  untuk data migration tahun pertama atau bug recovery — pada steady
  state setelah ≥2 tahun, bucket N-2 selalu sudah 0 dan step expire
  tidak menulis row baru.
- Step expire **idempotent**: jika ledger sudah punya row `expire`
  untuk `tahun_hak` tersebut, command akan baca saldo = 0 dan tidak
  menulis row tambahan.
- Ledger query `ledgerSum()` adalah helper:
  `CutiSaldoLedger::where(...)->sum('jumlah_hari')`.

### 8.2 Hari Kerja vs Hari Kalender

- Cuti dihitung berdasarkan **hari kerja** (Senin-Jumat).
- Weekend (Sabtu-Minggu) **tidak memotong saldo**.
- Libur nasional (dari `cuti_libur_master`) **tidak memotong saldo**.
- Cuti bersama (dari `cuti_libur_master` flag `is_cuti_bersama`)
  **tidak memotong saldo** (sesuai keputusan: dianggap libur biasa).

### 8.3 Validasi Saat Submit (Rule Engine per Jenis Cuti)

Aturan validasi **berbeda** per jenis cuti — saldo tahunan **bukan**
syarat universal. Implementasi via rule engine (Strategy pattern) yang
dipanggil oleh `PengajuanCutiService::validate($pengajuan)`.

**Validasi umum (semua jenis)**:
- Pegawai berstatus aktif (bukan pensiun/CLTN/diberhentikan)
- `tanggal_mulai <= tanggal_selesai`
- **No cross-year leave** (kebijakan PA Penajam): `tanggal_mulai.year()
  === tanggal_selesai.year()`. Pengajuan yang span pergantian tahun
  (mis. 28 Des 2026 s/d 5 Jan 2027) **ditolak di form layer**. Pegawai
  yang butuh cuti span tersebut harus mengajukan **dua pengajuan
  terpisah** (28-31 Des 2026 + 1-5 Jan 2027).
- Tidak ada **overlap tanggal** dengan pengajuan lain milik pegawai
  sama yang masih dalam state aktif:
  `DIAJUKAN`, `DIVERIFIKASI`, `DISETUJUI_ATASAN`, `DISETUJUI`
  (catatan: `DRAFT` & state ditolak/dibatalkan tidak diblokir)
- Cek overlap **harus dalam transaksi yang acquire pegawai-level lock**
  agar 2 submit paralel di-serialize (lihat di bawah)
- `cuti_alokasi_tahunan` row **harus ada** untuk jenis+tahun terkait
  (kecuali jenis non-saldo seperti CS — lihat di bawah)

**Pegawai-Level Lock untuk Submit (semua jenis, single-tahun)**:

Untuk mencegah 2 submit paralel pegawai sama lolos overlap check
(double-book tanggal sama), submit **wajib** acquire lock pada bucket
`cuti_alokasi_tahunan` untuk **tahun yang disentuh rentang tanggal
pengajuan**. Karena kebijakan **no cross-year leave** sudah memvalidasi
`tanggal_mulai.year() === tanggal_selesai.year()` di form layer, range
selalu single tahun. Pola `range()` dipertahankan sebagai defense
in-depth — jika validasi form bypass karena bug, lock tetap konsisten.

```php
DB::transaction(function () use ($pegawaiNip, $request) {
    $tahunMulai = Carbon::parse($request->tanggal_mulai)->year;
    $tahunSelesai = Carbon::parse($request->tanggal_selesai)->year;
    $tahunDisentuh = range($tahunMulai, $tahunSelesai);  // selalu [X] single tahun (no cross-year)

    // 1. Lock CT alokasi anchor untuk SEMUA tahun yang disentuh
    //    (urutan ASC untuk hindari deadlock antar request)
    foreach ($tahunDisentuh as $th) {
        CutiAlokasiTahunan::where('pegawai_nip', $pegawaiNip)
            ->where('jenis_cuti_kode', 'CT')
            ->where('tahun_hak', $th)
            ->lockForUpdate()
            ->firstOrFail();  // jika tidak ada → admin belum init → tolak
    }

    // 2. Setelah semua bucket terkunci, query overlap aman
    $overlap = CutiPengajuan::where('pegawai_nip', $pegawaiNip)
        ->whereIn('state', ['DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN', 'DISETUJUI'])
        ->where(function ($q) use ($request) {
            $q->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
              ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
              ->orWhere(function ($q2) use ($request) {
                  $q2->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                     ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
              });
        })
        ->exists();

    if ($overlap) {
        throw new OverlapPengajuanException();
    }

    // 3. Lanjut: write pengajuan + (CT only) ledger debit_pending FIFO
});
```

**Aturan deadlock-avoidance**: Lock dalam **urutan ASC** by `tahun_hak`.
Jika 2 request paralel ambil bucket sama, salah satu akan menunggu yang
lain. Tanpa urutan deterministik, ada risiko deadlock saat 2 request
ambil bucket yang sama tapi urutan berbeda.

**Catatan**: `cuti_alokasi_tahunan` (kombinasi CT + tahun yang
disentuh) dipakai sebagai **universal pegawai-level lock anchor** untuk
MVP — bukan hanya untuk validasi saldo CT. Ini berarti **semua pegawai
wajib punya alokasi CT** untuk semua tahun yang akan disentuh sebelum
bisa submit cuti (CS, CAP juga). Trade-off: simple (1 jenis anchor),
konsekuensi: admin harus init CT alokasi tahun depan **sebelum** pegawai
boleh submit cuti yang span ke tahun depan. Carry-over command per 1
Januari otomatis create alokasi tahun aktif → masalah hanya di
pengajuan ke tahun future yang belum di-init.

**Mitigasi**: Validasi di form layer dua lapis:
1. **No cross-year**: `tanggal_mulai.year() === tanggal_selesai.year()`
   (kebijakan PA Penajam — sudah dijelaskan di bagian validasi umum).
2. **Tahun harus punya alokasi**: tolak `tanggal_mulai.year()` jika
   `cuti_alokasi_tahunan` (CT, tahun tersebut, pegawai tersebut) belum
   ada. Carry-over command per 1 Januari otomatis create alokasi tahun
   aktif. Pengajuan ke tahun future yang belum di-init oleh admin akan
   reject di sini (404 ke alokasi → form error).

**Cuti Tahunan (CT)** — saldo-driven:
- `cuti_alokasi_tahunan` row wajib ada untuk tahun aktif
- `SUM(jumlah_hari)` per `tahun_hak` aktif harus ≥ `jumlah_hari_kerja`
- Submit minimum H-3 sebelum `tanggal_mulai` (kecuali admin override)

**Cuti Sakit Tier 1 (≤14 hari)** — dokumen-driven, tanpa saldo:
- Lampiran surat dokter wajib (PDF/JPG, max 5MB)
- Tanggal pengajuan boleh retroaktif sampai **H+3** sejak tanggal mulai
  sakit (di atas itu tolak otomatis, harus via CAP/admin)
- Durasi `tanggal_selesai - tanggal_mulai + 1` (kalender) ≤ 14 hari
- Tidak memotong saldo CT — ledger CS hanya untuk **audit count**, bukan
  enforcement (Phase 3 jika ada quota CS regulatif)

**Cuti Sakit Tier 2 (15 hari - 1 bulan)** — dokumen-driven, tanpa saldo:
- Lampiran surat dokter **pemerintah / RS pemerintah** wajib
- Durasi 15-30 hari kalender
- Sama seperti Tier 1: tidak memotong saldo CT

**Cuti Alasan Penting (CAP)** — kategori + dokumen, tanpa saldo CT:
- Pilih kategori: kematian keluarga inti, pernikahan, kelahiran anak,
  ibadah haji pertama, lainnya (free text)
- Lampiran sesuai kategori (akta kematian / undangan nikah / dll)
- Durasi maksimum sesuai kategori (mengikuti PP 11/2017 Pasal 329)
- CAP **tidak** mengurangi saldo cuti tahunan (interpretasi PP 11/2017)

**Catatan ledger CS/CAP**: Ledger `cuti_saldo_ledger` di MVP **CT-only**
— CS/CAP **tidak menulis** ke tabel ini sama sekali. Audit count untuk
CS (jumlah hari sakit per pegawai per tahun) didapat dari aggregasi
`cuti_pengajuan` yang state-nya `DISETUJUI` + `spatie/laravel-activitylog`,
bukan dari ledger. Ini konsisten dengan Section 7 (Authorization table)
yang sudah menyatakan "Efek ledger HANYA berlaku untuk CT".

### 8.4 FIFO Split Bucket (CT)

Cuti Tahunan bisa **memakai > 1 bucket** dalam 1 pengajuan. Contoh:
pegawai punya saldo N-1=4 hari + N=12 hari, ajukan cuti 7 hari. FIFO
debit harus split:
- 4 hari dari bucket N-1 (habis dulu yang akan hangus)
- 3 hari dari bucket N

**Konsekuensi**: Setiap mutation ledger (`debit_pending`, `debit_void`,
`debit_confirmed`, `kredit_refund`) berkemungkinan menulis **beberapa
row** dengan `pengajuan_id` sama, **tahun_hak berbeda**.

**Service implementation**:

```php
class SaldoLedgerService {
    /** Split debit FIFO: bucket lama duluan */
    public function debitPendingFifo(CutiPengajuan $p): array
    {
        $buckets = $this->bucketsAktif($p->pegawai_nip, 'CT'); // ASC by tahun_hak
        $sisa = $p->jumlah_hari_kerja;
        $rows = [];

        foreach ($buckets as $bucket) {
            if ($sisa <= 0) break;
            $available = $this->saldoBucket($p->pegawai_nip, 'CT', $bucket->tahun_hak);
            if ($available <= 0) continue;
            $ambil = min($sisa, $available);

            $rows[] = CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'jenis_transaksi' => 'debit_pending',
                'tahun_hak' => $bucket->tahun_hak,
                'jumlah_hari' => -$ambil,
                'pegawai_nip' => $p->pegawai_nip,
                'jenis_cuti_kode' => 'CT',
                // ...
            ]);
            $sisa -= $ambil;
        }

        if ($sisa > 0) {
            throw new SaldoTidakCukupException();
        }

        return $rows;  // bisa 1 atau 2+ row
    }

    /** Saat approve final: void semua debit_pending row + tulis confirmed
     *  dengan komposisi tahun_hak yang sama */
    public function commitConfirmed(CutiPengajuan $p): void
    {
        $pendingRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
            ->where('jenis_transaksi', 'debit_pending')
            ->get();

        foreach ($pendingRows as $pending) {
            // Void per bucket (positif, netralkan)
            CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'jenis_transaksi' => 'debit_void',
                'tahun_hak' => $pending->tahun_hak,
                'jumlah_hari' => -$pending->jumlah_hari,  // flip sign
                // ...
            ]);
            // Confirmed per bucket (negatif, commit)
            CutiSaldoLedger::create([
                'pengajuan_id' => $p->id,
                'jenis_transaksi' => 'debit_confirmed',
                'tahun_hak' => $pending->tahun_hak,
                'jumlah_hari' => $pending->jumlah_hari,  // sama dengan pending
                // ...
            ]);
        }
    }
}
```

**Constraint UNIQUE** `(pengajuan_id, jenis_transaksi, tahun_hak)`
mengakomodasi pola ini: 1 pengajuan bisa punya 2 row `debit_confirmed`
(satu untuk N-1, satu untuk N), tetapi **tidak boleh** ada 2 row
`debit_confirmed` dengan `tahun_hak` sama untuk pengajuan yang sama
(itulah yang dicegah).

### 8.5 Refund Pencabutan (CT only, FIFO per bucket)

Saat status `DISETUJUI` → `DICABUT_SETELAH_DISETUJUI` untuk **Cuti
Tahunan**: refund dihitung berdasarkan **sisa hari kerja yang belum
terjadi**, lalu dialokasikan ke bucket asal dengan **kebijakan FIFO**
(bucket terlama dulu, sesuai komposisi `debit_confirmed`). Bukan
proporsional rasio bucket.

**Aturan cutoff bisnis**:
- `today` = tanggal pencabutan diajukan (timezone server: Asia/Makassar)
- `tanggal_mulai` = hari pertama cuti
- Jika pencabutan diajukan **sebelum** `tanggal_mulai` (cuti belum
  mulai): refund **penuh** = total `jumlah_hari_kerja`
- Jika pencabutan diajukan pada `tanggal_mulai` atau setelahnya:
  hari berjalan dihitung **sudah terpakai** (tidak bisa refund hari
  itu — sudah masuk attendance). Refund hanya untuk **hari kerja
  setelah `today`** sampai `tanggal_selesai`.

**Refund harus dikembalikan ke bucket asal sesuai komposisi
`debit_confirmed`** dengan urutan FIFO (bucket dengan `tahun_hak`
terkecil dipenuhi dulu). Implementasi:

```php
function processRefund(CutiPengajuan $p): void {
    $totalRefund = hitungRefund($p);
    if ($totalRefund <= 0) return;

    // Ambil komposisi debit_confirmed per bucket (FIFO order: tahun_hak ASC)
    $confirmedRows = CutiSaldoLedger::where('pengajuan_id', $p->id)
        ->where('jenis_transaksi', 'debit_confirmed')
        ->orderBy('tahun_hak', 'asc')
        ->get();

    $totalConfirmed = abs($confirmedRows->sum('jumlah_hari'));
    $sisaRefund = $totalRefund;

    // Kebijakan: refund FIFO dari bucket terlama (return ke yang akan
    // hangus dulu — match urutan debit FIFO)
    foreach ($confirmedRows as $row) {
        if ($sisaRefund <= 0) break;
        $confirmedDiBucket = abs($row->jumlah_hari);
        $refundDiBucket = min($sisaRefund, $confirmedDiBucket);

        CutiSaldoLedger::create([
            'pengajuan_id' => $p->id,
            'jenis_transaksi' => 'kredit_refund',
            'tahun_hak' => $row->tahun_hak,  // kembalikan ke bucket asal
            'jumlah_hari' => +$refundDiBucket,
            // ...
        ]);
        $sisaRefund -= $refundDiBucket;
    }
}

function hitungRefund(CutiPengajuan $p): int {
    $today = now()->startOfDay();
    if ($today->lt($p->tanggal_mulai)) return $p->jumlah_hari_kerja;
    if ($today->gt($p->tanggal_selesai)) return 0;
    return HariKerjaCalculator::hitung(
        from: $today->copy()->addDay(),
        to: $p->tanggal_selesai
    );
}
```

**Constraint friendly**: 1 row `kredit_refund` per `tahun_hak` per
pengajuan — tidak melanggar UNIQUE.

**Alternatif kebijakan refund** (open question, lihat Section 15):
LIFO refund (kembalikan ke bucket termuda dulu) bisa lebih
menguntungkan pegawai karena bucket terlama tetap berisiko hangus.
Default MVP: FIFO (sesuai urutan debit) untuk simplicity.

**Catatan**: Pencabutan setelah `DISETUJUI` hanya berlaku untuk **CT
dan CAP** (sesuai matrix transisi Section 7). **CS Tier 1 dan Tier 2
tidak bisa dicabut** setelah disetujui karena cuti sakit sudah berjalan
saat diajukan (surat dokter retroaktif).

Untuk **CAP**, pencabutan **tidak menulis ledger sama sekali** (karena
tidak ada debit di awal — CAP tidak mengurangi saldo CT). State
transisi ke `DICABUT_SETELAH_DISETUJUI`, efek hanya ke audit log
(`spatie/laravel-activitylog`) + webhook notify attendance.

---

## 9. API & Events

### Web Routes (Inertia, untuk UI internal)

```
GET    /cuti/saya                       # Dashboard pegawai
GET    /cuti/pengajuan/baru             # Form (Inertia render)
POST   /cuti/pengajuan                  # Submit (Inertia redirect)
GET    /cuti/pengajuan/{id}             # Detail
GET    /cuti/inbox                      # Inbox approver
POST   /cuti/pengajuan/{id}/verify      # Petugas verifikasi
POST   /cuti/pengajuan/{id}/approve     # Atasan/Pejabat approve
POST   /cuti/pengajuan/{id}/reject      # Tolak
POST   /cuti/pengajuan/{id}/cancel      # Cabut
POST   /cuti/pengajuan/{id}/reassign-approver  # Reassign (admin only, manual)
GET    /cuti/pengajuan/{id}/pdf         # Generate/download PDF
GET    /admin/cuti/saldo                # Admin saldo management
```

Semua web routes pakai middleware `web` + `auth` + Inertia.

### REST API (Sanctum, untuk integrasi eksternal)

```
GET    /api/cuti/pengajuan              # List (untuk attendance sync, dll)
GET    /api/cuti/pengajuan/{id}         # Detail
GET    /api/cuti/saldo/{nip}            # Saldo per pegawai
GET    /api/cuti/saldo/{nip}/ledger     # Riwayat ledger
```

API hanya **read-only** untuk MVP — semua write action via Inertia web
routes. Auth: Sanctum personal access token + 4-layer security stack.

### Events

```
cuti.diajukan          # Setelah submit, snapshot approver captured
cuti.diverifikasi      # Petugas approve
cuti.disetujui_atasan  # Atasan langsung approve
cuti.disetujui         # Pejabat approve (final) → trigger webhook
cuti.ditolak           # Tolak di step manapun
cuti.dicabut           # Pencabutan setelah disetujui → trigger webhook
cuti.approver_diassign_ulang  # Reassign approver (audit only, no webhook)
```

### Webhook ke attendance-qr-system

**Security: 4 Layer** (sesuai existing pattern di `routes/api.php`)
1. HTTPS (TLS 1.2+)
2. Sanctum authentication (consumer punya API token)
3. HMAC-SHA256 signature dengan replay protection (lihat di bawah)
4. Rate limit 60/minute

**Note**: IP whitelist **tidak** di-enforce karena tidak ada middleware
untuk ini di codebase saat ini. Jika kemudian dibutuhkan, tambahkan
middleware `verify.ip` secara eksplisit dan dokumentasikan di sini.

**Shared secret** di-encrypt di database menggunakan
`Crypt::encryptString()` (pattern yang sama dengan
`IamApplication::api_secret_hash`):

```php
// Saat register consumer
$consumer->shared_secret_encrypted = Crypt::encryptString($plainSecret);
```

**Replay Protection** — sender mengirim 3 header per request:

| Header | Isi |
|---|---|
| `X-Event-Id` | UUID dari `cuti_events.id` (idempotency key, **wajib match `payload.event_id`**) |
| `X-Timestamp` | Unix timestamp saat dispatch (detik) |
| `X-Signature` | `hash_hmac('sha256', "{event_id}.{timestamp}.{raw_body}", $secret)` |

**Canonical string yang ditandatangani**: `{X-Event-Id}.{X-Timestamp}.{raw_body}`
— event_id, timestamp, dan body digabung dengan separator `.` (titik).
Event_id ikut ditandatangani agar **tidak bisa diganti** untuk bypass
dedupe. Receiver harus reconstruct string yang sama persis sebelum
verify.

```php
// Sender (kepegawaian-apps)
$secret = Crypt::decryptString($consumer->shared_secret_encrypted);
$timestamp = time();
$canonical = $event->id . '.' . $timestamp . '.' . $rawBody;
$signature = hash_hmac('sha256', $canonical, $secret);

Http::withHeaders([
    'X-Event-Id'  => $event->id,
    'X-Timestamp' => $timestamp,
    'X-Signature' => $signature,
])->post($consumer->webhook_url, $rawBody);
```

```php
// Receiver (attendance-qr-system) — verify middleware
$timestamp = (int) $request->header('X-Timestamp');
$eventId   = $request->header('X-Event-Id');
$signature = $request->header('X-Signature');
$rawBody   = $request->getContent();
$payload   = json_decode($rawBody, true);

// 1. Tolerance window: tolak jika |now - timestamp| > 300 detik
if (abs(time() - $timestamp) > 300) {
    abort(401, 'Timestamp out of tolerance');
}

// 2. X-Event-Id wajib match payload.event_id (anti-tampering)
if (!hash_equals((string) $payload['event_id'], $eventId)) {
    abort(401, 'Event ID mismatch between header and body');
}

// 3. Verify signature dengan canonical string yang ikut event_id
$canonical = $eventId . '.' . $timestamp . '.' . $rawBody;
$expected = hash_hmac('sha256', $canonical, $secret);
if (!hash_equals($expected, $signature)) {
    abort(401, 'Invalid signature');
}

// 4. Dedupe via X-Event-Id (kontrak retry yang ketat)
$existing = WebhookInboxReceiver::where('event_id', $eventId)->first();
if ($existing) {
    if ($existing->status === 'processed') {
        // Sender retry karena timeout response — kasih 200 OK idempotent
        return response()->json(['status' => 'already_processed'], 200);
    }
    if ($existing->status === 'failed') {
        // PENTING: jangan return 2xx agar sender lanjut retry sesuai
        // backoff. Receiver harus reset row ke 'pending' lalu reprocess
        // di worker — atau langsung return 503 untuk minta sender retry.
        $existing->update(['status' => 'pending', 'attempts_receiver' => DB::raw('attempts_receiver + 1')]);
        return response()->json(
            ['status' => 'previously_failed_retrying'],
            503  // Service Unavailable → sender akan retry sesuai backoff
        );
    }
    // status 'pending' → race condition (concurrent request) atau
    // worker belum sempat process. Tolak dengan 409 supaya sender
    // retry sebentar lagi.
    return response()->json(['status' => 'in_progress'], 409);
}

// 5. Insert inbox row (UNIQUE constraint sebagai final guard)
WebhookInboxReceiver::create([
    'event_id' => $eventId,
    'received_at' => now(),
    'status' => 'pending',
]);
```

**Tolerance window**: 300 detik (5 menit). Mengakomodasi clock skew
antar server tanpa membuka window replay terlalu lebar.

**Idempotency at receiver**: Tabel `webhook_inbox_receiver` di
attendance-qr-system dengan `UNIQUE(event_id)` + kolom
`status ENUM('pending', 'processed', 'failed')`.

**Kontrak response per status (KRITIS untuk hindari false delivered)**:

| Status existing | HTTP Response | Effect ke sender |
|---|---|---|
| (tidak ada row) | 200 OK setelah process selesai | Mark delivered |
| `processed` | **200 OK** (idempotent success) | Mark delivered |
| `failed` | **503 Service Unavailable** | Sender retry sesuai backoff |
| `pending` (race) | **409 Conflict** | Sender retry singkat |

Dengan kontrak ini, sender baru menandai `delivered` **hanya** kalau
receiver sudah benar-benar menyelesaikan processing (status →
`processed`). Failed di receiver tidak boleh di-claim sebagai
delivered di sender.

**Payload contoh**:

```json
{
  "event_id": "uuid-v4",
  "event_type": "cuti.disetujui",
  "occurred_at": "2026-05-01T10:30:00+08:00",
  "data": {
    "pengajuan_id": "01HXYZ...",
    "pegawai_nip": "199001012015031001",
    "jenis_cuti": "CT",
    "tanggal_mulai": "2026-06-01",
    "tanggal_selesai": "2026-06-05",
    "jumlah_hari_kerja": 5
  }
}
```

**Retry policy**: Exponential backoff (1m, 5m, 15m, 1h, 6h, 24h).
Max 6 attempts, lalu `dead_letter` (manual review).

---

## 10. UI Flow (MVP)

### Halaman Pegawai
- `/cuti/saya` — Dashboard cuti sendiri (saldo, riwayat, draft)
- `/cuti/pengajuan/baru` — Single-page form
- `/cuti/pengajuan/{id}` — Detail + audit trail

### Halaman Approver
- `/cuti/inbox` — Inbox approval (filter by role: kepegawaian / atasan / pejabat)
- `/cuti/pengajuan/{id}/approve` — Detail untuk approve/reject

### Halaman Admin
- `/admin/cuti/saldo` — Manajemen saldo (inisialisasi, penyesuaian)
- `/admin/cuti/audit` — Activity log viewer
- `/admin/cuti/konfigurasi` — Konfigurasi tahun aktif, libur, dll

### Komponen shadcn/ui yang dipakai
- Form, Input, Textarea, DatePicker (Calendar), Select, Button
- Card, Tabs, Table (TanStack), Badge, Alert
- Dialog (untuk konfirmasi reject/cancel), Toast (notif)

### Notifikasi MVP
- Database notifications via Laravel `Notification`
- In-app inbox bell icon (count unread)
- No email/WA blast di MVP

---

## 11. File Structure

```
app/
├── Http/
│   ├── Controllers/Cuti/
│   │   ├── PengajuanController.php
│   │   ├── ApprovalController.php
│   │   ├── SaldoController.php
│   │   └── PdfController.php
│   ├── Requests/Cuti/
│   │   ├── SubmitPengajuanRequest.php
│   │   ├── ApprovePengajuanRequest.php
│   │   └── ReassignApproverRequest.php
│   └── Resources/Cuti/
│       ├── PengajuanResource.php
│       └── SaldoResource.php
├── Models/Cuti/
│   ├── CutiPengajuan.php
│   ├── CutiSaldoLedger.php
│   ├── CutiJenisMaster.php
│   ├── CutiPengajuanApproverHistory.php
│   ├── CutiEvent.php
│   ├── CutiEventDelivery.php
│   └── CutiLiburMaster.php
├── States/Cuti/
│   ├── PengajuanState.php (abstract)
│   ├── DraftState.php
│   ├── DiajukanState.php
│   ├── DiverifikasiState.php
│   ├── DisetujuiAtasanState.php
│   ├── DisetujuiState.php
│   ├── DitolakKepegawaianState.php
│   ├── DitolakAtasanState.php
│   ├── DitolakPejabatState.php
│   ├── DibatalkanState.php
│   └── DicabutSetelahDisetujuiState.php
├── Services/Cuti/
│   ├── PengajuanCutiService.php
│   ├── SaldoLedgerService.php
│   ├── ApproverResolverService.php
│   ├── HariKerjaCalculatorService.php
│   ├── CarryOverProcessorService.php
│   └── EventDispatcherService.php
├── Policies/Cuti/
│   └── CutiPengajuanPolicy.php
├── Console/Commands/Cuti/
│   ├── ProcessCarryOverCommand.php
│   ├── DispatchPendingEventsCommand.php
│   └── ExpireOverdueDraftsCommand.php
├── Notifications/Cuti/
│   ├── PengajuanMenungguVerifikasi.php
│   ├── PengajuanMenungguApproval.php
│   ├── PengajuanDisetujui.php
│   └── PengajuanDitolak.php
└── Events/Cuti/
    ├── CutiDiajukan.php
    ├── CutiDisetujui.php
    └── CutiDicabut.php

database/
├── migrations/2026_05_01_*_create_cuti_*.php (15 tabel)
└── seeders/CutiJenisMasterSeeder.php

resources/js/
├── pages/cuti/
│   ├── Saya.tsx
│   ├── Inbox.tsx
│   ├── PengajuanBaru.tsx
│   ├── PengajuanDetail.tsx
│   └── admin/Saldo.tsx
└── components/cuti/
    ├── FormPengajuan.tsx
    ├── KartuSaldo.tsx
    ├── TimelineApproval.tsx
    └── DialogApprove.tsx

routes/
├── web.php  (additions for /cuti/*)
└── api.php  (additions for /api/cuti/*)

tests/
├── Unit/Cuti/
│   ├── SaldoLedgerServiceTest.php
│   ├── HariKerjaCalculatorServiceTest.php
│   ├── CarryOverProcessorTest.php
│   └── States/PengajuanStateTransitionTest.php
├── Feature/Cuti/
│   ├── SubmitPengajuanTest.php
│   ├── WorkflowApprovalTest.php
│   ├── PencabutanRefundTest.php
│   └── WebhookOutboxTest.php
└── Browser/Cuti/  (optional, Dusk)
```

**Estimasi**: ~120 files MVP (lebih ramping dari ~220 files spec v1)

---

## 12. Estimasi Effort

Estimasi dalam **person-days** (1 pd = 8 jam fokus). Asumsi 1 dev senior
Laravel + 1 dev frontend React, kerja paralel di mana memungkinkan.

| Section | Person-days | Catatan |
|---|---|---|
| Foundation (migration, model, seeder) | 3 | 15 tabel + factories |
| Saldo Ledger Service + tests | 3 | Critical path, TDD ketat (CT only) |
| State Machine (10 states + transitions) | 2 | spatie/laravel-model-states |
| Workflow Service + Policy + Idempotency Mutation | 4 | 4-step + reassign + lock pengajuan + tests |
| Web Controllers (Inertia actions) | 3 | 8 action: submit, verify, approve, reject, cancel, reassign, dll + form requests |
| REST API Controllers (read-only) | 1 | 4 endpoint: list, detail, saldo, ledger |
| Outbox + Webhook Worker + Replay Protection | 2.5 | Events + deliveries + canonical signature + receiver inbox |
| Carry-Over Command | 1 | Scheduled tiap 1 Jan + idempotency |
| PDF Generator (simple) | 1 | spatie/laravel-pdf |
| UI Pegawai (saya, form, detail) | 3 | 3 halaman Inertia |
| UI Approver (inbox, approve dialog) | 3 | 1 halaman + komponen |
| UI Admin (saldo init, audit) | 2 | 2 halaman |
| Notifications | 1 | 4 notification class |
| Integration Testing E2E | 2 | Workflow happy + edge cases (race conditions) |
| Code Review + Refactor | 2 | Iteration |
| **Total** | **32.5 pd** | ~6-7 minggu kalender (2 dev paralel: ~16 hari = 3-4 minggu) |

---

## 13. Implementation Approach

1. **Subagent-driven development**: Setelah plan disetujui, dispatch
   subagent per section (foundation, saldo, workflow, UI pegawai, UI
   approver, integration). Two-stage review per output.
2. **Phasing internal**:
   - Week 1: Foundation + Saldo Ledger + Carry-Over
   - Week 2: State Machine + Workflow + API
   - Week 3: UI Pegawai + UI Approver + Notifications
   - Week 4: PDF + Outbox + Integration Testing + Polish
3. **TDD ketat**: Service & Business Logic wajib test-first.
   Controllers smoke test cukup. Frontend optional Dusk.
4. **Activity log**: Pakai `spatie/laravel-activitylog` dengan
   `log_name = 'cuti'` untuk semua mutation di model utama.

---

## 14. Risiko & Mitigasi

| Risiko | Probabilitas | Dampak | Mitigasi |
|---|---|---|---|
| Concurrent saldo race | Sedang | Tinggi | `lockForUpdate()` dalam transaction; integration test dengan parallel |
| Webhook delivery gagal terus | Sedang | Sedang | Outbox + retry exponential + dead_letter alert + manual replay UI di Phase 2 |
| Atasan mutasi mid-flow | Rendah | Sedang | Reassign action + history table + snapshot tetap |
| PDF generation lambat | Rendah | Rendah | Browsershot async via queue job |
| Saldo awal tahun belum di-init | Sedang | Tinggi | Validation di submit (tolak jika `tahun_hak` aktif kosong) + admin alert dashboard |
| Lupa carry-over | Rendah | Tinggi | Scheduler + idempotency check (skip jika sudah ada `kredit` dengan `tahun_hak` baru) + log alert |

---

## 15. Open Questions (Untuk Review User)

### Locked Decisions (sudah di-ACK user 2026-05-01)

| # | Topic | Keputusan |
|---|---|---|
| L1 | Refund policy CT | **FIFO** ke bucket asal (default MVP). Lihat Section 8.5 untuk algoritma. |
| L2 | Cross-year leave | **Reject di form layer** — `tanggal_mulai.year() === tanggal_selesai.year()` wajib. Pegawai yang butuh cuti span Des-Jan harus split jadi 2 pengajuan. Lihat Section 8.3. |
| L3 | Composer dependencies Fase 1 | **Approved**: 3 paket baru (`spatie/laravel-model-states`, `spatie/laravel-pdf`, `spatie/laravel-activitylog`). Lihat Section 16. |

### Pending Questions (butuh konfirmasi sebelum implementasi)

1. **Format `nomor_pengajuan`**: Apakah pakai format lama (jika ada) atau
   bikin baru? Usulan: `CUTI/{YYYY}/{NIP-pendek}/{counter-4-digit}` —
   contoh `CUTI/2026/15031001/0042`.
2. **Working hours definition**: Apakah hari kerja PA Penajam adalah
   Senin-Jumat full, atau ada Sabtu setengah hari? (Asumsi MVP:
   Senin-Jumat saja, Sabtu-Minggu = libur.)
3. **Surat dokter retroaktif**: Untuk CS, batas maksimal hari ke-berapa
   surat dokter masih bisa diajukan? Usulan: H+3 setelah tanggal mulai
   sakit.
4. **CAP alasan**: Apakah CAP MVP perlu enum kategorisasi (kematian,
   pernikahan, kelahiran anak, dll) atau cukup free text? Usulan:
   enum + free text untuk "lainnya".
5. **Saldo init**: Bagaimana data saldo awal Januari 2026? Import dari
   spreadsheet HR existing, atau set manual via UI admin?

**Catatan**: Pending questions di atas tidak memblokir writing-plans —
default usulan dipakai sebagai placeholder yang akan dikonfirmasi saat
implementation per modul.

---

## 16. Dependency Decision

Modul ini menambah beberapa Composer & npm package baru ke project.
Setiap dependency butuh approval eksplisit dari user (decision lock).

### Composer Packages

| Package | Versi | Fase | Alasan | Alternatif | Status | Dampak Estimasi |
|---|---|---|---|---|---|---|
| `spatie/laravel-model-states` | ^2.7 | Fase 1 | State machine 10 states + transition validation runtime | Hand-rolled enum + manual guards | **Approved** (2026-05-01) | +0.5 pd untuk learning |
| `spatie/laravel-pdf` | ^1.5 | Fase 1 | PDF generation pakai Browsershot (Chromium headless) | `barryvdh/laravel-dompdf` (lebih ringan tapi tidak full CSS3) | **Approved** (2026-05-01) | +1 pd untuk Browsershot setup (Node + Puppeteer di server) |
| `spatie/laravel-activitylog` | ^4.10 | Fase 1 | Audit log mutation, log_name='cuti' | Hand-rolled audit table | **Approved** (2026-05-01) | +0.3 pd |
| `spatie/laravel-permission` | (existing) | – | Sudah dipakai di project untuk IAM | – | **Existing** | 0 |
| `masbug/flysystem-google-drive-ext` | ^2.4 | **Fase 2** | GDrive storage via OAuth Playground | Local disk (MVP), AWS S3 (skip — kantor tidak punya) | **Proposed (Fase 2)** | +2 pd di Fase 2 |

**Status legend**:
- **Proposed**: belum di-approve user, draft di spec ini.
- **Approved**: user telah meng-acknowledge & lock keputusan ini.
- **Existing**: sudah ada di `composer.json` project, no decision needed.
- **Rejected**: pernah diusulkan, ditolak (record alasan untuk future reference).

### Risk Item per Dependency

- **Browsershot (Browsershot/Chromium)** butuh Node.js + Puppeteer
  ter-install di server production. Jika server tidak punya Node,
  fallback ke `barryvdh/laravel-dompdf` (sudah pernah ada di project
  Laravel lain) — tapi template harus disesuaikan karena tidak
  support semua CSS3. **Action**: konfirmasi server provisioning
  sebelum start sprint PDF.
- **`spatie/laravel-model-states`** versi 2.x butuh PHP 8.1+ — project
  ini PHP 8.2 jadi aman. Migration: ada custom `state` column
  (varchar) + class StateConfig di model.

### Existing Stack (no new dep)

- Inertia v2, React 19, TypeScript, Tailwind v4, shadcn/ui, Sanctum,
  ULID via `tuupola/ulid` atau Laravel built-in.

### Approval Block

Apakah Anda menyetujui penambahan **3 paket Composer baru** di Fase 1:
1. `spatie/laravel-model-states` (state machine)
2. `spatie/laravel-pdf` (Browsershot)
3. `spatie/laravel-activitylog` (audit log)

`spatie/laravel-permission` **tidak dihitung** karena sudah ada di
project (existing IAM). `masbug/flysystem-google-drive-ext` di-defer ke
Fase 2 dan tidak masuk approval block ini.

Jika ya, lock decision ini sebelum masuk writing-plans. Jika ada paket
yang ingin di-swap (mis. ganti laravel-pdf dengan dompdf), beri tahu
sekarang agar plan task break-down tidak invalid.

---

## 17. References

### Regulasi
- PP No. 11 Tahun 2017 tentang Manajemen PNS — Pasal 309-339 (Cuti)
- PP No. 17 Tahun 2020 — Amandemen PP 11/2017
- PP No. 49 Tahun 2018 tentang Manajemen PPPK
- Peraturan BKN No. 24 Tahun 2017 — Tata Cara Cuti PNS
- Peraturan BKN No. 7 Tahun 2021 — Perubahan Peraturan BKN 24/2017
- Peraturan BKN No. 7 Tahun 2022 — Cuti PPPK
- UU ASN No. 20 Tahun 2023 — Kesetaraan PNS-PPPK
- SE Sekma MA No. 13 Tahun 2019 — Hierarki Cuti Hakim
- PERMA No. 7 Tahun 2015 — Organisasi Pengadilan

### Library
- `spatie/laravel-model-states` — State machine
- `spatie/laravel-pdf` — PDF generation (Browsershot)
- `spatie/laravel-activitylog` — Audit log
- `spatie/laravel-permission` — RBAC
- `masbug/flysystem-google-drive-ext` — GDrive (Phase 2)

### Existing Pattern
- `app/Models/IamApplication.php` — `Crypt::encryptString` untuk shared secret
- `routes/api.php` — 4-layer security middleware stack
- `app/Models/Pegawai.php` — Pegawai sebagai authenticatable

---

## Changelog

- **v7 (2026-05-01)**: Decision lock — user ACK 3 open questions + 1 kebijakan baru.
  - **L1**: Refund policy CT = **FIFO** (default MVP) — sudah selaras
    dengan algoritma `processRefund()` di Section 8.5.
  - **L2 (NEW POLICY)**: **No cross-year leave**. Pengajuan dengan
    `tanggal_mulai.year() !== tanggal_selesai.year()` ditolak di form
    layer. Kebijakan PA Penajam — tidak ada cuti yang span pergantian
    tahun. Pegawai yang butuh cuti span Des-Jan harus split jadi 2
    pengajuan terpisah. Implikasi:
    - Section 8.3 ditambah validasi umum no cross-year + mitigasi 2
      lapis (no cross-year + tahun harus punya alokasi).
    - Pegawai-Level Lock di-rename "single-tahun" + komentar `range()`
      diperbarui (selalu single tahun, dipertahankan sebagai defense
      in-depth jika form layer bypass karena bug).
  - **L3**: Composer dependencies status diubah dari `Proposed` menjadi
    `Approved` (2026-05-01) untuk 3 paket Fase 1: `model-states`,
    `laravel-pdf`, `activitylog`.
  - Section 15 di-restructure: tambah subsection "Locked Decisions"
    (L1-L3) di atas, "Pending Questions" di bawah dengan catatan
    bahwa pending questions tidak memblokir writing-plans.
  - Header status di-bump: `Draft v6` → `Draft v7`.
- **v6 (2026-05-01)**: Patch setelah review ke-5 (5 temuan ringan, no critical).
  - Section 8.5 (refund pencabutan) ditambah catatan eksplisit: pencabutan
    setelah `DISETUJUI` hanya berlaku untuk **CT dan CAP** sesuai matrix
    transisi Section 7. CS Tier 1 & Tier 2 **tidak bisa dicabut** karena
    cuti sakit sudah berjalan saat diajukan (surat dokter retroaktif).
    CAP tidak menulis ledger sama sekali (audit log + webhook saja).
  - Section 8.5 di-rename dari "Refund Proporsional" menjadi
    "Refund Pencabutan (CT only, FIFO per bucket)". Wording diperjelas:
    refund dialokasikan ke bucket asal dengan kebijakan **FIFO** (bucket
    terlama dulu, sesuai komposisi `debit_confirmed`), **bukan**
    proporsional rasio bucket — konsisten dengan algoritma di kode.
  - Approval block (Section 16) dikoreksi dari "4 paket Composer"
    menjadi **3 paket Composer baru** di Fase 1: `model-states`,
    `laravel-pdf`, `activitylog`. `spatie/laravel-permission` tidak
    dihitung karena sudah existing. `masbug/flysystem-google-drive-ext`
    di-defer ke Fase 2 dan tidak masuk approval block ini.
  - Section 7 Idempotency Mutation ditambah subsection
    "Idempotency untuk Transaksi System-Generated (`pengajuan_id = NULL`)"
    yang eksplisitkan bahwa `kredit`, `expire`, dan `penyesuaian`
    **tidak terlindungi** oleh UNIQUE constraint (MySQL allow multi NULL),
    melainkan oleh pola **service-level lock + existence check** di
    anchor row `cuti_alokasi_tahunan`. Dilengkapi pseudocode pattern
    dan rationale + future hardening (kolom `idempotency_key` jika
    diperlukan di Fase 2/3).
  - Header status di-bump: `Draft v2` → `Draft v6`.
- **v5 (2026-05-01)**: Patch setelah review ke-4 (6 temuan).
  - Constraint UNIQUE ledger jadi `(pengajuan_id, jenis_transaksi,
    tahun_hak)` — sebelumnya hanya 2 kolom, akan blok kasus valid
    multi-bucket FIFO.
  - Section 8.4 baru "FIFO Split Bucket (CT)" dengan implementasi
    `debitPendingFifo()` dan `commitConfirmed()` yang split lintas
    bucket. Section refund (sekarang 8.5) update jadi `processRefund()`
    yang kredit per bucket sesuai komposisi `debit_confirmed`.
  - Webhook receiver kontrak response: `processed` → 200, `failed` →
    503 (sender lanjut retry), `pending` race → 409, baru → 200 setelah
    process. Hindari false delivered.
  - Pegawai-level lock pakai `range(tahunMulai, tahunSelesai)` dari
    rentang tanggal pengajuan, bukan `now()->year`. Lock urutan ASC
    untuk hindari deadlock. Span Des-Jan & pengajuan tahun depan
    aman ter-serialize.
  - Hapus kontradiksi "optional logging" CS/CAP — tegaskan ledger
    **CT-only**, audit CS/CAP via `spatie/laravel-activitylog`.
  - Dependency status: ganti "Approved" → "Proposed" sampai user
    eksplisit ACK approval block. Tambah status legend.
- **v4 (2026-05-01)**: Patch setelah review ke-3 (9 temuan).
  - Tambah `lockForUpdate()` pada `cuti_pengajuan` di setiap mutation
    + re-validate state setelah lock (idempotency mutation).
  - Tambah `UNIQUE(pengajuan_id, jenis_transaksi)` di
    `cuti_saldo_ledger` sebagai defense-in-depth guard.
  - Tegaskan **efek ledger HANYA untuk CT**: CS Tier 1, CS Tier 2,
    CAP tidak menulis ledger sama sekali di MVP.
  - Carry-over model konsisten: **N-2 hangus per 1 Januari, max saldo
    18 hari**, hapus narasi "window 24" yang tidak usable.
  - Webhook canonical string ditambah event_id:
    `{event_id}.{timestamp}.{raw_body}`. Receiver wajib match
    `X-Event-Id` dengan `payload.event_id`.
  - Webhook duplicate yang sudah `processed` return **200 OK
    idempotent**, bukan 4xx (hindari false dead-letter).
  - Lock carry-over diperbaiki: `firstOrCreate` dulu lalu re-query
    dengan `lockForUpdate()` (firstOrCreate tidak bisa di-chain).
  - Outbox example tambah filter eksplisit + lock pengajuan + check
    state.
  - Overlap submit paralel pakai **pegawai-level lock** via
    `cuti_alokasi_tahunan` (CT + tahun aktif) sebagai universal
    anchor untuk semua jenis cuti.
  - Refund formula: hari pertama cuti dianggap terpakai jika
    pencabutan diajukan ≥ `tanggal_mulai`; refund hanya hari kerja
    setelah `today` (eksklusif).
  - Tambah Section 16 "Dependency Decision" untuk lock keputusan
    paket Composer baru sebelum writing-plans.
  - Estimasi split: web controllers (Inertia) vs REST API (read-only).
- **v3 (2026-05-01)**: Patch setelah review ke-2 (10 temuan).
  - Tambah `cuti_alokasi_tahunan` sebagai anchor row untuk
    `lockForUpdate()` (fix race condition di SUM agregat).
  - Eksplisit-kan pasangan ledger: approve = `debit_void` + `debit_confirmed`
    dalam 1 transaksi (fix double-debit).
  - Overlap validation perluas ke 4 state aktif: DIAJUKAN, DIVERIFIKASI,
    DISETUJUI_ATASAN, DISETUJUI.
  - Transactional outbox invariant ditegaskan: state + ledger + event
    dalam 1 `DB::transaction()`.
  - Rule engine validasi per jenis cuti: CT saldo-driven, CS/CAP
    dokumen-driven (saldo bukan syarat universal).
  - Carry-over algorithm lengkap: handle expire N-2, cap N-1, kredit N
    dengan idempotency check.
  - Reassign vs Plh/Plt clarification: MVP hanya manual admin, Plh/Plt
    auto-resolution defer ke Fase 2.
  - DIBATALKAN konsisten di state diagram + authorization + efek ledger.
  - Webhook replay protection: X-Event-Id + X-Timestamp + canonical
    string `{timestamp}.{raw_body}`, tolerance 300s, dedupe inbox di
    receiver.
  - UI/API split decision: Inertia routes untuk UI internal, REST API
    read-only untuk integrasi eksternal.
- **v2 (2026-05-01)**: Major rewrite setelah code review pertama.
  Reduced MVP scope (defer Plh/Plt, external Ketua PA, GDrive, PWA ke
  Phase 2). Pure ledger pattern. Split outbox. Approver snapshot. FK
  ke pegawai. Encrypt webhook secret. State machine vs authorization
  terpisah. Matriks transisi per jenis cuti. 4-layer security. Tabel
  = 15. Estimasi dalam person-days.
- **v1 (2026-05-01)**: Draft awal (commit ae466d3, sudah di-supersede).
