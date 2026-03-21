# Desain IAM SSO Gateway — kepegawaian-apps

**Tanggal:** 2026-03-21
**Status:** Disetujui
**Author:** Diskusi bersama developer PA Penajam
**Revisi:** v1.1 — fix HMAC header, SSO redirect security, migration edge case, schema gaps

---

## 1. Latar Belakang

kepegawaian-apps adalah sistem master data kepegawaian Pengadilan Agama Penajam. Saat ini sudah berjalan banyak aplikasi di ekosistem PA Penajam (attendance-qr-system, dan lainnya), namun masing-masing mengelola autentikasi dan otorisasi sendiri-sendiri.

**Tujuan:** Menjadikan kepegawaian-apps sebagai **single source of truth** untuk identitas user dan hak akses (RBAC) di seluruh ekosistem aplikasi PA Penajam — berperan sebagai IAM Hub / SSO Gateway.

---

## 2. Keputusan Desain Utama

| Keputusan | Pilihan | Alasan |
|-----------|---------|--------|
| Mekanisme validasi | API-based Session Check (Option C) | Paling simpel, konsisten dengan HMAC yang sudah ada, cocok untuk intranet |
| Model otorisasi | Role + Permission (bukan hardcoded) | Flexible, dapat dikonfigurasi via UI tanpa ubah kode |
| Registrasi aplikasi | Manual oleh Super Admin | Developer & admin adalah orang yang sama, semua app bersifat internal lokal |
| Arsitektur tabel | Modul IAM terpisah (Pendekatan 2) | Bersih, separation of concerns, kepegawaian-apps jadi "first app" di sistemnya sendiri |
| HMAC secret | Per-aplikasi (dari `iam_applications.api_secret`) | Setiap app punya secret sendiri, rotasi tidak mempengaruhi app lain |
| SSO token transfer | One-time code exchange (bukan token di URL) | Token tidak pernah masuk browser history atau server log |

---

## 3. Arsitektur

### Gambaran Sistem

```
┌────────────────────────────────────────────────┐
│              KEPEGAWAIAN-APPS                   │
│           (IAM Hub / SSO Gateway)               │
│                                                  │
│  ┌────────────┐  ┌────────────┐  ┌───────────┐  │
│  │ Auth/Login │  │ IAM Module │  │Kepegawaian│  │
│  │ (Fortify)  │  │  (★ Baru)  │  │ (Existing)│  │
│  └────────────┘  └────────────┘  └───────────┘  │
└──────────────────────┬─────────────────────────┘
                       │ API (HMAC + Sanctum)
         ┌─────────────┼─────────────┐
         ▼             ▼             ▼
  attendance-qr   app-B (e-surat)  app-C (dst)
```

### Alur Login SSO (Aman — One-Time Code Exchange)

```
1. User buka app-B → belum ada session → redirect ke:
   kepegawaian-apps/sso/login?app=attendance&redirect=https://attendance.local/sso/callback

2. User login di kepegawaian-apps (Fortify) → generate Sanctum token + SSO code
   SSO code: random 64-char string, disimpan di cache Redis/database, expire 60 detik, single-use

3. kepegawaian-apps redirect ke:
   https://attendance.local/sso/callback?code=abc123
   (BUKAN token — hanya one-time code pendek)

4. app-B server-side (bukan browser) POST ke:
   kepegawaian-apps /api/v1/iam/exchange-code
   Body: { code: "abc123" }
   Header: X-App-Key + X-Signature + X-Timestamp
   → Dapat Sanctum token

5. app-B simpan Sanctum token di session server-side (bukan localStorage)
   → user dianggap sudah login

6. Untuk setiap protected request, app-B kirim token ke /api/v1/iam/validate
   → cache hasil 60 detik

7. Logout terpusat:
   → kepegawaian-apps invalidate Sanctum token
   → app-B hapus session lokal
   → saat cache expired, semua app akan reject token
```

> **Mengapa one-time code?** Token di URL masuk ke browser history, server log, dan Referer header — sangat berisiko. Code pendek (60 detik, single-use) yang diexchange server-to-server tidak pernah terekspos ke browser history.

### Komponen Baru

| Komponen | Deskripsi |
|----------|-----------|
| IAM Module | UI manajemen aplikasi, role, permission via admin panel |
| API Validasi Session | `/api/v1/iam/validate` — cek token + ambil permissions |
| API Code Exchange | `/api/v1/iam/exchange-code` — tukar SSO code → Sanctum token |
| Application Registry | Daftar aplikasi terdaftar + per-app API Key management |
| VerifyIamSignature | Middleware HMAC baru yang resolve secret per-aplikasi dari DB |
| SSO Login Page | Halaman login terpusat: `/sso/login?app=&redirect=` |

### Yang Tidak Berubah

- Cara login user (Fortify tetap dipakai)
- Struktur database pegawai
- Seluruh modul kepegawaian yang sudah ada

### Yang Diubah / Dihapus

- `EnsureRole` middleware → diganti `VerifyIamPermission`
- `App\Enums\Role` → dihapus setelah kolom `users.role` di-drop
- `VerifyHmacSignature` lama → tetap ada untuk endpoint pegawai yang sudah ada (`/api/v1/pegawai/*`), endpoint IAM baru memakai `VerifyIamSignature`
- `config/kepegawaian.php` → tambah section `iam` atau buat `config/iam.php` baru

---

## 4. Database Schema

### Tabel IAM Baru (5 tabel)

#### `iam_applications`
```
id              ulid PK
nama            string
slug            string unique
url             string
deskripsi       text nullable
api_key         string unique          -- public identifier
api_secret_hash string                 -- bcrypt/argon2 hash dari api_secret
is_active       boolean default true
is_system       boolean default false  -- kepegawaian-apps: true (tidak bisa dihapus via UI)
timestamps
softDeletes
```

> `api_secret` asli hanya ditampilkan **sekali** saat generate/regenerate, lalu hanya hash yang disimpan.

#### `iam_roles`
```
id                  ulid PK
iam_application_id  FK → iam_applications (cascadeOnDelete)
nama                string
slug                string             -- format: nama-role (contoh: admin, operator)
keterangan          text nullable
is_system           boolean default false
timestamps
softDeletes

UNIQUE: [iam_application_id, slug]
```

#### `iam_permissions`
```
id                  ulid PK
iam_application_id  FK → iam_applications (cascadeOnDelete)
nama                string
slug                string             -- format: resource:action (contoh: absensi:create)
group               string nullable    -- untuk grouping di UI
keterangan          text nullable
timestamps
softDeletes                            -- konsisten dengan iam_roles

UNIQUE: [iam_application_id, slug]
```

#### `iam_role_permissions` (pivot)
```
id                  bigint PK auto-increment
iam_role_id         FK → iam_roles (cascadeOnDelete)
iam_permission_id   FK → iam_permissions (cascadeOnDelete)
timestamps

UNIQUE: [iam_role_id, iam_permission_id]
```

#### `iam_user_roles`
```
id              bigint PK auto-increment
user_id         FK → users (id bigint — bukan ULID, sesuai struktur users.id)
iam_role_id     FK → iam_roles (cascadeOnDelete)
assigned_at     timestamp
assigned_by     FK → users nullable    -- audit trail: siapa yang assign

timestamps

UNIQUE: [user_id, iam_role_id]
```

> **Catatan:** `users.id` bertipe `bigint auto-increment` (bukan ULID). FK ke `users` menggunakan `foreignId()`, bukan `foreignUlid()`.

#### `iam_sso_codes` (table sementara untuk code exchange)
```
id          bigint PK auto-increment
code        string unique              -- 64-char random string
user_id     FK → users
app_slug    string                     -- aplikasi tujuan
used_at     timestamp nullable         -- null = belum dipakai
expires_at  timestamp                  -- 60 detik dari created_at
created_at  timestamp
```

### Migrasi dari Skema Lama

| Tabel Lama | Tindakan |
|------------|----------|
| `ref_roles` | Dimigrasikan → `iam_roles` (dengan `iam_application_id` kepegawaian-apps) |
| `ref_permissions` | Dimigrasikan → `iam_permissions` |
| `ref_role_permission` | Dimigrasikan → `iam_role_permissions` |
| `users.role` (string) | Data dimigrasikan → `iam_user_roles`, kolom di-drop |

**Urutan migrasi (aman, tidak ada data loss):**

1. Buat 6 tabel IAM baru (termasuk `iam_sso_codes`)
2. Seed kepegawaian-apps sebagai aplikasi pertama (`is_system: true`, slug: `kepegawaian`)
3. Migrasi `ref_roles` → `iam_roles`:
   - Slug di-generate otomatis: `Str::slug($nama)` (contoh: "Admin" → "admin")
4. Migrasi `ref_permissions` → `iam_permissions` (slug dari `nama`)
5. Migrasi `ref_role_permission` → `iam_role_permissions`
6. Migrasi `users.role` → `iam_user_roles`:
   - Untuk setiap user, baca `users.role` (string: 'admin', 'operator', 'viewer')
   - Cari `iam_roles` yang slug-nya cocok untuk aplikasi kepegawaian
   - Jika cocok → insert ke `iam_user_roles`
   - **Jika tidak cocok** (misal role string tidak ada di ref_roles) → assign ke role `viewer` sebagai fallback, catat di log migration
7. Drop kolom `users.role`
8. Drop tabel `ref_role_permission`, `ref_permissions`, `ref_roles`
9. Hapus `App\Enums\Role` dan method `isAdmin()`, `isOperator()`, `isViewer()` dari `User` model
10. Update semua route yang pakai `EnsureRole` → pakai `VerifyIamPermission`

---

## 5. API Design

### HMAC Signature (Diperbarui untuk IAM)

Endpoint IAM menggunakan middleware `VerifyIamSignature` (berbeda dari `VerifyHmacSignature` lama):

**Header yang diperlukan:**
```
Authorization: Bearer {sanctum_token}     -- untuk endpoint yang butuh auth user
X-App-Key: {api_key}                      -- identifikasi aplikasi pemanggil
X-Signature: {hmac_sha256}                -- nama header konsisten dengan middleware lama
X-Timestamp: {unix_timestamp}
```

**Cara HMAC dihitung (per-aplikasi):**
```
secret = iam_applications.api_secret (di sisi app klien, belum di-hash)
payload = METHOD:PATH:SORTED_QUERY:TIMESTAMP
signature = HMAC-SHA256(payload, secret)
```

> Middleware `VerifyIamSignature` akan lookup `api_key` dari `X-App-Key` → ambil `api_secret_hash` dari DB → verify signature menggunakan `hash_equals`. Berbeda dari `VerifyHmacSignature` lama yang pakai satu shared secret dari config.

### Endpoint IAM

#### `GET /api/v1/iam/validate`
Validasi token + ambil info user & permissions untuk aplikasi pemanggil.

**Headers:** `Authorization: Bearer {token}`, `X-App-Key`, `X-Signature`, `X-Timestamp`

**Response 200:**
```json
{
  "user": {
    "id": "01J...",
    "name": "Budi Santoso",
    "email": "budi@pa-penajam.go.id",
    "nip": "199107132020121003"
  },
  "roles": ["operator"],
  "permissions": ["absensi:create", "rekap:read"],
  "token_expires_at": 1774065412
}
```

**Response jika user tidak punya role di aplikasi pemanggil:**
```json
{
  "user": { "id": "...", "name": "...", ... },
  "roles": [],
  "permissions": [],
  "token_expires_at": 1774065412
}
```
> Mengembalikan 200 dengan array kosong (bukan 403) — keputusan akses ada di sisi aplikasi klien.

**Response 401** jika token invalid/expired.

**Sanksi token expiry:** Sanctum token dibuat dengan TTL **8 jam** (satu hari kerja). Dapat dikonfigurasi per-aplikasi di masa depan.

#### `GET /api/v1/iam/check?permission={slug}`
Cek satu permission. Aplikasi klien disarankan menggunakan `validate` + local check untuk menghindari round-trip berulang. Endpoint ini cocok untuk middleware sederhana non-Laravel.

**Response 200:**
```json
{ "allowed": true, "permission": "absensi:create" }
```
> Endpoint ini juga memanfaatkan cache yang sama dengan `validate` (cache key: `iam_{token_hash}_{app_key}`).

#### `POST /api/v1/iam/exchange-code`
Tukar SSO one-time code → Sanctum token. Dipanggil server-to-server oleh aplikasi klien.

**Headers:** `X-App-Key`, `X-Signature`, `X-Timestamp` (tanpa Authorization)

**Request Body:**
```json
{ "code": "abc123...64chars" }
```

**Response 200:**
```json
{
  "token": "sanctum_token_here",
  "token_type": "Bearer",
  "expires_at": 1774065412
}
```

**Response 400** jika code tidak valid, sudah dipakai, atau expired (>60 detik).

#### `POST /api/v1/iam/logout`
Logout terpusat — invalidate Sanctum token. Saat api_key diregenerasi, semua token aktif yang dibuat via app tersebut TIDAK otomatis di-invalidate (token tetap valid sampai expired). Untuk revoke manual, gunakan endpoint logout per-token.

**Response 200:**
```json
{ "message": "Token invalidated" }
```

#### `GET /sso/login?app={slug}&redirect={url}` (Web Route)
Halaman login SSO terpusat. Setelah login berhasil:
- Generate SSO code (simpan di `iam_sso_codes`, expire 60 detik)
- Redirect ke `{redirect}?code={sso_code}`

#### `GET /sso/callback?code={code}` (di aplikasi klien)
Bukan endpoint kepegawaian-apps. Ini adalah route yang harus diimplementasi oleh masing-masing aplikasi klien. Route ini akan:
1. Ambil `?code` dari URL
2. Kirim `POST /api/v1/iam/exchange-code` ke kepegawaian-apps
3. Simpan Sanctum token di session server-side
4. Redirect ke halaman utama

### Endpoint Admin Web (Super Admin only)

```
GET  /iam/aplikasi                              → Daftar aplikasi
POST /iam/aplikasi                              → Daftarkan aplikasi baru
GET  /iam/aplikasi/{id}                         → Detail + kelola role & permission + API credentials
POST /iam/aplikasi/{id}/regenerate-key          → Regenerate API key & secret
POST /iam/aplikasi/{id}/roles                   → Tambah role
PUT  /iam/aplikasi/{id}/roles/{roleId}          → Edit role + permissions
DEL  /iam/aplikasi/{id}/roles/{roleId}          → Hapus role
GET  /iam/aplikasi/{id}/permissions             → Daftar permissions
POST /iam/aplikasi/{id}/permissions             → Tambah permission
PUT  /iam/aplikasi/{id}/permissions/{permId}    → Edit permission
DEL  /iam/aplikasi/{id}/permissions/{permId}    → Hapus permission
GET  /iam/users                                 → List semua user + akses summary
GET  /iam/users/{id}/akses                      → Kelola akses user di semua aplikasi
POST /iam/users/{id}/akses                      → Assign role ke user
DEL  /iam/users/{id}/akses/{roleId}             → Revoke role dari user
```

### Contoh Middleware di Aplikasi Klien (Laravel)

```php
// app/Http/Middleware/VerifyIamSession.php
public function handle(Request $request, string $permission = null): Response
{
    $token = $request->bearerToken();
    $cacheKey = 'iam_' . hash('sha256', $token . config('iam.api_key'));

    $data = Cache::remember($cacheKey, 60, function () use ($token) {
        return Http::withToken($token)
            ->withHeaders($this->buildIamHeaders('GET', '/api/v1/iam/validate'))
            ->get(config('iam.url') . '/api/v1/iam/validate')
            ->json();
    });

    if (!isset($data['user'])) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    if ($permission && !in_array($permission, $data['permissions'] ?? [])) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $request->merge(['iam_user' => $data['user'], 'iam_permissions' => $data['permissions']]);
    return $next($request);
}
```

---

## 6. UI Admin IAM

### Struktur Halaman

```
/iam/aplikasi                     → Daftar semua aplikasi terdaftar
/iam/aplikasi/{id}                → Detail: info + API credentials + tab Role/Permission/User
/iam/users                        → List user + shortcut ke halaman akses
/iam/users/{id}/akses             → Kelola akses user di semua aplikasi sekaligus
```

### Navigasi Sidebar (Grup Baru)

```
IAM
  🏢 Aplikasi
  🔑 Akses User
```

### Fitur Utama UI

**Halaman Daftar Aplikasi:**
- Tabel aplikasi, badge jumlah role, status aktif/nonaktif, tombol "Daftarkan Aplikasi"
- Badge khusus "Sistem" untuk kepegawaian-apps (tidak bisa dihapus)

**Halaman Detail Aplikasi (tabs: Role | Permission | User Terassign):**
- Info aplikasi + API Key masked (`att_live_k3y_••••`) → reveal on click
- Tombol "Regenerate" dengan konfirmasi modal
- Tab Role: CRUD role + checklist permission yang terassign
- Tab Permission: CRUD permission + field `group` untuk kategorisasi
- Tab User: daftar user yang memiliki role di aplikasi ini

**Halaman Akses User:**
- Satu layar lihat semua akses user di semua aplikasi
- Dropdown ganti role per aplikasi (tampil permission turunan otomatis)
- Tombol "+ Berikan Akses ke Aplikasi Lain"
- Tampilkan `assigned_by` dan `assigned_at` sebagai tooltip audit trail

---

## 7. Security

| Layer | Mekanisme | Keterangan |
|-------|-----------|------------|
| 1 | Sanctum Token | Identifikasi user yang login, TTL 8 jam |
| 2 | X-App-Key | Identifikasi aplikasi pemanggil, lookup ke `iam_applications` |
| 3 | X-Signature (HMAC-SHA256) | Anti-tampering + anti-replay (5-menit window), secret per-aplikasi |
| 4 | One-time SSO Code | Token tidak pernah masuk URL browser, code expire 60 detik single-use |

**API Secret:**
- Hanya ditampilkan sekali saat generate/regenerate
- Disimpan sebagai hash (`api_secret_hash`) di database
- Rotasi (regenerate) tidak mempengaruhi Sanctum token yang sudah aktif

**Proteksi aplikasi sistem:**
- `is_system: true` pada kepegawaian-apps mencegah edit/delete via UI
- Role dengan `is_system: true` tidak bisa dihapus

**Audit trail:**
- `assigned_by` di `iam_user_roles` — siapa yang memberikan role

---

## 8. Konfigurasi

File `config/iam.php` (baru):
```php
return [
    'token_ttl_hours' => env('IAM_TOKEN_TTL_HOURS', 8),
    'sso_code_ttl_seconds' => env('IAM_SSO_CODE_TTL', 60),
];
```

Setiap aplikasi klien memiliki `config/iam.php` sendiri:
```php
return [
    'url' => env('IAM_URL', 'http://kepegawaian.local'),
    'api_key' => env('IAM_API_KEY'),
    'api_secret' => env('IAM_API_SECRET'),
];
```

---

## 9. Scope yang Tidak Termasuk (Out of Scope)

- OAuth2 / OIDC flow lengkap
- Self-registration aplikasi oleh pihak ketiga
- ABAC (Attribute-Based Access Control)
- Two-factor authentication (sudah ada via Fortify, tetap dipakai)
- Notifikasi email saat role diubah
- Revoke semua session saat API key di-regenerate (dapat ditambahkan sebagai fitur lanjutan)

---

## 10. Urutan Implementasi

1. **Buat `config/iam.php`** — konfigurasi IAM
2. **Migrasi database** — buat 6 tabel IAM, jalankan migrasi data, drop tabel lama
3. **Model & relasi** — `IamApplication`, `IamRole`, `IamPermission`, `IamUserRole`, `IamSsoCode`
4. **Middleware `VerifyIamSignature`** — HMAC per-aplikasi (lookup dari DB)
5. **Middleware `VerifyIamPermission`** — gantikan `EnsureRole`, pakai `iam_user_roles`
6. **Update semua route** yang pakai `EnsureRole` → `VerifyIamPermission`
7. **Hapus** `App\Enums\Role` dan method terkait dari `User` model
8. **Seed data** — kepegawaian-apps sebagai first app + roles & permissions default
9. **API endpoint** — `validate`, `check`, `exchange-code`, `logout`
10. **SSO login page** — `/sso/login?app=&redirect=` + generate code
11. **UI halaman aplikasi** — CRUD aplikasi + API key management
12. **UI halaman role & permission** — per aplikasi
13. **UI halaman akses user** — assign/revoke role
14. **Testing** — uji end-to-end dengan attendance-qr-system
