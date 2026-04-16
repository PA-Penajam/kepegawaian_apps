# Improvement Roadmap — Kepegawaian Apps

**Tanggal:** 2026-04-17  
**Status:** Approved  
**Scope:** Semua 14 area improvement dari analisis_project.md  
**Pendekatan:** Milestone-based phases, urutan Stabilitas → Kepatuhan → UX → Fitur  
**Konteks:** Solo developer, dikerjakan sekuensial (satu item tuntas sebelum lanjut)

---

## Prinsip Roadmap

1. **Stabilitas sebelum fitur** — sistem tidak boleh crash atau lambat saat data tumbuh
2. **Setiap fase menghasilkan sistem yang lebih baik** — bisa berhenti kapan saja
3. **Satu item selesai tuntas** (termasuk test TDD) sebelum lanjut ke item berikutnya
4. **Dampak bisnis menentukan urutan** dalam setiap fase

---

## 🔴 FASE 1: Fondasi Stabil

> **Tujuan:** Sistem tidak lambat dan tidak crash saat data berkembang

### 1.1 Pagination di `KgbMonitoringService` & `KenaikanPangkatMonitoringService`

**Masalah:** Kedua service load seluruh tabel pegawai ke memory, lalu filter di PHP Collection. Berbahaya jika data ribuan pegawai.

**Solusi:** Refactor filter ke level query (database), gunakan `->paginate()` agar total dihitung dalam satu query. Sesuaikan frontend untuk menerima paginated response.

**File yang diubah:**
- `app/Services/KgbMonitoringService.php`
- `app/Services/KenaikanPangkatMonitoringService.php`
- `app/Http/Controllers/Monitoring/KgbMonitoringController.php`
- `app/Http/Controllers/Monitoring/KenaikanPangkatMonitoringController.php`
- `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`
- `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`
- `tests/Feature/Monitoring/`

---

### 1.2 Fix N+1 Query di `DashboardStatService`

**Masalah:** `getDistribusiGolongan()`, `getDistribusiJabatan()`, `getDistribusiPendidikan()` masing-masing load seluruh tabel pegawai ke memory, lalu proses di PHP.

**Solusi:** Ganti dengan `selectRaw` + `groupBy` langsung di SQL. Tambahkan `Cache::remember` dengan TTL 5 menit untuk hasil dashboard.

**File yang diubah:**
- `app/Services/DashboardStatService.php`
- `tests/Feature/DashboardTest.php`

---

### 1.3 Fix Cache IAM Tidak Di-invalidate

**Masalah:** Cache IAM permission di-set TTL 1 jam (`Cache::remember("iam_app:{$appSlug}", 3600, ...)`). Saat role/permission diubah, cache lama masih berlaku — potensi security vulnerability aktif.

**Solusi:** Tambahkan cache invalidation (`Cache::forget("iam_app:{$appSlug}")`) di setiap operasi mutasi pada `RoleController`, `PermissionController`, dan `UserAksesController`.

**File yang diubah:**
- `app/Http/Controllers/Iam/RoleController.php`
- `app/Http/Controllers/Iam/PermissionController.php`
- `app/Http/Controllers/Iam/UserAksesController.php`
- `tests/Feature/Iam/`

---

## 🟠 FASE 2: Kepatuhan & Kualitas Kode

> **Tujuan:** Memenuhi standar audit pemerintahan dan menjaga maintainability

### 2.1 Implementasi Audit Trail / Activity Log

**Masalah:** Tidak ada pencatatan siapa mengubah data apa dan kapan. Kritis untuk sistem kepegawaian pemerintahan — biasanya diwajibkan oleh regulasi (SPBE, BKD).

**Solusi:** Integrasikan `spatie/laravel-activitylog`. Log otomatis pada semua model utama (`Pegawai`, `RiwayatPangkat`, `RiwayatJabatan`, dll.) via `LogsActivity` trait. Buat halaman admin untuk melihat activity log.

**File yang diubah/ditambah:**
- `composer.json` (tambah `spatie/laravel-activitylog`)
- Semua Model utama di `app/Models/`
- `app/Http/Controllers/ActivityLogController.php` (baru)
- `resources/js/pages/activity-log/` (baru)
- `routes/web.php`
- `tests/Feature/ActivityLogTest.php` (baru)

---

### 2.2 FormRequest Consistency di IAM Controllers

**Masalah:** `AplikasiController` dan `UserAksesController` menggunakan inline validation — tidak konsisten dengan konvensi kepegawaian yang sudah pakai FormRequest.

**Solusi:** Buat FormRequest class untuk semua operasi IAM yang belum memilikinya.

**File yang ditambah:**
- `app/Http/Requests/Iam/StoreAplikasiRequest.php`
- `app/Http/Requests/Iam/UpdateAplikasiRequest.php`
- `app/Http/Requests/Iam/StoreUserAksesRequest.php`
- Update `AplikasiController.php` dan `UserAksesController.php`

---

### 2.3 Fix Duplikasi Query di `PegawaiApiController::search()`

**Masalah:** Dua query terpisah untuk data yang sama — satu untuk data, satu untuk total count.

**Solusi:** Ganti dengan `->paginate()` sehingga total otomatis dihitung dalam satu query.

**File yang diubah:**
- `app/Http/Controllers/Api/PegawaiApiController.php`
- `tests/Feature/Api/PegawaiApiTest.php`

---

## 🟡 FASE 3: UX Harian

> **Tujuan:** Pengalaman yang lebih baik untuk admin dan pegawai setiap hari

### 3.1 Upload & Tampil Foto Pegawai

**Masalah:** Field `foto` ada di model/migration tapi tidak ada UI untuk upload atau tampil.

**Solusi:** Implementasikan upload foto dengan validasi (max 2MB, mime: jpg/png/webp), resize dengan `intervention/image`, storage di disk `public`, tampil di halaman detail dan list pegawai.

**File yang diubah/ditambah:**
- `app/Http/Controllers/Kepegawaian/PegawaiController.php`
- `app/Http/Requests/Kepegawaian/UpdateFotoPegawaiRequest.php` (baru)
- `resources/js/pages/kepegawaian/pegawai/` (show + index)
- `resources/js/components/pegawai/FotoUpload.tsx` (baru)

---

### 3.2 Dashboard Loading State / Skeleton

**Masalah:** Data dashboard di-pass synchronous via Inertia props — halaman berat jika DB lambat.

**Solusi:** Gunakan **Inertia Deferred Props** (fitur Inertia v2) untuk data statistik berat. Tambahkan skeleton component selama data loading.

**File yang diubah:**
- `app/Http/Controllers/DashboardController.php`
- `resources/js/pages/dashboard/index.tsx`
- `resources/js/components/dashboard/` (skeleton components)

---

### 3.3 Filter Lengkap di Monitoring KGB & KP

**Masalah:** Monitoring tidak bisa difilter per unit kerja, golongan, atau periode.

**Solusi:** Tambah filter `unit_kerja_id`, `golongan`, `status` pada query monitoring. Tambah komponen filter UI di halaman monitoring.

**File yang diubah:**
- `app/Services/KgbMonitoringService.php`
- `app/Services/KenaikanPangkatMonitoringService.php`
- `resources/js/pages/kepegawaian/monitoring/` (kedua halaman)

---

### 3.4 Foto di Halaman Self-Service

**Masalah:** Halaman self-service tidak menampilkan foto pegawai (konsistensi setelah 3.1 selesai).

**Solusi:** Tampilkan foto pegawai di header halaman self-service. Bergantung pada selesainya item 3.1.

**File yang diubah:**
- `resources/js/pages/self-service/`

**Dependensi:** Setelah item 3.1 selesai.

---

## 🟢 FASE 4: Fitur Operasional

> **Tujuan:** Mendukung pekerjaan administratif kepegawaian sehari-hari

### 4.1 Export Monitoring KGB & KP (CSV/Excel)

**Masalah:** Tidak ada fitur export untuk laporan monitoring ke pimpinan.

**Solusi:** Implementasikan export CSV/Excel menggunakan `maatwebsite/laravel-excel`. Buat Export class untuk KGB dan KP. Tambah tombol export di halaman monitoring.

**File yang ditambah:**
- `app/Exports/KgbMonitoringExport.php`
- `app/Exports/KenaikanPangkatMonitoringExport.php`
- Update controller monitoring
- Update halaman React monitoring (tombol export)

---

### 4.2 Chart/Grafik di Dashboard

**Masalah:** Dashboard hanya menggunakan progress bar — tidak ada chart visual.

**Solusi:** Integrasikan `recharts` (sudah populer di ekosistem React). Ganti progress bar distribusi golongan, jabatan, pendidikan, dan jenis kelamin dengan chart yang sesuai (pie chart atau bar chart).

**File yang diubah:**
- `package.json` (tambah `recharts`)
- `resources/js/pages/dashboard/index.tsx`
- `resources/js/components/dashboard/` (chart components)

---

### 4.3 Notifikasi Otomatis KGB & KP

**Masalah:** Deadline KGB/KP yang jatuh tempo tidak memicu notifikasi apapun.

**Solusi:** Buat scheduled command yang berjalan harian. Kirim notifikasi email ke admin/pegawai bersangkutan menggunakan Laravel Notification + Mailable.

**File yang ditambah:**
- `app/Console/Commands/SendKgbNotification.php`
- `app/Console/Commands/SendKenaikanPangkatNotification.php`
- `app/Notifications/KgbJatuhTempoNotification.php`
- `app/Notifications/KenaikanPangkatEligibleNotification.php`
- Update `routes/console.php`

---

## ⚪ FASE 5: Fitur Lanjutan

> **Tujuan:** Fitur kompleks yang membutuhkan perencanaan terpisah

### 5.1 Peningkatan Test Coverage Monitoring

**Masalah:** Hanya ada 2 file test untuk monitoring (happy path). Tidak ada test edge case.

**Solusi:** Tambah test untuk: data kosong, pegawai tanpa riwayat pangkat, pagination dengan data besar, filter kombinasi, export dengan data kosong.

**File yang ditambah:**
- `tests/Feature/Monitoring/KgbMonitoringEdgeCaseTest.php`
- `tests/Feature/Monitoring/KenaikanPangkatEdgeCaseTest.php`

---

### 5.2 Self-Service: Pengajuan Perubahan Data + Approval Workflow

**Masalah:** Pegawai hanya bisa *melihat* data dirinya — tidak bisa mengajukan perubahan.

**Solusi:** Implementasikan modul pengajuan perubahan data dengan approval workflow. Membutuhkan desain database baru (tabel `pengajuan_perubahan_data`, status: pending/approved/rejected). Pegawai mengajukan, admin mereview dan approve/reject.

**Catatan:** Item ini membutuhkan sesi brainstorming tersendiri sebelum implementasi karena melibatkan desain database baru dan state machine workflow.

**File yang ditambah (estimasi):**
- Migration `pengajuan_perubahan_data`
- `app/Models/PengajuanPerubahanData.php`
- `app/Http/Controllers/SelfService/PengajuanController.php`
- `app/Http/Controllers/Kepegawaian/ApprovalPengajuanController.php`
- `resources/js/pages/self-service/pengajuan/`
- `resources/js/pages/kepegawaian/pengajuan/`

---

## Ringkasan

| Fase | Item | Status Awal |
|---|---|---|
| 1 — Fondasi Stabil | 1.1 Pagination Monitoring, 1.2 N+1 Dashboard, 1.3 IAM Cache | Pending |
| 2 — Kepatuhan & Kualitas | 2.1 Audit Trail, 2.2 FormRequest, 2.3 Dedup Query API | Pending |
| 3 — UX Harian | 3.1 Foto Pegawai, 3.2 Dashboard Skeleton, 3.3 Filter Monitoring, 3.4 Foto Self-Service | Pending |
| 4 — Fitur Operasional | 4.1 Export Monitoring, 4.2 Chart Dashboard, 4.3 Notifikasi | Pending |
| 5 — Fitur Lanjutan | 5.1 Test Coverage, 5.2 Self-Service Approval Workflow | Pending |

**Total: 14 item improvement** dalam 5 fase milestone.

---

## Catatan Implementasi

- Setiap item dikerjakan dengan **TDD (RED → GREEN → REFACTOR)**
- Setiap item memiliki **sesi brainstorming + writing-plans tersendiri** sebelum implementasi
- Item 5.2 (Self-Service Approval Workflow) **wajib** melalui sesi brainstorming terpisah karena kompleksitasnya
- Urutan dalam satu fase **fleksibel** jika ada blocker atau kebutuhan mendesak
