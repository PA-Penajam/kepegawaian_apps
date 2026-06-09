# Modul IAM Informatif — Design

**Status**: Draft (menunggu review)
**Tanggal**: 2026-05-16
**Author**: brainstorming dengan user (via superpowers:brainstorming)
**Modul terkait**: IAM Hub (`iam_applications`, `iam_permissions`, `iam_roles`)
**Referensi**: [`docs/sso-api/rbac-convention.md`](../../sso-api/rbac-convention.md), [`docs/superpowers/specs/2026-03-21-iam-sso-design.md`](2026-03-21-iam-sso-design.md)

---

## 1. Tujuan & Cakupan

### 1.1 Masalah yang Dipecahkan

User admin yang membuka form **Tambah Permission** di halaman IAM Aplikasi (`resources/js/pages/iam/aplikasi/show.tsx:559-731`) tidak tahu bagaimana mengisi field `nama`, `slug`, `group`, `keterangan` secara konsisten. Sumber kebingungan:

- **Placeholder menyesatkan**: field `nama` pakai `Contoh: create-post` (format kebab-case verb-resource) padahal konvensi sebenarnya adalah **nama deskriptif Bahasa Indonesia** (`Lihat Daftar Pegawai`).
- **Placeholder slug tidak informatif**: `Contoh: post.create` ada, tapi tidak ada penjelasan format `{resource}.{action}` atau `{module}.{resource}.{action}`.
- **Field `group` tanpa konteks**: user tidak tahu bahwa `group` = segment pertama slug.
- **Tidak ada validasi**: slug seperti `iam-manage` (yang melanggar konvensi) bisa lolos.
- **Dokumentasi konvensi terpisah**: `docs/sso-api/rbac-convention.md` (292 baris, lengkap) tidak terhubung ke UI.
- **Inkonsistensi internal codebase**: `IamSeeder.php:141` punya `iam-manage` yang melanggar konvensi dot-notation yang dipakai semua seeder lain.

### 1.2 Hasil yang Diinginkan

1. Form Permission punya **dua mode**: Builder (default, untuk operator) dan Free-input (untuk admin senior).
2. Validasi slug regex **keras tanpa override** di backend + client-side; submit ditolak jika tidak match `^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*){1,2}$`.
3. List view permission menampilkan **badge status canonical/legacy** + tombol **Migrate to canonical** per row legacy.
4. Command artisan `php artisan iam:audit-slugs` melaporkan inkonsistensi.
5. **Single source of truth** untuk konvensi di `config/iam.php`.
6. Helper text + link ke `rbac-convention.md` muncul inline di form (collapsible "Lihat konvensi lengkap").

### 1.3 Yang Tidak Masuk (YAGNI)

- Tidak ada auto-rename otomatis tanpa user trigger.
- Tidak ada bulk migration UI; bulk hanya via command artisan kalau perlu.
- Tidak ada perubahan ke form **Role** (slug role kebab-case sederhana sudah aman; tidak ada laporan kebingungan).
- Tidak ada perubahan ke endpoint `/v1/iam/validate` (kontrak SSO tetap).
- Tidak ada artisan `iam:check-references` untuk dry-run grep code (bisa ditambah nanti jika dibutuhkan).
- Test untuk komponen React di-skip kecuali ditemukan framework testing yang sudah established di repo saat implementasi.

---

## 2. Single Source of Truth (`config/iam.php`)

Konvensi RBAC dipusatkan di **`config/iam.php`** agar dipakai oleh: Rule object, FormRequest, seeder, command audit, dan Inertia shared props.

```php
return [
    'slug' => [
        'pattern' => '/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*){1,2}$/',
        'min_segments' => 2,
        'max_segments' => 3,
    ],

    'standard_actions' => [
        'view'        => 'Lihat list/detail (read-only)',
        'create'      => 'Buat record baru',
        'update'      => 'Ubah record yang sudah ada',
        'delete'      => 'Hapus record',
        'manage'      => 'Umbrella: view+create+update+delete',
        'approve'     => 'Setujui/tolak workflow',
        'process'     => 'Eksekusi workflow yang sudah disetujui',
        'read'        => 'Read-only untuk laporan/log',
        'submit'      => 'Ajukan ke tahap berikutnya',
        'verify'      => 'Verifikasi dokumen/data',
        'cancel-own'  => 'Batalkan milik sendiri',
        'cancel-any'  => 'Batalkan milik siapapun (admin)',
        'view-own'    => 'Lihat milik sendiri',
        'view-team'   => 'Lihat milik tim',
        'view-all'    => 'Lihat semua (admin/auditor)',
        'audit'       => 'Akses audit log/trail',
        'reassign'    => 'Alihkan tanggung jawab (mis. ganti penyetuju)',
        'adjust'      => 'Penyesuaian numerik (mis. saldo)',
    ],

    'standard_roles' => [
        'admin'     => 'Akses penuh app',
        'operator'  => 'Operasional harian',
        'pimpinan'  => 'Atasan/penyetuju workflow',
        'pegawai'   => 'Pengguna umum',
        'auditor'   => 'Read-only laporan + log',
        'viewer'    => 'Read-only umum',
        'validator' => 'Validator pengajuan/dokumen',
    ],

    'docs_url' => '/docs/sso-api/rbac-convention.md',
];
```

**Konsekuensi**:
- `ValidIamSlug` rule object membaca `config('iam.slug.pattern')` — kalau regex berubah, semua entry point ikut update.
- Builder UI di React menerima daftar `standard_actions` lewat Inertia shared props.
- Command artisan `iam:audit-slugs` pakai validator yang sama; tidak ada drift.
- File `docs/sso-api/rbac-convention.md` tetap canonical narrative; `config/iam.php` adalah machine-readable mirror.

**Catatan**: `standard_actions` adalah daftar **rekomendasi**, bukan allowlist. Action di luar daftar tetap valid asalkan match regex. Daftar dipakai untuk dropdown di Builder UI.

---

## 3. Backend: Validator & FormRequest

### 3.1 Rule Object `ValidIamSlug`

Lokasi: `app/Rules/ValidIamSlug.php`

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIamSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = config('iam.slug.pattern');

        if (! is_string($value) || ! preg_match($pattern, $value)) {
            $fail('Slug harus format {resource}.{action} atau {module}.{resource}.{action}. '
                . 'Contoh: pegawai.view, cuti.pengajuan.create. '
                . 'Lowercase, antar-segment pakai titik, antar-kata pakai strip.');
            return;
        }

        $segments = explode('.', $value);
        $max = config('iam.slug.max_segments');
        if (count($segments) > $max) {
            $fail("Slug maksimal {$max} segment ({resource}.{action} atau {module}.{resource}.{action}).");
        }
    }
}
```

### 3.2 FormRequest Update

Lokasi: `app/Http/Requests/Iam/StorePermissionRequest.php` dan `UpdatePermissionRequest.php`.

```php
public function rules(): array
{
    return [
        'nama' => ['required', 'string', 'min:3', 'max:120'],
        'slug' => [
            'required', 'string', 'max:120',
            new ValidIamSlug,
            Rule::unique('iam_permissions', 'slug')
                ->where('iam_application_id', $this->route('aplikasi')->id)
                ->ignore($this->route('permission')?->id),
        ],
        'group'      => ['nullable', 'string', 'max:60'],
        'keterangan' => ['nullable', 'string', 'max:255'],
    ];
}

protected function prepareForValidation(): void
{
    if (! $this->filled('group') && $this->filled('slug')) {
        $this->merge(['group' => explode('.', $this->slug)[0]]);
    }
}
```

### 3.3 Service `IamPermissionAuditor`

Lokasi: `app/Services/Iam/IamPermissionAuditor.php`.

```php
<?php

namespace App\Services\Iam;

use App\Models\IamPermission;
use Illuminate\Support\Collection;

class IamPermissionAuditor
{
    /** @return Collection<int, array{id:int, slug:string, app:string, reason:string, suggested:?string}> */
    public function findNonCanonical(): Collection
    {
        return IamPermission::with('application')
            ->get()
            ->filter(fn ($p) => ! $this->isValidSlug($p->slug))
            ->map(fn ($p) => [
                'id'        => $p->id,
                'slug'      => $p->slug,
                'app'       => $p->application->slug,
                'reason'    => $this->violationReason($p->slug),
                'suggested' => $this->suggestCanonical($p->slug),
            ])
            ->values();
    }

    public function isValidSlug(string $slug): bool
    {
        return (bool) preg_match(config('iam.slug.pattern'), $slug);
    }

    public function suggestCanonical(string $slug): ?string
    {
        if (! str_contains($slug, '.') && str_contains($slug, '-')) {
            return preg_replace('/-(?=[a-z]+$)/', '.', $slug, 1);
        }
        return null;
    }

    private function violationReason(string $slug): string
    {
        if (! str_contains($slug, '.')) return 'Tidak ada titik pemisah';
        if (preg_match('/[A-Z]/', $slug)) return 'Mengandung uppercase';
        if (str_contains($slug, '_'))    return 'Underscore tidak diizinkan';
        return 'Format tidak match regex konvensi';
    }
}
```

---

## 4. Frontend: Permission Builder Form

### 4.1 Inertia Shared Props

Lokasi: `app/Http/Middleware/HandleInertiaRequests.php` — extend method `share()`.

```php
'iam' => fn () => [
    'slug_pattern'      => config('iam.slug.pattern'),
    'standard_actions'  => config('iam.standard_actions'),
    'standard_roles'    => config('iam.standard_roles'),
    'min_segments'      => config('iam.slug.min_segments'),
    'max_segments'      => config('iam.slug.max_segments'),
    'docs_url'          => config('iam.docs_url'),
],
```

Frontend baca via `usePage().props.iam` — tidak butuh endpoint baru.

### 4.2 Komponen `<PermissionFormFields />`

Lokasi: `resources/js/pages/iam/aplikasi/components/permission-form-fields.tsx`.

Komponen di-extract dari inline form di `show.tsx` agar reusable (Create & Edit). Struktur:

```
┌─ Mode Toggle ─────────────────────────────────────────────┐
│  ● Builder      ○ Mode ahli (free-input)                  │
└───────────────────────────────────────────────────────────┘

[Builder Mode]
   Resource:    [pegawai            ]
                ⓘ Resource utama, kebab-case

   Sub-resource:[                  ] (opsional)
                ⓘ Untuk modul kompleks (mis. cuti.pengajuan)

   Action:      [view         ▾]
                ⓘ 18 action standar atau pilih "custom..."
                  → custom mode: [____text input____]

   ─────────────────────────────────────
   Preview slug: cuti.pengajuan.view ✅
   Group: cuti (auto)

[Free-input Mode]
   Slug:        [_______________________] (mono font)
                ✅ Sesuai konvensi (real-time)
                ⓘ Format: {resource}.{action}
                  atau {module}.{resource}.{action}

[Kedua Mode]
   Nama: [Lihat Daftar Pegawai     ]
         ⓘ Nama deskriptif Bahasa Indonesia

   Keterangan: [Multi-line input] (opsional)

[ Lihat konvensi lengkap ▾ ]  (collapsible <ConventionHelpPanel />)
   → Embed isi pasal 2.2 (action standar) +
     link "Buka rbac-convention.md di tab baru"
```

### 4.3 State Logic (high-level)

```tsx
const [mode, setMode] = useState<'builder' | 'free'>('builder');
const [resource, setResource] = useState('');
const [subResource, setSubResource] = useState('');
const [action, setAction] = useState('view');
const [customAction, setCustomAction] = useState('');
const [freeInputSlug, setFreeInputSlug] = useState('');

const builderSlug = [
    resource,
    subResource,
    action === 'custom' ? customAction : action,
].filter(Boolean).join('.');

const effectiveSlug = mode === 'builder' ? builderSlug : freeInputSlug;
const slugRegex = new RegExp(usePage().props.iam.slug_pattern);
const isValid = slugRegex.test(effectiveSlug);
const group = effectiveSlug.split('.')[0] ?? '';
```

### 4.4 Validasi UX

- Tombol **Simpan** disabled jika `!isValid`.
- Saat pindah dari builder ke free, slug builder otomatis di-copy agar tidak hilang.
- Real-time indicator: ✅ canonical / ❌ regex tidak match / ⓘ kosong.
- Error backend (mis. unique violation) tampil di `<AlertError />` existing pattern.

### 4.5 Komponen Pendukung

- `<SlugStatusBadge slug="..." />`: badge `✅ Canonical` (hijau) atau `⚠ Legacy` (kuning) berdasarkan regex.
- `<ConventionHelpPanel />`: collapsible panel berisi ringkasan action standar dari shared props, plus tombol "Buka rbac-convention.md".

---

## 5. List View & Banner Legacy

### 5.1 Banner di Atas Tab Permissions

Inertia controller (`PermissionController@index` di tab) menambah props:

```php
'permission_audit' => [
    'non_canonical_count' => $auditor->findNonCanonical()
        ->filter(fn ($p) => $p['app'] === $aplikasi->slug)
        ->count(),
],
```

Banner conditional (muncul jika `count > 0`):

```
┌─ Banner (kuning) ──────────────────────────────────────────┐
│ ⚠  N permission di aplikasi ini melanggar konvensi.        │
│    Cek kolom "Status" pada list di bawah, atau jalankan    │
│    `php artisan iam:audit-slugs` untuk laporan lengkap.    │
│                                              [Tutup ×]     │
└────────────────────────────────────────────────────────────┘
```

### 5.2 Kolom Baru di Tabel Permissions

| Nama | Slug | Group | Status | Aksi |
|---|---|---|---|---|
| Kelola IAM | `iam-manage` | iam | ⚠ Legacy → `iam.manage`? `[Migrate]` | [Edit] [Hapus] |
| Lihat Pegawai | `pegawai.view` | pegawai | ✅ Canonical | [Edit] [Hapus] |

`<SlugStatusBadge>`:
- Canonical: badge hijau `✅ Canonical`.
- Legacy: badge kuning `⚠ Legacy`; hover tooltip → `reason`; jika `suggested ≠ null`, tampilkan tombol kecil `[Migrate → <suggested>]`.

### 5.3 Flow Tombol Migrate

```
┌─ Konfirmasi Migrasi Slug ─────────────────────────┐
│ Ubah slug:                                        │
│   iam-manage  →  iam.manage                       │
│                                                   │
│ Akan dilakukan:                                   │
│ 1. Rename slug di tabel iam_permissions           │
│ 2. Group auto-update jadi `iam`                   │
│ 3. Audit log mencatat perubahan (user, before/after)│
│                                                   │
│ ⚠ Catatan: reference di kode (route middleware,   │
│   policy) yang masih pakai `iam-manage` HARUS     │
│   di-grep & update manual oleh developer.         │
│   Migrasi ini hanya mengubah database.            │
│                                                   │
│              [Batal]  [Ya, Migrate]               │
└───────────────────────────────────────────────────┘
```

### 5.4 Endpoint Migrate

Route: `POST /iam/aplikasi/{aplikasi}/permissions/{permission}/migrate-slug`
Controller: `app/Http/Controllers/Iam/PermissionController@migrateSlug`

```php
public function migrateSlug(
    IamApplication $aplikasi,
    IamPermission $permission,
    IamPermissionAuditor $auditor,
): RedirectResponse {
    $this->authorize('update', $permission);

    $suggested = $auditor->suggestCanonical($permission->slug);
    if (! $suggested) {
        return back()->withErrors(['slug' => 'Tidak ada saran canonical untuk slug ini. Edit manual.']);
    }

    if (! $auditor->isValidSlug($suggested)) {
        return back()->withErrors(['slug' => 'Saran canonical tidak valid. Edit manual.']);
    }

    $exists = IamPermission::where('iam_application_id', $aplikasi->id)
        ->where('slug', $suggested)
        ->where('id', '!=', $permission->id)
        ->exists();

    if ($exists) {
        return back()->withErrors([
            'slug' => "Slug '{$suggested}' sudah ada di aplikasi ini. Edit manual untuk resolusi konflik.",
        ]);
    }

    $before = $permission->slug;
    $permission->update([
        'slug'  => $suggested,
        'group' => explode('.', $suggested)[0],
    ]);

    activity('iam.permission')
        ->causedBy(auth()->user())
        ->performedOn($permission)
        ->withProperties(['before' => $before, 'after' => $suggested])
        ->log('slug-migrated');

    return back()->with('success', "Slug dimigrasi: {$before} → {$suggested}");
}
```

---

## 6. Audit Command & Data Migration

### 6.1 Command `php artisan iam:audit-slugs`

Lokasi: `app/Console/Commands/Iam/AuditSlugsCommand.php`.

```php
class AuditSlugsCommand extends Command
{
    protected $signature = 'iam:audit-slugs
                            {--app= : Filter ke aplikasi tertentu (slug)}
                            {--json : Output JSON untuk piping}';
    protected $description = 'Audit slug permission IAM yang melanggar konvensi';

    public function handle(IamPermissionAuditor $auditor): int
    {
        $items = $auditor->findNonCanonical();

        if ($appSlug = $this->option('app')) {
            $items = $items->filter(fn ($p) => $p['app'] === $appSlug);
        }

        if ($items->isEmpty()) {
            $this->info('✅ Semua slug permission canonical. Tidak ada yang perlu di-migrate.');
            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($items->values()->toJson(JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$items->count()} slug non-canonical:");
        $this->table(
            ['App', 'Slug Sekarang', 'Alasan', 'Saran Canonical'],
            $items->map(fn ($p) => [
                $p['app'], $p['slug'], $p['reason'], $p['suggested'] ?? '—',
            ])->toArray(),
        );

        $this->newLine();
        $this->line('Migrate via UI: buka halaman aplikasi IAM → tab Permissions → tombol [Migrate]');
        $this->line('Atau edit manual jika kasus kompleks.');

        return self::SUCCESS;
    }
}
```

### 6.2 Data Migration untuk `iam-manage`

Tidak ada migration file otomatis. Reasoning: rename slug akan memutus reference kode (middleware, policy) yang mungkin masih pakai string lama. Sebagai gantinya, **test guard** di Section 7.5 akan gagal jika `iam-manage` masih ada di seeder — memaksa developer untuk fix sebelum merge.

**Proses fix `iam-manage`**:

1. Jalankan `php artisan iam:audit-slugs` → konfirmasi `iam-manage` di list.
2. Update `database/seeders/IamSeeder.php`: ganti `'slug' => 'iam-manage'` jadi `'slug' => 'iam.manage'`.
3. Grep code: `rg "iam-manage" app/ resources/ routes/ config/` → update semua reference. **Wajib di-check**:
   - `routes/web.php:64` — middleware `iam.permission:iam-manage`
   - Policy/gate definition jika ada
   - Config file & seeder lain yang refer slug
4. Jalankan migrate via UI (tombol per row di tab Permissions) **atau** `php artisan db:seed --class=IamSeeder` (idempoten).
5. Test `SeederSlugCanonicalTest` pass.
6. Commit `iam-manage` rename **terpisah** dari penambahan validator agar reversible.

---

## 7. Testing Strategy (TDD)

Mengikuti **RED-GREEN-REFACTOR** dari `superpowers:test-driven-development`. Setiap test ditulis **sebelum** implementasi terkait.

### 7.1 Unit — `ValidIamSlug`

Lokasi: `tests/Unit/Rules/ValidIamSlugTest.php`

```php
test('menerima slug 2-segment canonical', function () {
    foreach (['pegawai.view', 'cuti.create', 'barang.manage'] as $slug) {
        expect(validateWithRule(new ValidIamSlug, $slug))->toBeNull();
    }
});

test('menerima slug 3-segment canonical', function () {
    foreach (['cuti.pengajuan.approve-langsung', 'kenaikan-pangkat.usulan.create'] as $slug) {
        expect(validateWithRule(new ValidIamSlug, $slug))->toBeNull();
    }
});

test('menolak slug tanpa titik', function () {
    expect(validateWithRule(new ValidIamSlug, 'iam-manage'))->toContain('format');
});

test('menolak slug dengan uppercase', function () {
    expect(validateWithRule(new ValidIamSlug, 'Pegawai.View'))->not->toBeNull();
});

test('menolak slug dengan underscore', function () {
    expect(validateWithRule(new ValidIamSlug, 'pegawai_view'))->not->toBeNull();
});

test('menolak slug 4-segment', function () {
    expect(validateWithRule(new ValidIamSlug, 'a.b.c.d'))->not->toBeNull();
});
```

`validateWithRule()` adalah helper test yang menjalankan rule dan return error message (atau null).

### 7.2 Unit — `IamPermissionAuditor`

```php
test('findNonCanonical mengembalikan slug yang melanggar', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'pegawai.view']);
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'iam-manage']);

    $result = app(IamPermissionAuditor::class)->findNonCanonical();

    expect($result)->toHaveCount(1)
        ->and($result->first()['slug'])->toBe('iam-manage');
});

test('suggestCanonical mengkonversi strip-tunggal trailing', function () {
    $auditor = app(IamPermissionAuditor::class);
    expect($auditor->suggestCanonical('iam-manage'))->toBe('iam.manage');
    expect($auditor->suggestCanonical('barang_masuk'))->toBeNull();
});
```

### 7.3 Feature — FormRequest

```php
test('store permission tolak slug invalid', function () {
    $app = IamApplication::factory()->create();
    $admin = $this->createAdminUser();

    $this->actingAs($admin)
        ->post("/iam/aplikasi/{$app->id}/permissions", [
            'nama' => 'Test',
            'slug' => 'invalid-slug',
        ])
        ->assertSessionHasErrors('slug');
});

test('store permission auto-derive group dari slug', function () {
    $app = IamApplication::factory()->create();
    $admin = $this->createAdminUser();

    $this->actingAs($admin)
        ->post("/iam/aplikasi/{$app->id}/permissions", [
            'nama' => 'Lihat Cuti',
            'slug' => 'cuti.pengajuan.view-own',
        ]);

    expect(IamPermission::where('slug', 'cuti.pengajuan.view-own')->first()->group)
        ->toBe('cuti');
});
```

### 7.4 Feature — Migrate Endpoint

```php
test('migrateSlug rename slug dan update group', function () {
    $app = IamApplication::factory()->create();
    $perm = IamPermission::factory()->for($app, 'application')
        ->create(['slug' => 'iam-manage', 'group' => 'iam']);

    $this->actingAs($this->createAdminUser())
        ->post("/iam/aplikasi/{$app->id}/permissions/{$perm->id}/migrate-slug")
        ->assertRedirect();

    expect($perm->fresh())
        ->slug->toBe('iam.manage')
        ->group->toBe('iam');
});

test('migrateSlug tolak jika ada konflik unique', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'iam.manage']);
    $legacy = IamPermission::factory()->for($app, 'application')->create(['slug' => 'iam-manage']);

    $this->actingAs($this->createAdminUser())
        ->post("/iam/aplikasi/{$app->id}/permissions/{$legacy->id}/migrate-slug")
        ->assertSessionHasErrors('slug');

    expect($legacy->fresh()->slug)->toBe('iam-manage');
});
```

### 7.5 Seeder Canonical Guard (kunci utama)

Lokasi: `tests/Feature/Iam/SeederSlugCanonicalTest.php`

```php
test('semua slug dari seeder utama canonical', function (string $seederClass) {
    $this->seed($seederClass);
    $auditor = app(IamPermissionAuditor::class);

    $nonCanonical = $auditor->findNonCanonical();

    expect($nonCanonical)->toBeEmpty(
        "{$seederClass} masih punya slug non-canonical: "
        . $nonCanonical->pluck('slug')->implode(', ')
    );
})->with([
    IamSeeder::class,
    CutiPermissionSeeder::class,
    PermissionSikepP1Seeder::class,
    PersediaanRoleSeeder::class,
]);
```

**`RefPermissionSeeder` dikecualikan** karena `ref_permissions` adalah tabel **legacy** terpisah dari `iam_permissions` — konvensi ini hanya berlaku untuk IAM Hub.

### 7.6 Frontend

Verifikasi manual via browser sebagai default. Saat implementasi, **cek dulu** apakah ada framework testing React (`package.json` script `test`, `vitest`, atau `jest`):
- Jika **ada**: tulis test komponen untuk `<PermissionFormFields>` (validasi slug regex client-side, mode toggle behavior).
- Jika **tidak ada**: hanya verifikasi manual; jangan setup framework testing baru dalam scope ini (YAGNI).

**Checklist verifikasi manual**:

1. Buka halaman aplikasi IAM → tab Permissions.
2. Klik **Tambah Permission** → mode Builder default.
3. Pilih resource `test`, action `view` → preview slug "test.view" ✅.
4. Ketik resource `Test` (uppercase) → tombol Simpan disabled.
5. Toggle ke Mode ahli → input slug `invalid-slug` → red ❌, Simpan disabled.
6. Migrate `iam-manage` via tombol → konfirmasi → slug update di tabel.

---

## 8. File Changes & Implementation Order

### 8.1 File Baru

| File | Tujuan |
|---|---|
| `config/iam.php` | Single source of truth |
| `app/Rules/ValidIamSlug.php` | Rule object regex |
| `app/Services/Iam/IamPermissionAuditor.php` | Audit + suggestCanonical |
| `app/Console/Commands/Iam/AuditSlugsCommand.php` | `iam:audit-slugs` |
| `resources/js/pages/iam/aplikasi/components/permission-form-fields.tsx` | Hybrid builder |
| `resources/js/pages/iam/aplikasi/components/slug-status-badge.tsx` | Badge canonical/legacy |
| `resources/js/pages/iam/aplikasi/components/convention-help-panel.tsx` | Collapsible konvensi |
| `tests/Unit/Rules/ValidIamSlugTest.php` | Unit test validator |
| `tests/Unit/Services/Iam/IamPermissionAuditorTest.php` | Unit test auditor |
| `tests/Feature/Iam/PermissionFormRequestTest.php` | Feature test FormRequest |
| `tests/Feature/Iam/PermissionSlugMigrateTest.php` | Feature test migrate |
| `tests/Feature/Iam/SeederSlugCanonicalTest.php` | Regression guard seeder |

### 8.2 File Dimodifikasi

| File | Perubahan |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Tambah shared prop `iam` |
| `app/Http/Controllers/Iam/PermissionController.php` | Tambah `migrateSlug()` + share `permission_audit` di parent show |
| `app/Http/Requests/Iam/StorePermissionRequest.php` | Pakai `ValidIamSlug` + `prepareForValidation()` |
| `app/Http/Requests/Iam/UpdatePermissionRequest.php` | Sama |
| `routes/web.php` | Tambah route `POST .../permissions/{permission}/migrate-slug` dalam group `iam.` (sekitar line 83); update middleware `iam.permission:iam-manage` (line 64) → `iam.permission:iam.manage` saat rename |
| `resources/js/pages/iam/aplikasi/show.tsx` | Extract form ke komponen, tambah banner, tambah kolom Status |
| `database/seeders/IamSeeder.php` | Rename `iam-manage` → `iam.manage` (commit terpisah) |
| `docs/sso-api/rbac-convention.md` | Update pasal 9 (Referensi) ke `config/iam.php`, `ValidIamSlug`, command audit |

### 8.3 Urutan TDD (high-level)

```
1. RED:    tests/Unit/Rules/ValidIamSlugTest.php
   GREEN:  config/iam.php + app/Rules/ValidIamSlug.php
   commit

2. RED:    tests/Unit/Services/Iam/IamPermissionAuditorTest.php
   GREEN:  app/Services/Iam/IamPermissionAuditor.php
   commit

3. RED:    tests/Feature/Iam/PermissionFormRequestTest.php
   GREEN:  Update Store/UpdatePermissionRequest pakai ValidIamSlug
   commit

4. RED:    tests/Feature/Iam/PermissionSlugMigrateTest.php
   GREEN:  PermissionController::migrateSlug + route
   commit

5. RED:    tests/Feature/Iam/SeederSlugCanonicalTest.php
   GREEN:  Rename iam-manage di IamSeeder + grep/update reference di code
   commit (commit terpisah untuk rename)

6. GREEN:  HandleInertiaRequests shared prop (trivial, no RED-first)
   commit

7. Frontend (verifikasi manual):
   - <SlugStatusBadge>           commit
   - <ConventionHelpPanel>       commit
   - <PermissionFormFields>      commit
   - Banner + kolom Status di show.tsx + integrasi      commit

8. AuditSlugsCommand (manual test di terminal)
   commit

9. Update docs/sso-api/rbac-convention.md pasal 9
   commit

10. Final verification: php artisan iam:audit-slugs harus clean
```

### 8.4 Risk & Mitigation

| Risiko | Mitigasi |
|---|---|
| Rename `iam-manage` memutus session admin yang sedang login | Force re-login semua admin setelah deploy; catatan di PR description |
| Frontend validation drift dari backend regex | Inertia shared props inject regex dari config — 1 source of truth |
| Test seeder gagal di CI sebelum reference di-grep | Commit terpisah untuk rename agar reversible |
| User bingung Builder vs Free-input | Default Builder; mode toggle prominent; helper text di kedua mode |
| `suggestCanonical()` salah suggest | Konservatif: hanya tangani strip-tunggal trailing; kasus lain → null + edit manual |

---

## 9. Definition of Done

- [ ] `config/iam.php` ada dan terisi sesuai spec
- [ ] `ValidIamSlug` lulus semua test unit (7.1)
- [ ] `IamPermissionAuditor` lulus semua test unit (7.2)
- [ ] FormRequest Permission validate slug (test 7.3)
- [ ] Endpoint migrate-slug lulus test (7.4)
- [ ] Test seeder canonical guard hijau untuk 4 seeder (7.5)
- [ ] `iam-manage` sudah di-rename ke `iam.manage` di seeder & code
- [ ] `php artisan iam:audit-slugs` keluar dengan exit 0 dan tidak ada slug yang dilaporkan
- [ ] Form Permission di UI punya mode Builder + Free-input dengan validasi real-time
- [ ] Banner muncul di tab Permissions kalau ada legacy slug
- [ ] Tombol Migrate per row berfungsi (manual test)
- [ ] `docs/sso-api/rbac-convention.md` pasal 9 di-update
- [ ] Code review pass via `superpowers:requesting-code-review`

---

## 10. Referensi

- Eksplorasi codebase awal: tab Permissions di `resources/js/pages/iam/aplikasi/show.tsx:559-731`
- Dokumen konvensi: `docs/sso-api/rbac-convention.md`
- Design SSO sebelumnya: `docs/superpowers/specs/2026-03-21-iam-sso-design.md`
- Models: `app/Models/IamApplication.php`, `IamPermission.php`, `IamRole.php`
- Seeders existing: `database/seeders/IamSeeder.php`, `CutiPermissionSeeder.php`, `PermissionSikepP1Seeder.php`, `PersediaanRoleSeeder.php`
