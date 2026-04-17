# Fase 4: Fitur Operasional — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementasi tiga fitur operasional: export monitoring KGB & KP ke Excel/CSV, chart visual di dashboard, dan notifikasi email otomatis untuk pegawai yang mendekati/sudah jatuh tempo KGB & KP.

**Architecture:** Tiga subsistem independen yang dikerjakan sekuensial. Export menggunakan Laravel Excel (`maatwebsite/laravel-excel`) dengan class terpisah per monitoring. Chart menggunakan Recharts menggantikan Progress bar di `DashboardHeavySection`. Notifikasi menggunakan Laravel Notification + Schedule.

**Tech Stack:** PHP 8.2, Laravel 12, maatwebsite/laravel-excel ^3.1, React 19, Recharts ^3, Pest 4, Inertia v2

---

## File Map

### 4.1 Export Monitoring KGB & KP

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `app/Exports/KgbMonitoringExport.php` | Create | Export class KGB: query + heading + mapping |
| `app/Exports/KenaikanPangkatMonitoringExport.php` | Create | Export class KP: query + heading + mapping |
| `app/Http/Controllers/Monitoring/MonitoringKgbController.php` | Modify | Tambah method `export()` |
| `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php` | Modify | Tambah method `export()` |
| `routes/web.php` | Modify | Tambah GET route export untuk KGB dan KP |
| `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx` | Modify | Tambah tombol export |
| `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx` | Modify | Tambah tombol export |
| `tests/Feature/Monitoring/KgbExportTest.php` | Create | Test download Excel KGB |
| `tests/Feature/Monitoring/KenaikanPangkatExportTest.php` | Create | Test download Excel KP |

### 4.2 Chart/Grafik di Dashboard

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `package.json` | Modify | Tambah `recharts` |
| `resources/js/components/dashboard/GolonganBarChart.tsx` | Create | Bar chart distribusi golongan |
| `resources/js/components/dashboard/PendidikanBarChart.tsx` | Create | Horizontal bar chart distribusi pendidikan |
| `resources/js/components/dashboard/JenisKelaminPieChart.tsx` | Create | Pie chart distribusi jenis kelamin |
| `resources/js/components/dashboard/DashboardHeavySection.tsx` | Modify | Ganti Progress bar dengan chart components |

### 4.3 Notifikasi Otomatis KGB & KP

| File | Aksi | Tanggung Jawab |
|---|---|---|
| `app/Notifications/KgbJatuhTempoNotification.php` | Create | Notification class: subject + body email |
| `app/Notifications/KenaikanPangkatEligibleNotification.php` | Create | Notification class: subject + body email |
| `app/Console/Commands/SendKgbNotification.php` | Create | Artisan command: query pegawai + kirim notif |
| `app/Console/Commands/SendKenaikanPangkatNotification.php` | Create | Artisan command: query pegawai + kirim notif |
| `routes/console.php` | Modify | Daftarkan dua command ke Schedule (daily) |
| `tests/Feature/Notifications/KgbNotificationTest.php` | Create | Test command + notification KGB |
| `tests/Feature/Notifications/KenaikanPangkatNotificationTest.php` | Create | Test command + notification KP |

---

## 4.1 — Export Monitoring KGB & KP

### Task 1: Install & Publish Laravel Excel

- [ ] **Step 1: Install package**

```bash
cd /Volumes/Dev/Projects/kepegawaian_apps
composer require maatwebsite/excel
```

Expected: `maatwebsite/excel` muncul di `composer.json` dan `vendor/`.

- [ ] **Step 2: Publish config**

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

Expected: File `config/excel.php` terbuat.

- [ ] **Step 3: Verifikasi instalasi**

```bash
php artisan list | grep excel
```

Expected: Tidak ada error, Laravel bisa boot.

---

### Task 2: KGB Export Class (TDD)

**Files:**
- Create: `app/Exports/KgbMonitoringExport.php`
- Create: `tests/Feature/Monitoring/KgbExportTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Buat file `tests/Feature/Monitoring/KgbExportTest.php`:

```php
<?php

use App\Enums\StatusPegawai;
use App\Exports\KgbMonitoringExport;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createKgbPegawai(string $nextKgbDate, array $overrides = []): Pegawai
{
    $pegawai = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $overrides));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('KgbMonitoringExport bisa di-download sebagai xlsx', function () {
    Excel::fake();

    $user = \App\Models\User::factory()->create();
    actingAs($user);

    createKgbPegawai('2026-05-01');

    $export = new KgbMonitoringExport();

    Excel::download($export, 'kgb-monitoring.xlsx');

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('KgbMonitoringExport memiliki heading yang benar', function () {
    $export = new KgbMonitoringExport();

    expect($export->headings())->toBe([
        'NIP',
        'Nama Lengkap',
        'Pangkat/Golongan',
        'TMT Pangkat',
        'KGB Berikutnya',
        'Sisa Hari',
        'Status',
    ]);
});

test('endpoint export kgb mengembalikan file xlsx', function () {
    Excel::fake();

    $user = \App\Models\User::factory()->create();
    actingAs($user);

    createKgbPegawai('2026-05-01');

    $response = $this->get('/kepegawaian/monitoring/kgb/export');

    $response->assertStatus(200);
    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('endpoint export kgb dengan filter unit_kerja', function () {
    Excel::fake();

    $user = \App\Models\User::factory()->create();
    actingAs($user);

    createKgbPegawai('2026-05-01');

    $response = $this->get('/kepegawaian/monitoring/kgb/export?golongan=III');

    $response->assertStatus(200);
    Excel::assertDownloaded('kgb-monitoring.xlsx');
});
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Monitoring/KgbExportTest.php
```

Expected: FAIL — class `App\Exports\KgbMonitoringExport` not found.

- [ ] **Step 3: Buat KgbMonitoringExport class**

Buat file `app/Exports/KgbMonitoringExport.php`:

```php
<?php

namespace App\Exports;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KgbMonitoringExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly ?string $unitKerjaId = null,
        private readonly ?string $golongan = null,
        private readonly ?string $status = null,
        private readonly int $months = 3,
    ) {}

    public function collection(): Collection
    {
        $service = app(KgbMonitoringService::class);
        $maxSisaHari = $this->months * 30;
        $maxKgbDate = Carbon::today()->addDays($maxSisaHari)->toDateString();

        $query = Pegawai::query()
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->with([
                'riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt'),
            ])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->when($this->unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $this->unitKerjaId))
            ->when($this->golongan !== null, fn ($q) => $q->byGolongan($this->golongan));

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $kgbDateExpr = $driver === 'mysql'
            ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
            : "date(rp_kgb.tmt, '+2 years')";

        if ($this->status === null) {
            $query->whereRaw("{$kgbDateExpr} <= ?", [$maxKgbDate]);
        } else {
            $today = Carbon::today()->toDateString();
            match ($this->status) {
                'jatuh-tempo' => $query->whereRaw("{$kgbDateExpr} <= ?", [$today]),
                'segera' => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                    $today,
                    Carbon::today()->addDays(60)->toDateString(),
                ]),
                'mendekati' => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                    Carbon::today()->addDays(60)->toDateString(),
                    Carbon::today()->addDays(90)->toDateString(),
                ]),
                'aman' => $query->whereRaw("{$kgbDateExpr} > ?", [
                    Carbon::today()->addDays(90)->toDateString(),
                ]),
                default => $query->whereRaw("{$kgbDateExpr} <= ?", [$maxKgbDate]),
            };
        }

        return $query->get()->map(function (Pegawai $pegawai) use ($service): array {
            $kgbStatus = $service->getKgbStatus($pegawai);
            $riwayatAktif = $pegawai->riwayatPangkat->first();

            return [
                'nip'                    => $pegawai->nip ?? '-',
                'nama_lengkap'           => $pegawai->nama_lengkap,
                'pangkat_gol'            => $pegawai->nama_pangkat_lengkap ?? '-',
                'tmt_pangkat'            => $riwayatAktif?->tmt?->toDateString() ?? '-',
                'tanggal_kgb_berikutnya' => $kgbStatus['tanggal_kgb_berikutnya']->toDateString(),
                'sisa_hari'              => $kgbStatus['sisa_hari'],
                'status'                 => $kgbStatus['status'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Lengkap',
            'Pangkat/Golongan',
            'TMT Pangkat',
            'KGB Berikutnya',
            'Sisa Hari',
            'Status',
        ];
    }

    public function map($row): array
    {
        return array_values($row);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

```bash
php artisan test tests/Feature/Monitoring/KgbExportTest.php
```

Expected: 4 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Exports/KgbMonitoringExport.php tests/Feature/Monitoring/KgbExportTest.php
git commit -m "feat: tambahkan KgbMonitoringExport class dengan test"
```

---

### Task 3: KP Export Class (TDD)

**Files:**
- Create: `app/Exports/KenaikanPangkatMonitoringExport.php`
- Create: `tests/Feature/Monitoring/KenaikanPangkatExportTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Buat file `tests/Feature/Monitoring/KenaikanPangkatExportTest.php`:

```php
<?php

use App\Enums\StatusPegawai;
use App\Exports\KenaikanPangkatMonitoringExport;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createKpPegawai(string $tmtPangkat, array $overrides = []): Pegawai
{
    $pegawai = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $overrides));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => $tmtPangkat,
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('KenaikanPangkatMonitoringExport bisa di-download sebagai xlsx', function () {
    Excel::fake();

    $user = \App\Models\User::factory()->create();
    actingAs($user);

    createKpPegawai('2022-04-01');

    $export = new KenaikanPangkatMonitoringExport();

    Excel::download($export, 'kp-monitoring.xlsx');

    Excel::assertDownloaded('kp-monitoring.xlsx');
});

test('KenaikanPangkatMonitoringExport memiliki heading yang benar', function () {
    $export = new KenaikanPangkatMonitoringExport();

    expect($export->headings())->toBe([
        'NIP',
        'Nama Lengkap',
        'Pangkat Saat Ini',
        'TMT Pangkat',
        'TMT KP Berikutnya',
        'Periode Usul',
        'Batas Usul',
        'Sisa Hari Usul',
        'Status',
    ]);
});

test('endpoint export kp mengembalikan file xlsx', function () {
    Excel::fake();

    $user = \App\Models\User::factory()->create();
    actingAs($user);

    createKpPegawai('2022-04-01');

    $response = $this->get('/kepegawaian/monitoring/kenaikan-pangkat/export');

    $response->assertStatus(200);
    Excel::assertDownloaded('kp-monitoring.xlsx');
});
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Monitoring/KenaikanPangkatExportTest.php
```

Expected: FAIL — class `App\Exports\KenaikanPangkatMonitoringExport` not found.

- [ ] **Step 3: Buat KenaikanPangkatMonitoringExport class**

Buat file `app/Exports/KenaikanPangkatMonitoringExport.php`:

```php
<?php

namespace App\Exports;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KenaikanPangkatMonitoringExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly ?string $periode = null,
        private readonly ?string $unitKerjaId = null,
        private readonly ?string $golongan = null,
    ) {}

    public function collection(): Collection
    {
        $service = app(KenaikanPangkatMonitoringService::class);
        $normalizedPeriode = $this->periode !== null ? strtolower($this->periode) : null;

        $query = Pegawai::query()
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kp', function ($join) {
                $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kp.is_aktif', true);
            })
            ->with([
                'riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat')->orderByDesc('tmt'),
            ])
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->when($this->unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $this->unitKerjaId))
            ->when($this->golongan !== null, fn ($q) => $q->byGolongan($this->golongan))
            ->orderBy('nama_lengkap');

        return $query->get()
            ->filter(fn (Pegawai $p) => $p->riwayatPangkat->isNotEmpty())
            ->map(function (Pegawai $pegawai) use ($service): array {
                $riwayatAktif = $pegawai->riwayatPangkat->first();
                $status = $service->getKpStatus($pegawai);

                return [
                    'nip'                => $pegawai->nip ?? '-',
                    'nama_lengkap'       => $pegawai->nama_lengkap,
                    'pangkat_saat_ini'   => $riwayatAktif->pangkat?->nama ?? '-',
                    'tmt_pangkat'        => $riwayatAktif->tmt?->toDateString() ?? '-',
                    'tmt_kp_berikutnya'  => $status['tmt_kp_berikutnya']->toDateString(),
                    'periode_usul'       => $status['periode_usul'],
                    'batas_usul'         => $status['batas_usul']->toDateString(),
                    'sisa_hari_usul'     => $status['sisa_hari_usul'],
                    'status'             => $status['status'],
                ];
            });
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Lengkap',
            'Pangkat Saat Ini',
            'TMT Pangkat',
            'TMT KP Berikutnya',
            'Periode Usul',
            'Batas Usul',
            'Sisa Hari Usul',
            'Status',
        ];
    }

    public function map($row): array
    {
        return array_values($row);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

```bash
php artisan test tests/Feature/Monitoring/KenaikanPangkatExportTest.php
```

Expected: 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Exports/KenaikanPangkatMonitoringExport.php tests/Feature/Monitoring/KenaikanPangkatExportTest.php
git commit -m "feat: tambahkan KenaikanPangkatMonitoringExport class dengan test"
```

---

### Task 4: Export Routes & Controller Methods

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Monitoring/MonitoringKgbController.php`
- Modify: `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`

- [ ] **Step 1: Tambah method `export()` di MonitoringKgbController**

Edit `app/Http/Controllers/Monitoring/MonitoringKgbController.php`, tambahkan import dan method:

```php
<?php

namespace App\Http\Controllers\Monitoring;

use App\Exports\KgbMonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KgbMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function export(Request $request): BinaryFileResponse
    {
        $unitKerja = $request->string('unit_kerja')->value() ?: null;
        $golongan  = $request->string('golongan')->value() ?: null;
        $status    = $request->string('status')->value() ?: null;

        return Excel::download(
            new KgbMonitoringExport($unitKerja, $golongan, $status),
            'kgb-monitoring.xlsx',
        );
    }
}
```

- [ ] **Step 2: Tambah method `export()` di MonitoringKenaikanPangkatController**

Edit `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`:

```php
<?php

namespace App\Http\Controllers\Monitoring;

use App\Exports\KenaikanPangkatMonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function export(Request $request, KenaikanPangkatMonitoringService $service): BinaryFileResponse
    {
        $periode   = $request->string('periode')->value() ?: null;
        $unitKerja = $request->string('unit_kerja')->value() ?: null;
        $golongan  = $request->string('golongan')->value() ?: null;

        return Excel::download(
            new KenaikanPangkatMonitoringExport($periode, $unitKerja, $golongan),
            'kp-monitoring.xlsx',
        );
    }
}
```

- [ ] **Step 3: Tambah routes di `routes/web.php`**

Temukan bagian monitoring routes (sekitar baris 80-84) dan tambahkan route export:

```php
Route::get('kepegawaian/monitoring/kgb', [MonitoringKgbController::class, 'index'])
    ->name('monitoring.kgb.index');
Route::get('kepegawaian/monitoring/kgb/export', [MonitoringKgbController::class, 'export'])
    ->name('monitoring.kgb.export');

Route::get('kepegawaian/monitoring/kenaikan-pangkat', [MonitoringKenaikanPangkatController::class, 'index'])
    ->name('monitoring.kenaikan-pangkat.index');
Route::get('kepegawaian/monitoring/kenaikan-pangkat/export', [MonitoringKenaikanPangkatController::class, 'export'])
    ->name('monitoring.kenaikan-pangkat.export');
```

- [ ] **Step 4: Jalankan semua test export**

```bash
php artisan test tests/Feature/Monitoring/KgbExportTest.php tests/Feature/Monitoring/KenaikanPangkatExportTest.php
```

Expected: 7 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/Monitoring/
git commit -m "feat: tambahkan export endpoint untuk monitoring KGB dan KP"
```

---

### Task 5: Frontend — Tombol Export di Halaman KGB

**Files:**
- Modify: `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`

- [ ] **Step 1: Tambah tombol export**

Temukan bagian filter di `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`, tambahkan import `Download` dari lucide-react dan tombol export. Tambahkan function `handleExport` dan tombol di sebelah filter:

```tsx
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
```

Tambahkan function di dalam komponen (setelah `handleFilterChange`):

```tsx
function handleExport() {
    const params = new URLSearchParams();
    if (localFilters.unit_kerja) params.set('unit_kerja', localFilters.unit_kerja);
    if (localFilters.golongan) params.set('golongan', localFilters.golongan);
    if (localFilters.status) params.set('status', localFilters.status);
    window.location.href = `/kepegawaian/monitoring/kgb/export?${params.toString()}`;
}
```

Tambahkan tombol di dalam `div` filter (sejajar dengan filter lainnya):

```tsx
<div className="grid gap-1.5">
    <label className="text-sm font-medium invisible">Export</label>
    <Button
        variant="outline"
        size="sm"
        onClick={handleExport}
        className="h-9 gap-2"
    >
        <Download className="h-4 w-4" />
        Export Excel
    </Button>
</div>
```

- [ ] **Step 2: Jalankan semua test untuk memastikan tidak ada regresi**

```bash
php artisan test tests/Feature/Monitoring/
```

Expected: Semua test PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/kepegawaian/monitoring/kgb/index.tsx
git commit -m "feat: tambahkan tombol export Excel di halaman monitoring KGB"
```

---

### Task 6: Frontend — Tombol Export di Halaman KP

**Files:**
- Modify: `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`

- [ ] **Step 1: Tambah import dan function export**

Edit `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`. Tambahkan import:

```tsx
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
```

Tambahkan function `handleExport` di dalam komponen:

```tsx
function handleExport() {
    const params = new URLSearchParams();
    if (localFilters.unit_kerja) params.set('unit_kerja', localFilters.unit_kerja);
    if (localFilters.golongan) params.set('golongan', localFilters.golongan);
    if (localFilters.periode) params.set('periode', localFilters.periode);
    window.location.href = `/kepegawaian/monitoring/kenaikan-pangkat/export?${params.toString()}`;
}
```

Tambahkan tombol export di sebelah filter (di dalam CardContent filter section):

```tsx
<div className="grid gap-1.5">
    <label className="text-sm font-medium invisible">Export</label>
    <Button
        variant="outline"
        size="sm"
        onClick={handleExport}
        className="h-9 gap-2"
    >
        <Download className="h-4 w-4" />
        Export Excel
    </Button>
</div>
```

- [ ] **Step 2: Jalankan semua test monitoring**

```bash
php artisan test tests/Feature/Monitoring/
```

Expected: Semua test PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx
git commit -m "feat: tambahkan tombol export Excel di halaman monitoring KP"
```

---

## 4.2 — Chart/Grafik di Dashboard

### Task 7: Install Recharts

- [ ] **Step 1: Install recharts**

```bash
npm install recharts react-is
```

Expected: `recharts` dan `react-is` muncul di `package.json` dependencies.

- [ ] **Step 2: Verifikasi TypeScript types tersedia**

```bash
cat node_modules/recharts/types/index.d.ts | head -5
```

Expected: File d.ts ada (recharts include types bawaan).

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install recharts untuk chart dashboard"
```

---

### Task 8: GolonganBarChart Component

**Files:**
- Create: `resources/js/components/dashboard/GolonganBarChart.tsx`

- [ ] **Step 1: Buat komponen GolonganBarChart**

Buat file `resources/js/components/dashboard/GolonganBarChart.tsx`:

```tsx
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface GolonganItem {
    golongan: string;
    count: number;
    percentage: number;
}

interface Props {
    data: GolonganItem[];
}

const COLORS = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd'];

export function GolonganBarChart({ data }: Props) {
    const chartData = data.map((item) => ({
        name: `Gol ${item.golongan}`,
        value: item.count,
        pct: item.percentage,
    }));

    return (
        <ResponsiveContainer width="100%" height={200}>
            <BarChart data={chartData} margin={{ top: 4, right: 8, left: -16, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                <YAxis tick={{ fontSize: 12 }} allowDecimals={false} />
                <Tooltip
                    formatter={(value: number, _name: string, props: { payload?: { pct: number } }) => [
                        `${value} pegawai (${props.payload?.pct ?? 0}%)`,
                        'Jumlah',
                    ]}
                />
                <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                    {chartData.map((_entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}
```

- [ ] **Step 2: Build TypeScript check**

```bash
npx tsc --noEmit 2>&1 | grep -i "GolonganBarChart" | head -10
```

Expected: Tidak ada error terkait `GolonganBarChart`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/dashboard/GolonganBarChart.tsx
git commit -m "feat: tambahkan GolonganBarChart component dengan Recharts"
```

---

### Task 9: PendidikanBarChart Component

**Files:**
- Create: `resources/js/components/dashboard/PendidikanBarChart.tsx`

- [ ] **Step 1: Buat komponen PendidikanBarChart (horizontal)**

Buat file `resources/js/components/dashboard/PendidikanBarChart.tsx`:

```tsx
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface PendidikanItem {
    pendidikan: string;
    count: number;
    percentage: number;
}

interface Props {
    data: PendidikanItem[];
}

export function PendidikanBarChart({ data }: Props) {
    const chartData = data.map((item) => ({
        name: item.pendidikan,
        value: item.count,
        pct: item.percentage,
    }));

    return (
        <ResponsiveContainer width="100%" height={Math.max(200, chartData.length * 40)}>
            <BarChart
                data={chartData}
                layout="vertical"
                margin={{ top: 4, right: 48, left: 8, bottom: 0 }}
            >
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 12 }} allowDecimals={false} />
                <YAxis
                    type="category"
                    dataKey="name"
                    tick={{ fontSize: 12 }}
                    width={80}
                />
                <Tooltip
                    formatter={(value: number, _name: string, props: { payload?: { pct: number } }) => [
                        `${value} pegawai (${props.payload?.pct ?? 0}%)`,
                        'Jumlah',
                    ]}
                />
                <Bar dataKey="value" fill="#6366f1" radius={[0, 4, 4, 0]} label={{ position: 'right', fontSize: 12 }} />
            </BarChart>
        </ResponsiveContainer>
    );
}
```

- [ ] **Step 2: Build TypeScript check**

```bash
npx tsc --noEmit 2>&1 | grep -i "PendidikanBarChart" | head -10
```

Expected: Tidak ada error.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/dashboard/PendidikanBarChart.tsx
git commit -m "feat: tambahkan PendidikanBarChart component dengan Recharts"
```

---

### Task 10: JenisKelaminPieChart Component

**Files:**
- Create: `resources/js/components/dashboard/JenisKelaminPieChart.tsx`

- [ ] **Step 1: Buat komponen JenisKelaminPieChart**

Buat file `resources/js/components/dashboard/JenisKelaminPieChart.tsx`:

```tsx
import {
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';

interface JenisKelaminItem {
    label: string;
    total: number;
    percentage: number;
}

interface Props {
    data: JenisKelaminItem[];
}

const COLORS: Record<string, string> = {
    'Laki-laki': '#6366f1',
    Perempuan: '#f472b6',
};
const FALLBACK_COLORS = ['#6366f1', '#f472b6'];

export function JenisKelaminPieChart({ data }: Props) {
    const chartData = data.map((item) => ({
        name: item.label,
        value: item.total,
        pct: item.percentage,
    }));

    return (
        <ResponsiveContainer width="100%" height={240}>
            <PieChart>
                <Pie
                    data={chartData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={100}
                    paddingAngle={4}
                    dataKey="value"
                    label={({ name, pct }) => `${name}: ${pct}%`}
                    labelLine={false}
                >
                    {chartData.map((entry, index) => (
                        <Cell
                            key={`cell-${index}`}
                            fill={COLORS[entry.name] ?? FALLBACK_COLORS[index % FALLBACK_COLORS.length]}
                        />
                    ))}
                </Pie>
                <Tooltip formatter={(value: number) => [`${value} pegawai`, 'Jumlah']} />
                <Legend />
            </PieChart>
        </ResponsiveContainer>
    );
}
```

- [ ] **Step 2: Build TypeScript check**

```bash
npx tsc --noEmit 2>&1 | grep -i "JenisKelaminPieChart" | head -10
```

Expected: Tidak ada error.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/dashboard/JenisKelaminPieChart.tsx
git commit -m "feat: tambahkan JenisKelaminPieChart component dengan Recharts"
```

---

### Task 11: Update DashboardHeavySection dengan Chart Components

**Files:**
- Modify: `resources/js/components/dashboard/DashboardHeavySection.tsx`

- [ ] **Step 1: Update DashboardHeavySection**

Ganti isi `resources/js/components/dashboard/DashboardHeavySection.tsx` dengan versi yang menggunakan chart components. Ganti Progress bar Golongan, Pendidikan, dan JenisKelamin dengan chart, tapi pertahankan tabel Unit Kerja dan Jabatan (progress bar cocok untuk list panjang):

```tsx
import { usePage } from '@inertiajs/react';
import {
    Briefcase, Building2, GraduationCap, UserCircle, Users2,
} from 'lucide-react';
import {
    Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { BlurFade } from '@/components/ui/blur-fade';
import { useDashboardStats } from '@/hooks/use-dashboard-stats';
import type { HeavyDashboardStats } from '@/hooks/use-dashboard-stats';
import { GolonganBarChart } from './GolonganBarChart';
import { PendidikanBarChart } from './PendidikanBarChart';
import { JenisKelaminPieChart } from './JenisKelaminPieChart';

export function DashboardHeavySection() {
    const { heavyStats } = usePage<{ heavyStats: HeavyDashboardStats }>().props;
    const {
        golonganItems, unitKerjaItems, jabatanItems, pendidikanItems, jenisKelaminItems,
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
                    <CardContent>
                        {golonganItems.length > 0 ? (
                            <GolonganBarChart data={golonganItems} />
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data golongan</p>
                        )}
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
                                        <span className="truncate pr-4 font-medium" title={item.nama}>{item.nama}</span>
                                        <span className="whitespace-nowrap text-muted-foreground">{item.count} pegawai</span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data unit kerja</p>
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
                                        <span className="truncate pr-4 font-medium" title={item.nama}>{item.nama}</span>
                                        <span className="whitespace-nowrap text-muted-foreground">{item.count} pegawai</span>
                                    </div>
                                    <Progress value={item.percentage} className="h-2" />
                                </div>
                            ))
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data jabatan</p>
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
                    <CardContent>
                        {pendidikanItems.length > 0 ? (
                            <PendidikanBarChart data={pendidikanItems} />
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data pendidikan</p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>

            <BlurFade delay={0.5} className="col-span-1 md:col-span-2 lg:col-span-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users2 className="h-5 w-5 text-accent" />
                            Distribusi Jenis Kelamin
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {jenisKelaminItems.length > 0 ? (
                            <JenisKelaminPieChart data={jenisKelaminItems} />
                        ) : (
                            <p className="py-4 text-center text-sm text-muted-foreground">Belum ada data jenis kelamin</p>
                        )}
                    </CardContent>
                </Card>
            </BlurFade>
        </div>
    );
}
```

- [ ] **Step 2: Build TypeScript check**

```bash
npx tsc --noEmit 2>&1 | head -20
```

Expected: Tidak ada error TypeScript.

- [ ] **Step 3: Build frontend**

```bash
npm run build 2>&1 | tail -10
```

Expected: Build sukses tanpa error.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/dashboard/
git commit -m "feat: ganti progress bar dengan recharts di dashboard distribusi"
```

---

## 4.3 — Notifikasi Otomatis KGB & KP

### Task 12: KGB Notification Class (TDD)

**Files:**
- Create: `app/Notifications/KgbJatuhTempoNotification.php`
- Create: `tests/Feature/Notifications/KgbNotificationTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Buat file `tests/Feature/Notifications/KgbNotificationTest.php`:

```php
<?php

use App\Console\Commands\SendKgbNotification;
use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Notifications\KgbJatuhTempoNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createKgbPegawaiForNotif(string $nextKgbDate, ?string $email = null): Pegawai
{
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'email' => $email ?? fake()->safeEmail(),
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('KgbJatuhTempoNotification dapat dikirim ke pegawai', function () {
    Notification::fake();

    $pegawai = createKgbPegawaiForNotif('2026-04-10', 'pegawai@example.com');

    $notification = new KgbJatuhTempoNotification(
        kgbDate: Carbon::parse('2026-04-10'),
        sisaHari: -7,
        status: 'Sudah Jatuh Tempo',
    );

    Notification::send($pegawai, $notification);

    Notification::assertSentTo($pegawai, KgbJatuhTempoNotification::class);
});

test('KgbJatuhTempoNotification memiliki subject dan body yang benar', function () {
    $notification = new KgbJatuhTempoNotification(
        kgbDate: Carbon::parse('2026-04-10'),
        sisaHari: -7,
        status: 'Sudah Jatuh Tempo',
    );

    $mail = $notification->toMail(new Pegawai(['nama_lengkap' => 'Budi Santoso']));

    expect($mail->subject)->toContain('KGB')
        ->and($mail->introLines)->not->toBeEmpty();
});

test('command SendKgbNotification mengirim notifikasi ke pegawai yang sudah jatuh tempo', function () {
    Notification::fake();

    // Pegawai KGB sudah jatuh tempo (KGB date = kemarin)
    createKgbPegawaiForNotif(
        Carbon::today()->subDay()->toDateString(),
        'jatuh@example.com',
    );

    // Pegawai KGB masih aman (KGB date = 6 bulan lagi)
    createKgbPegawaiForNotif(
        Carbon::today()->addMonths(6)->toDateString(),
        'aman@example.com',
    );

    $this->artisan('kgb:notify')->assertExitCode(0);

    Notification::assertSentToTimes(
        Pegawai::where('email', 'jatuh@example.com')->first(),
        KgbJatuhTempoNotification::class,
        1,
    );

    Notification::assertNotSentTo(
        Pegawai::where('email', 'aman@example.com')->first(),
        KgbJatuhTempoNotification::class,
    );
});

test('command SendKgbNotification tidak crash saat tidak ada pegawai KGB', function () {
    Notification::fake();

    $this->artisan('kgb:notify')->assertExitCode(0);

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Notifications/KgbNotificationTest.php
```

Expected: FAIL — class `App\Notifications\KgbJatuhTempoNotification` not found.

- [ ] **Step 3: Buat KgbJatuhTempoNotification**

Buat file `app/Notifications/KgbJatuhTempoNotification.php`:

```php
<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KgbJatuhTempoNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Carbon $kgbDate,
        private readonly int $sisaHari,
        private readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nama = $notifiable->nama_lengkap ?? 'Pegawai';
        $tanggal = $this->kgbDate->translatedFormat('d F Y');

        $subject = $this->sisaHari <= 0
            ? "KGB Sudah Jatuh Tempo — {$tanggal}"
            : "Pengingat KGB Mendekati Jatuh Tempo — {$tanggal}";

        $introLine = $this->sisaHari <= 0
            ? "Kenaikan Gaji Berkala (KGB) Anda telah jatuh tempo pada {$tanggal}."
            : "Kenaikan Gaji Berkala (KGB) Anda akan jatuh tempo pada {$tanggal} ({$this->sisaHari} hari lagi).";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Yth. {$nama},")
            ->line($introLine)
            ->line("Status KGB: **{$this->status}**")
            ->line('Harap segera mengurus dokumen KGB ke bagian kepegawaian.')
            ->action('Lihat Monitoring KGB', url('/kepegawaian/monitoring/kgb'))
            ->salutation('Hormat kami, Sistem Kepegawaian');
    }
}
```

- [ ] **Step 4: Buat SendKgbNotification command**

Buat file `app/Console/Commands/SendKgbNotification.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Notifications\KgbJatuhTempoNotification;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendKgbNotification extends Command
{
    protected $signature = 'kgb:notify';
    protected $description = 'Kirim notifikasi email ke pegawai yang KGB-nya sudah/mendekati jatuh tempo';

    public function handle(KgbMonitoringService $service): int
    {
        $driver = DB::connection()->getDriverName();
        $kgbDateExpr = $driver === 'mysql'
            ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
            : "date(rp_kgb.tmt, '+2 years')";

        $batasNotif = Carbon::today()->addDays(90)->toDateString();

        $pegawaiList = Pegawai::query()
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->with(['riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt')])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->whereNotNull('pegawai.email')
            ->whereRaw("{$kgbDateExpr} <= ?", [$batasNotif])
            ->get();

        $count = 0;
        foreach ($pegawaiList as $pegawai) {
            try {
                $kgbStatus = $service->getKgbStatus($pegawai);
                $pegawai->notify(new KgbJatuhTempoNotification(
                    kgbDate: $kgbStatus['tanggal_kgb_berikutnya'],
                    sisaHari: $kgbStatus['sisa_hari'],
                    status: $kgbStatus['status'],
                ));
                $count++;
            } catch (\Exception $e) {
                $this->error("Gagal kirim notifikasi ke pegawai ID {$pegawai->id}: {$e->getMessage()}");
            }
        }

        $this->info("Notifikasi KGB terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan PASS**

```bash
php artisan test tests/Feature/Notifications/KgbNotificationTest.php
```

Expected: 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/KgbJatuhTempoNotification.php app/Console/Commands/SendKgbNotification.php tests/Feature/Notifications/KgbNotificationTest.php
git commit -m "feat: tambahkan KgbJatuhTempoNotification dan SendKgbNotification command"
```

---

### Task 13: KP Notification Class (TDD)

**Files:**
- Create: `app/Notifications/KenaikanPangkatEligibleNotification.php`
- Create: `app/Console/Commands/SendKenaikanPangkatNotification.php`
- Create: `tests/Feature/Notifications/KenaikanPangkatNotificationTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Buat file `tests/Feature/Notifications/KenaikanPangkatNotificationTest.php`:

```php
<?php

use App\Console\Commands\SendKenaikanPangkatNotification;
use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Notifications\KenaikanPangkatEligibleNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createKpPegawaiForNotif(string $tmtPangkat, ?string $email = null): Pegawai
{
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'email' => $email ?? fake()->safeEmail(),
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => $tmtPangkat,
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('KenaikanPangkatEligibleNotification dapat dikirim ke pegawai', function () {
    Notification::fake();

    $pegawai = createKpPegawaiForNotif('2022-04-01', 'pegawai@example.com');

    $notification = new KenaikanPangkatEligibleNotification(
        tmtKpBerikutnya: Carbon::parse('2026-04-01'),
        periodeUsul: 'April 2026',
        batasUsul: Carbon::parse('2025-10-01'),
        status: 'Sudah Eligible',
    );

    Notification::send($pegawai, $notification);

    Notification::assertSentTo($pegawai, KenaikanPangkatEligibleNotification::class);
});

test('KenaikanPangkatEligibleNotification memiliki subject dan body yang benar', function () {
    $notification = new KenaikanPangkatEligibleNotification(
        tmtKpBerikutnya: Carbon::parse('2026-04-01'),
        periodeUsul: 'April 2026',
        batasUsul: Carbon::parse('2025-10-01'),
        status: 'Sudah Eligible',
    );

    $mail = $notification->toMail(new Pegawai(['nama_lengkap' => 'Siti Aminah']));

    expect($mail->subject)->toContain('Kenaikan Pangkat')
        ->and($mail->introLines)->not->toBeEmpty();
});

test('command SendKenaikanPangkatNotification mengirim notifikasi ke pegawai eligible', function () {
    Notification::fake();

    // TMT 4 tahun lalu = sudah eligible
    createKpPegawaiForNotif(
        Carbon::today()->subYears(4)->toDateString(),
        'eligible@example.com',
    );

    // TMT 1 tahun lalu = belum eligible
    createKpPegawaiForNotif(
        Carbon::today()->subYear()->toDateString(),
        'belum@example.com',
    );

    $this->artisan('kp:notify')->assertExitCode(0);

    Notification::assertSentToTimes(
        Pegawai::where('email', 'eligible@example.com')->first(),
        KenaikanPangkatEligibleNotification::class,
        1,
    );

    Notification::assertNotSentTo(
        Pegawai::where('email', 'belum@example.com')->first(),
        KenaikanPangkatEligibleNotification::class,
    );
});

test('command SendKenaikanPangkatNotification tidak crash saat tidak ada pegawai eligible', function () {
    Notification::fake();

    $this->artisan('kp:notify')->assertExitCode(0);

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Notifications/KenaikanPangkatNotificationTest.php
```

Expected: FAIL — class `App\Notifications\KenaikanPangkatEligibleNotification` not found.

- [ ] **Step 3: Buat KenaikanPangkatEligibleNotification**

Buat file `app/Notifications/KenaikanPangkatEligibleNotification.php`:

```php
<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KenaikanPangkatEligibleNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Carbon $tmtKpBerikutnya,
        private readonly string $periodeUsul,
        private readonly Carbon $batasUsul,
        private readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nama = $notifiable->nama_lengkap ?? 'Pegawai';
        $tmtFormatted = $this->tmtKpBerikutnya->translatedFormat('d F Y');
        $batasFormatted = $this->batasUsul->translatedFormat('d F Y');

        $isEligible = $this->status === 'Sudah Eligible';
        $subject = $isEligible
            ? "Kenaikan Pangkat Anda Sudah Eligible — Periode {$this->periodeUsul}"
            : "Pengingat Kenaikan Pangkat — Mendekati Eligibilitas";

        $introLine = $isEligible
            ? "Anda telah memenuhi syarat Kenaikan Pangkat (KP) per {$tmtFormatted} untuk periode usul **{$this->periodeUsul}**."
            : "Anda akan memenuhi syarat Kenaikan Pangkat per {$tmtFormatted} untuk periode usul **{$this->periodeUsul}**.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Yth. {$nama},")
            ->line($introLine)
            ->line("Batas waktu pengajuan usul: **{$batasFormatted}**")
            ->line("Status: **{$this->status}**")
            ->line('Segera siapkan berkas persyaratan dan ajukan ke bagian kepegawaian sebelum batas waktu.')
            ->action('Lihat Monitoring Kenaikan Pangkat', url('/kepegawaian/monitoring/kenaikan-pangkat'))
            ->salutation('Hormat kami, Sistem Kepegawaian');
    }
}
```

- [ ] **Step 4: Buat SendKenaikanPangkatNotification command**

Buat file `app/Console/Commands/SendKenaikanPangkatNotification.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Notifications\KenaikanPangkatEligibleNotification;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendKenaikanPangkatNotification extends Command
{
    protected $signature = 'kp:notify';
    protected $description = 'Kirim notifikasi email ke pegawai yang sudah/mendekati eligible Kenaikan Pangkat';

    public function handle(KenaikanPangkatMonitoringService $service): int
    {
        $driver = DB::connection()->getDriverName();
        $tmtPlus4Year = $driver === 'mysql'
            ? 'DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)'
            : "date(rp_kp.tmt, '+4 years')";

        $batasNotif = Carbon::today()->addMonths(6)->toDateString();

        $pegawaiList = Pegawai::query()
            ->join('riwayat_pangkat as rp_kp', function ($join) {
                $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kp.is_aktif', true);
            })
            ->with(['riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat')->orderByDesc('tmt')])
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->whereNotNull('pegawai.email')
            ->whereRaw("{$tmtPlus4Year} <= ?", [$batasNotif])
            ->get();

        $count = 0;
        foreach ($pegawaiList as $pegawai) {
            try {
                $kpStatus = $service->getKpStatus($pegawai);
                $pegawai->notify(new KenaikanPangkatEligibleNotification(
                    tmtKpBerikutnya: $kpStatus['tmt_kp_berikutnya'],
                    periodeUsul: $kpStatus['periode_usul'],
                    batasUsul: $kpStatus['batas_usul'],
                    status: $kpStatus['status'],
                ));
                $count++;
            } catch (\Exception $e) {
                $this->error("Gagal kirim notifikasi ke pegawai ID {$pegawai->id}: {$e->getMessage()}");
            }
        }

        $this->info("Notifikasi KP terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan PASS**

```bash
php artisan test tests/Feature/Notifications/KenaikanPangkatNotificationTest.php
```

Expected: 4 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/KenaikanPangkatEligibleNotification.php app/Console/Commands/SendKenaikanPangkatNotification.php tests/Feature/Notifications/KenaikanPangkatNotificationTest.php
git commit -m "feat: tambahkan KenaikanPangkatEligibleNotification dan SendKenaikanPangkatNotification command"
```

---

### Task 14: Register Schedule di console.php

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Daftarkan command ke scheduler**

Edit `routes/console.php`, tambahkan dua schedule command:

```php
<?php

use App\Models\IamSsoCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pruning SSO codes expired setiap jam
Schedule::command('model:prune', ['--model' => IamSsoCode::class])->hourly();

// Notifikasi KGB setiap hari jam 07:00
Schedule::command('kgb:notify')->dailyAt('07:00');

// Notifikasi KP setiap hari jam 07:00
Schedule::command('kp:notify')->dailyAt('07:00');
```

- [ ] **Step 2: Verifikasi schedule terdaftar**

```bash
php artisan schedule:list
```

Expected: `kgb:notify` dan `kp:notify` muncul dengan jadwal "Daily at 07:00".

- [ ] **Step 3: Jalankan semua test notifikasi**

```bash
php artisan test tests/Feature/Notifications/
```

Expected: 8 tests PASS.

- [ ] **Step 4: Commit**

```bash
git add routes/console.php
git commit -m "feat: jadwalkan kgb:notify dan kp:notify setiap hari jam 07:00"
```

---

### Task 15: Final Verification — Semua Test Fase 4

- [ ] **Step 1: Jalankan semua test**

```bash
php artisan test tests/Feature/Monitoring/ tests/Feature/Notifications/
```

Expected: Semua test PASS, tidak ada error.

- [ ] **Step 2: Jalankan full test suite**

```bash
php artisan test
```

Expected: Semua test PASS, tidak ada regresi.

- [ ] **Step 3: Build frontend final check**

```bash
npm run build
```

Expected: Build sukses, tidak ada error atau warning TypeScript.

---

## Self-Review Checklist

### Spec Coverage

| Item Spec | Task yang Mengimplementasikan |
|---|---|
| 4.1 Export KGB CSV/Excel | Task 1–6 |
| 4.1 Export class KgbMonitoringExport | Task 2 |
| 4.1 Export class KenaikanPangkatMonitoringExport | Task 3 |
| 4.1 Tombol export di halaman monitoring | Task 5, 6 |
| 4.2 Chart dashboard | Task 7–11 |
| 4.2 Ganti progress bar distribusi golongan | Task 8, 11 |
| 4.2 Ganti progress bar distribusi pendidikan | Task 9, 11 |
| 4.2 Ganti progress bar jenis kelamin | Task 10, 11 |
| 4.3 SendKgbNotification command | Task 12 |
| 4.3 SendKenaikanPangkatNotification command | Task 13 |
| 4.3 KgbJatuhTempoNotification | Task 12 |
| 4.3 KenaikanPangkatEligibleNotification | Task 13 |
| 4.3 Jadwal harian di console.php | Task 14 |

### Type Consistency Check

- `KgbMonitoringExport` constructor: `(?string $unitKerjaId, ?string $golongan, ?string $status, int $months)` → dipakai di Task 4 MonitoringKgbController: `new KgbMonitoringExport($unitKerja, $golongan, $status)` ✓
- `KenaikanPangkatMonitoringExport` constructor: `(?string $periode, ?string $unitKerjaId, ?string $golongan)` → dipakai di Task 4: `new KenaikanPangkatMonitoringExport($periode, $unitKerja, $golongan)` ✓
- `KgbJatuhTempoNotification` constructor: `(Carbon $kgbDate, int $sisaHari, string $status)` → dipakai di Task 12 command ✓
- `KenaikanPangkatEligibleNotification` constructor: `(Carbon $tmtKpBerikutnya, string $periodeUsul, Carbon $batasUsul, string $status)` → dipakai di Task 13 command ✓
- `GolonganBarChart` props: `{ data: GolonganItem[] }` → dipakai di `DashboardHeavySection` dengan `golonganItems` dari `useDashboardStats` yang bertipe `GolonganItem[]` ✓
- `PendidikanBarChart` props: `{ data: PendidikanItem[] }` → dipakai dengan `pendidikanItems` ✓
- `JenisKelaminPieChart` props: `{ data: JenisKelaminItem[] }` → dipakai dengan `jenisKelaminItems` ✓
