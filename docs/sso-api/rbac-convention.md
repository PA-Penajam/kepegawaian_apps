# Konvensi RBAC IAM Hub

**Status**: Berlaku per 2026-05-16
**Berlaku untuk**: Semua aplikasi client yang terdaftar di IAM Hub (`kepegawaian-apps`)

Dokumen ini menetapkan aturan baku penamaan & struktur Role-Based Access Control (RBAC) di IAM Hub. Setiap aplikasi baru wajib mengikuti konvensi ini agar konsisten dan tidak konflik dengan aplikasi lain.

---

## 1. Prinsip Dasar

IAM Hub menerapkan unique constraint `(iam_application_id, slug)` pada tabel `iam_permissions` dan `iam_roles`. Artinya:

- Permission/role dipisahkan **per aplikasi** secara otomatis di level database.
- App yang berbeda boleh punya slug yang sama (mis. `barang.manage` di app `persediaan` dan app `logistik` adalah dua row terpisah, tidak konflik).
- Endpoint `/v1/iam/validate` selalu mengembalikan permission yang sudah di-scope ke `application_id` dari Sanctum token.

**Konsekuensi**: prefix nama aplikasi pada slug **tidak diperlukan dan tidak boleh dipakai**.

---

## 2. Aturan Penamaan Slug

### 2.1 Format

```
{resource}.{action}              ← format default
{module}.{resource}.{action}     ← untuk modul kompleks dengan multiple sub-resource
```

**Wajib**:
- Lowercase
- Pemisah segment: titik (`.`)
- Pemisah kata dalam segment: tanda strip (`-`), bukan underscore atau spasi

**Dilarang**:
- Prefix nama aplikasi (`persediaan:barang.manage` ❌ → `barang.manage` ✅)
- Underscore (`barang_manage` ❌)
- camelCase / PascalCase (`barangManage` ❌)
- Karakter selain `[a-z0-9.-]`

### 2.2 Action Standar

| Action | Penggunaan |
|---|---|
| `view` | Lihat list/detail (read-only) |
| `create` | Buat record baru |
| `update` | Ubah record yang sudah ada |
| `delete` | Hapus record |
| `manage` | Umbrella: mencakup view + create + update + delete |
| `approve` | Setujui/tolak workflow item |
| `process` | Eksekusi workflow item yang sudah disetujui |
| `read` | Read-only untuk laporan / log (sinonim `view` untuk konteks reporting) |
| `submit` | Ajukan ke tahap berikutnya (workflow) |
| `verify` | Verifikasi dokumen/data |
| `cancel-own` | Batalkan milik sendiri |
| `cancel-any` | Batalkan milik siapapun (admin power) |
| `view-own` | Lihat milik sendiri |
| `view-team` | Lihat milik tim |
| `view-all` | Lihat semua (admin/auditor) |
| `audit` | Akses audit log/trail |

Bila ada kebutuhan action baru, tambahkan di tabel ini lewat PR dan jaga konsistensi.

### 2.3 Granularity

**Aturan**: pakai action umbrella (`manage`) hanya jika **semua role yang punya akses ke resource ini selalu butuh CRUD penuh**.

**Pecah** menjadi `view`/`create`/`update`/`delete` jika:
- Ada role read-only (auditor/viewer) yang butuh `view` saja
- Ada role yang boleh tambah tapi tidak boleh hapus
- Ada workflow yang memisah create-vs-modify

**Contoh keputusan nyata**:

```text
✅ pegawai.view + .create + .update + .delete    (granular — di-kepegawaian, ada role auditor)
✅ barang.manage                                  (umbrella — di persediaan, semua yang boleh akses pasti CRUD)
✅ permintaan.create + .approve + .process        (workflow — pegawai create, pimpinan approve, operator process)
```

### 2.4 Field `group`

`group` di tabel `iam_permissions` = segment pertama slug.

```
slug=barang.manage              → group=barang
slug=cuti.pengajuan.approve     → group=cuti
slug=kenaikan-pangkat.usulan.*  → group=kenaikan-pangkat-usulan (tetap kebab-case)
```

Konsekuensi: UI IAM Hub bisa otomatis grouping permission berdasarkan field ini.

---

## 3. Aturan Penamaan Role

### 3.1 Format

```
{role-slug}      ← lowercase, kebab-case, tanpa prefix app
```

### 3.2 Role Standar (rekomendasi reuse)

| Slug | Nama | Tujuan |
|---|---|---|
| `admin` | Admin | Akses penuh app (semua permission) |
| `operator` | Operator | Operasional harian, eksekusi workflow |
| `pimpinan` | Pimpinan | Atasan/penyetuju workflow |
| `pegawai` | Pegawai | Pengguna umum (membuat permintaan, lihat data sendiri) |
| `auditor` | Auditor | Read-only laporan + log audit |
| `viewer` | Viewer | Read-only umum |
| `validator` | Validator | Validator pengajuan/dokumen |

App boleh menambah role custom (mis. `kasubbag-kepegawaian`, `wakil-ketua`) selama:
- Tidak duplikasi makna dari role standar
- Slug deskriptif dan kebab-case

---

## 4. Implementasi di Seeder

### 4.1 Idempoten Wajib

Semua seeder permission harus pakai `firstOrCreate` (bukan `create`) agar aman re-run.

```php
$app->permissions()->firstOrCreate(
    ['slug' => $def['slug']],
    [
        'nama' => $def['nama'],
        'group' => $def['group'],
        'keterangan' => $def['keterangan'],
    ],
);
```

### 4.2 Pivot Pakai `syncWithoutDetaching`

Saat attach permission ke role, pakai `syncWithoutDetaching` (bukan `sync`) agar tidak menghapus assignment yang ditambahkan admin secara manual via UI.

```php
$role->permissions()->syncWithoutDetaching($permIds);
```

### 4.3 Generate Kredensial Hanya Saat Create Aplikasi

`generateApiCredentials()` mengembalikan plaintext `secret` yang **hanya ditampilkan satu kali**. Seeder harus:

```php
$app = IamApplication::where('slug', $appSlug)->first();
if (! $app) {
    ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();
    // ... create app, set api_key & api_secret_hash, save
    $this->command->warn('Salin kredensial ini ke .env aplikasi client:');
    $this->command->line("  IAM_API_KEY={$key}");
    $this->command->line("  IAM_API_SECRET={$secret}");
}
```

---

## 5. Implementasi di Aplikasi Client

### 5.1 Validasi Permission

Saat user login lewat SSO, `iam_permissions` di session berisi array slug **tanpa prefix app**:

```php
session('iam_permissions') === [
    'barang.manage',
    'permintaan.create',
    'laporan.read',
];
```

Middleware/policy memvalidasi slug ini langsung tanpa transformasi:

```php
// Route
Route::middleware('permission:barang.manage')->group(...);

// Policy
return $this->hasPermission('barang.manage');

// Livewire component
if (! $this->can('barang.manage')) abort(403);
```

### 5.2 Daftar Permission di Config

Setiap app client menyimpan daftar slug yang dipakainya di `config/{app}.php` agar mudah audit:

```php
return [
    'permission_slugs' => [
        'barang.manage',
        'permintaan.create',
        'permintaan.approve',
        // ...
    ],
];
```

Daftar ini harus identik dengan yang ada di seeder IAM Hub.

---

## 6. Contoh: Aplikasi-Aplikasi Saat Ini

### 6.1 `kepegawaian` (45 permissions)

```text
pegawai.view, pegawai.create, pegawai.update, pegawai.delete
cuti.pengajuan.create, cuti.pengajuan.approve-langsung, cuti.pengajuan.approve-pejabat,
  cuti.pengajuan.cancel-own, cuti.pengajuan.cancel-any, cuti.pengajuan.view-own,
  cuti.pengajuan.view-team, cuti.pengajuan.view-all, cuti.pengajuan.verify,
  cuti.pengajuan.reassign, cuti.audit.view, cuti.saldo.view-own,
  cuti.saldo.view-all, cuti.saldo.adjust
checklist.template.view/.create/.update/.delete
checklist.submission.view/.update-item/.validate-item
kenaikan-pangkat.usulan.* (10 actions)
kenaikan-pangkat.monitoring.view, monitoring.view
referensi.view, referensi.create, referensi.update, referensi.delete
pengajuan-perubahan.validate
iam.manage, rbac.manage
```

### 6.2 `persediaan` (12 permissions)

```text
barang.manage
permintaan.create, permintaan.approve, permintaan.process
pembelian.create, pembelian.approve, pembelian.process
barangmasuk.manage
stockopname.manage
laporan.read
log.read
setting.manage
```

### 6.3 `attendance-qr-system` (7 permissions)

```text
attendance.view, attendance.manage
reports.view, reports.generate
users.view, users.manage
settings.manage
```

---

## 7. Checklist untuk Aplikasi Baru

Saat menambahkan app baru ke IAM Hub:

- [ ] Daftarkan `IamApplication` dengan slug singkat & lowercase (mis. `logistik`, bukan `Logistik` atau `logistik-app`)
- [ ] Tulis seeder `{App}RoleSeeder.php` di `kepegawaian-apps/database/seeders/`
- [ ] Definisikan permission tanpa prefix app, ikut format pasal 2
- [ ] Definisikan role minimal: `admin` (boleh hanya 1 jika sederhana)
- [ ] Field `group` per permission = first segment slug
- [ ] Pakai `firstOrCreate` + `syncWithoutDetaching` (idempoten)
- [ ] Output kredensial via `$this->command->warn()` saat aplikasi baru dibuat
- [ ] Di app client: daftar slug juga di `config/{app}.php` array `permission_slugs`
- [ ] Tulis test Pest yang memverifikasi slug match antara seeder dan config
- [ ] Update dokumen ini di pasal 6 dengan daftar permission app baru

---

## 8. Migrasi Aplikasi Existing yang Tidak Sesuai

Bila menemukan app dengan slug yang tidak ikut konvensi (mis. pakai prefix, underscore, camelCase):

1. Backup tabel `iam_permissions`, `iam_role_permissions` di app tersebut
2. Update slug di seeder app
3. Update reference di kode app client (route middleware, policies, config)
4. Hapus row lama, jalankan seeder ulang
5. Test login + akses fitur untuk verifikasi
6. Update dokumen ini

**Contoh historis**: app `persediaan` awalnya pakai prefix `persediaan:barang.manage`. Dimigrasi ke `barang.manage` pada 2026-05-16, mengikuti konvensi ini.

---

## 9. Referensi

### Sumber kebenaran (machine-readable)

- **Config**: `config/iam.php` — pola regex slug, daftar action standar, daftar role standar (single source of truth)
- **Validator**: `app/Rules/ValidIamSlug.php` — rule object dipakai di FormRequest, seeder validation, dan audit
- **Service**: `app/Services/Iam/IamPermissionAuditor.php` — audit + suggest canonical
- **Command**: `php artisan iam:audit-slugs` — laporan slug non-canonical (opsi: `--app=<slug>`, `--json`)
- **FormRequest**: `app/Http/Requests/Iam/StorePermissionRequest.php` (validasi keras saat create, auto-derive `group` dari segment pertama slug)
- **FormRequest**: `app/Http/Requests/Iam/UpdatePermissionRequest.php` (slug TIDAK divalidasi — immutable, pakai endpoint migrate)

### Migration & seeder

- Migration: `database/migrations/2026_03_21_000001_create_iam_tables.php`
- Seeder template: `database/seeders/IamSeeder.php`, `PersediaanRoleSeeder.php`, `CutiPermissionSeeder.php`, `PermissionSikepP1Seeder.php`

### Endpoint & API

- HMAC API contract: `docs/sso-api/authentication.md`
- Endpoint validate: `docs/sso-api/endpoints.md`
- Endpoint migrate-slug (UI internal admin): `POST /iam/aplikasi/{aplikasi}/permissions/{permission}/migrate-slug`

### Test guard

- `tests/Feature/Iam/SeederSlugCanonicalTest.php` — regression guard semua seeder utama (4 seeder)
- `tests/Unit/Rules/ValidIamSlugTest.php` — unit test validator (8 cases)
- `tests/Unit/Services/Iam/IamPermissionAuditorTest.php` — unit test auditor (8 cases)
- `tests/Feature/Iam/PermissionSlugMigrateTest.php` — feature test endpoint migrate (5 cases termasuk IDOR + audit log)

### Design doc

- `docs/superpowers/specs/2026-05-16-iam-informative-design.md` — design rationale modul IAM informatif (2026-05-16)
- `docs/superpowers/plans/2026-05-16-iam-informative-plan.md` — implementation plan TDD
