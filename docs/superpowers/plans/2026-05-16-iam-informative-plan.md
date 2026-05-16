# IAM Informatif Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Buat form Tambah Permission di IAM Hub menjadi informatif dan konsisten, sehingga admin tidak bingung saat mengisi data permission, dengan validasi keras + audit + tombol migrate untuk slug legacy.

**Architecture:** Single source of truth konvensi di `config/iam.php` → dikonsumsi oleh `ValidIamSlug` rule (backend), `IamPermissionAuditor` service (audit + suggest), Inertia shared props (frontend), dan command artisan. Form Permission jadi hybrid (Builder default + Free-input). Validasi keras tanpa override. Slug legacy ditangani via banner + tombol migrate per row.

**Tech Stack:** Laravel 11, Pest, Inertia.js, React + TypeScript, shadcn/ui, spatie/activitylog.

**Spec referensi:** `docs/superpowers/specs/2026-05-16-iam-informative-design.md`

---

## File Structure

### Files to Create

| File | Responsibility |
|---|---|
| `config/iam.php` | Single source of truth: regex slug, action standar, role standar |
| `app/Rules/ValidIamSlug.php` | Invokable rule object — validate slug match regex |
| `app/Services/Iam/IamPermissionAuditor.php` | Audit slug non-canonical + suggest canonical |
| `app/Http/Requests/Iam/StorePermissionRequest.php` | FormRequest untuk store permission (validasi + auto-derive group) |
| `app/Http/Requests/Iam/UpdatePermissionRequest.php` | FormRequest untuk update permission (TIDAK validate slug; immutable) |
| `app/Console/Commands/Iam/AuditSlugsCommand.php` | `php artisan iam:audit-slugs` |
| `database/factories/IamPermissionFactory.php` | Factory untuk test |
| `resources/js/pages/iam/aplikasi/components/permission-form-fields.tsx` | Hybrid form (Builder + Free-input) |
| `resources/js/pages/iam/aplikasi/components/slug-status-badge.tsx` | Badge canonical/legacy |
| `resources/js/pages/iam/aplikasi/components/convention-help-panel.tsx` | Collapsible konvensi |
| `tests/Unit/Rules/ValidIamSlugTest.php` | Test validator |
| `tests/Unit/Services/Iam/IamPermissionAuditorTest.php` | Test auditor |
| `tests/Feature/Iam/PermissionSlugMigrateTest.php` | Test endpoint migrate |
| `tests/Feature/Iam/SeederSlugCanonicalTest.php` | Regression guard semua seeder |

### Files to Modify

| File | Perubahan |
|---|---|
| `app/Http/Controllers/Iam/PermissionController.php` | Pakai FormRequest baru; tambah method `migrateSlug()`; tambah props `permission_audit` (via parent AplikasiController) |
| `app/Http/Controllers/Iam/AplikasiController.php` | Tambah props `permission_audit` ke `show()` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Tambah shared prop `iam` |
| `routes/web.php` | Tambah route `POST .../permissions/{permission}/migrate-slug`; update middleware string `iam-manage` → `iam.manage` |
| `resources/js/pages/iam/aplikasi/show.tsx` | Extract form ke komponen, tambah banner audit, tambah kolom Status di tabel |
| `database/seeders/IamSeeder.php` | Rename `iam-manage` → `iam.manage` |
| `tests/Feature/Iam/PermissionControllerTest.php` | Update slug test fixtures yang non-canonical (mis. `read` → `data.read`) |
| `docs/sso-api/rbac-convention.md` | Update pasal 9 referensi |

---

## Task 1: Config `iam.php` (Single Source of Truth)

**Files:**
- Create: `config/iam.php`

- [ ] **Step 1: Buat file `config/iam.php`**

```php
<?php

return [
    'slug' => [
        // Format: {resource}.{action} atau {module}.{resource}.{action}
        // Lowercase, antar-segment titik, antar-kata strip
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
        'reassign'    => 'Alihkan tanggung jawab',
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

- [ ] **Step 2: Clear config cache & verifikasi**

Run: `php artisan config:clear && php artisan tinker --execute="echo config('iam.slug.pattern');"`
Expected: `/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*){1,2}$/`

- [ ] **Step 3: Commit**

```bash
git add config/iam.php
git commit -m "feat(iam): add config/iam.php sebagai single source of truth konvensi RBAC"
```

---

## Task 2: Factory `IamPermissionFactory`

**Files:**
- Create: `database/factories/IamPermissionFactory.php`

- [ ] **Step 1: Buat factory**

```php
<?php

namespace Database\Factories;

use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IamPermission>
 */
class IamPermissionFactory extends Factory
{
    protected $model = IamPermission::class;

    public function definition(): array
    {
        // Default canonical slug (2-segment)
        $resource = $this->faker->unique()->word();
        $action = $this->faker->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'iam_application_id' => IamApplication::factory(),
            'nama'       => ucfirst($action).' '.ucfirst($resource),
            'slug'       => "{$resource}.{$action}",
            'group'      => $resource,
            'keterangan' => null,
        ];
    }

    /** State untuk slug legacy (non-canonical) — pakai untuk test audit */
    public function legacy(string $slug = 'iam-manage'): static
    {
        return $this->state(fn () => [
            'slug'  => $slug,
            'group' => str_contains($slug, '.') ? explode('.', $slug)[0] : $slug,
        ]);
    }
}
```

- [ ] **Step 2: Verifikasi factory works di tinker**

Run:
```
php artisan tinker --execute="\App\Models\IamPermission::factory()->make()->slug"
```
Expected: string format `{word}.{action}` (mis. `apple.view`)

- [ ] **Step 3: Commit**

```bash
git add database/factories/IamPermissionFactory.php
git commit -m "test(iam): add IamPermissionFactory dengan state legacy()"
```

---

## Task 3: Rule `ValidIamSlug` (TDD)

**Files:**
- Create: `app/Rules/ValidIamSlug.php`
- Test: `tests/Unit/Rules/ValidIamSlugTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Unit/Rules/ValidIamSlugTest.php

use App\Rules\ValidIamSlug;
use Illuminate\Support\Facades\Validator;

function validateSlug(string $slug): ?string
{
    $validator = Validator::make(
        ['slug' => $slug],
        ['slug' => [new ValidIamSlug]],
    );
    return $validator->errors()->first('slug') ?: null;
}

test('menerima slug 2-segment canonical', function () {
    foreach (['pegawai.view', 'cuti.create', 'barang.manage', 'iam.manage'] as $slug) {
        expect(validateSlug($slug))->toBeNull("Slug '{$slug}' seharusnya valid");
    }
});

test('menerima slug 3-segment canonical', function () {
    foreach (['cuti.pengajuan.approve-langsung', 'kenaikan-pangkat.usulan.create', 'checklist.template.update'] as $slug) {
        expect(validateSlug($slug))->toBeNull("Slug '{$slug}' seharusnya valid");
    }
});

test('menolak slug tanpa titik (single segment)', function () {
    $error = validateSlug('iam-manage');
    expect($error)->not->toBeNull()
        ->and($error)->toContain('format');
});

test('menolak slug dengan uppercase', function () {
    expect(validateSlug('Pegawai.View'))->not->toBeNull();
    expect(validateSlug('pegawai.View'))->not->toBeNull();
});

test('menolak slug dengan underscore', function () {
    expect(validateSlug('pegawai_view'))->not->toBeNull();
    expect(validateSlug('cuti.pengajuan_create'))->not->toBeNull();
});

test('menolak slug 4-segment (terlalu dalam)', function () {
    expect(validateSlug('a.b.c.d'))->not->toBeNull();
});

test('menolak slug dengan karakter terlarang', function () {
    expect(validateSlug('cuti.pengajuan@create'))->not->toBeNull();
    expect(validateSlug('cuti.pengajuan create'))->not->toBeNull();
    expect(validateSlug(''))->not->toBeNull();
});

test('menolak slug yang dimulai dengan strip atau angka', function () {
    expect(validateSlug('-cuti.view'))->not->toBeNull();
    expect(validateSlug('1cuti.view'))->not->toBeNull();
});
```

- [ ] **Step 2: Run test — pastikan gagal**

Run: `vendor/bin/pest tests/Unit/Rules/ValidIamSlugTest.php`
Expected: FAIL — `Class "App\Rules\ValidIamSlug" not found`

- [ ] **Step 3: Buat rule class**

```php
<?php
// app/Rules/ValidIamSlug.php

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
        $max = (int) config('iam.slug.max_segments');
        if (count($segments) > $max) {
            $fail("Slug maksimal {$max} segment ({resource}.{action} atau {module}.{resource}.{action}).");
        }
    }
}
```

- [ ] **Step 4: Run test — pastikan pass**

Run: `vendor/bin/pest tests/Unit/Rules/ValidIamSlugTest.php`
Expected: PASS — 8 passed

- [ ] **Step 5: Commit**

```bash
git add app/Rules/ValidIamSlug.php tests/Unit/Rules/ValidIamSlugTest.php
git commit -m "feat(iam): add ValidIamSlug rule untuk validasi format slug"
```

---

## Task 4: Service `IamPermissionAuditor` (TDD)

**Files:**
- Create: `app/Services/Iam/IamPermissionAuditor.php`
- Test: `tests/Unit/Services/Iam/IamPermissionAuditorTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Unit/Services/Iam/IamPermissionAuditorTest.php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Services\Iam\IamPermissionAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->auditor = app(IamPermissionAuditor::class);
});

test('isValidSlug menerima slug canonical', function () {
    expect($this->auditor->isValidSlug('pegawai.view'))->toBeTrue();
    expect($this->auditor->isValidSlug('cuti.pengajuan.approve-langsung'))->toBeTrue();
});

test('isValidSlug menolak slug non-canonical', function () {
    expect($this->auditor->isValidSlug('iam-manage'))->toBeFalse();
    expect($this->auditor->isValidSlug('Pegawai.View'))->toBeFalse();
    expect($this->auditor->isValidSlug('pegawai_view'))->toBeFalse();
});

test('findNonCanonical mengembalikan hanya slug yang melanggar', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'pegawai.view']);
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'cuti.create']);
    IamPermission::factory()->for($app, 'application')->legacy('iam-manage')->create();

    $result = $this->auditor->findNonCanonical();

    expect($result)->toHaveCount(1)
        ->and($result->first()['slug'])->toBe('iam-manage')
        ->and($result->first()['app'])->toBe($app->slug)
        ->and($result->first()['suggested'])->toBe('iam.manage');
});

test('findNonCanonical mengembalikan kosong jika semua canonical', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'a.view']);
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'b.create']);

    expect($this->auditor->findNonCanonical())->toBeEmpty();
});

test('suggestCanonical mengkonversi strip-tunggal trailing menjadi titik', function () {
    expect($this->auditor->suggestCanonical('iam-manage'))->toBe('iam.manage');
    expect($this->auditor->suggestCanonical('audit-view'))->toBe('audit.view');
});

test('suggestCanonical kembalikan null untuk underscore', function () {
    expect($this->auditor->suggestCanonical('barang_masuk'))->toBeNull();
});

test('suggestCanonical kembalikan null untuk slug yang sudah punya titik', function () {
    expect($this->auditor->suggestCanonical('pegawai.view'))->toBeNull();
    expect($this->auditor->suggestCanonical('cuti.pengajuan.approve-langsung'))->toBeNull();
});

test('violationReason memberikan alasan spesifik', function () {
    $result = $this->auditor->findNonCanonical();
    // populate beberapa kasus
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->legacy('iam-manage')->create();
    IamPermission::factory()->for($app, 'application')->legacy('pegawai_view')->create();
    IamPermission::factory()->for($app, 'application')->legacy('Pegawai.View')->create();

    $result = $this->auditor->findNonCanonical();

    $reasons = $result->pluck('reason', 'slug');
    expect($reasons['iam-manage'])->toBe('Tidak ada titik pemisah');
    expect($reasons['pegawai_view'])->toBe('Tidak ada titik pemisah'); // tidak ada titik
    expect($reasons['Pegawai.View'])->toBe('Mengandung uppercase');
});
```

- [ ] **Step 2: Run test — pastikan gagal**

Run: `vendor/bin/pest tests/Unit/Services/Iam/IamPermissionAuditorTest.php`
Expected: FAIL — `Class "App\Services\Iam\IamPermissionAuditor" not found`

- [ ] **Step 3: Buat service class**

```php
<?php
// app/Services/Iam/IamPermissionAuditor.php

namespace App\Services\Iam;

use App\Models\IamPermission;
use Illuminate\Support\Collection;

class IamPermissionAuditor
{
    /**
     * @return Collection<int, array{id:string, slug:string, app:string, reason:string, suggested:?string}>
     */
    public function findNonCanonical(): Collection
    {
        return IamPermission::with('application')
            ->get()
            ->filter(fn (IamPermission $p) => ! $this->isValidSlug($p->slug))
            ->map(fn (IamPermission $p) => [
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

    /**
     * Saran konservatif: hanya tangani slug single-segment dengan strip trailing
     * (mis. `iam-manage` → `iam.manage`). Kasus lain dikembalikan null
     * agar developer putuskan manual.
     */
    public function suggestCanonical(string $slug): ?string
    {
        if (str_contains($slug, '.')) {
            return null;
        }
        if (str_contains($slug, '_')) {
            return null;
        }
        if (! str_contains($slug, '-')) {
            return null;
        }
        // Konversi strip TERAKHIR menjadi titik: iam-manage → iam.manage
        $pos = strrpos($slug, '-');
        $candidate = substr($slug, 0, $pos).'.'.substr($slug, $pos + 1);

        return $this->isValidSlug($candidate) ? $candidate : null;
    }

    private function violationReason(string $slug): string
    {
        if (! str_contains($slug, '.'))     return 'Tidak ada titik pemisah';
        if (preg_match('/[A-Z]/', $slug))   return 'Mengandung uppercase';
        if (str_contains($slug, '_'))       return 'Underscore tidak diizinkan';

        return 'Format tidak match regex konvensi';
    }
}
```

- [ ] **Step 4: Run test — pastikan pass**

Run: `vendor/bin/pest tests/Unit/Services/Iam/IamPermissionAuditorTest.php`
Expected: PASS — 8 passed

- [ ] **Step 5: Commit**

```bash
git add app/Services/Iam/IamPermissionAuditor.php tests/Unit/Services/Iam/IamPermissionAuditorTest.php
git commit -m "feat(iam): add IamPermissionAuditor service untuk audit & suggest canonical slug"
```

---

## Task 5: Extract Validation ke `StorePermissionRequest` + `UpdatePermissionRequest`

**Files:**
- Create: `app/Http/Requests/Iam/StorePermissionRequest.php`
- Create: `app/Http/Requests/Iam/UpdatePermissionRequest.php`
- Modify: `app/Http/Controllers/Iam/PermissionController.php`
- Test: `tests/Feature/Iam/PermissionControllerTest.php` (tambah test baru, jangan hapus existing)

- [ ] **Step 1: Tulis failing tests baru di PermissionControllerTest**

Append ke akhir file `tests/Feature/Iam/PermissionControllerTest.php`:

```php
it('menolak store permission dengan slug non-canonical', function () {
    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Test',
            'slug' => 'invalid-slug',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');
    $this->assertDatabaseMissing('iam_permissions', ['slug' => 'invalid-slug']);
});

it('menerima store permission dengan slug canonical', function () {
    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Akses Demo',
            'slug' => 'demo.view',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('iam_permissions', [
        'iam_application_id' => $this->kepegawaian->id,
        'slug' => 'demo.view',
        'group' => 'demo', // auto-derived
    ]);
});

it('auto-derive group dari segment pertama slug saat group kosong', function () {
    $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Approve Cuti Langsung',
            'slug' => 'cuti.pengajuan.approve-langsung',
            // group sengaja tidak diisi
        ]);

    $this->assertDatabaseHas('iam_permissions', [
        'slug' => 'cuti.pengajuan.approve-langsung',
        'group' => 'cuti',
    ]);
});

it('menerima override group eksplisit (tidak auto-derive)', function () {
    $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Read Logs',
            'slug' => 'log.read',
            'group' => 'monitoring', // override
        ]);

    $this->assertDatabaseHas('iam_permissions', [
        'slug' => 'log.read',
        'group' => 'monitoring',
    ]);
});

it('update permission tidak mengubah slug (slug immutable)', function () {
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Awal', 'slug' => 'awal.view', 'group' => 'awal',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->put("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}", [
            'nama' => 'Updated',
            'slug' => 'baru.view', // harus diabaikan
            'group' => 'baru',
        ]);

    $response->assertStatus(302);
    expect($perm->fresh())
        ->slug->toBe('awal.view') // tidak berubah
        ->nama->toBe('Updated');
});
```

**Catatan**: test existing `menolak slug permission duplikat dalam satu aplikasi` pakai slug `pegawai.view` — sudah canonical, tetap berfungsi. Test IDOR pakai slug `read` dan `hacked` — keduanya non-canonical tapi dibuat lewat `$app2->permissions()->create()` (bypass FormRequest), jadi tidak terpengaruh validasi baru.

- [ ] **Step 2: Run test — pastikan gagal**

Run: `vendor/bin/pest tests/Feature/Iam/PermissionControllerTest.php`
Expected: FAIL — test baru gagal (slug `invalid-slug` masih lolos karena belum ada `ValidIamSlug`)

- [ ] **Step 3: Buat StorePermissionRequest**

```php
<?php
// app/Http/Requests/Iam/StorePermissionRequest.php

namespace App\Http\Requests\Iam;

use App\Models\IamApplication;
use App\Rules\ValidIamSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth dijalankan via middleware iam.permission di route
    }

    public function rules(): array
    {
        /** @var IamApplication $aplikasi */
        $aplikasi = $this->route('aplikasi');

        return [
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'slug' => [
                'required', 'string', 'max:120',
                new ValidIamSlug,
                Rule::unique('iam_permissions', 'slug')
                    ->where('iam_application_id', $aplikasi->id),
            ],
            'group'      => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-derive group dari segment pertama slug jika user tidak isi
        if (! $this->filled('group') && $this->filled('slug') && is_string($this->slug)) {
            $segments = explode('.', $this->slug);
            $this->merge(['group' => $segments[0]]);
        }
    }
}
```

- [ ] **Step 4: Buat UpdatePermissionRequest**

```php
<?php
// app/Http/Requests/Iam/UpdatePermissionRequest.php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Slug TIDAK divalidasi di sini karena immutable — gunakan endpoint
     * migrate-slug untuk rename. Field slug yang dikirim akan diabaikan
     * di controller (tidak ada di rules dan tidak masuk validated()).
     */
    public function rules(): array
    {
        return [
            'nama'       => ['required', 'string', 'min:3', 'max:100'],
            'group'      => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 5: Update PermissionController pakai FormRequest baru**

Replace seluruh isi `app/Http/Controllers/Iam/PermissionController.php`:

```php
<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StorePermissionRequest;
use App\Http\Requests\Iam\UpdatePermissionRequest;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class PermissionController extends Controller
{
    public function store(StorePermissionRequest $request, IamApplication $aplikasi): RedirectResponse
    {
        try {
            $aplikasi->permissions()->create($request->validated());

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menambahkan permission. Silakan coba lagi.');
        }
    }

    public function update(UpdatePermissionRequest $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        try {
            $permission->update($request->validated());

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat memperbarui permission. Silakan coba lagi.');
        }
    }

    public function destroy(IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        try {
            $permission->delete();

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus permission. Silakan coba lagi.');
        }
    }
}
```

- [ ] **Step 6: Run test — pastikan semua pass**

Run: `vendor/bin/pest tests/Feature/Iam/PermissionControllerTest.php`
Expected: PASS — semua test (existing + 5 test baru) hijau.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Iam/StorePermissionRequest.php \
        app/Http/Requests/Iam/UpdatePermissionRequest.php \
        app/Http/Controllers/Iam/PermissionController.php \
        tests/Feature/Iam/PermissionControllerTest.php
git commit -m "feat(iam): extract validasi ke FormRequest + ValidIamSlug enforcement"
```

---

## Task 6: Endpoint `migrateSlug` + Route (TDD)

**Files:**
- Modify: `app/Http/Controllers/Iam/PermissionController.php` (tambah method)
- Modify: `routes/web.php` (tambah route)
- Test: `tests/Feature/Iam/PermissionSlugMigrateTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Feature/Iam/PermissionSlugMigrateTest.php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\Pegawai;

beforeEach(function () {
    $this->kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
    $this->admin = Pegawai::factory()->admin()->create();
});

it('migrate slug iam-manage menjadi iam.manage', function () {
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Legacy', 'slug' => 'demo-action', 'group' => 'demo-action',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}/migrate-slug");

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    expect($perm->fresh())
        ->slug->toBe('demo.action')
        ->group->toBe('demo');
});

it('tolak migrate jika tidak ada saran canonical', function () {
    // Slug dengan underscore tidak punya saran (suggestCanonical → null)
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Bad', 'slug' => 'foo_bar', 'group' => 'foo_bar',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}/migrate-slug");

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');

    expect($perm->fresh()->slug)->toBe('foo_bar'); // tidak berubah
});

it('tolak migrate jika ada konflik unique', function () {
    // Sudah ada 'demo.action' canonical
    $this->kepegawaian->permissions()->create([
        'nama' => 'Existing', 'slug' => 'demo.action', 'group' => 'demo',
    ]);
    $legacy = $this->kepegawaian->permissions()->create([
        'nama' => 'Legacy', 'slug' => 'demo-action', 'group' => 'demo-action',
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$legacy->id}/migrate-slug");

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');

    expect($legacy->fresh()->slug)->toBe('demo-action');
});

it('tolak migrate permission milik aplikasi lain (IDOR)', function () {
    $app2 = IamApplication::factory()->create(['is_active' => true]);
    $permApp2 = $app2->permissions()->create([
        'nama' => 'Test', 'slug' => 'foo-bar', 'group' => 'foo-bar',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}/migrate-slug");

    $response->assertStatus(404);
});

it('mencatat audit log saat slug dimigrasi', function () {
    $perm = $this->kepegawaian->permissions()->create([
        'nama' => 'Legacy', 'slug' => 'audit-test', 'group' => 'audit-test',
    ]);

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$perm->id}/migrate-slug");

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'iam.permission',
        'subject_id' => $perm->id,
        'description' => 'slug-migrated',
    ]);
});
```

- [ ] **Step 2: Run test — pastikan gagal**

Run: `vendor/bin/pest tests/Feature/Iam/PermissionSlugMigrateTest.php`
Expected: FAIL — route belum ada (404 untuk semua)

- [ ] **Step 3: Tambah method `migrateSlug` di PermissionController**

Tambahkan di akhir class `PermissionController` (sebelum `}` penutup):

```php
public function migrateSlug(
    IamApplication $aplikasi,
    IamPermission $permission,
    \App\Services\Iam\IamPermissionAuditor $auditor,
): RedirectResponse {
    // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
    abort_unless($permission->iam_application_id === $aplikasi->id, 404);

    $suggested = $auditor->suggestCanonical($permission->slug);
    if (! $suggested) {
        return back()->withErrors([
            'slug' => 'Tidak ada saran canonical untuk slug ini. Edit manual via tabel permission.',
        ]);
    }

    if (! $auditor->isValidSlug($suggested)) {
        return back()->withErrors([
            'slug' => 'Saran canonical tidak valid menurut konvensi. Edit manual.',
        ]);
    }

    // Cek uniqueness dalam aplikasi
    $exists = IamPermission::where('iam_application_id', $aplikasi->id)
        ->where('slug', $suggested)
        ->where('id', '!=', $permission->id)
        ->exists();

    if ($exists) {
        return back()->withErrors([
            'slug' => "Slug '{$suggested}' sudah ada di aplikasi ini. Resolusi konflik manual diperlukan.",
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

    Cache::forget("iam_app:{$aplikasi->slug}");

    return back()->with('success', "Slug dimigrasi: {$before} → {$suggested}");
}
```

- [ ] **Step 4: Tambah route di `routes/web.php`**

Buka `routes/web.php`. Cari group route dengan prefix `iam`. Setelah baris `Route::delete('aplikasi/{aplikasi}/permissions/{permission}', ...)` (sekitar line 83), tambahkan:

```php
Route::post('aplikasi/{aplikasi}/permissions/{permission}/migrate-slug', [PermissionController::class, 'migrateSlug'])
    ->name('aplikasi.permissions.migrate-slug');
```

- [ ] **Step 5: Run test — pastikan pass**

Run: `vendor/bin/pest tests/Feature/Iam/PermissionSlugMigrateTest.php`
Expected: PASS — 5 passed

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Iam/PermissionController.php \
        routes/web.php \
        tests/Feature/Iam/PermissionSlugMigrateTest.php
git commit -m "feat(iam): add endpoint migrate-slug untuk rename slug legacy ke canonical"
```

---

## Task 7: Seeder Canonical Guard (TDD — RED yang sengaja)

**Files:**
- Test: `tests/Feature/Iam/SeederSlugCanonicalTest.php`

Test ini akan **GAGAL** sampai Task 8 selesai (karena `IamSeeder` masih punya `iam-manage`). Ini sengaja sebagai gate.

- [ ] **Step 1: Buat test seeder guard**

```php
<?php
// tests/Feature/Iam/SeederSlugCanonicalTest.php

use App\Services\Iam\IamPermissionAuditor;
use Database\Seeders\CutiPermissionSeeder;
use Database\Seeders\IamSeeder;
use Database\Seeders\PermissionSikepP1Seeder;
use Database\Seeders\PersediaanRoleSeeder;

it('semua slug dari seeder utama canonical', function (string $seederClass) {
    // RefreshDatabase di-handle oleh Pest beforeEach
    // Re-seed seeder yang diuji (idempoten via firstOrCreate)
    $this->seed($seederClass);

    $auditor = app(IamPermissionAuditor::class);
    $nonCanonical = $auditor->findNonCanonical();

    expect($nonCanonical)->toBeEmpty(
        "{$seederClass} masih punya slug non-canonical: "
        . $nonCanonical->pluck('slug')->implode(', ')
    );
})->with([
    'IamSeeder' => IamSeeder::class,
    'CutiPermissionSeeder' => CutiPermissionSeeder::class,
    'PermissionSikepP1Seeder' => PermissionSikepP1Seeder::class,
    'PersediaanRoleSeeder' => PersediaanRoleSeeder::class,
]);
```

- [ ] **Step 2: Run test — pastikan gagal untuk IamSeeder**

Run: `vendor/bin/pest tests/Feature/Iam/SeederSlugCanonicalTest.php`
Expected: FAIL pada dataset `IamSeeder` — error mention `iam-manage`. Dataset lain mungkin lolos jika seeder mereka sudah canonical.

**Catatan**: Jika ada seeder LAIN yang gagal (bukan hanya IamSeeder), catat hasilnya dan akan ditangani di Task 8 step tambahan.

- [ ] **Step 3: Commit test (red state)**

```bash
git add tests/Feature/Iam/SeederSlugCanonicalTest.php
git commit -m "test(iam): add seeder canonical guard (RED — iam-manage gagal sampai rename)"
```

---

## Task 8: Rename `iam-manage` → `iam.manage` + Update Code References

**Files:**
- Modify: `database/seeders/IamSeeder.php`
- Modify: `routes/web.php` (line 64 middleware string)
- Modify: file lain yang refer `iam-manage` (hasil grep)

- [ ] **Step 1: Grep semua referensi `iam-manage` di code**

Run: `rg --no-heading -n "iam-manage" app/ resources/ routes/ config/ database/ tests/`

Catat semua hasil sebagai checklist. Expected setidaknya:
- `database/seeders/IamSeeder.php` (definisi slug)
- `routes/web.php:64` (middleware `iam.permission:iam-manage`)
- `docs/sso-api/rbac-convention.md` (kontekstual reference — biarkan untuk update di Task 14)

- [ ] **Step 2: Update `database/seeders/IamSeeder.php`**

Buka file, cari `'slug' => 'iam-manage'` dan ganti menjadi `'slug' => 'iam.manage'`. Update juga field `group` jika hardcoded.

- [ ] **Step 3: Update `routes/web.php:64`**

Cari: `Route::middleware(['auth', 'verified', 'iam.permission:iam-manage'])->group(function () {`
Ganti: `Route::middleware(['auth', 'verified', 'iam.permission:iam.manage'])->group(function () {`

- [ ] **Step 4: Update reference lain (hasil grep)**

Untuk setiap file dari Step 1 yang bukan dokumen kontekstual:
- Pastikan string `iam-manage` diganti `iam.manage`.
- Untuk file yang ambiguity (mis. komentar lama), gunakan judgment — jika referensi nama permission, ganti; jika sekedar narrative, biarkan.

- [ ] **Step 5: Migrate existing data di tabel `iam_permissions`**

Buat seeder one-shot atau jalankan tinker:

```
php artisan tinker --execute="
\App\Models\IamPermission::where('slug', 'iam-manage')->update([
    'slug' => 'iam.manage',
    'group' => 'iam',
]);
echo 'Updated: '.\App\Models\IamPermission::where('slug', 'iam.manage')->count();
"
```

Expected output: `Updated: 1`

**Catatan**: ini operasi di environment dev. Untuk production, prosedurnya: deploy code → jalankan `php artisan db:seed --class=IamSeeder` (idempoten, akan create `iam.manage` baru) → eksekusi rename manual via tinker atau UI tombol Migrate setelah Task 13 selesai.

- [ ] **Step 6: Run test seeder guard — pastikan pass**

Run: `vendor/bin/pest tests/Feature/Iam/SeederSlugCanonicalTest.php`
Expected: PASS — 4 dataset hijau.

- [ ] **Step 7: Run full test suite untuk regression**

Run: `vendor/bin/pest --filter=Iam`
Expected: tidak ada test yang break karena rename.

- [ ] **Step 8: Commit (terpisah)**

```bash
git add database/seeders/IamSeeder.php routes/web.php
# tambahkan file lain hasil grep
git commit -m "fix(iam): rename slug iam-manage ke iam.manage sesuai konvensi dot-notation"
```

---

## Task 9: Inertia Shared Props untuk Konvensi IAM

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Baca file existing untuk paham struktur**

Run: `cat app/Http/Middleware/HandleInertiaRequests.php`

Identifikasi method `share()` dan struktur return.

- [ ] **Step 2: Tambah shared prop `iam`**

Di dalam return array method `share()`, tambahkan key `iam`:

```php
'iam' => fn () => [
    'slug_pattern'     => config('iam.slug.pattern'),
    'standard_actions' => config('iam.standard_actions'),
    'standard_roles'   => config('iam.standard_roles'),
    'min_segments'     => config('iam.slug.min_segments'),
    'max_segments'     => config('iam.slug.max_segments'),
    'docs_url'         => config('iam.docs_url'),
],
```

- [ ] **Step 3: Verifikasi via dev server**

Run dev server (jika belum jalan): `npm run dev` + `php artisan serve` (atau gunakan setup yang ada).

Buka browser ke halaman IAM, buka DevTools Console, ketik:
```js
JSON.parse(document.getElementById('app').dataset.page).props.iam
```
Expected: object dengan keys `slug_pattern`, `standard_actions`, `standard_roles`, `docs_url`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat(iam): expose konvensi IAM via Inertia shared props"
```

---

## Task 10: Komponen React `<SlugStatusBadge>`

**Files:**
- Create: `resources/js/pages/iam/aplikasi/components/slug-status-badge.tsx`

- [ ] **Step 1: Buat komponen**

```tsx
// resources/js/pages/iam/aplikasi/components/slug-status-badge.tsx
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';

interface Props {
    slug: string;
}

export function SlugStatusBadge({ slug }: Props) {
    // Akses regex dari Inertia shared props
    const iam = (usePage().props as { iam: { slug_pattern: string } }).iam;
    const regex = new RegExp(iam.slug_pattern);
    const isValid = regex.test(slug);

    if (isValid) {
        return (
            <Badge variant="outline" className="border-green-500 text-green-700 dark:text-green-400">
                <CheckCircle2 className="mr-1 h-3 w-3" />
                Canonical
            </Badge>
        );
    }

    const reason = !slug.includes('.')
        ? 'Tidak ada titik pemisah'
        : /[A-Z]/.test(slug)
            ? 'Mengandung uppercase'
            : slug.includes('_')
                ? 'Underscore tidak diizinkan'
                : 'Format tidak sesuai konvensi';

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-400">
                        <AlertTriangle className="mr-1 h-3 w-3" />
                        Legacy
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    <p className="text-xs">{reason}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
```

- [ ] **Step 2: Verifikasi tidak ada TypeScript error**

Run: `npm run lint` atau `npx tsc --noEmit`
Expected: no errors di file ini.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/iam/aplikasi/components/slug-status-badge.tsx
git commit -m "feat(iam-ui): add SlugStatusBadge component"
```

---

## Task 11: Komponen React `<ConventionHelpPanel>`

**Files:**
- Create: `resources/js/pages/iam/aplikasi/components/convention-help-panel.tsx`

- [ ] **Step 1: Buat komponen**

```tsx
// resources/js/pages/iam/aplikasi/components/convention-help-panel.tsx
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink, Info } from 'lucide-react';
import { useState } from 'react';

export function ConventionHelpPanel() {
    const [open, setOpen] = useState(false);
    const iam = (usePage().props as {
        iam: {
            standard_actions: Record<string, string>;
            docs_url: string;
        };
    }).iam;

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="rounded-md border bg-muted/30 p-3">
            <CollapsibleTrigger asChild>
                <Button variant="ghost" size="sm" className="w-full justify-between p-2">
                    <span className="flex items-center gap-2 text-sm font-medium">
                        <Info className="h-4 w-4" />
                        Lihat konvensi lengkap
                    </span>
                    <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-3 space-y-3 text-sm">
                <div>
                    <p className="font-medium">Format slug:</p>
                    <ul className="ml-4 list-disc text-muted-foreground">
                        <li><code className="font-mono">{`{resource}.{action}`}</code> — mis. <code className="font-mono">pegawai.view</code></li>
                        <li><code className="font-mono">{`{module}.{resource}.{action}`}</code> — mis. <code className="font-mono">cuti.pengajuan.create</code></li>
                    </ul>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Lowercase, antar-segment titik, antar-kata strip. Tidak boleh underscore atau uppercase.
                    </p>
                </div>

                <div>
                    <p className="font-medium">Action standar:</p>
                    <div className="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-muted-foreground sm:grid-cols-3">
                        {Object.entries(iam.standard_actions).map(([key, desc]) => (
                            <div key={key}>
                                <code className="font-mono text-foreground">{key}</code>
                                <span className="ml-1 text-muted-foreground">— {desc}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="border-t pt-2">
                    <a
                        href={iam.docs_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        Buka dokumen konvensi lengkap
                        <ExternalLink className="h-3 w-3" />
                    </a>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
```

- [ ] **Step 2: Cek `@/components/ui/collapsible` tersedia**

Run: `ls resources/js/components/ui/collapsible.tsx`
Expected: file exists.

Jika tidak ada, install shadcn collapsible: `npx shadcn@latest add collapsible`. Catat ini sebagai dependency tambahan.

- [ ] **Step 3: Run type check**

Run: `npx tsc --noEmit` (atau `npm run lint`)
Expected: no errors di file ini.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/iam/aplikasi/components/convention-help-panel.tsx
# Tambahkan collapsible.tsx jika baru di-install
git commit -m "feat(iam-ui): add ConventionHelpPanel collapsible component"
```

---

## Task 12: Komponen React `<PermissionFormFields>` (Hybrid Builder)

**Files:**
- Create: `resources/js/pages/iam/aplikasi/components/permission-form-fields.tsx`

- [ ] **Step 1: Buat komponen**

```tsx
// resources/js/pages/iam/aplikasi/components/permission-form-fields.tsx
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConventionHelpPanel } from './convention-help-panel';

interface Props {
    data: {
        nama: string;
        slug: string;
        group: string;
        keterangan: string;
    };
    setData: <K extends keyof Props['data']>(key: K, value: Props['data'][K]) => void;
    errors: Partial<Record<keyof Props['data'], string>>;
    disabled?: boolean;
}

export function PermissionFormFields({ data, setData, errors, disabled }: Props) {
    const iam = (
        usePage().props as {
            iam: {
                slug_pattern: string;
                standard_actions: Record<string, string>;
            };
        }
    ).iam;
    const regex = useMemo(() => new RegExp(iam.slug_pattern), [iam.slug_pattern]);

    const [mode, setMode] = useState<'builder' | 'free'>('builder');
    const [resource, setResource] = useState('');
    const [subResource, setSubResource] = useState('');
    const [action, setAction] = useState('view');
    const [customAction, setCustomAction] = useState('');

    // Build slug dari builder fields
    const builderSlug = useMemo(() => {
        const finalAction = action === '__custom__' ? customAction : action;
        return [resource, subResource, finalAction].filter(Boolean).join('.');
    }, [resource, subResource, action, customAction]);

    // Sync ke parent data.slug saat builder mode
    useEffect(() => {
        if (mode === 'builder') {
            setData('slug', builderSlug);
            setData('group', builderSlug.split('.')[0] ?? '');
        }
    }, [builderSlug, mode, setData]);

    const isValid = regex.test(data.slug);
    const group = data.slug.split('.')[0] ?? '';

    return (
        <div className="grid gap-4 py-4">
            {/* Mode toggle */}
            <RadioGroup
                value={mode}
                onValueChange={(v) => {
                    if (v === 'free') {
                        // Pindah dari builder ke free: copy slug builder ke free input
                        setData('slug', builderSlug);
                    }
                    setMode(v as 'builder' | 'free');
                }}
                className="flex gap-4"
                disabled={disabled}
            >
                <div className="flex items-center gap-2">
                    <RadioGroupItem value="builder" id="mode-builder" />
                    <Label htmlFor="mode-builder" className="cursor-pointer text-sm font-normal">
                        Builder (disarankan)
                    </Label>
                </div>
                <div className="flex items-center gap-2">
                    <RadioGroupItem value="free" id="mode-free" />
                    <Label htmlFor="mode-free" className="cursor-pointer text-sm font-normal">
                        Mode ahli (free-input slug)
                    </Label>
                </div>
            </RadioGroup>

            {/* Builder Mode */}
            {mode === 'builder' && (
                <div className="grid gap-3 rounded-md border bg-muted/20 p-3">
                    <div className="grid gap-2">
                        <Label htmlFor="resource">Resource</Label>
                        <Input
                            id="resource"
                            value={resource}
                            onChange={(e) => setResource(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                            placeholder="contoh: pegawai, cuti, barang"
                            className="font-mono"
                            disabled={disabled}
                        />
                        <p className="text-xs text-muted-foreground">
                            Resource utama. Lowercase, kebab-case. Auto-sanitize saat ketik.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="sub-resource">Sub-resource <span className="text-muted-foreground">(opsional)</span></Label>
                        <Input
                            id="sub-resource"
                            value={subResource}
                            onChange={(e) => setSubResource(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                            placeholder="contoh: pengajuan, usulan"
                            className="font-mono"
                            disabled={disabled}
                        />
                        <p className="text-xs text-muted-foreground">
                            Untuk modul kompleks (mis. <code>cuti.pengajuan</code>). Boleh kosong.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="action">Action</Label>
                        <Select value={action} onValueChange={setAction} disabled={disabled}>
                            <SelectTrigger id="action">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(iam.standard_actions).map(([key, desc]) => (
                                    <SelectItem key={key} value={key}>
                                        <span className="font-mono">{key}</span>
                                        <span className="ml-2 text-xs text-muted-foreground">{desc}</span>
                                    </SelectItem>
                                ))}
                                <SelectItem value="__custom__">
                                    <span className="italic">Custom...</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        {action === '__custom__' && (
                            <Input
                                value={customAction}
                                onChange={(e) => setCustomAction(e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                                placeholder="contoh: tanda-tangan-ketua"
                                className="font-mono"
                                disabled={disabled}
                            />
                        )}
                    </div>

                    <div className="rounded-md bg-background p-2 text-sm">
                        <span className="text-muted-foreground">Preview slug:</span>{' '}
                        <code className="font-mono">{builderSlug || '—'}</code>{' '}
                        {builderSlug && (isValid ? (
                            <CheckCircle2 className="ml-1 inline h-4 w-4 text-green-600" />
                        ) : (
                            <AlertCircle className="ml-1 inline h-4 w-4 text-amber-600" />
                        ))}
                        <div className="mt-1 text-xs text-muted-foreground">
                            Group: <code className="font-mono">{group || '—'}</code> (auto)
                        </div>
                    </div>
                </div>
            )}

            {/* Free-input Mode */}
            {mode === 'free' && (
                <div className="grid gap-2 rounded-md border bg-muted/20 p-3">
                    <Label htmlFor="perm-slug-free">Slug</Label>
                    <Input
                        id="perm-slug-free"
                        value={data.slug}
                        onChange={(e) => {
                            setData('slug', e.target.value);
                            setData('group', e.target.value.split('.')[0] ?? '');
                        }}
                        placeholder="contoh: cuti.pengajuan.approve-langsung"
                        className="font-mono"
                        disabled={disabled}
                    />
                    {data.slug && (
                        <p className={`text-xs ${isValid ? 'text-green-600' : 'text-amber-700'}`}>
                            {isValid ? (
                                <><CheckCircle2 className="inline h-3 w-3" /> Sesuai konvensi</>
                            ) : (
                                <><AlertCircle className="inline h-3 w-3" /> Format tidak match regex konvensi</>
                            )}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Format: <code className="font-mono">{`{resource}.{action}`}</code> atau{' '}
                        <code className="font-mono">{`{module}.{resource}.{action}`}</code>
                    </p>
                </div>
            )}

            {errors.slug && (
                <p className="text-sm text-destructive">{errors.slug}</p>
            )}

            {/* Nama (selalu tampil) */}
            <div className="grid gap-2">
                <Label htmlFor="perm-nama">Nama Permission</Label>
                <Input
                    id="perm-nama"
                    value={data.nama}
                    onChange={(e) => setData('nama', e.target.value)}
                    placeholder="contoh: Lihat Daftar Pegawai"
                    disabled={disabled}
                    required
                />
                <p className="text-xs text-muted-foreground">
                    Nama deskriptif dalam Bahasa Indonesia.
                </p>
                {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
            </div>

            {/* Keterangan */}
            <div className="grid gap-2">
                <Label htmlFor="perm-keterangan">Keterangan <span className="text-muted-foreground">(opsional)</span></Label>
                <Input
                    id="perm-keterangan"
                    value={data.keterangan}
                    onChange={(e) => setData('keterangan', e.target.value)}
                    placeholder="Deskripsi singkat untuk audit/dokumentasi"
                    disabled={disabled}
                />
                {errors.keterangan && <p className="text-sm text-destructive">{errors.keterangan}</p>}
            </div>

            {/* Help panel */}
            <ConventionHelpPanel />
        </div>
    );
}

PermissionFormFields.isSlugValid = (slug: string, pattern: string): boolean => {
    return new RegExp(pattern).test(slug);
};
```

- [ ] **Step 2: Cek dependencies UI components**

Run: `ls resources/js/components/ui/radio-group.tsx resources/js/components/ui/select.tsx`
Expected: kedua file ada.

Jika tidak: `npx shadcn@latest add radio-group select`.

- [ ] **Step 3: Type check**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/iam/aplikasi/components/permission-form-fields.tsx
# Tambah radio-group.tsx / select.tsx jika baru di-install
git commit -m "feat(iam-ui): add PermissionFormFields hybrid builder component"
```

---

## Task 13: Integrasi ke `show.tsx` (Banner + Kolom Status + Replace Form)

**Files:**
- Modify: `resources/js/pages/iam/aplikasi/show.tsx`
- Modify: `app/Http/Controllers/Iam/AplikasiController.php` (tambah props audit)

- [ ] **Step 1: Tambah props `permission_audit` di AplikasiController@show**

Buka `app/Http/Controllers/Iam/AplikasiController.php`, cari method `show($aplikasi)`. Tambahkan injection `IamPermissionAuditor` dan kirim count ke Inertia:

```php
// Di awal method show:
use App\Services\Iam\IamPermissionAuditor; // di top file

public function show(IamApplication $aplikasi, IamPermissionAuditor $auditor): Response
{
    // ... existing code ...

    $nonCanonicalCount = $auditor->findNonCanonical()
        ->filter(fn ($p) => $p['app'] === $aplikasi->slug)
        ->count();

    return Inertia::render('iam/aplikasi/show', [
        // ... existing props ...
        'permission_audit' => [
            'non_canonical_count' => $nonCanonicalCount,
        ],
    ]);
}
```

**Catatan**: jika struktur method sudah berbeda, sesuaikan dengan pattern yang ada. Pastikan return tidak break existing functionality.

- [ ] **Step 2: Update `show.tsx` — tambah banner di tab Permissions**

Buka `resources/js/pages/iam/aplikasi/show.tsx`. Cari `<TabsContent value="permissions">`. Di awal `<div className="flex flex-col gap-4">`, tambahkan banner:

```tsx
{(props.permission_audit?.non_canonical_count ?? 0) > 0 && (
    <Alert variant="default" className="border-amber-500 bg-amber-50 dark:bg-amber-950/30">
        <AlertTriangle className="h-4 w-4 text-amber-600" />
        <AlertTitle>Ada permission yang melanggar konvensi</AlertTitle>
        <AlertDescription>
            {props.permission_audit.non_canonical_count} permission di aplikasi ini punya slug non-canonical.
            Cek kolom "Status" pada list, atau jalankan{' '}
            <code className="font-mono text-xs">php artisan iam:audit-slugs</code> untuk laporan lengkap.
        </AlertDescription>
    </Alert>
)}
```

Pastikan import `Alert`, `AlertTitle`, `AlertDescription` dari `@/components/ui/alert` dan `AlertTriangle` dari `lucide-react`. Tambah `permission_audit` ke type props di file.

- [ ] **Step 3: Tambah kolom Status di tabel Permissions**

Cari `<TableHeader>` di dalam tab Permissions. Tambah `<TableHead>Status</TableHead>` sebelum kolom Aksi.

Di `<TableBody>` `<TableRow>`, tambah `<TableCell>` baru:

```tsx
<TableCell>
    <div className="flex items-center gap-2">
        <SlugStatusBadge slug={permission.slug} />
        {/* Tombol Migrate jika legacy */}
        <SlugMigrateButton
            aplikasiId={aplikasi.id}
            permissionId={permission.id}
            slug={permission.slug}
        />
    </div>
</TableCell>
```

Import `SlugStatusBadge` dari komponen yang dibuat di Task 10. `SlugMigrateButton` akan dibuat di step berikut.

- [ ] **Step 4: Buat komponen inline `SlugMigrateButton` (kecil, di file yang sama atau extract)**

Untuk simplicity, buat sebagai file terpisah:

```tsx
// resources/js/pages/iam/aplikasi/components/slug-migrate-button.tsx
import { Button } from '@/components/ui/button';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader,
    AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { router, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    aplikasiId: string;
    permissionId: string;
    slug: string;
}

export function SlugMigrateButton({ aplikasiId, permissionId, slug }: Props) {
    const [open, setOpen] = useState(false);
    const iam = (usePage().props as { iam: { slug_pattern: string } }).iam;
    const regex = new RegExp(iam.slug_pattern);

    const suggested = useMemo(() => {
        if (regex.test(slug)) return null;
        if (slug.includes('.') || slug.includes('_') || !slug.includes('-')) return null;
        const pos = slug.lastIndexOf('-');
        const candidate = slug.substring(0, pos) + '.' + slug.substring(pos + 1);
        return regex.test(candidate) ? candidate : null;
    }, [slug, regex]);

    if (!suggested) return null;

    const handleMigrate = () => {
        router.post(
            `/iam/aplikasi/${aplikasiId}/permissions/${permissionId}/migrate-slug`,
            {},
            { onFinish: () => setOpen(false) },
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <Button size="sm" variant="outline" className="h-7 gap-1 text-xs">
                    Migrate <ArrowRight className="h-3 w-3" /> <code className="font-mono">{suggested}</code>
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Konfirmasi Migrasi Slug</AlertDialogTitle>
                    <AlertDialogDescription className="space-y-2">
                        <span>
                            Ubah slug: <code className="font-mono">{slug}</code> →{' '}
                            <code className="font-mono">{suggested}</code>
                        </span>
                        <span className="block">Akan dilakukan:</span>
                        <ol className="ml-4 list-decimal text-sm">
                            <li>Rename slug di tabel iam_permissions</li>
                            <li>Group auto-update jadi <code className="font-mono">{suggested.split('.')[0]}</code></li>
                            <li>Audit log mencatat perubahan</li>
                        </ol>
                        <span className="block rounded-md border border-amber-400 bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                            ⚠ Reference di kode (route middleware, policy) yang masih pakai{' '}
                            <code className="font-mono">{slug}</code> HARUS di-grep & update manual oleh developer.
                            Migrasi ini hanya mengubah database.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction onClick={handleMigrate}>Ya, Migrate</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
```

- [ ] **Step 5: Replace inline form Tambah Permission dengan `<PermissionFormFields>`**

Di `show.tsx`, cari `<Dialog open={showAddPermissionDialog}...>` block (sekitar line 559-731). Replace seluruh `<form>` content (4 grid fields yang ada) dengan satu pemanggilan komponen:

```tsx
<form onSubmit={(e) => { e.preventDefault(); handleAddPermission(); }}>
    {Object.keys(addPermissionForm.errors).length > 0 && (
        <AlertError errors={errorsToArray(addPermissionForm.errors)} title="Gagal menambahkan permission" />
    )}
    <PermissionFormFields
        data={addPermissionForm.data}
        setData={addPermissionForm.setData}
        errors={addPermissionForm.errors}
        disabled={addPermissionForm.processing}
    />
    <DialogFooter>
        <Button variant="outline" type="button" onClick={() => setShowAddPermissionDialog(false)}>
            Batal
        </Button>
        <Button
            type="submit"
            disabled={
                addPermissionForm.processing
                || !addPermissionForm.data.slug
                || !PermissionFormFields.isSlugValid(addPermissionForm.data.slug, iam.slug_pattern)
            }
        >
            {addPermissionForm.processing ? 'Menyimpan...' : 'Simpan'}
        </Button>
    </DialogFooter>
</form>
```

Tambah import:
```tsx
import { PermissionFormFields } from './components/permission-form-fields';
```

Akses `iam` shared props: di komponen atas, ambil via `usePage().props.iam`.

- [ ] **Step 6: Verifikasi build frontend tidak error**

Run: `npm run build`
Expected: build sukses tanpa error TypeScript.

- [ ] **Step 7: Manual test di browser**

1. Jalankan dev server: `npm run dev` + `php artisan serve` (atau sesuai setup).
2. Login sebagai admin.
3. Buka halaman aplikasi IAM (mis. `/iam/aplikasi/<id-kepegawaian>`).
4. Klik tab Permissions:
   - **Verifikasi**: Banner muncul jika ada legacy (mis. saat data uji punya `demo-action`).
   - **Verifikasi**: Kolom Status menampilkan badge canonical/legacy.
   - **Verifikasi**: Tombol Migrate muncul untuk slug legacy yang punya saran.
5. Klik **Tambah Permission**:
   - **Verifikasi**: Mode Builder aktif default.
   - **Verifikasi**: Pilih resource `demo`, action `view` → preview slug `demo.view` ✅.
   - **Verifikasi**: Coba submit kosong → tombol Simpan disabled.
   - **Verifikasi**: Toggle Mode ahli → input `invalid-slug` → indicator merah, Simpan disabled.
   - **Verifikasi**: Input `valid.slug` di Mode ahli → indicator hijau, Simpan aktif.
6. Klik Migrate pada row legacy:
   - **Verifikasi**: Dialog konfirmasi muncul.
   - **Verifikasi**: Setelah konfirmasi, slug di DB ter-update, kolom Status jadi canonical.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/iam/aplikasi/show.tsx \
        resources/js/pages/iam/aplikasi/components/slug-migrate-button.tsx \
        app/Http/Controllers/Iam/AplikasiController.php
git commit -m "feat(iam-ui): integrate PermissionFormFields + banner audit + kolom status di show"
```

---

## Task 14: Command `php artisan iam:audit-slugs`

**Files:**
- Create: `app/Console/Commands/Iam/AuditSlugsCommand.php`

- [ ] **Step 1: Buat command**

```php
<?php
// app/Console/Commands/Iam/AuditSlugsCommand.php

namespace App\Console\Commands\Iam;

use App\Services\Iam\IamPermissionAuditor;
use Illuminate\Console\Command;

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
            $items = $items->filter(fn ($p) => $p['app'] === $appSlug)->values();
        }

        if ($items->isEmpty()) {
            $this->info('✅ Semua slug permission canonical. Tidak ada yang perlu di-migrate.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($items->toJson(JSON_PRETTY_PRINT));

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
        $this->line('Migrate via UI: halaman aplikasi IAM → tab Permissions → tombol [Migrate]');
        $this->line('Atau edit manual jika kasus kompleks.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Test manual command**

Setup data uji (jika belum ada legacy):
```
php artisan tinker --execute="
\$app = \App\Models\IamApplication::first();
\$app->permissions()->firstOrCreate(['slug' => 'demo-action'], ['nama' => 'Demo', 'group' => 'demo']);
"
```

Run: `php artisan iam:audit-slugs`
Expected output:
```
Ditemukan 1 slug non-canonical:
+--------------+---------------+--------------------------+-----------------+
| App          | Slug Sekarang | Alasan                   | Saran Canonical |
+--------------+---------------+--------------------------+-----------------+
| kepegawaian  | demo-action   | Tidak ada titik pemisah  | demo.action     |
+--------------+---------------+--------------------------+-----------------+
```

Run: `php artisan iam:audit-slugs --json`
Expected: valid JSON array dengan 1 entry.

Run: `php artisan iam:audit-slugs --app=tidak-ada`
Expected: `✅ Semua slug permission canonical.`

- [ ] **Step 3: Cleanup data uji**

```
php artisan tinker --execute="\App\Models\IamPermission::where('slug', 'demo-action')->forceDelete();"
```

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/Iam/AuditSlugsCommand.php
git commit -m "feat(iam): add command iam:audit-slugs untuk audit slug non-canonical"
```

---

## Task 15: Update Dokumentasi `rbac-convention.md` Pasal 9

**Files:**
- Modify: `docs/sso-api/rbac-convention.md`

- [ ] **Step 1: Update pasal 9 (Referensi)**

Buka file, cari pasal `## 9. Referensi`. Replace isi pasal menjadi:

```markdown
## 9. Referensi

### Sumber kebenaran (machine-readable)

- **Config**: `config/iam.php` — pola regex slug, daftar action standar, daftar role standar
- **Validator**: `app/Rules/ValidIamSlug.php` — rule object dipakai di FormRequest, seeder validation, dan audit
- **Service**: `app/Services/Iam/IamPermissionAuditor.php` — audit + suggest canonical
- **Command**: `php artisan iam:audit-slugs` — laporan slug non-canonical
- **FormRequest**: `app/Http/Requests/Iam/StorePermissionRequest.php` (validasi keras saat create)

### Migration & seeder

- Migration: `database/migrations/2026_03_21_000001_create_iam_tables.php`
- Seeder template: `database/seeders/IamSeeder.php`, `PersediaanRoleSeeder.php`, `CutiPermissionSeeder.php`, `PermissionSikepP1Seeder.php`

### Endpoint & API

- HMAC API contract: `docs/sso-api/authentication.md`
- Endpoint validate: `docs/sso-api/endpoints.md`
- Endpoint migrate-slug (UI internal): `POST /iam/aplikasi/{aplikasi}/permissions/{permission}/migrate-slug`

### Test guard

- `tests/Feature/Iam/SeederSlugCanonicalTest.php` — regression guard semua seeder utama
- `tests/Unit/Rules/ValidIamSlugTest.php` — unit test validator
- `tests/Unit/Services/Iam/IamPermissionAuditorTest.php` — unit test auditor

### Design doc

- `docs/superpowers/specs/2026-05-16-iam-informative-design.md` — design rationale modul IAM informatif (2026-05-16)
```

- [ ] **Step 2: Update bagian 6.1 menyebutkan `iam.manage` bukan `iam-manage`**

Cari di pasal 6.1 (Aplikasi kepegawaian), baris yang menyebut `iam-manage`. Ganti menjadi `iam.manage`.

- [ ] **Step 3: Commit**

```bash
git add docs/sso-api/rbac-convention.md
git commit -m "docs(iam): update rbac-convention referensi & rename iam-manage di contoh"
```

---

## Task 16: Final Verification

- [ ] **Step 1: Jalankan semua test suite IAM**

Run: `vendor/bin/pest --filter=Iam`
Expected: semua hijau (Unit + Feature).

- [ ] **Step 2: Jalankan `iam:audit-slugs` — harus clean**

Run: `php artisan iam:audit-slugs`
Expected: `✅ Semua slug permission canonical.`

- [ ] **Step 3: Grep final untuk memastikan `iam-manage` tidak tersisa (kecuali doc historis)**

Run: `rg "iam-manage" app/ routes/ config/ database/ resources/`
Expected: tidak ada hit di file fungsional. Hit di docs OK jika kontekstual narrative.

- [ ] **Step 4: Build frontend production untuk regression**

Run: `npm run build`
Expected: no TypeScript errors, build sukses.

- [ ] **Step 5: Manual smoke test di browser**

Ulangi checklist dari Task 13 Step 7 sebagai sanity check final.

- [ ] **Step 6: Cek code review readiness**

Run: `git log --oneline main..HEAD` (atau di feature branch saat ini)
Expected: 14-16 commit granular, masing-masing reversible.

---

## Definition of Done (dari spec, untuk tracking)

- [ ] `config/iam.php` ada dan terisi
- [ ] `ValidIamSlug` lulus semua test (8 cases)
- [ ] `IamPermissionAuditor` lulus semua test (8 cases)
- [ ] FormRequest Permission validate slug — test pass
- [ ] Endpoint migrate-slug lulus test (5 cases termasuk IDOR + audit log)
- [ ] Test seeder canonical hijau (4 seeder)
- [ ] `iam-manage` sudah rename di seeder & code
- [ ] `php artisan iam:audit-slugs` keluar exit 0 clean
- [ ] Form Permission UI mode Builder + Free-input + validasi real-time
- [ ] Banner audit muncul di tab Permissions
- [ ] Tombol Migrate per row berfungsi (manual test)
- [ ] `rbac-convention.md` pasal 9 di-update
- [ ] (Optional) Code review pass via `superpowers:requesting-code-review`
