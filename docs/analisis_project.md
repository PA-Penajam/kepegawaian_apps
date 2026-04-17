# Analisis Project: Kepegawaian Apps

## Ringkasan Eksekutif

**Kepegawaian Apps** adalah aplikasi manajemen kepegawaian berbasis web yang dibangun dengan **Laravel 12 + Inertia.js + React**. Aplikasi ini berfungsi sebagai **pusat data kepegawaian** yang terintegrasi dengan sistem lain (seperti `attendance-qr-system`) melalui API berbasis IAM (Identity & Access Management).

---

## 🏗️ Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | React 19, Inertia.js v2, TypeScript |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Auth | Laravel Fortify (2FA, email verification) |
| API Token | Laravel Sanctum |
| Routing (FE) | Laravel Wayfinder |
| Testing | Pest v4 (63 file test) |
| ORM | Eloquent dengan ULID, SoftDeletes |

---

## 📦 Modul & Fitur yang Ada

### 1. 🔐 Autentikasi & Keamanan (`auth/`)
- Login/Logout via Laravel Fortify
- Reset Password
- Email Verification
- **Two-Factor Authentication (2FA/TOTP)**
- Confirm Password
- SSO (Single Sign-On) sebagai **Identity Provider**

### 2. 📊 Dashboard
- Total pegawai aktif
- Pegawai baru bulan ini
- Alert KGB (Kenaikan Gaji Berkala) segera (≤2 bulan)
- Alert KP (Kenaikan Pangkat) yang sudah eligible
- **Distribusi Golongan** (I, II, III, IV) dengan progress bar
- **Top Unit Kerja** berdasarkan jumlah pegawai
- **Top Jabatan** berdasarkan jumlah pegawai
- **Distribusi Pendidikan** terakhir
- **Distribusi Jenis Kelamin**

### 3. 👤 Kepegawaian - Master Data Pegawai (`kepegawaian/pegawai/`)

#### a. CRUD Pegawai
- List pegawai dengan **search, filter, sort, pagination** (15 per halaman)
- Filter: unit kerja, status pegawai, jabatan, golongan
- Sort: nama, NIP, pangkat, jabatan
- Tambah/Edit/Hapus pegawai (soft delete)
- Data lengkap: biodata, NIP, tempat/tanggal lahir, jenis kelamin, agama, status perkawinan, golongan darah, alamat, telepon, email

#### b. Tab Detail Pegawai (`show.tsx`)
| Tab | Isi |
|---|---|
| Biodata | Data pribadi lengkap + identitas kepegawaian |
| Riwayat Pangkat | Daftar pangkat dengan TMT |
| Riwayat Jabatan | Riwayat jabatan dan unit kerja |
| Riwayat Pendidikan | Jenjang dan institusi pendidikan |
| Riwayat Diklat | Pelatihan/pendidikan non-formal |
| Keluarga | Data suami/istri/anak |
| Penghargaan | Daftar penghargaan yang diterima |
| Hukuman Disiplin | Catatan hukuman disiplin |
| Dokumen | Upload/kelola dokumen kepegawaian |

### 4. 📈 Monitoring Kepegawaian (`kepegawaian/monitoring/`)

#### a. Monitoring KGB (Kenaikan Gaji Berkala)
- Daftar pegawai yang KGB-nya akan jatuh tempo
- Status: **Sudah Jatuh Tempo / Segera (≤60 hari) / Mendekati (≤90 hari) / Aman**
- Kalkulasi otomatis dari TMT pangkat aktif + 2 tahun

#### b. Monitoring Kenaikan Pangkat
- Daftar pegawai yang mendekati/sudah eligible kenaikan pangkat
- Status: **Sudah Eligible / Mendekati Eligible / Belum Eligible**
- Periode usul: **April** atau **Oktober**
- Menampilkan batas usul dan sisa hari
- Kalkulasi otomatis dari TMT pangkat aktif + 4 tahun

### 5. 🔑 IAM (Identity & Access Management) (`iam/`)

#### a. Manajemen Aplikasi
- CRUD aplikasi yang terdaftar dalam IAM
- Generate & Regenerate API Key + API Secret (hashed)
- Tampilkan API key dengan masking (4 char depan + 8 char belakang)
- Lindungi aplikasi sistem (`is_system = true`) dari penghapusan/edit

#### b. Manajemen Role & Permission per Aplikasi
- Tambah/Edit/Hapus Role per aplikasi
- Tambah/Edit/Hapus Permission per Role
- Struktur: Aplikasi → Role → Permission (berjenjang)

#### c. Manajemen User Akses
- Lihat semua pegawai beserta role-nya
- Assign/Revoke role dari setiap pegawai
- Multi-role per pegawai per aplikasi

### 6. 🌐 SSO Provider (`/sso/`)
- Login endpoint untuk aplikasi eksternal
- Redirect flow: Aplikasi → SSO → Login → Callback → Redirect dengan code
- Validasi host redirect (anti open-redirect)
- Code TTL 60 detik (configurable)

### 7. 🔌 REST API (untuk integrasi eksternal)

#### Pegawai API (`/api/v1/pegawai/`)
- `GET /api/v1/pegawai/{nip}` — Lookup single pegawai by NIP 18 digit
- `GET /api/v1/pegawai` — Batch lookup (maks 50 NIP) atau search by nama

#### IAM API (`/api/v1/iam/`)
- `GET /api/v1/iam/validate` — Validasi token + roles + permissions user
- `GET /api/v1/iam/check` — Cek satu permission tertentu
- `POST /api/v1/iam/logout` — Invalidate token
- `POST /api/v1/iam/exchange-code` — Tukar SSO code untuk Bearer token

### 8. 📋 Data Referensi (`referensi/`)
- **Jenis Dokumen** — CRUD master jenis dokumen
- **Status Kepegawaian** — CRUD master status kepegawaian
- **Status Pegawai** — CRUD master status pegawai
- **Roles** — CRUD master role kepegawaian

### 9. 👁️ Self-Service Pegawai (`self-service/`)
- Halaman untuk pegawai melihat data dirinya sendiri
- Info KGB berikutnya
- Info status KP (kenaikan pangkat)
- Tab detail lengkap (sama seperti admin)

### 10. ⚙️ Settings (`settings/`)
- **Profil** — Edit nama dan email
- **Keamanan** — Ganti password + setup 2FA
- **Appearance** — Light/Dark mode

---

## 🛡️ Lapisan Keamanan API

```
4-Layer Security untuk API:
1. HTTPS (transport)
2. Sanctum Token (auth:sanctum)
3. HMAC-SHA256 Signature (X-Signature header + X-Timestamp)
   - Anti-replay attack (window 5 menit)
   - Payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
4. Rate Limiting (throttle middleware)
   - Pegawai API: 60 req/menit
   - IAM API: 120 req/menit
   - Exchange Code: 10 req/menit (sensitif)
```

---

## 🚨 Area yang Perlu Diimprove

### 🔴 Prioritas Tinggi

#### 1. **Performa Dashboard — N+1 Query di `DashboardStatService`**
```php
// ❌ Masalah: Load semua pegawai hanya untuk hitung distribusi jabatan
public function getDistribusiJabatan(): Collection
{
    return $this->pegawaiAktifQuery()
        ->with('jabatan')
        ->get()         // ← Load ALL pegawai ke memory
        ->groupBy(...)  // ← Proses di PHP bukan database
}
```
- `getDistribusiGolongan()`, `getDistribusiJabatan()`, `getDistribusiPendidikan()` semuanya load seluruh tabel pegawai ke memory
- **Solusi**: Gunakan `selectRaw` + `groupBy` langsung di query SQL, atau tambahkan **caching** hasil dashboard (misal TTL 5 menit dengan `Cache::remember`)

#### 2. **Tidak Ada Pagination pada `KgbMonitoringService` & `KenaikanPangkatMonitoringService`**
- Kedua service ini load **seluruh tabel pegawai** lalu filter di PHP (Collection)
- Sangat berbahaya jika data pegawai ribuan orang
- **Solusi**: Refactor filter ke level query (database), bukan Collection

#### 3. **Inline Validation di Controller — Tidak Konsisten**
```php
// AplikasiController::store() — Inline validation
$data = $request->validate([...]);

// Semua Kepegawaian — Sudah pakai FormRequest ✅
public function store(StorePegawaiRequest $request)
```
- `AplikasiController`, `UserAksesController` menggunakan inline validation — tidak konsisten dengan konvensi Kepegawaian yang sudah pakai FormRequest
- **Solusi**: Buat `StoreAplikasiRequest`, `UpdateAplikasiRequest`, `StoreUserAksesRequest`

### 🟡 Prioritas Sedang

#### 4. **Dashboard Tidak Ada Loading State / Skeleton**
- Data dashboard di-pass langsung (synchronous melalui Inertia props)
- Jika database lambat, halaman akan terasa berat
- **Solusi**: Gunakan **Inertia Deferred Props** (fitur Inertia v2) untuk data statistik berat

#### 5. **Duplikasi Query di `PegawaiApiController::search()`**
```php
// Dua query terpisah untuk data yang sama
$query = Pegawai::with(...)->...->get();
$total = Pegawai::when(...)->...->count(); // ← Query ulang!
```
- **Solusi**: Pakai `->paginate()` sehingga total otomatis dihitung dalam satu query

#### 6. **Halaman Monitoring tanpa Export**
- Tidak ada fitur export ke Excel/PDF untuk monitoring KGB dan KP
- **Saran**: Tambah export CSV/Excel menggunakan Laravel Excel atau Spatie

#### 7. **Self-Service: Tidak Ada Fitur Edit Data Mandiri**
- Pegawai hanya bisa *melihat* data dirinya, tidak bisa mengajukan perubahan (seperti update alamat, telepon, dsb.)
- **Saran**: Implementasikan fitur **pengajuan perubahan data** dengan approval workflow

#### 8. **Tidak Ada Audit Trail / Activity Log**
- Tidak ada pencatatan siapa yang mengubah data apa dan kapan
- Kritis untuk sistem kepegawaian pemerintahan
- **Solusi**: Integrasikan `spatie/laravel-activitylog`

#### 9. **Foto Pegawai Belum Ada Implementasi**
- Field `foto` ada di model/migration, tapi tidak ada UI untuk upload/tampil foto
- **Solusi**: Implementasikan upload foto dengan validasi (max size, mime type) dan storage di disk yang tepat

### 🟢 Prioritas Rendah (Nice to Have)

#### 10. **Tidak Ada Notifikasi Otomatis**
- KGB/KP yang jatuh tempo tidak memicu notifikasi (email/in-app)
- **Saran**: Buat scheduled job + notifikasi email untuk deadline KGB dan KP

#### 11. **Monitoring Filter Kurang Lengkap**
- Monitoring KGB/KP tidak bisa difilter per unit kerja, golongan, atau periode
- **Saran**: Tambah filter dan search pada halaman monitoring

#### 12. **Coverage Test untuk Monitoring Masih Minimal**
- Hanya ada 2 file test untuk monitoring (Happy path)
- Tidak ada test untuk edge case (data kosong, pegawai tanpa riwayat pangkat, dsb.)

#### 13. **Cache IAM Permission Tidak Di-invalidate Saat Data Berubah**
```php
// Cache selama 1 jam — tidak auto-invalidate saat role/permission diubah
Cache::remember("iam_app:{$appSlug}", 3600, ...)
```
- **Solusi**: Tambahkan cache invalidation pada `RoleController`, `PermissionController`, `UserAksesController` saat ada perubahan data

#### 14. **Tidak Ada Grafik / Chart Visual**
- Dashboard hanya menggunakan progress bar, tidak ada chart (pie chart, bar chart)
- **Saran**: Integrasikan `recharts` atau `chart.js` untuk visualisasi data yang lebih menarik

---

## 📊 Ringkasan Test Coverage

| Area | Jumlah File Test |
|---|---|
| Kepegawaian (CRUD Pegawai + Sub-resources) | 13 |
| IAM (SSO, Token, Roles, Applications) | 13 |
| Auth (Login, 2FA, Reset Password) | 6 |
| API Pegawai | 1 |
| Monitoring | 2 |
| Self-Service | 1 |
| Settings | 2 |
| Unit Test | 3+ |
| **Total** | **~63** |

> [!TIP]
> Test coverage sudah cukup baik untuk modul inti, terutama IAM dan Kepegawaian. Area yang paling kurang teruji adalah Monitoring dan API.

---

## 🗺️ Arsitektur Ringkas

```
kepegawaian-apps (IAM Provider)
├── Web App (Inertia/React)
│   ├── Dashboard (statistik + chart)
│   ├── Kepegawaian (data master pegawai)
│   ├── Monitoring (KGB + KP)
│   ├── Referensi (master data)
│   ├── IAM (aplikasi, role, permission, user akses)
│   ├── Self-Service (portal pegawai)
│   └── Settings (profil, 2FA)
│
├── REST API (untuk integrasi)
│   ├── /api/v1/pegawai → attendance-qr-system
│   └── /api/v1/iam → semua sistem terintegrasi
│
└── SSO Provider
    └── /sso/login → redirect dengan code → exchange token
```
