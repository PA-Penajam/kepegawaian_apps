# Fase 5: Fitur Lanjutan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menuntaskan fase 5 secara aman dengan urutan: hardening modul monitoring lewat peningkatan test coverage, lalu membuka sub-proyek terpisah untuk workflow pengajuan perubahan data yang memang membutuhkan desain dan plan implementasi sendiri.

**Architecture:** Fase 5 dibagi menjadi dua jalur yang sengaja tidak digabung. Item 5.1 adalah perluasan regression coverage pada modul monitoring KGB/KP dan export yang sudah ada, dengan prinsip TDD ketat dan perubahan implementasi hanya jika test baru membuktikan ada gap perilaku. Item 5.2 tidak langsung diimplementasikan dari roadmap ini; ia masuk ke gate desain terpisah karena menambah tabel baru, state machine approval, otorisasi admin vs pegawai, dan form Inertia dua sisi.

**Tech Stack:** Laravel 12.54, Pest 4.4, Inertia v2, React 19.2, Laravel Wayfinder, SQLite test database, Laravel Excel yang sudah terpasang.

**Reference Alignment:** Plan ini disusun dengan referensi resmi Laravel 12 untuk migration, Form Request authorization, pagination, dan database assertions; Inertia v2 untuk `useForm`, validation error handling, dan preserve state; serta Pest 4 untuk Laravel feature test helpers.

---

## File Map

### 5.1 Peningkatan Test Coverage Monitoring

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `tests/Feature/Monitoring/KgbMonitoringEdgeCaseTest.php` | Create | Edge-case coverage untuk empty state, exception path, pagination besar, dan kombinasi filter KGB |
| `tests/Feature/Monitoring/KenaikanPangkatEdgeCaseTest.php` | Create | Edge-case coverage untuk empty state, exception path, pagination besar, dan kombinasi filter KP |
| `tests/Feature/Monitoring/KgbExportTest.php` | Modify | Tambah regression test export KGB saat dataset kosong |
| `tests/Feature/Monitoring/KenaikanPangkatExportTest.php` | Modify | Tambah regression test export KP saat dataset kosong |
| `app/Services/KgbMonitoringService.php` | Modify jika RED phase membuktikan gap | Menutup gap perilaku yang ditemukan oleh test KGB |
| `app/Services/KenaikanPangkatMonitoringService.php` | Modify jika RED phase membuktikan gap | Menutup gap perilaku yang ditemukan oleh test KP |
| `app/Exports/KgbMonitoringExport.php` | Modify jika RED phase membuktikan gap | Menjamin export tetap stabil untuk dataset kosong |
| `app/Exports/KenaikanPangkatMonitoringExport.php` | Modify jika RED phase membuktikan gap | Menjamin export tetap stabil untuk dataset kosong |

### 5.2 Self-Service Pengajuan Perubahan Data

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `docs/superpowers/specs/2026-04-17-self-service-pengajuan-perubahan-data-design.md` | Create | Spec terpisah untuk domain, state machine, otorisasi, dan UX approval workflow |
| `docs/superpowers/plans/2026-04-17-self-service-pengajuan-perubahan-data.md` | Create | Plan implementasi khusus 5.2 setelah spec disetujui |
| `database/migrations/*_create_pengajuan_perubahan_data_table.php` | Deferred until spec approved | Struktur tabel pengajuan, snapshot data, dan audit kolom |
| `app/Models/PengajuanPerubahanData.php` | Deferred until spec approved | Model Eloquent dan relasi ke `Pegawai` |
| `app/Enums/StatusPengajuanPerubahanData.php` | Deferred until spec approved | Representasi state `pending/approved/rejected` |
| `app/Http/Controllers/SelfService/PengajuanPerubahanDataController.php` | Deferred until spec approved | Endpoint pegawai untuk daftar, form, dan submit pengajuan |
| `app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php` | Deferred until spec approved | Endpoint admin/operator untuk review, approve, dan reject |
| `app/Http/Requests/SelfService/StorePengajuanPerubahanDataRequest.php` | Deferred until spec approved | Validasi payload pengajuan |
| `app/Http/Requests/Kepegawaian/ApprovePengajuanPerubahanDataRequest.php` | Deferred until spec approved | Validasi aksi approve |
| `app/Http/Requests/Kepegawaian/RejectPengajuanPerubahanDataRequest.php` | Deferred until spec approved | Validasi aksi reject beserta alasan |
| `app/Policies/PengajuanPerubahanDataPolicy.php` | Deferred until spec approved | Batas akses pegawai vs admin/operator |
| `resources/js/pages/self-service/pengajuan/index.tsx` | Deferred until spec approved | Listing riwayat pengajuan milik pegawai |
| `resources/js/pages/self-service/pengajuan/create.tsx` | Deferred until spec approved | Form pengajuan berbasis Inertia `useForm` |
| `resources/js/pages/kepegawaian/pengajuan/index.tsx` | Deferred until spec approved | Inbox approval untuk admin/operator |
| `resources/js/pages/kepegawaian/pengajuan/show.tsx` | Deferred until spec approved | Detail diff data dan aksi approve/reject |
| `tests/Feature/SelfService/PengajuanPerubahanDataTest.php` | Deferred until spec approved | Feature tests alur submit pegawai |
| `tests/Feature/Kepegawaian/ApprovalPengajuanPerubahanDataTest.php` | Deferred until spec approved | Feature tests alur review admin/operator |

---

## 5.1 — Peningkatan Test Coverage Monitoring

### Task 1: Tambah edge-case coverage untuk KGB monitoring

**Files:**
- Create: `tests/Feature/Monitoring/KgbMonitoringEdgeCaseTest.php`
- Modify if needed: `app/Services/KgbMonitoringService.php`

- [ ] **Step 1: Tulis failing tests untuk skenario KGB yang belum tertutup**

Tambahkan file `tests/Feature/Monitoring/KgbMonitoringEdgeCaseTest.php` dengan skenario minimum berikut:

```php
<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\RiwayatPangkat;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeKgbPegawai(string $nextKgbDate, array $pegawai = []): Pegawai
{
    $record = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $pegawai));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $record->id,
        'ref_pangkat_id' => $record->ref_pangkat_id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $record;
}

it('menampilkan empty state inertia ketika tidak ada data monitoring kgb', function (): void {
    $user = Pegawai::factory()->operator()->create();

    actingAs($user)
        ->get(route('monitoring.kgb.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->where('pegawaiList.total', 0)
            ->has('pegawaiList.data', 0)
            ->where('kgbStats.total', 0)
            ->where('kgbStats.jatuhTempo', 0)
            ->where('kgbStats.segera', 0)
            ->where('kgbStats.mendekati', 0)
            ->where('kgbStats.aman', 0)
        );
});

it('melempar exception ketika status kgb dihitung untuk pegawai tanpa riwayat aktif', function (): void {
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => false,
    ]);

    expect(fn () => app(KgbMonitoringService::class)->getKgbStatus($pegawai))
        ->toThrow(InvalidArgumentException::class);
});

it('mempertahankan total data dan halaman kedua untuk dataset besar', function (): void {
    $user = Pegawai::factory()->operator()->create();

    foreach (range(1, 31) as $index) {
        makeKgbPegawai('2026-05-01', ['nama_lengkap' => "Pegawai KGB {$index}"]);
    }

    actingAs($user)
        ->get(route('monitoring.kgb.index', ['page' => 2, 'per_page' => 15]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawaiList.current_page', 2)
            ->where('pegawaiList.total', 31)
            ->where('pegawaiList.last_page', 3)
            ->has('pegawaiList.data', 15)
        );
});

it('menerapkan kombinasi filter unit kerja, golongan, dan status', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $unitCocok = RefUnitKerja::factory()->create();
    $unitLain = RefUnitKerja::factory()->create();
    $pangkatCocok = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatLain = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    makeKgbPegawai('2026-05-01', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatCocok->id,
        'nama_lengkap' => 'Target KGB',
    ]);

    makeKgbPegawai('2026-05-01', [
        'ref_unit_kerja_id' => $unitLain->id,
        'ref_pangkat_id' => $pangkatCocok->id,
        'nama_lengkap' => 'Salah Unit',
    ]);

    makeKgbPegawai('2026-09-30', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatLain->id,
        'nama_lengkap' => 'Salah Golongan',
    ]);

    actingAs($admin);

    $result = app(KgbMonitoringService::class)->getUpcomingKgb(6, 15, $unitCocok->id, 'III', 'segera');

    expect(collect($result->items())->pluck('nama_lengkap')->all())->toBe(['Target KGB']);
});
```

- [ ] **Step 2: Jalankan file test dan pastikan ada RED phase yang valid**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring/KgbMonitoringEdgeCaseTest.php
```

Expected: Minimal satu skenario gagal atau file belum ada.

- [ ] **Step 3: Tulis perubahan minimum sampai semua test KGB lulus**

Perubahan implementasi yang diperbolehkan hanya pada perilaku yang dibuktikan gagal oleh Step 2. Fokus perbaikan:

```php
// app/Services/KgbMonitoringService.php
return $query->paginate($perPage)
    ->withQueryString()
    ->through(function (Pegawai $pegawai): array {
        // Pertahankan transformasi stabil untuk setiap item hasil query
    });
```

Jika test menunjukkan gap lain, batasi perubahan ke `KgbMonitoringService` saja; jangan refactor file lain tanpa bukti test.

- [ ] **Step 4: Jalankan ulang file test KGB**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring/KgbMonitoringEdgeCaseTest.php
```

Expected: PASS.

---

### Task 2: Tambah edge-case coverage untuk monitoring kenaikan pangkat

**Files:**
- Create: `tests/Feature/Monitoring/KenaikanPangkatEdgeCaseTest.php`
- Modify if needed: `app/Services/KenaikanPangkatMonitoringService.php`

- [ ] **Step 1: Tulis failing tests untuk KP monitoring**

Tambahkan file `tests/Feature/Monitoring/KenaikanPangkatEdgeCaseTest.php` dengan skenario minimum berikut:

```php
<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\RiwayatPangkat;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeKpPegawai(string $tmt, array $pegawai = []): Pegawai
{
    $pangkat = $pegawai['ref_pangkat_id'] ?? RefPangkat::factory()->create()->id;

    $record = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_pangkat_id' => $pangkat,
    ], $pegawai));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $record->id,
        'ref_pangkat_id' => $pangkat,
        'tmt' => $tmt,
        'is_aktif' => true,
    ]);

    return $record;
}

it('menampilkan empty state inertia ketika monitoring kp kosong', function (): void {
    $user = Pegawai::factory()->operator()->create();

    actingAs($user)
        ->get(route('monitoring.kenaikan-pangkat.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->where('pegawaiList.total', 0)
            ->has('pegawaiList.data', 0)
            ->where('kpStats.total', 0)
            ->where('kpStats.sudahEligible', 0)
            ->where('kpStats.mendekatiEligible', 0)
            ->where('kpStats.belumEligible', 0)
        );
});

it('melempar exception ketika status kp dihitung untuk pegawai tanpa riwayat aktif', function (): void {
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    expect(fn () => app(KenaikanPangkatMonitoringService::class)->getKpStatus($pegawai->fresh(['riwayatPangkat'])))
        ->toThrow(RuntimeException::class);
});

it('mempertahankan metadata pagination untuk dataset kp besar', function (): void {
    $user = Pegawai::factory()->operator()->create();

    foreach (range(1, 31) as $index) {
        makeKpPegawai('2021-04-01', ['nama_lengkap' => "Pegawai KP {$index}"]);
    }

    actingAs($user)
        ->get(route('monitoring.kenaikan-pangkat.index', ['page' => 3, 'per_page' => 10]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawaiList.current_page', 3)
            ->where('pegawaiList.total', 31)
            ->where('pegawaiList.last_page', 4)
            ->has('pegawaiList.data', 10)
        );
});

it('menerapkan kombinasi filter periode, unit kerja, dan golongan pada kp', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $unitCocok = RefUnitKerja::factory()->create();
    $unitLain = RefUnitKerja::factory()->create();
    $pangkatIII = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatIV = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    makeKpPegawai('2023-01-01', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'nama_lengkap' => 'Target KP',
    ]);

    makeKpPegawai('2023-01-01', [
        'ref_unit_kerja_id' => $unitLain->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'nama_lengkap' => 'Salah Unit KP',
    ]);

    makeKpPegawai('2023-07-01', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatIV->id,
        'nama_lengkap' => 'Salah Golongan KP',
    ]);

    actingAs($admin);

    $result = app(KenaikanPangkatMonitoringService::class)
        ->getUpcomingKenaikanPangkat('april', 15, $unitCocok->id, 'III');

    expect(collect($result->items())->pluck('nama_lengkap')->all())->toBe(['Target KP']);
});
```

- [ ] **Step 2: Jalankan file test KP dan pastikan RED phase valid**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring/KenaikanPangkatEdgeCaseTest.php
```

Expected: Minimal satu skenario gagal atau file belum ada.

- [ ] **Step 3: Tulis perubahan minimum sampai semua test KP lulus**

Perubahan implementasi dibatasi ke perilaku yang gagal:

```php
// app/Services/KenaikanPangkatMonitoringService.php
return $query
    ->paginate($perPage)
    ->withQueryString()
    ->through(function (Pegawai $pegawai): array {
        // Transformasi item harus tetap konsisten untuk dataset besar dan filter gabungan
    });
```

Jika RED phase menunjukkan bug lain, selesaikan hanya pada service ini.

- [ ] **Step 4: Jalankan ulang file test KP**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring/KenaikanPangkatEdgeCaseTest.php
```

Expected: PASS.

---

### Task 3: Tambah regression test export saat dataset kosong

**Files:**
- Modify: `tests/Feature/Monitoring/KgbExportTest.php`
- Modify: `tests/Feature/Monitoring/KenaikanPangkatExportTest.php`
- Modify if needed: `app/Exports/KgbMonitoringExport.php`
- Modify if needed: `app/Exports/KenaikanPangkatMonitoringExport.php`

- [ ] **Step 1: Tambah test export kosong untuk KGB dan KP**

Tambahkan masing-masing satu test berikut:

```php
test('endpoint export kgb tetap bisa di-download walau data kosong', function () {
    Excel::fake();

    $user = \App\Models\Pegawai::factory()->admin()->create();
    actingAs($user);

    $this->get(route('monitoring.kgb.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('endpoint export kp tetap bisa di-download walau data kosong', function () {
    Excel::fake();

    $user = \App\Models\Pegawai::factory()->admin()->create();
    actingAs($user);

    $this->get(route('monitoring.kenaikan-pangkat.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kp-monitoring.xlsx');
});
```

- [ ] **Step 2: Jalankan dua file test export**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring/KgbExportTest.php tests/Feature/Monitoring/KenaikanPangkatExportTest.php
```

Expected: PASS, atau FAIL yang menunjukkan export class belum stabil untuk collection kosong.

- [ ] **Step 3: Jika gagal, lakukan fix minimum pada export classes**

Target perubahan:

```php
// app/Exports/KgbMonitoringExport.php
public function collection(): Collection
{
    return collect(app(KgbMonitoringService::class)->getUpcomingKgb(...)->items());
}
```

```php
// app/Exports/KenaikanPangkatMonitoringExport.php
public function collection(): Collection
{
    return collect(app(KenaikanPangkatMonitoringService::class)->getUpcomingKenaikanPangkat(...)->items());
}
```

Intinya: export harus tetap menghasilkan file dengan heading walau data kosong, tanpa melempar exception.

- [ ] **Step 4: Jalankan ulang dua file export test**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring/KgbExportTest.php tests/Feature/Monitoring/KenaikanPangkatExportTest.php
```

Expected: PASS.

---

### Task 4: Jalankan regression suite monitoring sebagai penutup 5.1

**Files:**
- No code changes

- [ ] **Step 1: Jalankan seluruh suite monitoring**

Run:

```bash
php artisan test --compact tests/Feature/Monitoring
```

Expected: Semua test monitoring PASS.

- [ ] **Step 2: Format PHP jika ada file PHP yang berubah**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Expected: Tidak ada error formatting.

- [ ] **Step 3: Catat hasil akhir 5.1**

Checklist akhir 5.1:

```text
- Empty state KGB tercakup
- Empty state KP tercakup
- Exception path tanpa riwayat aktif tercakup
- Pagination dataset besar tercakup
- Filter kombinasi tercakup
- Export kosong tercakup
- Seluruh suite Monitoring PASS
```

---

## 5.2 — Self-Service Pengajuan Perubahan Data + Approval Workflow

> **Hard Gate:** Roadmap menyatakan item ini membutuhkan sesi brainstorming terpisah sebelum implementasi. Karena itu, fase 5 tidak boleh langsung menulis migration/controller/page untuk 5.2 sebelum spec khusus 5.2 disetujui.

### Task 5: Tulis spec terpisah untuk sub-proyek 5.2

**Files:**
- Create: `docs/superpowers/specs/2026-04-17-self-service-pengajuan-perubahan-data-design.md`

- [ ] **Step 1: Lakukan brainstorming satu topik per iterasi**

Keputusan yang wajib ditutup sebelum ada kode:

```text
1. Field apa saja yang boleh diajukan pegawai untuk diubah?
2. Apakah payload disimpan sebagai snapshot penuh, diff JSON, atau keduanya?
3. Siapa yang berhak approve/reject: admin saja, operator saja, atau keduanya?
4. Apakah approve menulis langsung ke tabel pegawai/riwayat terkait atau melalui service sinkronisasi?
5. Apakah reject wajib punya alasan?
6. Bagaimana aturan pengajuan ganda untuk field yang sama saat status masih pending?
7. Apakah butuh notifikasi atau cukup inbox approval?
8. Bagaimana audit trail setelah approve/reject?
```

- [ ] **Step 2: Tulis design doc yang disetujui**

Struktur spec minimal:

```markdown
# Self-Service Pengajuan Perubahan Data — Design

## Tujuan
## Aktor dan hak akses
## State machine
## Data model
## Validasi bisnis
## UX pegawai
## UX admin/operator
## Error handling
## Testing strategy
```

- [ ] **Step 3: Minta user review atas spec 5.2**

Outcome yang diharapkan:

```text
Spec 5.2 disetujui tertulis sebelum plan implementasi ditulis.
```

---

### Task 6: Turunkan spec 5.2 menjadi implementation plan khusus

**Files:**
- Create: `docs/superpowers/plans/2026-04-17-self-service-pengajuan-perubahan-data.md`

- [ ] **Step 1: Susun file map final berdasarkan spec yang sudah disetujui**

Permukaan implementasi yang hampir pasti ada setelah desain final:

```text
database/migrations/*_create_pengajuan_perubahan_data_table.php
app/Enums/StatusPengajuanPerubahanData.php
app/Models/PengajuanPerubahanData.php
app/Policies/PengajuanPerubahanDataPolicy.php
app/Http/Requests/SelfService/StorePengajuanPerubahanDataRequest.php
app/Http/Requests/Kepegawaian/ApprovePengajuanPerubahanDataRequest.php
app/Http/Requests/Kepegawaian/RejectPengajuanPerubahanDataRequest.php
app/Http/Controllers/SelfService/PengajuanPerubahanDataController.php
app/Http/Controllers/Kepegawaian/ApprovalPengajuanPerubahanDataController.php
resources/js/pages/self-service/pengajuan/index.tsx
resources/js/pages/self-service/pengajuan/create.tsx
resources/js/pages/kepegawaian/pengajuan/index.tsx
resources/js/pages/kepegawaian/pengajuan/show.tsx
tests/Feature/SelfService/PengajuanPerubahanDataTest.php
tests/Feature/Kepegawaian/ApprovalPengajuanPerubahanDataTest.php
```

- [ ] **Step 2: Wajib ikuti konvensi implementasi dari dokumentasi proyek**

Konvensi yang harus tertulis di plan 5.2:

```text
- Form frontend memakai Inertia `useForm` atau `<Form>`
- Validation errors ditampilkan dari props `errors`
- Authorization diletakkan di Policy/Form Request, bukan inline validation
- Route frontend memakai helper `@/routes` atau Wayfinder, bukan string hard-coded baru
- Test backend ditulis dengan Pest feature tests dan database assertions
- Setiap perubahan perilaku dimulai dari RED -> GREEN -> REFACTOR
```

- [ ] **Step 3: Simpan plan khusus 5.2 dan pilih mode eksekusi**

Outcome:

```text
Tidak ada implementasi kode 5.2 sebelum plan khusus ini selesai dan disetujui.
```

---

## Self-Review

### Cakupan Spec

- 5.1 pada roadmap tercakup penuh: data kosong, tanpa riwayat aktif, pagination besar, filter kombinasi, dan export kosong.
- 5.2 sengaja tidak langsung diimplementasikan karena roadmap mewajibkan brainstorming terpisah; plan ini menutup gap itu dengan gate spec dan plan turunan.

### Placeholder Scan

- Tidak ada `TODO`, `TBD`, atau “implement later” untuk 5.1.
- Item deferred pada 5.2 bukan placeholder implementasi; itu adalah hard gate yang memang ditentukan roadmap.

### Konsistensi

- Konvensi Laravel 12, Inertia v2, Pest 4, dan Wayfinder telah diselaraskan dengan dokumentasi yang diambil saat menyusun plan ini.
- Tidak ada penambahan dependency baru di fase 5.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-04-17-fase-5-fitur-lanjutan.md`. Two execution options:**

**1. Subagent-Driven (recommended)** - dispatch subagent baru per task, review per task, cocok untuk 5.1 yang terukur dan 5.2 yang punya gate desain

**2. Inline Execution** - kerjakan langsung di sesi ini secara berurutan dengan checkpoint review

**Catatan:** Untuk fase 5, eksekusi yang valid adalah `5.1` dulu, lalu berhenti di gate `5.2 Task 5` sampai spec khusus 5.2 selesai dan disetujui.
