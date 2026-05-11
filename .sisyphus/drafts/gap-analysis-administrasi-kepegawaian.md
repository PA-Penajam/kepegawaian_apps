# Draft: Analisis Gap Spesifikasi Administrasi Kepegawaian

## Konteks
- **Dokumen sumber**: `docs/spesifikasi-fitur-administrasi-kepegawaian-belum-tercover.md`
- **Project**: `kepegawaian-apps` (Laravel 12 + Inertia v2 + React 19 + Tailwind v4)
- **Tujuan**: Mengidentifikasi fitur yang belum ada / belum lengkap untuk disusun ke rencana kerja

---

## A. Infrastruktur Reusable (Sangat Kuat)

Project sudah memiliki fondasi teknis yang solid untuk semua modul baru:

| Package / Komponen | Status | Kegunaan untuk Modul Baru |
|---|---|---|
| `spatie/laravel-model-states` ^2.7 | ✅ Terpasang & dipakai di `CutiPengajuan` | State machine workflow (mutasi, pensiun, KP) |
| `spatie/laravel-activitylog` ^5.0 | ✅ Dipakai ~38 model | Audit trail otomatis |
| `spatie/laravel-pdf` ^1.5 | ✅ Dipakai di `Cuti/PdfController` + template Blade | Generate SK, surat cuti, surat mutasi |
| `maatwebsite/excel` ^3.1 | ✅ Terpasang | Laporan periodik (LAP-01..LAP-09) |
| Custom IAM (roles + permissions) | ✅ Lengkap + middleware | Approval berjenjang, hak akses |
| Notifications infra (DB + Mail) | ✅ `NotificationController`, `NotificationBell.tsx` | Notifikasi deadline, approval |
| Dokumen Pegawai | ✅ Model + storage pattern | Template lampiran per proses |

---

## B. Fitur SUDAH ADA

- ✅ Manajemen data pegawai (`Pegawai` model, ULID, softDeletes, 40+ kolom, enum casts)
- ✅ Riwayat pangkat/jabatan/pendidikan/diklat + service
- ✅ Dokumen, Keluarga, Hukuman Disiplin, Penghargaan
- ✅ Pengajuan Perubahan Data + approval + policy + enum workflow
- ✅ Monitoring KGB (service + command + notification + test)
- ✅ Monitoring Kenaikan Pangkat (service + command + notification + test) — **TAPI MASIH PAKAI 2 PERIODE (April/Oktober)**
- ✅ IAM/SSO/RBAC (model `IamRole`, `IamPermission`, `IamUserRole`, middleware `VerifyIamPermission`/`EnsurePermission`)
- ✅ Activity Log (`activity_log` table + controller + trait)
- ✅ Modul Cuti **LENGKAP** (lihat detail di C)

---

## C. Modul Cuti — VERIFIKASI PENTING

**SUDAH ADA (sangat lengkap):**

**Tabel (16 migrasi)**:
- `cuti_konfigurasi`, `cuti_jenis_master`, `cuti_jenis_per_status_pegawai`
- `cuti_libur_master`, `cuti_alokasi_tahunan`, `cuti_saldo_ledger`
- `cuti_pengajuan`, `cuti_pengajuan_periode`, `cuti_pengajuan_lampiran`
- `cuti_pengajuan_approval_steps`, `cuti_pengajuan_approver_history`, `cuti_pengajuan_state_history`
- `cuti_events`, `cuti_event_deliveries`, `cuti_pengajuan_pdf`

**Model/Controller/UI**:
- 15 model di `app/Models/Cuti/`
- Controller: `PengajuanController`, `ApprovalController`, `SaldoController`, `PdfController`, `AuditController`
- API: `Api/Cuti/PengajuanController`, `Api/Cuti/SaldoController`
- UI: `resources/js/pages/cuti/{pengajuan,approval,saldo,admin}/*`

**Pemetaan ke CUTI-01..CUTI-10 (pending verifikasi detail):**

| Kode | Requirement | Status Dugaan |
|---|---|---|
| CUTI-01 | Pegawai mengajukan cuti/izin | ✅ `PengajuanController@store` |
| CUTI-02 | Jenis cuti (tahunan, sakit, melahirkan, dll) | ✅ `CutiJenisMaster` |
| CUTI-03 | Izin tidak masuk/keluar/terlambat | ⚠️ Perlu verifikasi (tergantung `cuti_jenis_master`) |
| CUTI-04 | Tanggal mulai/selesai/hari/alasan/lampiran | ✅ `CutiPengajuan` + `CutiPengajuanLampiran` + `CutiPengajuanPeriode` |
| CUTI-05 | Validasi sisa cuti | ✅ `CutiSaldoLedger` + `SaldoController` |
| CUTI-06 | Atasan setuju/tolak | ✅ `ApprovalController` + `cuti_pengajuan_approval_steps` |
| CUTI-07 | Riwayat persetujuan | ✅ `cuti_pengajuan_approver_history` + `state_history` |
| CUTI-08 | Terbitkan surat cuti/izin | ✅ `PdfController` + `cuti_pengajuan_pdf` |
| CUTI-09 | Rekap per pegawai/periode | ✅ `AuditController` + `cuti_saldo_ledger` |
| CUTI-10 | Bedakan aturan PNS/PPPK | ✅ `cuti_jenis_per_status_pegawai` |

**Kesimpulan**: Modul Cuti secara struktural sudah memenuhi semua 10 requirement. Gap (jika ada) lebih pada polish UX atau jenis izin (CUTI-03) yang perlu dicek isi master data-nya.

---

## D. Fitur BELUM ADA (Gap Utama)

### D.1 Workflow Kenaikan Pangkat Usulan ❌
- **Sekarang**: Hanya `KenaikanPangkatMonitoringService` (read-only monitoring eligibility). Tidak ada workflow usulan, approval, SK.
- **Masalah kritis**: Service masih pakai **2 periode (April/Oktober)** — tidak sesuai Peraturan BKN 4/2025 yang wajib **12 periode/tahun**. Lihat `KenaikanPangkatMonitoringService.php:42-46, 116-120, 132-150, 190-207`.
- **Dibutuhkan**:
  - Refactor service → 12 periode bulanan
  - Model baru: `UsulanKenaikanPangkat`, `UsulanKenaikanPangkatApprovalStep`, `UsulanKenaikanPangkatLampiran`, `UsulanKenaikanPangkatStateHistory`
  - State machine: Eligible → Draft → Diajukan → Verifikasi → Perlu Perbaikan → Disetujui/Ditolak → SK Terbit → Selesai
  - UI: daftar eligible, form usulan, inbox approval, detail, daftar SK
  - Auto-update `riwayat_pangkat` + `pegawai.ref_pangkat_id` jika disetujui

### D.2 Workflow Mutasi Pegawai ❌
- **Sekarang**: 0% (tidak ada tabel/model/controller/UI)
- **Dibutuhkan**:
  - Migrasi: `mutasi_pengajuan`, `mutasi_pengajuan_approval_steps`, `mutasi_pengajuan_lampiran`, `mutasi_pengajuan_state_history`
  - State: Draft → Diajukan → Verifikasi → Perlu Perbaikan → Disetujui/Ditolak → Selesai/Dibatalkan
  - Jenis: jabatan, unit kerja, antar satker, masuk, keluar
  - Auto-update `riwayat_jabatan` + `pegawai.ref_jabatan_id`/`ref_unit_kerja_id`
  - Checklist berkas + SK mutasi

### D.3 Workflow Pensiun ❌
- **Sekarang**: Kolom `pegawai.tanggal_pensiun_bup` ada, tapi tidak ada monitoring/workflow
- **Dibutuhkan**:
  - Service monitoring: daftar mendekati BUP (24/12/6/3 bulan) — mirip pola KGB
  - Notifikasi otomatis (console command)
  - Model: `UsulanPensiun`, state machine, checklist, SK pensiun
  - Jenis: BUP, dini, meninggal, lainnya
  - Auto-update `pegawai.status_pegawai` → Pensiun

### D.4 Checklist Berkas Administrasi ❌
- **Sekarang**: Tidak ada sistem checklist generik
- **Dibutuhkan**:
  - Template checklist per domain (cuti, izin, mutasi, pensiun, KP)
  - Model polimorfik: `berkas_checklist_template` + `berkas_checklist_item` + `berkas_checklist_submission` + `berkas_checklist_submission_item`
  - Status per item: belum ada / ada / valid / perlu perbaikan
  - Upload lampiran per item
  - Persentase kelengkapan
  - Gate workflow (tidak bisa lanjut jika wajib belum lengkap)

### D.5 Generate Dokumen/SK Generik ❌
- **Sekarang**: `spatie/laravel-pdf` ada tapi hanya dipakai untuk surat cuti
- **Dibutuhkan**:
  - Template engine: `dokumen_template` table (variabel: nama, NIP, pangkat, jabatan, dll)
  - `NomorSuratService` — generator nomor surat berurutan per jenis + tahun
  - Workflow status dokumen: draft → final → ditandatangani
  - Simpan ke arsip pegawai (`dokumen_pegawai`)
  - Template awal: surat cuti/izin, pengantar mutasi, SK mutasi, pengantar pensiun, checklist pensiun, usulan KP, berita acara

### D.6 Laporan Periodik Lintas Modul ⚠️
- **Sekarang**: `maatwebsite/excel` terpasang, export per modul belum seragam
- **Dibutuhkan**: LAP-01..LAP-09 dalam format PDF + Excel dengan filter (periode, unit kerja, status, jenis)

### D.7 Dashboard Monitoring Administrasi ⚠️
- **Sekarang**: `DashboardController` + `DashboardStatService` ada
- **Dibutuhkan**: Widget/card baru di dashboard: pensiun mendekati, mutasi pending, usulan KP per periode, berkas belum lengkap

---

## E. Ringkasan Gap (Prioritas per Dokumen)

| Prioritas | Fitur | Status | Estimasi Effort |
|---|---|---|---|
| **P1** | Manajemen Cuti & Izin | ✅ **SUDAH ADA** (verifikasi saja) | Quick (audit + polish) |
| **P1** | Workflow KP 12 periode (refactor + usulan) | ❌ Monitoring saja, masih 2 periode | **L (Large)** |
| **P1** | Checklist Berkas (generic) | ❌ Belum ada | **M (Medium)** |
| **P2** | Workflow Pensiun | ❌ Belum ada | **M (Medium)** |
| **P2** | Workflow Mutasi | ❌ Belum ada | **L (Large)** |
| **P2** | Generate Dokumen/SK + NomorSurat | ❌ Belum ada (hanya cuti) | **M (Medium)** |
| **P3** | Dashboard monitoring | ⚠️ Sebagian | **S (Small)** |
| **P3** | Laporan periodik (PDF + Excel) | ⚠️ Sebagian | **M (Medium)** |
| **P3** | Notifikasi deadline | ⚠️ Sebagian | **S (Small)** |

**Total effort kasar (tanpa Cuti)**: ~6-9 minggu dev effort (asumsi 1 dev fulltime).

---

## F. Rekomendasi Arsitektur (Konsisten dengan Pola Cuti)

Semua modul workflow baru **wajib menggunakan pola Cuti** agar konsisten:

```
{modul}_pengajuan                  — entitas utama + state
{modul}_pengajuan_approval_steps   — langkah approval berjenjang
{modul}_pengajuan_approver_history — audit approver
{modul}_pengajuan_state_history    — audit transisi state
{modul}_pengajuan_lampiran         — file pendukung
{modul}_pengajuan_pdf              — hasil PDF generated
```

**Dengan abstraksi tambahan**:
- `berkas_checklist_*` polimorfik (bisa dipakai cuti juga kalau perlu)
- `dokumen_template` untuk template surat
- `nomor_surat_sequence` untuk nomor otomatis
- `PengajuanWorkflow` trait/interface sebagai kontrak umum

---

## Pertanyaan Interview untuk User

1. **Skup prioritas**: Plan ini mencakup SEMUA gap (P1+P2+P3) atau hanya P1?
2. **Modul Cuti**: Audit saja, atau perlu polish tertentu?
3. **KP 12 periode**: Refactor monitoring + bangun workflow usulan full, atau refactor dulu lalu workflow terpisah?
4. **Checklist generic vs per-modul**: Generic polimorfik (reusable) atau per-modul (lebih simple)?
5. **NomorSurat**: Format/pattern yang dipakai (contoh: `{jenis}/{kode_unit}/{bulan-romawi}/{tahun}/{seq}`)?
6. **Role approver**: Apakah ada hirarki baru (Kasubbag → Sekretaris → Pimpinan) atau pakai role existing?
7. **Test strategy**: TDD, tests-after, atau no tests? (infra Pest 4 sudah ada)
8. **Timebox**: Ada deadline atau progressive delivery?
