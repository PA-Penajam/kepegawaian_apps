# Design Spec: Pegawai sebagai User (Authentication Refactor)

**Tanggal:** 2026-03-16
**Status:** Reviewed

## Ringkasan

Mengubah arsitektur autentikasi dari model `User` terpisah menjadi **model `Pegawai` sebagai Authenticatable**. Aplikasi ini adalah Master Data Pegawai yang digunakan lintas aplikasi, sehingga pegawai langsung menjadi aktor utama (login, role, permission).

## Keputusan Desain

| Keputusan | Pilihan |
|-----------|---------|
| Siapa yang login | Pegawai (bukan User) |
| Login credential | NIP + Password |
| Relasi role | Many-to-many (pegawai ↔ ref_roles via pivot) |
| 1 pegawai : N role | Ya |
| Assign role | Checklist pegawai di halaman Edit Role |
| Menu Pengguna | Dihapus |
| Set password pegawai | Di halaman Edit Pegawai |
| Tabel users | Dihapus setelah migrasi data |
| Strategi implementasi | Sekaligus (bukan bertahap) |
| Pegawai existing tanpa akun | Tampil di checklist role, password di-set nanti |
| Pegawai dengan NIP null | Tidak bisa login (NIP wajib untuk login) |
| 2FA | Data dimigrasikan dari users ke pegawai, fitur tetap aktif |
| Notifikasi | Via email pegawai (field `email` di tabel pegawai) |

## Arsitektur

```
pegawai (Authenticatable)
├── nip (login credential, unique, required untuk login)
├── password (bcrypt hash, nullable — null = belum bisa login)
├── remember_token
├── two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at
├── nama_lengkap, tempat_lahir, ... (data kepegawaian)
│
├── roles(): BelongsToMany → ref_roles
│     via tabel pivot: pegawai_role
│         ├── pegawai_id (FK)
│         ├── ref_role_id (FK)
│         └── unique(pegawai_id, ref_role_id)
│
└── permissions (didapat via roles → ref_role_permission → ref_permissions)
```

## Perubahan Database

### 1. Tambah kolom di tabel `pegawai`

```sql
ALTER TABLE pegawai
  ADD COLUMN password VARCHAR(255) NULL,
  ADD COLUMN remember_token VARCHAR(100) NULL,
  ADD COLUMN two_factor_secret TEXT NULL,
  ADD COLUMN two_factor_recovery_codes TEXT NULL,
  ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL;
```

### 2. Buat tabel pivot `pegawai_role`

```sql
CREATE TABLE pegawai_role (
  pegawai_id CHAR(26) NOT NULL,
  ref_role_id CHAR(26) NOT NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (pegawai_id, ref_role_id),
  FOREIGN KEY (pegawai_id) REFERENCES pegawai(id) ON DELETE CASCADE,
  FOREIGN KEY (ref_role_id) REFERENCES ref_roles(id) ON DELETE CASCADE
);
```

### 3. Migrasi data dari `users` ke `pegawai`

- Untuk setiap user yang punya `pegawai_id`:
  - Copy `password` → `pegawai.password`
  - Copy `two_factor_secret` → `pegawai.two_factor_secret`
  - Copy `two_factor_recovery_codes` → `pegawai.two_factor_recovery_codes`
  - Copy `two_factor_confirmed_at` → `pegawai.two_factor_confirmed_at`
  - Insert `(pegawai_id, ref_role_id)` ke `pegawai_role`
- Untuk user tanpa `pegawai_id`:
  - Buat record pegawai baru dengan data minimal:
    - `nama_lengkap` = `user.name`
    - `tempat_lahir` = 'Tidak Diketahui'
    - `tanggal_lahir` = '1970-01-01'
    - `jenis_kelamin` = 'Laki-Laki'
    - `agama` = 'Islam'
    - `status_perkawinan` = 'Belum_Kawin'
    - `status_kepegawaian` = 'PNS'
    - `status_pegawai` = 'Aktif'
    - `tanggal_masuk` = now()
    - `email` = `user.email`
    - `nip` = generate placeholder (misal: `TEMP-{user.id}`)
  - Copy password, 2FA data, dan role ke pegawai baru

### 4. Update tabel `sessions`

```sql
-- Ubah sessions.user_id dari bigint ke varchar agar support ULID
ALTER TABLE sessions MODIFY COLUMN user_id VARCHAR(26) NULL;
-- Hapus foreign key ke users jika ada, tambah index
```

### 5. Update tabel `password_reset_tokens`

```sql
-- Ganti kolom email menjadi nip untuk reset password via NIP
-- Atau tetap pakai email karena reset password dikirim ke email
-- Keputusan: tetap pakai email (pegawai punya field email)
```

### 6. Hapus tabel `users`

```sql
DROP TABLE users;
```

**Catatan:** Migration harus reversible — method `down()` harus bisa recreate tabel users dan restore data.

## Perubahan Model

### `Pegawai` (diubah)

```php
class Pegawai extends Authenticatable  // sebelumnya: extends Model
{
    use HasFactory, HasUlids, SoftDeletes, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'pegawai';

    protected $fillable = [
        ...existing,
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    // Tambah cast
    protected function casts(): array
    {
        return [
            ...existing,
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // Relasi baru (menggantikan user(): HasOne)
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RefRole::class, 'pegawai_role', 'pegawai_id', 'ref_role_id');
    }

    // Method permission — cek SEMUA roles (multi-role support)
    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('nama', $permission);
        })->exists();
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($permissions) {
            $q->whereIn('nama', $permissions);
        })->exists();
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('nama', 'Admin')->exists();
    }

    // Untuk Notifiable — notifikasi dikirim ke email pegawai
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }
}
```

### `RefRole` (diubah)

```php
// Ganti relasi users() HasMany → pegawai() BelongsToMany
public function pegawai(): BelongsToMany
{
    return $this->belongsToMany(Pegawai::class, 'pegawai_role', 'ref_role_id', 'pegawai_id');
}
```

### `User` (dihapus)

File `app/Models/User.php` dihapus setelah migrasi.

## Perubahan Authentication

### `config/auth.php`

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Pegawai::class,
    ],
],

'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
    ],
],
```

Catatan: Hardcode model (tidak pakai `env()`), karena ini keputusan arsitektural.

### `config/fortify.php`

```php
'username' => 'nip',
```

### Fortify Service Provider

Verifikasi bahwa Fortify menggunakan `nip` untuk lookup user saat login:
```php
Fortify::authenticateUsing(function ($request) {
    $pegawai = Pegawai::where('nip', $request->nip)->first();

    if ($pegawai && Hash::check($request->password, $pegawai->password)) {
        return $pegawai;
    }
});
```

### Login Form (frontend)

- Field `email` → `nip` (label: "NIP", type: text)
- Placeholder: "Masukkan NIP"
- Validasi: NIP tidak boleh kosong

### Perilaku login untuk edge cases

| Kondisi | Behavior |
|---------|----------|
| NIP valid, password benar | Login sukses |
| NIP valid, password null (belum di-set) | Login gagal: "NIP atau password salah" |
| NIP null (pegawai tanpa NIP) | Tidak bisa login — admin harus set NIP dulu |
| NIP valid, password salah | Login gagal: "NIP atau password salah" |

## Perubahan Middleware

| Middleware | Perubahan |
|-----------|-----------|
| `EnsureRole` | Update: cek `$user->roles()->where('nama', ...)` (multi-role) |
| `EnsurePermission` | Tetap — `hasAnyPermission()` sudah multi-role aware |
| `EnsurePegawaiLinked` | **Dihapus** (yang login sudah pegawai) |
| `HandleInertiaRequests` | Update shared data (lihat field mapping di bawah) |

### Field Mapping: Inertia Shared Data

```php
// HandleInertiaRequests — shared auth.user
'auth' => [
    'user' => $pegawai ? [
        'id' => $pegawai->id,           // ULID (string)
        'nama_lengkap' => $pegawai->nama_lengkap,
        'nip' => $pegawai->nip,
        'email' => $pegawai->email,
        'foto' => $pegawai->foto,        // avatar equivalent
        // roles & permissions untuk frontend
        'roles' => $pegawai->roles->pluck('nama'),
        'permissions' => $pegawai->roles->flatMap->permissions->pluck('nama')->unique(),
    ] : null,
],
```

### TypeScript Type Update

```typescript
// resources/js/types/auth.ts
interface User {
    id: string;              // ULID, bukan number
    nama_lengkap: string;    // bukan name
    nip: string;
    email: string | null;
    foto: string | null;     // bukan avatar
    roles: string[];
    permissions: string[];
}
```

## Perubahan Controller & Frontend

### Dihapus:
- `UserController` + routes + views (`pengguna/*`)
- `StoreUserRequest`, `UpdateUserRequest`
- `UserPolicy`
- `EnsurePegawaiLinked` middleware
- Menu "Pengguna" dari sidebar

### Diubah:

**`RefRoleController@edit`** — tambah data pegawai untuk checklist:
```php
return Inertia::render('referensi/roles/edit', [
    'role' => $role,
    'pegawaiList' => Pegawai::query()
        ->select('id', 'nama_lengkap', 'nip')
        ->when($request->search, fn($q, $s) => $q->where('nama_lengkap', 'like', "%$s%")->orWhere('nip', 'like', "%$s%"))
        ->orderBy('nama_lengkap')
        ->paginate(15),
    'assignedPegawaiIds' => $role->pegawai()->pluck('pegawai.id'),
]);
```

**`RefRoleController@update`** — sync pegawai:
```php
$role->update($validated);
$role->pegawai()->sync($request->input('pegawai_ids', []));
```

**`RefRoleController@destroy`** — update validasi:
```php
// Sebelum: $role->users()->exists()
// Sesudah:
if ($role->pegawai()->exists()) {
    return back()->with('error', 'Role masih memiliki pegawai yang di-assign.');
}
```

**`PegawaiController@update`** — tambah handling password:
```php
if ($request->filled('password')) {
    $pegawai->update(['password' => bcrypt($request->validated('password'))]);
}
```

**Semua reference `auth()->user()` property:**
| Sebelum | Sesudah |
|---------|---------|
| `->name` | `->nama_lengkap` |
| `->id` (int) | `->id` (string/ULID) |
| `->avatar` | `->foto` |

### Edit Role — UI Assign Pegawai:

```
Edit Role: Operator
├── Nama Role (input)
├── Keterangan (textarea)
└── Assign Pegawai
    ├── Search bar (cari nama/NIP)
    ├── Checklist pegawai (semua, termasuk tanpa password)
    │   ├── 15 pegawai per halaman
    │   ├── Sort by nama_lengkap
    │   └── Tampilkan: ☐ Nama Lengkap (NIP: xxx)
    ├── Pagination
    └── Simpan → sync pivot pegawai_role
```

### Edit Pegawai — Section Akun Login:

```
Edit Pegawai
├── ... (data existing) ...
└── Section "Akun Login"
    ├── Password Baru (opsional, kosongkan jika tidak ubah)
    ├── Konfirmasi Password
    └── Info role (readonly, menampilkan role yang di-assign dari pivot)
```

## Perubahan Test

### Factory Pegawai (update)

```php
// database/factories/PegawaiFactory.php — tambah:
'password' => bcrypt('password'),
'nip' => fn() => fake()->unique()->numerify('##################'),
```

### Test yang diupdate:
- Semua `User::factory()` → `Pegawai::factory()`
- Semua assertion `->name` → `->nama_lengkap`
- Login test: gunakan `nip` bukan `email`

### Test baru:
- Login via NIP + password
- Login gagal: NIP null, password null
- Assign pegawai ke role via edit role
- Unassign pegawai dari role
- Multi-role permission check
- 2FA flow dengan model Pegawai

### Test yang dihapus:
- Semua test terkait `UserController`
- Test `RoleMiddlewareTest` yang reference User model

## Risiko & Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Migrasi data gagal | Migration reversible (`down()` recreate users table) |
| User tanpa pegawai_id | Auto-create pegawai dengan data placeholder |
| `auth()->user()` property mismatch | Cek semua file: grep `auth()->user()`, `$request->user()` |
| Test gagal | Update semua factory & assertion |
| 2FA data hilang | Explicit migration 2FA columns dari users ke pegawai |
| Sessions invalid setelah migrasi | Sessions table user_id diubah ke varchar(26) |
| NIP null tidak bisa login | Dokumentasikan: admin harus set NIP dulu |
| Fortify tidak kompatibel ULID | Custom `authenticateUsing()` di FortifyServiceProvider |
| Password reset tokens | Tetap pakai email (field email ada di pegawai) |

## Out of Scope

- Integrasi dengan aplikasi lain (mereka punya project sendiri)
- Register pegawai baru via login page (hanya admin yang buat)
- Forgot password via NIP (tetap pakai email, bisa ditambah nanti)
