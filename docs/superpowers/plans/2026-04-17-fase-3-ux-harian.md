# Fase 3: UX Harian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementasi 4 fitur UX Harian: upload foto pegawai, dashboard skeleton loading, filter lengkap monitoring KGB & KP, dan tampil foto di self-service.

**Architecture:** Item 3.1 (foto pegawai) menjadi prerequisite item 3.4 (foto self-service) karena `foto_url` accessor yang ditambahkan di model akan digunakan di kedua fitur. Item 3.2 (dashboard skeleton) dan 3.3 (filter monitoring) berdiri sendiri dan bisa dikerjakan setelah 3.1 selesai.

**Tech Stack:** Laravel 11 + Pest PHP (backend), React 18 + TypeScript + Inertia.js v2 + Tailwind (frontend), `intervention/image:^3.0` (resize foto), Inertia Deferred Props (dashboard skeleton), Wayfinder (route helpers).

---

## Urutan Implementasi

```
3.1 Upload Foto Pegawai
  └── 3.4 Foto Self-Service (depends on 3.1)
3.2 Dashboard Skeleton (independent)
3.3 Filter Monitoring (independent)
```

---

## Item 3.1: Upload & Tampil Foto Pegawai

### File Mapping

| File | Action |
|------|--------|
| `app/Http/Requests/Kepegawaian/UpdateFotoPegawaiRequest.php` | Create |
| `app/Models/Pegawai.php` | Modify — tambah `foto_url` accessor + `$appends` |
| `app/Http/Controllers/Kepegawaian/PegawaiController.php` | Modify — tambah `updateFoto` method |
| `routes/web.php` | Modify — tambah route `kepegawaian.pegawai.foto.update` |
| `resources/js/types/kepegawaian.ts` | Modify — tambah `foto_url` ke `Pegawai` type |
| `resources/js/types/pegawai-detail.ts` | Modify — tambah `foto_url` ke `PegawaiDetail` type |
| `resources/js/components/pegawai/FotoUpload.tsx` | Create |
| `resources/js/pages/kepegawaian/pegawai/show.tsx` | Modify — ganti `foto` → `foto_url`, tambah `FotoUpload` |
| `resources/js/pages/kepegawaian/pegawai/index.tsx` | Modify — tambah avatar thumbnail di tabel |
| `tests/Feature/Kepegawaian/FotoPegawaiTest.php` | Create |

---

### Task 1.0: Install `intervention/image`

**Files:**
- Modify: `composer.json` (via composer require)

- [ ] **Step 1: Install package**

```bash
cd /Volumes/Dev/Projects/kepegawaian_apps
composer require intervention/image:^3.0
```

Expected: `Package operations: 1 install` dan `intervention/image` muncul di `composer.json`.

- [ ] **Step 2: Verifikasi instalasi**

```bash
php artisan about | grep -i intervention
```

Expected: Tidak ada error.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: install intervention/image ^3.0 untuk resize foto pegawai"
```

---

### Task 1.1: `foto_url` Accessor di Model Pegawai (TDD)

**Files:**
- Test: `tests/Feature/Kepegawaian/FotoPegawaiTest.php`
- Modify: `app/Models/Pegawai.php`

- [ ] **Step 1: Tulis failing test**

Buat file `tests/Feature/Kepegawaian/FotoPegawaiTest.php`:

```php
<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;

test('foto_url returns null when foto is null', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    expect($pegawai->foto_url)->toBeNull();
});

test('foto_url returns full storage URL when foto path is set', function () {
    Storage::fake('public');
    Storage::disk('public')->put('fotos/test.webp', 'fake-content');

    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/test.webp']);

    expect($pegawai->foto_url)
        ->toBeString()
        ->toContain('fotos/test.webp');
});

test('foto_url is included in model serialization via appends', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    $array = $pegawai->toArray();

    expect($array)->toHaveKey('foto_url');
});
```

- [ ] **Step 2: Jalankan test — harus FAIL**

```bash
php artisan test tests/Feature/Kepegawaian/FotoPegawaiTest.php
```

Expected: FAIL — `foto_url` property belum ada.

- [ ] **Step 3: Implementasi accessor di `app/Models/Pegawai.php`**

Tambahkan import `Attribute` dan `Storage` di bagian atas file, lalu tambahkan `foto_url` ke `$appends` dan buat accessor.

Di dalam class `Pegawai`, tambahkan:

```php
// Tambahkan ke import di atas file (setelah namespace, setelah use lainnya):
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
```

Di dalam class `Pegawai`, tambahkan `$appends`:

```php
protected $appends = ['foto_url'];
```

Di dalam class `Pegawai`, tambahkan accessor (setelah casts() method):

```php
protected function fotoUrl(): Attribute
{
    return Attribute::make(
        get: fn () => $this->foto !== null
            ? Storage::disk('public')->url($this->foto)
            : null,
    );
}
```

- [ ] **Step 4: Jalankan test — harus PASS**

```bash
php artisan test tests/Feature/Kepegawaian/FotoPegawaiTest.php
```

Expected: 3 tests passed.

- [ ] **Step 5: Jalankan full test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: All tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Pegawai.php tests/Feature/Kepegawaian/FotoPegawaiTest.php
git commit -m "feat: tambah foto_url accessor ke model Pegawai"
```

---

### Task 1.2: UpdateFotoPegawaiRequest + Route + Controller (TDD)

**Files:**
- Test: `tests/Feature/Kepegawaian/FotoPegawaiTest.php` (tambah test baru)
- Create: `app/Http/Requests/Kepegawaian/UpdateFotoPegawaiRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Kepegawaian/PegawaiController.php`

- [ ] **Step 1: Tambah failing tests ke `FotoPegawaiTest.php`**

Tambahkan test-test berikut ke file yang sudah ada:

```php
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('guest tidak bisa upload foto pegawai', function () {
    $pegawai = Pegawai::factory()->create();

    post(route('kepegawaian.pegawai.foto.update', $pegawai))
        ->assertRedirect(route('login'));
});

test('pegawai biasa tidak bisa upload foto pegawai lain', function () {
    $user = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($user);

    post(route('kepegawaian.pegawai.foto.update', $pegawai))
        ->assertForbidden();
});

test('admin dapat upload foto pegawai dengan file valid', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);
    actingAs($admin);

    $file = UploadedFile::fake()->image('foto.jpg', 300, 300);

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertRedirect();

    $pegawai->refresh();
    expect($pegawai->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($pegawai->foto);
});

test('upload foto gagal jika ukuran file lebih dari 2MB', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->create('foto.jpg', 3000, 'image/jpeg');

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertSessionHasErrors('foto');
});

test('upload foto gagal jika bukan file gambar', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertSessionHasErrors('foto');
});

test('upload foto baru menggantikan foto lama di storage', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);
    actingAs($admin);

    // Upload pertama
    $file1 = UploadedFile::fake()->image('foto1.jpg', 300, 300);
    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file1]);

    $pegawai->refresh();
    $path1 = $pegawai->foto;

    // Upload kedua — harus menimpa path yang sama
    $file2 = UploadedFile::fake()->image('foto2.jpg', 300, 300);
    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file2]);

    $pegawai->refresh();
    expect($pegawai->foto)->toBe($path1);
    Storage::disk('public')->assertExists($pegawai->foto);
});
```

- [ ] **Step 2: Jalankan test baru — harus FAIL**

```bash
php artisan test tests/Feature/Kepegawaian/FotoPegawaiTest.php --filter="guest tidak bisa"
```

Expected: FAIL — route `kepegawaian.pegawai.foto.update` belum ada.

- [ ] **Step 3: Buat `UpdateFotoPegawaiRequest`**

Buat file `app/Http/Requests/Kepegawaian/UpdateFotoPegawaiRequest.php`:

```php
<?php

namespace App\Http\Requests\Kepegawaian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFotoPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.required' => 'File foto wajib diunggah.',
            'foto.image'    => 'File harus berupa gambar.',
            'foto.mimes'    => 'Format foto harus JPG, PNG, atau WebP.',
            'foto.max'      => 'Ukuran foto maksimal 2MB.',
        ];
    }
}
```

- [ ] **Step 4: Tambahkan route di `routes/web.php`**

Di dalam blok `Route::middleware(['auth', 'verified', 'iam.permission'])->group(function () {`, tambahkan setelah `Route::resource('kepegawaian/pegawai', PegawaiController::class)`:

```php
Route::post('kepegawaian/pegawai/{pegawai}/foto', [PegawaiController::class, 'updateFoto'])
    ->name('kepegawaian.pegawai.foto.update');
```

- [ ] **Step 5: Tambahkan method `updateFoto` ke `PegawaiController`**

Tambahkan import di atas file:
```php
use App\Http\Requests\Kepegawaian\UpdateFotoPegawaiRequest;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
```

Tambahkan method baru ke dalam class `PegawaiController`:

```php
public function updateFoto(UpdateFotoPegawaiRequest $request, Pegawai $pegawai): RedirectResponse
{
    Gate::authorize('update', $pegawai);

    $path = "fotos/{$pegawai->id}.webp";

    $manager = new ImageManager(new Driver());
    $image = $manager->read($request->file('foto')->getRealPath());
    $image->coverDown(400, 400, 'center');
    $encoded = $image->toWebp(quality: 80);

    Storage::disk('public')->put($path, $encoded->toString());

    $pegawai->update(['foto' => $path]);

    return back();
}
```

- [ ] **Step 6: Jalankan semua test foto — harus PASS**

```bash
php artisan test tests/Feature/Kepegawaian/FotoPegawaiTest.php
```

Expected: 9 tests passed.

- [ ] **Step 7: Generate Wayfinder routes**

```bash
php artisan wayfinder:generate
```

Expected: File route TypeScript di-generate (termasuk `updateFoto` helper).

- [ ] **Step 8: Jalankan full test suite**

```bash
php artisan test
```

Expected: All tests passed.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Kepegawaian/UpdateFotoPegawaiRequest.php \
    app/Http/Controllers/Kepegawaian/PegawaiController.php \
    routes/web.php \
    resources/js/routes/kepegawaian/pegawai/index.ts \
    tests/Feature/Kepegawaian/FotoPegawaiTest.php
git commit -m "feat: implementasi upload foto pegawai (3.1) — validasi, resize WebP, storage"
```

---

### Task 1.3: FotoUpload Component + Update show.tsx

**Files:**
- Modify: `resources/js/types/pegawai-detail.ts`
- Modify: `resources/js/types/kepegawaian.ts`
- Create: `resources/js/components/pegawai/FotoUpload.tsx`
- Modify: `resources/js/pages/kepegawaian/pegawai/show.tsx`

- [ ] **Step 1: Tambah `foto_url` ke TypeScript types**

Di `resources/js/types/pegawai-detail.ts`, tambahkan `foto_url` setelah `foto`:

```typescript
// Di type PegawaiDetail, setelah: foto: string | null;
foto_url: string | null;
```

Di `resources/js/types/kepegawaian.ts`, tambahkan `foto_url` setelah `foto` (sekitar baris 239):

```typescript
// Di type Pegawai, setelah: foto: string | null;
foto_url: string | null;
```

- [ ] **Step 2: Buat `FotoUpload.tsx`**

Buat file `resources/js/components/pegawai/FotoUpload.tsx`:

```tsx
import { router } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

type Props = {
    pegawaiId: string;
    currentFotoUrl: string | null;
    initials: string;
    canUpdate: boolean;
};

export function FotoUpload({ pegawaiId, currentFotoUrl, initials, canUpdate }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setSelectedFile(file);
        setPreviewUrl(URL.createObjectURL(file));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedFile) return;

        const formData = new FormData();
        formData.append('foto', selectedFile);

        setProcessing(true);
        router.post(`/kepegawaian/pegawai/${pegawaiId}/foto`, formData, {
            forceFormData: true,
            onSuccess: () => {
                setPreviewUrl(null);
                setSelectedFile(null);
            },
            onFinish: () => setProcessing(false),
        });
    }

    const displayUrl = previewUrl ?? currentFotoUrl;

    return (
        <form onSubmit={handleSubmit} className="flex flex-col items-center gap-3">
            <div className="relative">
                <Avatar className="h-20 w-20 border-2 border-border">
                    <AvatarImage src={displayUrl ?? undefined} alt={initials} />
                    <AvatarFallback className="text-2xl">{initials}</AvatarFallback>
                </Avatar>
                {canUpdate && (
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        className="absolute bottom-0 right-0 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm"
                        title="Ganti foto"
                    >
                        <Camera className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>
            <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleFileChange}
            />
            {selectedFile !== null && (
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Menyimpan...' : 'Simpan Foto'}
                </Button>
            )}
        </form>
    );
}
```

- [ ] **Step 3: Update `show.tsx` untuk gunakan `FotoUpload` dan `foto_url`**

Di `resources/js/pages/kepegawaian/pegawai/show.tsx`:

1. Tambah import `FotoUpload`:
```tsx
import { FotoUpload } from '@/components/pegawai/FotoUpload';
```

2. Ganti blok `Avatar` yang ada (baris 39-47) dengan `FotoUpload`:
```tsx
<FotoUpload
    pegawaiId={pegawai.id}
    currentFotoUrl={pegawai.foto_url}
    initials={getInitials(pegawai.nama_lengkap)}
    canUpdate={true}
/>
```

- [ ] **Step 4: Update `index.tsx` untuk tampilkan thumbnail foto di tabel**

Di `resources/js/pages/kepegawaian/pegawai/index.tsx`:

1. Tambah import `Avatar`:
```tsx
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
```

2. Di `TableHead` row, tambahkan kolom foto (sebelum kolom NIP):
```tsx
<TableHead className="w-12">Foto</TableHead>
```

3. Di `TableRow` body, tambahkan cell foto sebelum cell NIP:
```tsx
<TableCell>
    <Avatar className="h-8 w-8">
        <AvatarImage src={pegawai.foto_url ?? undefined} alt={pegawai.nama_lengkap} />
        <AvatarFallback className="text-xs">
            {pegawai.nama_lengkap.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase()}
        </AvatarFallback>
    </Avatar>
</TableCell>
```

- [ ] **Step 5: Jalankan type check**

```bash
cd /Volumes/Dev/Projects/kepegawaian_apps && npx tsc --noEmit 2>&1 | head -30
```

Expected: Tidak ada error TypeScript.

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/pegawai-detail.ts \
    resources/js/types/kepegawaian.ts \
    resources/js/components/pegawai/FotoUpload.tsx \
    resources/js/pages/kepegawaian/pegawai/show.tsx \
    resources/js/pages/kepegawaian/pegawai/index.tsx
git commit -m "feat: FotoUpload component, tampil foto di show dan index pegawai (3.1)"
```

---

## Item 3.2: Dashboard Loading State / Skeleton

### File Mapping

| File | Action |
|------|--------|
| `app/Services/DashboardStatService.php` | Modify — split `getStats()` → `getFastStats()` + `getHeavyStats()` |
| `app/Http/Controllers/DashboardController.php` | Modify — gunakan `Inertia::defer()` |
| `resources/js/hooks/use-dashboard-stats.ts` | Modify — split `DashboardStats` type |
| `resources/js/components/dashboard/DashboardDistribusiSkeleton.tsx` | Create |
| `resources/js/components/dashboard/DashboardHeavySection.tsx` | Create |
| `resources/js/pages/dashboard.tsx` | Modify — gunakan `<Deferred>` |
| `tests/Feature/DashboardTest.php` | Modify — update untuk deferred props |

---

### Task 2.1: Split DashboardStatService (TDD)

**Files:**
- Test: `tests/Feature/DashboardTest.php`
- Modify: `app/Services/DashboardStatService.php`
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] **Step 1: Update `DashboardTest.php` — harus FAIL dulu**

Ganti test `dashboard returns required statistics` dan tambah test baru:

```php
<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = Pegawai::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns fastStats langsung dan heavyStats sebagai deferred', function () {
    $user = Pegawai::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('fastStats', fn (AssertableInertia $stats) => $stats
                ->has('total_pegawai_aktif')
                ->has('kgb_segera_count')
                ->has('kp_eligible_count')
                ->has('pegawai_baru_bulan_ini')
            )
            ->missing('heavyStats')
            ->loadDeferredProps(fn (AssertableInertia $deferred) => $deferred
                ->has('heavyStats', fn (AssertableInertia $heavy) => $heavy
                    ->has('distribusi_golongan')
                    ->has('distribusi_unit_kerja')
                    ->has('distribusi_jenis_kelamin')
                    ->has('distribusi_jabatan')
                    ->has('distribusi_pendidikan')
                )
            )
        );
});

test('distribusi golongan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiGolongan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($result)->toBeArray()
        ->and($result)->toHaveKeys(['I', 'II', 'III', 'IV']);
});

test('distribusi jabatan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiJabatan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});

test('distribusi pendidikan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiPendidikan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});
```

- [ ] **Step 2: Jalankan test — harus FAIL**

```bash
php artisan test tests/Feature/DashboardTest.php
```

Expected: FAIL — `fastStats` prop tidak ada, `heavyStats` deferred tidak ada.

- [ ] **Step 3: Tambah `getFastStats()` dan `getHeavyStats()` ke `DashboardStatService`**

Modifikasi `app/Services/DashboardStatService.php` — tambahkan dua method baru dan ubah `getStats()` menjadi wrapper:

```php
public function getStats(): array
{
    return array_merge($this->getFastStats(), $this->getHeavyStats());
}

public function getFastStats(): array
{
    return Cache::remember('dashboard_stats_fast', 300, fn () => [
        'total_pegawai_aktif'    => $this->getTotalPegawaiAktif(),
        'kgb_segera_count'       => $this->getKgbSegeraCount(),
        'kp_eligible_count'      => $this->getKpEligibleCount(),
        'pegawai_baru_bulan_ini' => $this->getPegawaiBaruBulanIni(),
    ]);
}

public function getHeavyStats(): array
{
    return Cache::remember('dashboard_stats_heavy', 300, fn () => [
        'distribusi_golongan'     => $this->getDistribusiGolongan(),
        'distribusi_unit_kerja'   => $this->getDistribusiUnitKerja(),
        'distribusi_jenis_kelamin'=> $this->getDistribusiJenisKelamin(),
        'distribusi_jabatan'      => $this->getDistribusiJabatan(),
        'distribusi_pendidikan'   => $this->getDistribusiPendidikan(),
    ]);
}
```

Hapus cache key lama `dashboard_stats` dari `getStats()` (sudah tidak dipakai).

- [ ] **Step 4: Update `DashboardController` untuk gunakan Inertia::defer**

Ganti isi `app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStatService $service): Response
    {
        return Inertia::render('dashboard', [
            'fastStats' => $service->getFastStats(),
            'heavyStats' => Inertia::defer(fn () => $service->getHeavyStats()),
        ]);
    }
}
```

- [ ] **Step 5: Jalankan DashboardTest — harus PASS**

```bash
php artisan test tests/Feature/DashboardTest.php
```

Expected: 6 tests passed.

- [ ] **Step 6: Jalankan full test suite**

```bash
php artisan test
```

Expected: All tests passed.

- [ ] **Step 7: Commit**

```bash
git add app/Services/DashboardStatService.php \
    app/Http/Controllers/DashboardController.php \
    tests/Feature/DashboardTest.php
git commit -m "feat: split dashboard stats menjadi fastStats (eager) + heavyStats (deferred)"
```

---

### Task 2.2: Dashboard Skeleton Component + Frontend Update

**Files:**
- Modify: `resources/js/hooks/use-dashboard-stats.ts`
- Create: `resources/js/components/dashboard/DashboardDistribusiSkeleton.tsx`
- Create: `resources/js/components/dashboard/DashboardHeavySection.tsx`
- Modify: `resources/js/pages/dashboard.tsx`

- [ ] **Step 1: Split types di `use-dashboard-stats.ts`**

Di `resources/js/hooks/use-dashboard-stats.ts`, split `DashboardStats` menjadi dua type dan update hook signature:

```typescript
export interface FastDashboardStats {
    total_pegawai_aktif: number;
    kgb_segera_count: number;
    kp_eligible_count: number;
    pegawai_baru_bulan_ini: number;
}

export interface HeavyDashboardStats {
    distribusi_golongan: Record<string, number>;
    distribusi_unit_kerja: Array<{ nama: string; pegawai_count: number }>;
    distribusi_jenis_kelamin: Array<{ jenis_kelamin: string; total: number }>;
    distribusi_jabatan: Array<{ nama: string; pegawai_count: number }>;
    distribusi_pendidikan: Array<{ pendidikan: string; pegawai_count: number }>;
}

// DashboardStats tetap ada untuk backward compat (digunakan internal saja)
export type DashboardStats = FastDashboardStats & HeavyDashboardStats;
```

Update signature `useDashboardStats` untuk terima `HeavyDashboardStats`:

```typescript
export function useDashboardStats(
    stats: HeavyDashboardStats,
): DashboardComputedStats {
    // Body tetap sama
}
```

- [ ] **Step 2: Buat `DashboardDistribusiSkeleton.tsx`**

Buat file `resources/js/components/dashboard/DashboardDistribusiSkeleton.tsx`:

```tsx
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function DashboardDistribusiSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <Card key={i} className={i === 1 || i === 2 ? 'col-span-1 lg:col-span-2' : 'col-span-1'}>
                    <CardHeader>
                        <Skeleton className="h-5 w-44" />
                        <Skeleton className="mt-1 h-4 w-32" />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {Array.from({ length: 4 }).map((_, j) => (
                            <div key={j} className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <Skeleton className="h-3.5 w-28" />
                                    <Skeleton className="h-3.5 w-16" />
                                </div>
                                <Skeleton className="h-2 w-full rounded-full" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
```

- [ ] **Step 3: Buat `DashboardHeavySection.tsx`**

Buat file `resources/js/components/dashboard/DashboardHeavySection.tsx` — isinya adalah distribusi cards yang sekarang ada di `dashboard.tsx`, direfaktor menjadi komponen terpisah:

```tsx
import { usePage } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    GraduationCap,
    UserCircle,
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { NumberTicker } from '@/components/ui/number-ticker';
import { Progress } from '@/components/ui/progress';
import { BlurFade } from '@/components/ui/blur-fade';
import { useDashboardStats } from '@/hooks/use-dashboard-stats';
import type { HeavyDashboardStats } from '@/hooks/use-dashboard-stats';

export function DashboardHeavySection() {
    const { heavyStats } = usePage<{ heavyStats: HeavyDashboardStats }>().props;
    const {
        golonganItems,
        unitKerjaItems,
        jabatanItems,
        pendidikanItems,
        jenisKelaminItems,
    } = useDashboardStats(heavyStats);

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <BlurFade delay={0.1} className="col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserCircle className="h-5 w-5 text-accent" />
                            Distribusi Golongan
                        </CardTitle>
                        <CardDescription>Berdasarkan pangkat terakhir</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {golonganItems.map((item) => (
                            <div key={item.golongan} className="space-y-1">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium">Golongan {item.golongan}</span>
                                    <span className="text-muted-foreground">
                                        {item.count} ({item.percentage}%)
                                    </span>
                                </div>
                                <Progress value={item.percentage} className="h-2" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.2} className="col-span-1 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5 text-accent" />
                            Top Unit Kerja
                        </CardTitle>
                        <CardDescription>Berdasarkan jumlah pegawai aktif</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {unitKerjaItems.length > 0 ? (
                            unitKerjaItems.map((item, idx) => (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate pr-4 font-medium" title={item.nama}>
                                            {item.nama}
                                        </span>
                                        <span className="whitespace-nowrap text-muted-foreground">
                                            {item.count} pegawai
                                        </span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                Belum ada data unit kerja
                            </p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.3} className="col-span-1 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Briefcase className="h-5 w-5 text-accent" />
                            Top Jabatan
                        </CardTitle>
                        <CardDescription>Berdasarkan jumlah pegawai aktif</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {jabatanItems.length > 0 ? (
                            jabatanItems.map((item, idx) => (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate pr-4 font-medium" title={item.nama}>
                                            {item.nama}
                                        </span>
                                        <span className="whitespace-nowrap text-muted-foreground">
                                            {item.count} pegawai
                                        </span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                Belum ada data jabatan
                            </p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.4} className="col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GraduationCap className="h-5 w-5 text-accent" />
                            Distribusi Pendidikan
                        </CardTitle>
                        <CardDescription>Berdasarkan pendidikan terakhir</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {pendidikanItems.length > 0 ? (
                            pendidikanItems.map((item, idx) => (
                                <div key={idx} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="truncate pr-4 font-medium">
                                            {item.pendidikan}
                                        </span>
                                        <span className="whitespace-nowrap text-muted-foreground">
                                            {item.count} pegawai
                                        </span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                Belum ada data pendidikan
                            </p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.5} className="col-span-1 md:col-span-2 lg:col-span-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Distribusi Jenis Kelamin</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {jenisKelaminItems.length > 0 ? (
                                jenisKelaminItems.map((item, idx) => (
                                    <div key={idx} className="flex items-center rounded-lg border p-4">
                                        <div className="flex-1 space-y-1">
                                            <p className="text-sm leading-none font-medium">{item.label}</p>
                                            <p className="text-2xl font-bold">
                                                <NumberTicker value={item.total} />
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-sm text-muted-foreground">
                                                {item.percentage}%
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="col-span-2 py-4 text-center text-sm text-muted-foreground">
                                    Belum ada data jenis kelamin
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </BlurFade>
        </div>
    );
}
```

- [ ] **Step 4: Update `dashboard.tsx` untuk gunakan Deferred + komponen baru**

Ganti isi `resources/js/pages/dashboard.tsx`:

```tsx
import { Deferred, Head, Link } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { NumberTicker } from '@/components/ui/number-ticker';
import { BorderBeam } from '@/components/ui/border-beam';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { DashboardDistribusiSkeleton } from '@/components/dashboard/DashboardDistribusiSkeleton';
import { DashboardHeavySection } from '@/components/dashboard/DashboardHeavySection';
import type { FastDashboardStats } from '@/hooks/use-dashboard-stats';
import type { BreadcrumbItem } from '@/types';
import {
    AlertCircle,
    TrendingUp,
    UserPlus,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

interface Props {
    fastStats: FastDashboardStats;
}

export default function Dashboard({ fastStats }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Pegawai Aktif
                            </CardTitle>
                            <Users className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                <NumberTicker value={fastStats.total_pegawai_aktif} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pegawai dengan status aktif
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pegawai Baru (Bulan Ini)
                            </CardTitle>
                            <UserPlus className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                <NumberTicker value={fastStats.pegawai_baru_bulan_ini} />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pegawai masuk bulan ini
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden">
                        <BorderBeam />
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                KGB Segera (≤2 bln)
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-destructive" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    <NumberTicker value={fastStats.kgb_segera_count} />
                                </div>
                                {fastStats.kgb_segera_count > 0 && (
                                    <Badge variant="destructive">Perlu Perhatian</Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                <Link
                                    href="/kepegawaian/monitoring/kgb"
                                    className="text-primary hover:underline"
                                >
                                    Lihat Monitoring KGB
                                </Link>
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="relative overflow-hidden">
                        <BorderBeam />
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">KP Eligible</CardTitle>
                            <TrendingUp className="h-4 w-4 text-accent" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="text-2xl font-bold">
                                    <NumberTicker value={fastStats.kp_eligible_count} />
                                </div>
                                {fastStats.kp_eligible_count > 0 && (
                                    <Badge variant="default" className="bg-accent hover:bg-accent/90">
                                        Eligible
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                <Link
                                    href="/kepegawaian/monitoring/kenaikan-pangkat"
                                    className="text-primary hover:underline"
                                >
                                    Lihat Monitoring KP
                                </Link>
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Deferred data="heavyStats" fallback={<DashboardDistribusiSkeleton />}>
                    <DashboardHeavySection />
                </Deferred>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Jalankan type check**

```bash
npx tsc --noEmit 2>&1 | head -20
```

Expected: Tidak ada error TypeScript.

- [ ] **Step 6: Commit**

```bash
git add resources/js/hooks/use-dashboard-stats.ts \
    resources/js/components/dashboard/DashboardDistribusiSkeleton.tsx \
    resources/js/components/dashboard/DashboardHeavySection.tsx \
    resources/js/pages/dashboard.tsx
git commit -m "feat: dashboard skeleton dengan Inertia Deferred Props (3.2)"
```

---

## Item 3.3: Filter Lengkap di Monitoring KGB & KP

### File Mapping

| File | Action |
|------|--------|
| `app/Services/KgbMonitoringService.php` | Modify — tambah filter `unit_kerja_id`, `golongan`, `status` |
| `app/Services/KenaikanPangkatMonitoringService.php` | Modify — tambah filter `unit_kerja_id`, `golongan` |
| `app/Http/Controllers/Monitoring/MonitoringKgbController.php` | Modify — pass filter params + filter options |
| `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php` | Modify — pass filter params + filter options |
| `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx` | Modify — filter server-side dengan `router.get()` |
| `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx` | Modify — tambah filter unit_kerja + golongan |

---

### Task 3.1: Filter Server-Side KGB (TDD)

**Files:**
- Test: `tests/Feature/Monitoring/KgbMonitoringTest.php` (tambah test filter)
- Modify: `app/Services/KgbMonitoringService.php`
- Modify: `app/Http/Controllers/Monitoring/MonitoringKgbController.php`

- [ ] **Step 1: Tambah failing tests di `KgbMonitoringTest.php`**

Buka `tests/Feature/Monitoring/KgbMonitoringTest.php` dan tambahkan di akhir:

```php
use App\Models\RefUnitKerja;

test('filter unit_kerja_id hanya menampilkan pegawai dari unit kerja tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $unitKerja1 = RefUnitKerja::factory()->create();
    $unitKerja2 = RefUnitKerja::factory()->create();

    $pangkat = RefPangkat::factory()->create(['kode' => 'III/a']);

    // Pegawai unit kerja 1 dengan KGB jatuh tempo
    $pegawai1 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja1->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai1->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    // Pegawai unit kerja 2 dengan KGB jatuh tempo
    $pegawai2 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja2->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai2->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    $service = app(\App\Services\KgbMonitoringService::class);
    $result = $service->getUpcomingKgb(3, 15, $unitKerja1->id);

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawai1->id)
        ->and($ids)->not->toContain($pegawai2->id);
});

test('filter status jatuh-tempo hanya menampilkan KGB yang sudah lewat', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $pangkat = RefPangkat::factory()->create(['kode' => 'III/a']);

    // KGB sudah lewat (tmt 2 tahun + 1 hari yang lalu)
    $pegawaiJatuhTempo = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiJatuhTempo->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->subDay(),
        'is_aktif' => true,
    ]);

    // KGB masih segera (tmt 2 tahun dikurangi 30 hari ke depan)
    $pegawaiSegera = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiSegera->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->addDays(30),
        'is_aktif' => true,
    ]);

    $service = app(\App\Services\KgbMonitoringService::class);
    $result = $service->getUpcomingKgb(3, 15, null, null, 'jatuh-tempo');

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawaiJatuhTempo->id)
        ->and($ids)->not->toContain($pegawaiSegera->id);
});

test('filter golongan hanya menampilkan pegawai dengan golongan tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $pangkatIII = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatIV = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    $pegawaiIII = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIII->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIII->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    $pegawaiIV = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIV->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIV->id,
        'ref_pangkat_id' => $pangkatIV->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    $service = app(\App\Services\KgbMonitoringService::class);
    $result = $service->getUpcomingKgb(3, 15, null, 'III');

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawaiIII->id)
        ->and($ids)->not->toContain($pegawaiIV->id);
});

test('controller kgb meneruskan filter ke service dan kembali ke view', function () {
    $admin = Pegawai::factory()->admin()->create();
    $unitKerja = RefUnitKerja::factory()->create();
    actingAs($admin);

    get(route('monitoring.kgb.index', ['unit_kerja' => $unitKerja->id, 'status' => 'segera']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('filters', fn (Assert $f) => $f
                ->where('unit_kerja', $unitKerja->id)
                ->where('status', 'segera')
                ->etc()
            )
            ->has('filterOptions', fn (Assert $f) => $f
                ->has('unitKerja')
                ->has('golongan')
                ->etc()
            )
        );
});
```

- [ ] **Step 2: Jalankan test baru — harus FAIL**

```bash
php artisan test tests/Feature/Monitoring/KgbMonitoringTest.php --filter="filter"
```

Expected: FAIL — parameter filter belum ada di service.

- [ ] **Step 3: Update `KgbMonitoringService::getUpcomingKgb()` dan `getKgbStats()`**

Modifikasi signature method di `app/Services/KgbMonitoringService.php`:

```php
public function getUpcomingKgb(
    int $months = 3,
    int $perPage = 15,
    ?string $unitKerjaId = null,
    ?string $golongan = null,
    ?string $status = null,
): LengthAwarePaginator {
    $maxSisaHari = $months * 30;
    $driver = DB::connection()->getDriverName();
    $isMySQL = $driver === 'mysql';

    $maxKgbDate = Carbon::today()->addDays($maxSisaHari)->toDateString();
    $today = Carbon::today()->toDateString();

    $kgbDateExpr = $isMySQL
        ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
        : "date(rp_kgb.tmt, '+2 years')";

    $query = Pegawai::query()
        ->select('pegawai.*')
        ->join('riwayat_pangkat as rp_kgb', function ($join) {
            $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                ->where('rp_kgb.is_aktif', true);
        })
        ->with([
            'pangkat',
            'riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt'),
        ])
        ->whereIn('status_pegawai', [
            StatusPegawai::Aktif->value,
            StatusPegawai::MutasiKeluar->value,
        ])
        ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
        ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan));

    if ($isMySQL) {
        $query->whereRaw("DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR) <= ?", [$maxKgbDate]);
    } else {
        $query->whereRaw("date(rp_kgb.tmt, '+2 years') <= ?", [$maxKgbDate]);
    }

    // Filter status (server-side)
    match ($status) {
        'jatuh-tempo' => $query->whereRaw("{$kgbDateExpr} <= ?", [$today]),
        'segera'      => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                            $today,
                            Carbon::today()->addDays(60)->toDateString(),
                         ]),
        'mendekati'   => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                            Carbon::today()->addDays(60)->toDateString(),
                            Carbon::today()->addDays(90)->toDateString(),
                         ]),
        'aman'        => $query->whereRaw("{$kgbDateExpr} > ?", [
                            Carbon::today()->addDays(90)->toDateString(),
                         ]),
        default       => null,
    };

    if ($isMySQL) {
        $query->orderByRaw('DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR) ASC');
    } else {
        $query->orderByRaw("date(rp_kgb.tmt, '+2 years') ASC");
    }

    return $query->paginate($perPage)
        ->through(function (Pegawai $pegawai): array {
            $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);
            $statusKgb = $this->getKgbStatus($pegawai);

            return [
                'id'                    => $pegawai->id,
                'nip'                   => $pegawai->nip,
                'nama_lengkap'          => $pegawai->nama_lengkap,
                'pangkat_gol'           => $pegawai->nama_pangkat_lengkap,
                'tmt_pangkat'           => $riwayatPangkatAktif?->tmt?->toDateString(),
                'tanggal_kgb_berikutnya'=> $statusKgb['tanggal_kgb_berikutnya']->toDateString(),
                'sisa_hari'             => $statusKgb['sisa_hari'],
                'status'                => $statusKgb['status'],
            ];
        });
}
```

Juga update `getKgbStats()` untuk mendukung filter yang sama:

```php
public function getKgbStats(
    int $months = 3,
    ?string $unitKerjaId = null,
    ?string $golongan = null,
): array {
    $maxSisaHari = $months * 30;
    $driver = DB::connection()->getDriverName();
    $isMySQL = $driver === 'mysql';

    $maxKgbDate = Carbon::today()->addDays($maxSisaHari)->toDateString();
    $today = Carbon::today()->toDateString();

    $kgbDateExpr = $isMySQL
        ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
        : "date(rp_kgb.tmt, '+2 years')";

    $sisaHariExpr = $isMySQL
        ? "DATEDIFF({$kgbDateExpr}, CURDATE())"
        : "CAST(julianday({$kgbDateExpr}) - julianday('{$today}') AS INTEGER)";

    $row = Pegawai::query()
        ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN {$sisaHariExpr} <= 0 THEN 1 ELSE 0 END) as jatuh_tempo,
            SUM(CASE WHEN {$sisaHariExpr} BETWEEN 1 AND 60 THEN 1 ELSE 0 END) as segera,
            SUM(CASE WHEN {$sisaHariExpr} BETWEEN 61 AND 90 THEN 1 ELSE 0 END) as mendekati,
            SUM(CASE WHEN {$sisaHariExpr} > 90 THEN 1 ELSE 0 END) as aman
        ")
        ->join('riwayat_pangkat as rp_kgb', function ($join) {
            $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                ->where('rp_kgb.is_aktif', true);
        })
        ->whereIn('status_pegawai', [
            StatusPegawai::Aktif->value,
            StatusPegawai::MutasiKeluar->value,
        ])
        ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
        ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan))
        ->whereRaw(
            $isMySQL
                ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR) <= ?'
                : "date(rp_kgb.tmt, '+2 years') <= ?",
            [$maxKgbDate]
        )
        ->first();

    return [
        'total'      => (int) ($row?->total ?? 0),
        'jatuhTempo' => (int) ($row?->jatuh_tempo ?? 0),
        'segera'     => (int) ($row?->segera ?? 0),
        'mendekati'  => (int) ($row?->mendekati ?? 0),
        'aman'       => (int) ($row?->aman ?? 0),
    ];
}
```

- [ ] **Step 4: Update `MonitoringKgbController`**

Ganti isi `app/Http/Controllers/Monitoring/MonitoringKgbController.php`:

```php
<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKgbController extends Controller
{
    public function __construct(
        protected KgbMonitoringService $kgbMonitoringService,
    ) {}

    public function index(Request $request): Response
    {
        $perPage    = $request->integer('per_page', 15);
        $unitKerja  = $request->string('unit_kerja')->value() ?: null;
        $golongan   = $request->string('golongan')->value() ?: null;
        $status     = $request->string('status')->value() ?: null;

        return Inertia::render('kepegawaian/monitoring/kgb/index', [
            'pegawaiList' => $this->kgbMonitoringService->getUpcomingKgb(3, $perPage, $unitKerja, $golongan, $status),
            'kgbStats'    => $this->kgbMonitoringService->getKgbStats(3, $unitKerja, $golongan),
            'filters'     => [
                'unit_kerja' => $unitKerja,
                'golongan'   => $golongan,
                'status'     => $status,
            ],
            'filterOptions' => [
                'unitKerja' => RefUnitKerja::query()
                    ->select(['id', 'nama'])
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(),
                'golongan' => RefPangkat::query()
                    ->selectRaw('DISTINCT golongan')
                    ->whereNotNull('golongan')
                    ->orderBy('golongan')
                    ->pluck('golongan'),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Jalankan test KGB — harus PASS**

```bash
php artisan test tests/Feature/Monitoring/KgbMonitoringTest.php
```

Expected: All tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/KgbMonitoringService.php \
    app/Http/Controllers/Monitoring/MonitoringKgbController.php \
    tests/Feature/Monitoring/KgbMonitoringTest.php
git commit -m "feat: tambah filter server-side unit_kerja, golongan, status ke monitoring KGB (3.3)"
```

---

### Task 3.2: Update Frontend KGB — Filter Server-Side

**Files:**
- Modify: `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`

- [ ] **Step 1: Update `kgb/index.tsx`**

Ganti isi `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`:

```tsx
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData } from '@/types/kepegawaian';

type KgbStatus = 'Sudah Jatuh Tempo' | 'Segera' | 'Mendekati' | 'Aman';
type StatusFilter = 'semua' | 'jatuh-tempo' | 'segera' | 'mendekati' | 'aman';

type PegawaiMonitoringKgb = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
    pangkat_gol: string;
    tmt_pangkat: string | null;
    tanggal_kgb_berikutnya: string;
    sisa_hari: number;
    status: KgbStatus;
};

type UnitKerjaOption = { id: string; nama: string };
type FilterOptions = {
    unitKerja: UnitKerjaOption[];
    golongan: string[];
};

type Filters = {
    unit_kerja: string | null;
    golongan: string | null;
    status: string | null;
};

type Props = {
    pegawaiList: PaginatedData<PegawaiMonitoringKgb>;
    kgbStats: {
        total: number;
        jatuhTempo: number;
        segera: number;
        mendekati: number;
        aman: number;
    };
    filters: Filters;
    filterOptions: FilterOptions;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monitoring KGB', href: '/kepegawaian/monitoring/kgb' },
];

const statusBadgeClass: Record<KgbStatus, string> = {
    'Sudah Jatuh Tempo': 'bg-red-100 text-red-700 border-red-200 hover:bg-red-100',
    Segera: 'bg-orange-100 text-orange-700 border-orange-200 hover:bg-orange-100',
    Mendekati: 'bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-100',
    Aman: 'bg-emerald-100 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
};

function formatDate(date: string | null): string {
    if (date === null) return '-';
    const parsed = new Date(date);
    return Number.isNaN(parsed.getTime())
        ? '-'
        : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parsed);
}

function applyFilter(newFilters: Partial<Filters>) {
    const params: Record<string, string> = {};
    const merged = { ...newFilters };
    if (merged.unit_kerja) params.unit_kerja = merged.unit_kerja;
    if (merged.golongan) params.golongan = merged.golongan;
    if (merged.status) params.status = merged.status;
    router.get('/kepegawaian/monitoring/kgb', params, { preserveState: true, replace: true });
}

export default function MonitoringKgbIndex({ pegawaiList, kgbStats, filters, filterOptions }: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    function handleFilterChange(key: keyof Filters, value: string) {
        const updated = { ...localFilters, [key]: value === 'semua' || value === '' ? null : value };
        setLocalFilters(updated);
        applyFilter(updated);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monitoring KGB" />

            <div className="space-y-6 p-4 sm:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Monitoring Kenaikan Gaji Berkala
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pantau pegawai yang mendekati atau sudah jatuh tempo KGB.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total</CardDescription>
                            <CardTitle>{kgbStats.total}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Jatuh Tempo</CardDescription>
                            <CardTitle>{kgbStats.jatuhTempo}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Segera</CardDescription>
                            <CardTitle>{kgbStats.segera}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Mendekati</CardDescription>
                            <CardTitle>{kgbStats.mendekati}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Monitoring KGB</CardTitle>
                        <CardDescription>Data pegawai disusun berdasarkan sisa hari terdekat.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:flex-wrap">
                            <div className="grid gap-1.5">
                                <label htmlFor="filter-unit-kerja" className="text-sm font-medium">
                                    Unit Kerja
                                </label>
                                <select
                                    id="filter-unit-kerja"
                                    value={localFilters.unit_kerja ?? ''}
                                    onChange={(e) => handleFilterChange('unit_kerja', e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-48"
                                >
                                    <option value="">Semua Unit</option>
                                    {filterOptions.unitKerja.map((uk) => (
                                        <option key={uk.id} value={uk.id}>{uk.nama}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <label htmlFor="filter-golongan" className="text-sm font-medium">
                                    Golongan
                                </label>
                                <select
                                    id="filter-golongan"
                                    value={localFilters.golongan ?? ''}
                                    onChange={(e) => handleFilterChange('golongan', e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-36"
                                >
                                    <option value="">Semua Gol</option>
                                    {filterOptions.golongan.map((gol) => (
                                        <option key={gol} value={gol}>Golongan {gol}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <label htmlFor="filter-status" className="text-sm font-medium">
                                    Status
                                </label>
                                <select
                                    id="filter-status"
                                    value={localFilters.status ?? 'semua'}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-40"
                                >
                                    <option value="semua">Semua Status</option>
                                    <option value="jatuh-tempo">Jatuh Tempo</option>
                                    <option value="segera">Segera</option>
                                    <option value="mendekati">Mendekati</option>
                                    <option value="aman">Aman</option>
                                </select>
                            </div>
                        </div>

                        <div className="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>NIP</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Pangkat/Gol</TableHead>
                                        <TableHead>TMT Pangkat</TableHead>
                                        <TableHead>KGB Berikutnya</TableHead>
                                        <TableHead>Sisa Hari</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pegawaiList.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                Tidak ada data monitoring KGB.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        pegawaiList.data.map((pegawai) => (
                                            <TableRow key={pegawai.id}>
                                                <TableCell className="font-medium">
                                                    {pegawai.nip ?? '-'}
                                                </TableCell>
                                                <TableCell>{pegawai.nama_lengkap}</TableCell>
                                                <TableCell>{pegawai.pangkat_gol || '-'}</TableCell>
                                                <TableCell>{formatDate(pegawai.tmt_pangkat)}</TableCell>
                                                <TableCell>{formatDate(pegawai.tanggal_kgb_berikutnya)}</TableCell>
                                                <TableCell>{pegawai.sisa_hari}</TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className={statusBadgeClass[pegawai.status]}
                                                    >
                                                        {pegawai.status}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationWrapper links={pegawaiList.links} lastPage={pegawaiList.last_page} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Jalankan type check**

```bash
npx tsc --noEmit 2>&1 | head -20
```

Expected: Tidak ada error TypeScript.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/kepegawaian/monitoring/kgb/index.tsx
git commit -m "feat: filter server-side (unit kerja, golongan, status) di halaman monitoring KGB"
```

---

### Task 3.3: Filter Server-Side KP (TDD)

**Files:**
- Test: `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php` (tambah test filter)
- Modify: `app/Services/KenaikanPangkatMonitoringService.php`
- Modify: `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`
- Modify: `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`

- [ ] **Step 1: Tambah failing tests di `KenaikanPangkatMonitoringTest.php`**

Buka file test dan tambahkan:

```php
use App\Models\RefUnitKerja;

test('filter unit_kerja_id hanya menampilkan pegawai KP dari unit kerja tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $unitKerja1 = RefUnitKerja::factory()->create();
    $unitKerja2 = RefUnitKerja::factory()->create();

    $pangkat = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);

    $pegawai1 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja1->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai1->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $pegawai2 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja2->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai2->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $service = app(\App\Services\KenaikanPangkatMonitoringService::class);
    $result = $service->getUpcomingKenaikanPangkat(null, 15, $unitKerja1->id);

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawai1->id)
        ->and($ids)->not->toContain($pegawai2->id);
});

test('filter golongan hanya menampilkan pegawai KP dengan golongan tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $pangkatIII = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatIV = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    $pegawaiIII = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIII->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIII->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $pegawaiIV = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIV->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIV->id,
        'ref_pangkat_id' => $pangkatIV->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $service = app(\App\Services\KenaikanPangkatMonitoringService::class);
    $result = $service->getUpcomingKenaikanPangkat(null, 15, null, 'III');

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawaiIII->id)
        ->and($ids)->not->toContain($pegawaiIV->id);
});

test('controller kp meneruskan filter dan filterOptions ke view', function () {
    $admin = Pegawai::factory()->admin()->create();
    $unitKerja = RefUnitKerja::factory()->create();
    actingAs($admin);

    get(route('monitoring.kenaikan-pangkat.index', ['unit_kerja' => $unitKerja->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->has('filters', fn (Assert $f) => $f
                ->where('unit_kerja', $unitKerja->id)
                ->etc()
            )
            ->has('filterOptions', fn (Assert $f) => $f
                ->has('unitKerja')
                ->has('golongan')
                ->etc()
            )
        );
});
```

- [ ] **Step 2: Jalankan test — harus FAIL**

```bash
php artisan test tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php --filter="filter"
```

Expected: FAIL — parameter filter belum ada di service.

- [ ] **Step 3: Update `KenaikanPangkatMonitoringService::getUpcomingKenaikanPangkat()`**

Modifikasi signature method di `app/Services/KenaikanPangkatMonitoringService.php`:

```php
public function getUpcomingKenaikanPangkat(
    ?string $periode = null,
    int $perPage = 15,
    ?string $unitKerjaId = null,
    ?string $golongan = null,
): LengthAwarePaginator {
    $normalizedPeriode = $periode !== null ? strtolower($periode) : null;

    $query = Pegawai::query()
        ->select('pegawai.*')
        ->join('riwayat_pangkat as rp_kp', function ($join) {
            $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                ->where('rp_kp.is_aktif', true);
        })
        ->with([
            'pangkat',
            'riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat')->orderByDesc('tmt'),
        ])
        ->whereNotIn('status_pegawai', [
            StatusPegawai::Pensiun->value,
            StatusPegawai::Meninggal->value,
            StatusPegawai::Diberhentikan->value,
        ])
        ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
        ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan))
        ->orderBy('nama_lengkap');

    if ($normalizedPeriode === 'april') {
        $query->whereRaw($this->getPeriodeFilterSql('april'), [4]);
    } elseif ($normalizedPeriode === 'oktober') {
        $query->whereRaw($this->getPeriodeFilterSql('oktober'));
    }

    return $query
        ->paginate($perPage)
        ->through(function (Pegawai $pegawai): array {
            $riwayatPangkatAktif = $pegawai->riwayatPangkat->first();

            if ($riwayatPangkatAktif === null) {
                return [];
            }

            $status = $this->getKpStatus($pegawai);

            return [
                'id'               => $pegawai->id,
                'nip'              => $pegawai->nip,
                'nama_lengkap'     => $pegawai->nama_lengkap,
                'pangkat_saat_ini' => $riwayatPangkatAktif->pangkat?->nama ?? $pegawai->pangkat?->nama,
                'pangkat_kode'     => $riwayatPangkatAktif->pangkat?->kode ?? $pegawai->pangkat?->kode,
                'tmt_pangkat'      => $riwayatPangkatAktif->tmt?->toDateString(),
                'tmt_kp_berikutnya'=> $status['tmt_kp_berikutnya']->toDateString(),
                'periode_usul'     => $status['periode_usul'],
                'batas_usul'       => $status['batas_usul']->toDateString(),
                'sisa_hari_usul'   => $status['sisa_hari_usul'],
                'status'           => $status['status'],
            ];
        });
}
```

Juga update `getKpStats()` agar konsisten:

```php
public function getKpStats(?string $periode = null, ?string $unitKerjaId = null, ?string $golongan = null): array
{
    // ... tambahkan ->when() untuk unitKerjaId dan golongan ke existing query
    // (sama persis seperti pada getUpcomingKenaikanPangkat)
```

Lengkap, modifikasi `getKpStats()`:

```php
public function getKpStats(?string $periode = null, ?string $unitKerjaId = null, ?string $golongan = null): array
{
    $normalizedPeriode = $periode !== null ? strtolower($periode) : null;
    $today = Carbon::today()->toDateString();

    $driver = DB::connection()->getDriverName();
    $tmtPlus4Year = $driver === 'sqlite'
        ? "date(rp_kp.tmt, '+4 years')"
        : 'DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)';
    $todayForComparison = $driver === 'sqlite'
        ? "date('{$today}')"
        : "'{$today}'";
    $sixMonthsLater = $driver === 'sqlite'
        ? "date('{$today}', '+6 months')"
        : "DATE_ADD('{$today}', INTERVAL 6 MONTH)";

    $query = Pegawai::query()
        ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN {$tmtPlus4Year} <= {$todayForComparison} THEN 1 ELSE 0 END) as sudah_eligible,
            SUM(CASE WHEN {$tmtPlus4Year} > {$todayForComparison}
                AND {$tmtPlus4Year} <= {$sixMonthsLater}
                THEN 1 ELSE 0 END) as mendekati_eligible,
            SUM(CASE WHEN {$tmtPlus4Year} > {$sixMonthsLater}
                THEN 1 ELSE 0 END) as belum_eligible
        ")
        ->join('riwayat_pangkat as rp_kp', function ($join) {
            $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                ->where('rp_kp.is_aktif', true);
        })
        ->whereNotIn('status_pegawai', [
            StatusPegawai::Pensiun->value,
            StatusPegawai::Meninggal->value,
            StatusPegawai::Diberhentikan->value,
        ])
        ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
        ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan));

    if ($normalizedPeriode === 'april') {
        $query->whereRaw($this->getPeriodeFilterSql('april'), [4]);
    } elseif ($normalizedPeriode === 'oktober') {
        $query->whereRaw($this->getPeriodeFilterSql('oktober'));
    }

    $row = $query->first();

    return [
        'total'             => (int) ($row?->total ?? 0),
        'sudahEligible'     => (int) ($row?->sudah_eligible ?? 0),
        'mendekatiEligible' => (int) ($row?->mendekati_eligible ?? 0),
        'belumEligible'     => (int) ($row?->belum_eligible ?? 0),
    ];
}
```

- [ ] **Step 4: Update `MonitoringKenaikanPangkatController`**

Ganti isi `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`:

```php
<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKenaikanPangkatController extends Controller
{
    public function index(Request $request, KenaikanPangkatMonitoringService $service): Response
    {
        $periode    = $request->string('periode')->value() ?: null;
        $perPage    = $request->integer('per_page', 15);
        $unitKerja  = $request->string('unit_kerja')->value() ?: null;
        $golongan   = $request->string('golongan')->value() ?: null;

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList'     => $service->getUpcomingKenaikanPangkat($periode, $perPage, $unitKerja, $golongan),
            'selectedPeriode' => $periode,
            'kpStats'         => $service->getKpStats($periode, $unitKerja, $golongan),
            'filters'         => [
                'unit_kerja' => $unitKerja,
                'golongan'   => $golongan,
                'periode'    => $periode,
            ],
            'filterOptions'   => [
                'unitKerja' => RefUnitKerja::query()
                    ->select(['id', 'nama'])
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(),
                'golongan' => RefPangkat::query()
                    ->selectRaw('DISTINCT golongan')
                    ->whereNotNull('golongan')
                    ->orderBy('golongan')
                    ->pluck('golongan'),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Jalankan test KP — harus PASS**

```bash
php artisan test tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php
```

Expected: All tests passed.

- [ ] **Step 6: Update `kenaikan-pangkat/index.tsx` tambahkan filter unit kerja + golongan**

Di `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`, tambahkan:

1. Perbarui type `Props` — tambahkan `filters` dan `filterOptions`:

```tsx
type UnitKerjaOption = { id: string; nama: string };
type KpFilters = {
    unit_kerja: string | null;
    golongan: string | null;
    periode: string | null;
};
type KpFilterOptions = {
    unitKerja: UnitKerjaOption[];
    golongan: string[];
};

type Props = {
    pegawaiList: PaginatedData<PegawaiMonitoringRow>;
    selectedPeriode: string | null;
    kpStats: {
        total: number;
        sudahEligible: number;
        mendekatiEligible: number;
        belumEligible: number;
    };
    filters: KpFilters;
    filterOptions: KpFilterOptions;
};
```

2. Di dalam komponen, tambahkan state dan handler untuk filter baru:

```tsx
const [unitKerjaFilter, setUnitKerjaFilter] = useState(filters.unit_kerja ?? '');
const [golonganFilter, setGolonganFilter] = useState(filters.golongan ?? '');

function handleFilterChange(newParams: Record<string, string>) {
    const params: Record<string, string> = {};
    const resolved = {
        unit_kerja: unitKerjaFilter,
        golongan: golonganFilter,
        periode: periodeFilter !== 'semua' ? periodeFilter : '',
        ...newParams,
    };
    if (resolved.unit_kerja) params.unit_kerja = resolved.unit_kerja;
    if (resolved.golongan) params.golongan = resolved.golongan;
    if (resolved.periode) params.periode = resolved.periode;
    router.get('/kepegawaian/monitoring/kenaikan-pangkat', params, {
        preserveState: true,
        replace: true,
    });
}
```

3. Tambahkan dua select baru (unit kerja + golongan) di blok filter yang sudah ada (sejajar dengan filter periode):

```tsx
<div className="grid gap-2">
    <label htmlFor="unit-kerja" className="text-sm font-medium">Unit Kerja</label>
    <select
        id="unit-kerja"
        value={unitKerjaFilter}
        onChange={(e) => {
            setUnitKerjaFilter(e.target.value);
            handleFilterChange({ unit_kerja: e.target.value });
        }}
        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
    >
        <option value="">Semua Unit</option>
        {filterOptions.unitKerja.map((uk) => (
            <option key={uk.id} value={uk.id}>{uk.nama}</option>
        ))}
    </select>
</div>

<div className="grid gap-2">
    <label htmlFor="golongan" className="text-sm font-medium">Golongan</label>
    <select
        id="golongan"
        value={golonganFilter}
        onChange={(e) => {
            setGolonganFilter(e.target.value);
            handleFilterChange({ golongan: e.target.value });
        }}
        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
    >
        <option value="">Semua Gol</option>
        {filterOptions.golongan.map((gol) => (
            <option key={gol} value={gol}>Golongan {gol}</option>
        ))}
    </select>
</div>
```

Juga update handler `periodeFilter` agar memanggil `handleFilterChange`:
```tsx
onChange={(event) => {
    const value = event.target.value as 'semua' | 'april' | 'oktober';
    setPeriodeFilter(value);
    handleFilterChange({ periode: value === 'semua' ? '' : value });
}}
```

- [ ] **Step 7: Jalankan full test suite**

```bash
php artisan test
```

Expected: All tests passed.

- [ ] **Step 8: Commit**

```bash
git add app/Services/KenaikanPangkatMonitoringService.php \
    app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php \
    tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php \
    resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx
git commit -m "feat: filter server-side unit_kerja + golongan di monitoring KP (3.3)"
```

---

## Item 3.4: Foto di Halaman Self-Service

> **Prerequisite:** Task 1.1 (foto_url accessor) harus sudah selesai.

### File Mapping

| File | Action |
|------|--------|
| `resources/js/pages/self-service/index.tsx` | Modify — gunakan `foto_url` |
| `resources/js/pages/self-service/detail.tsx` | Modify — gunakan `foto_url` |

**Catatan:** Karena `foto_url` sudah di-append oleh model Pegawai (`$appends = ['foto_url']`), backend sudah otomatis mengirim `foto_url` ke frontend. Tidak ada perubahan backend yang diperlukan.

---

### Task 4.1: Update Self-Service Frontend untuk gunakan `foto_url`

**Files:**
- Modify: `resources/js/pages/self-service/index.tsx`
- Modify: `resources/js/pages/self-service/detail.tsx`

- [ ] **Step 1: Update type `PegawaiSummary` di `self-service/index.tsx`**

Tambahkan `foto_url` ke type `PegawaiSummary`:

```tsx
type PegawaiSummary = Pick<
    PegawaiDetail,
    | 'id'
    | 'nip'
    | 'nama_lengkap'
    | 'foto'
    | 'foto_url'   // <-- tambah ini
    | 'pangkat'
    | 'jabatan'
    | 'unit_kerja'
    | 'tmt_pns'
>;
```

- [ ] **Step 2: Ganti `pegawai.foto` → `pegawai.foto_url` di `index.tsx`**

Di `resources/js/pages/self-service/index.tsx`, ubah baris:
```tsx
src={pegawai.foto ?? undefined}
```
menjadi:
```tsx
src={pegawai.foto_url ?? undefined}
```

- [ ] **Step 3: Ganti `pegawai.foto` → `pegawai.foto_url` di `detail.tsx`**

Di `resources/js/pages/self-service/detail.tsx`, ubah baris:
```tsx
src={pegawai.foto ?? undefined}
```
menjadi:
```tsx
src={pegawai.foto_url ?? undefined}
```

- [ ] **Step 4: Ganti `pegawai.foto` → `pegawai.foto_url` di `pegawai/show.tsx`** (jika belum dari Task 1.3)

Di `resources/js/pages/kepegawaian/pegawai/show.tsx`, pastikan sudah menggunakan `foto_url` bukan `foto` di semua tempat.

- [ ] **Step 5: Jalankan type check**

```bash
npx tsc --noEmit 2>&1 | head -20
```

Expected: Tidak ada error TypeScript.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/self-service/index.tsx \
    resources/js/pages/self-service/detail.tsx
git commit -m "feat: tampil foto pegawai di self-service menggunakan foto_url (3.4)"
```

---

## Verifikasi Akhir Fase 3

- [ ] **Jalankan full test suite**

```bash
php artisan test
```

Expected: All tests passed.

- [ ] **Jalankan type check frontend**

```bash
npx tsc --noEmit
```

Expected: No errors.

- [ ] **Test manual: upload foto**

1. Buka halaman detail pegawai
2. Klik tombol kamera di avatar
3. Pilih gambar JPG/PNG
4. Klik "Simpan Foto"
5. Verifikasi foto tampil di show, index, dan self-service

- [ ] **Test manual: dashboard skeleton**

1. Buka halaman dashboard
2. Verifikasi 4 kartu stats muncul langsung
3. Verifikasi distribusi cards muncul dengan skeleton dulu lalu tergantikan data

- [ ] **Test manual: filter monitoring**

1. Buka monitoring KGB
2. Filter berdasarkan unit kerja → verifikasi pagination berubah
3. Filter berdasarkan status "Jatuh Tempo" → verifikasi hanya data jatuh tempo muncul di semua halaman
4. Buka monitoring KP → filter unit kerja + golongan

---

## Ringkasan File yang Berubah

### Backend (PHP)
- `composer.json` — tambah `intervention/image:^3.0`
- `app/Models/Pegawai.php` — tambah `foto_url` accessor + `$appends`
- `app/Http/Requests/Kepegawaian/UpdateFotoPegawaiRequest.php` — baru
- `app/Http/Controllers/Kepegawaian/PegawaiController.php` — tambah `updateFoto`
- `routes/web.php` — tambah route `kepegawaian.pegawai.foto.update`
- `app/Services/DashboardStatService.php` — split `getFastStats` + `getHeavyStats`
- `app/Http/Controllers/DashboardController.php` — gunakan `Inertia::defer`
- `app/Services/KgbMonitoringService.php` — tambah filter params
- `app/Http/Controllers/Monitoring/MonitoringKgbController.php` — pass filters + filterOptions
- `app/Services/KenaikanPangkatMonitoringService.php` — tambah filter params
- `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php` — pass filters + filterOptions

### Frontend (TypeScript/React)
- `resources/js/types/kepegawaian.ts` — tambah `foto_url`
- `resources/js/types/pegawai-detail.ts` — tambah `foto_url`
- `resources/js/hooks/use-dashboard-stats.ts` — split types
- `resources/js/components/pegawai/FotoUpload.tsx` — baru
- `resources/js/components/dashboard/DashboardDistribusiSkeleton.tsx` — baru
- `resources/js/components/dashboard/DashboardHeavySection.tsx` — baru
- `resources/js/pages/dashboard.tsx` — refaktor dengan Deferred
- `resources/js/pages/kepegawaian/pegawai/show.tsx` — FotoUpload + foto_url
- `resources/js/pages/kepegawaian/pegawai/index.tsx` — avatar thumbnail
- `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx` — filter server-side
- `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx` — filter baru
- `resources/js/pages/self-service/index.tsx` — gunakan foto_url
- `resources/js/pages/self-service/detail.tsx` — gunakan foto_url

### Tests
- `tests/Feature/Kepegawaian/FotoPegawaiTest.php` — baru
- `tests/Feature/DashboardTest.php` — update untuk deferred props
- `tests/Feature/Monitoring/KgbMonitoringTest.php` — tambah test filter
- `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php` — tambah test filter
