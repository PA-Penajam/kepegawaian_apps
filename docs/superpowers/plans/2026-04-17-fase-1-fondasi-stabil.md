# Fase 1: Fondasi Stabil — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki 3 masalah performa dan reliability kritis sebelum menambahkan fitur baru.

**Architecture:** Setiap item dikerjakan sekuensial dan tuntas (termasuk test) sebelum lanjut. Item 1.1 harus selesai sebelum 1.2 karena `DashboardStatService` memanggil `KgbMonitoringService`.

**Tech Stack:** Laravel 12, PHP 8.4, Pest v4, React 19, Inertia.js v2, TypeScript, `Illuminate\Pagination\LengthAwarePaginator`

---

## Urutan Pengerjaan

1. **Task 1** → **Task 2** → **Task 3** → **Task 4**
2. Task 1 dan 2 (monitoring pagination) harus selesai sebelum Task 3 (dashboard)
3. Task 4 (IAM cache) independen, bisa dikerjakan kapan saja

---

## Task 1: Pagination KGB Monitoring

**Files:**
- Modify: `app/Services/KgbMonitoringService.php`
- Modify: `app/Http/Controllers/Monitoring/MonitoringKgbController.php`
- Modify: `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`
- Modify: `tests/Feature/Monitoring/KgbMonitoringTest.php`

---

- [ ] **Step 1.1: Tulis test baru yang expects paginated response**

Tambahkan test ini di `tests/Feature/Monitoring/KgbMonitoringTest.php`:

```php
test('controller mengembalikan data pegawai dalam format paginasi', function () {
    Carbon::setTestNow('2026-01-01');

    $user = Pegawai::factory()->operator()->create();

    // Buat 20 pegawai dengan KGB segera agar melebihi default per_page 15
    foreach (range(1, 20) as $i) {
        createPegawaiWithAktifPangkat('2026-01-31', [
            'nama_lengkap' => "Pegawai {$i}",
        ]);
    }

    actingAs($user);

    get(route('monitoring.kgb.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('pegawaiList.data', 15) // default per_page 15
            ->has('pegawaiList.meta')
            ->where('pegawaiList.meta.total', 20)
            ->where('pegawaiList.meta.last_page', 2)
            ->has('kgbStats')
            ->where('kgbStats.total', 20),
        );

    Carbon::setTestNow();
});
```

- [ ] **Step 1.2: Jalankan test, pastikan FAIL**

```bash
cd /home/moohard/dev/project/kepegawaian-apps
php artisan test tests/Feature/Monitoring/KgbMonitoringTest.php --filter="controller mengembalikan data pegawai dalam format paginasi" --stop-on-failure
```

Expected: FAIL — `pegawaiList.data` tidak ada (saat ini `pegawaiList` adalah array flat)

- [ ] **Step 1.3: Refactor `KgbMonitoringService` — tambah method `getKgbStats()` dan ubah `getUpcomingKgb()` untuk paginate**

Ganti seluruh isi `app/Services/KgbMonitoringService.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class KgbMonitoringService
{
    public function getUpcomingKgb(int $months = 3, int $perPage = 15): LengthAwarePaginator
    {
        $maxSisaHari = $months * 30;

        return Pegawai::query()
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
            ->whereRaw('DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) <= ?', [$maxSisaHari])
            ->orderByRaw('DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) ASC')
            ->paginate($perPage)
            ->through(function (Pegawai $pegawai): array {
                $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);
                $statusKgb = $this->getKgbStatus($pegawai);

                return [
                    'id' => $pegawai->id,
                    'nip' => $pegawai->nip,
                    'nama_lengkap' => $pegawai->nama_lengkap,
                    'pangkat_gol' => $pegawai->nama_pangkat_lengkap,
                    'tmt_pangkat' => $riwayatPangkatAktif?->tmt?->toDateString(),
                    'tanggal_kgb_berikutnya' => $statusKgb['tanggal_kgb_berikutnya']->toDateString(),
                    'sisa_hari' => $statusKgb['sisa_hari'],
                    'status' => $statusKgb['status'],
                ];
            });
    }

    public function getKgbStats(int $months = 3): array
    {
        $maxSisaHari = $months * 30;

        $row = Pegawai::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) <= 0 THEN 1 ELSE 0 END) as jatuh_tempo,
                SUM(CASE WHEN DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) BETWEEN 1 AND 60 THEN 1 ELSE 0 END) as segera,
                SUM(CASE WHEN DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) as mendekati,
                SUM(CASE WHEN DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) > 90 THEN 1 ELSE 0 END) as aman
            ')
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->whereRaw('DATEDIFF(DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR), CURDATE()) <= ?', [$maxSisaHari])
            ->first();

        return [
            'total'      => (int) ($row?->total ?? 0),
            'jatuhTempo' => (int) ($row?->jatuh_tempo ?? 0),
            'segera'     => (int) ($row?->segera ?? 0),
            'mendekati'  => (int) ($row?->mendekati ?? 0),
            'aman'       => (int) ($row?->aman ?? 0),
        ];
    }

    public function getKgbStatus(Pegawai $pegawai): array
    {
        $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);

        if ($riwayatPangkatAktif === null || $riwayatPangkatAktif->tmt === null) {
            throw new InvalidArgumentException('Pegawai tidak memiliki riwayat pangkat aktif.');
        }

        $tanggalKgbBerikutnya = Carbon::parse($riwayatPangkatAktif->tmt)->addYears(2)->startOfDay();
        $sisaHari = (int) Carbon::today()->diffInDays($tanggalKgbBerikutnya, false);

        return [
            'tanggal_kgb_berikutnya' => $tanggalKgbBerikutnya,
            'sisa_hari'              => $sisaHari,
            'status'                 => $this->resolveStatusLabel($sisaHari),
        ];
    }

    protected function getRiwayatPangkatAktif(Pegawai $pegawai): ?RiwayatPangkat
    {
        if (! $pegawai->relationLoaded('riwayatPangkat')) {
            $pegawai->load([
                'riwayatPangkat' => fn ($query) => $query->aktif()->latest('tmt'),
            ]);
        }

        return $pegawai->riwayatPangkat->first();
    }

    protected function resolveStatusLabel(int $sisaHari): string
    {
        if ($sisaHari <= 0) {
            return 'Sudah Jatuh Tempo';
        }

        if ($sisaHari <= 60) {
            return 'Segera';
        }

        if ($sisaHari <= 90) {
            return 'Mendekati';
        }

        return 'Aman';
    }
}
```

- [ ] **Step 1.4: Update `MonitoringKgbController` — gunakan method baru dan kirim stats terpisah**

Ganti seluruh isi `app/Http/Controllers/Monitoring/MonitoringKgbController.php`:

```php
<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
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
        $perPage = $request->integer('per_page', 15);

        return Inertia::render('kepegawaian/monitoring/kgb/index', [
            'pegawaiList' => $this->kgbMonitoringService->getUpcomingKgb(3, $perPage),
            'kgbStats'    => $this->kgbMonitoringService->getKgbStats(3),
        ]);
    }
}
```

- [ ] **Step 1.5: Update TypeScript types dan tambah pagination di frontend**

Di `resources/js/pages/kepegawaian/monitoring/kgb/index.tsx`, ubah bagian import dan type Props:

Tambahkan import di baris atas (setelah import yang sudah ada):
```tsx
import { PaginationWrapper } from '@/components/pagination-wrapper';
import type { PaginatedData } from '@/types';
```

Ubah type `Props`:
```tsx
type Props = {
    pegawaiList: PaginatedData<PegawaiMonitoringKgb>;
    kgbStats: {
        total: number;
        jatuhTempo: number;
        segera: number;
        mendekati: number;
        aman: number;
    };
};
```

Ubah semua referensi `pegawaiList` di dalam komponen:
- Ganti `pegawaiList` (array flat) menjadi `pegawaiList.data`
- Setelah tabel, tambahkan komponen pagination:

```tsx
<PaginationWrapper meta={pegawaiList.meta} />
```

Catatan: Variabel `filtered` yang ada menggunakan `useMemo` dengan `pegawaiList` — ubah menjadi `pegawaiList.data`:
```tsx
const filtered = useMemo(
    () =>
        filterMap[activeFilter] === null
            ? pegawaiList.data
            : pegawaiList.data.filter((p) => p.status === filterMap[activeFilter]),
    [pegawaiList.data, activeFilter],
);
```

- [ ] **Step 1.6: Update test lama yang menggunakan struktur array flat**

Di `tests/Feature/Monitoring/KgbMonitoringTest.php`, update test `controller index menampilkan inertia monitoring kgb`:

```php
test('controller index menampilkan inertia monitoring kgb', function () {
    Carbon::setTestNow('2026-01-01');

    $user = Pegawai::factory()->operator()->create();
    createPegawaiWithAktifPangkat('2026-01-31', [
        'nama_lengkap' => 'Operator Monitor',
    ]);

    actingAs($user);

    get(route('monitoring.kgb.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.nama_lengkap', 'Operator Monitor')
            ->where('pegawaiList.data.0.status', 'Segera')
            ->where('kgbStats.total', 1)
            ->where('kgbStats.jatuhTempo', 0)
            ->where('kgbStats.segera', 1)
            ->where('kgbStats.mendekati', 0)
            ->where('kgbStats.aman', 0),
        );

    Carbon::setTestNow();
});
```

- [ ] **Step 1.7: Jalankan semua test monitoring KGB, pastikan semua PASS**

```bash
php artisan test tests/Feature/Monitoring/KgbMonitoringTest.php --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 1.8: Commit**

```bash
git add app/Services/KgbMonitoringService.php \
        app/Http/Controllers/Monitoring/MonitoringKgbController.php \
        resources/js/pages/kepegawaian/monitoring/kgb/index.tsx \
        tests/Feature/Monitoring/KgbMonitoringTest.php
git commit -m "perf: pagination KGB monitoring — filter ke DB, ganti Collection->get() dengan paginate()"
```

---

## Task 2: Pagination KP Monitoring

**Files:**
- Modify: `app/Services/KenaikanPangkatMonitoringService.php`
- Modify: `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`
- Modify: `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`
- Modify: `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php`

---

- [ ] **Step 2.1: Tulis test baru yang expects paginated response**

Tambahkan test ini di `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php` (baca file ini terlebih dahulu untuk melihat helper yang ada, lalu tambahkan):

```php
test('controller mengembalikan data kp dalam format paginasi', function () {
    Carbon::setTestNow('2026-01-01');

    $user = Pegawai::factory()->operator()->create();

    // Buat 20 pegawai yang eligible KP (TMT 4+ tahun lalu)
    foreach (range(1, 20) as $i) {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif->value,
            'nama_lengkap' => "Pegawai KP {$i}",
        ]);
        RiwayatPangkat::factory()->create([
            'pegawai_id' => $pegawai->id,
            'tmt' => Carbon::now()->subYears(5)->toDateString(),
            'is_aktif' => true,
        ]);
    }

    actingAs($user);

    get(route('monitoring.kenaikan-pangkat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->has('pegawaiList.data', 15)
            ->has('pegawaiList.meta')
            ->where('pegawaiList.meta.total', 20)
            ->where('pegawaiList.meta.last_page', 2)
            ->has('kpStats')
            ->where('kpStats.sudahEligible', 20),
        );

    Carbon::setTestNow();
});
```

- [ ] **Step 2.2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php --filter="controller mengembalikan data kp dalam format paginasi" --stop-on-failure
```

Expected: FAIL

- [ ] **Step 2.3: Refactor `KenaikanPangkatMonitoringService` — tambah `getKpStats()` dan ubah `getUpcomingKenaikanPangkat()` untuk paginate**

Ganti seluruh isi `app/Services/KenaikanPangkatMonitoringService.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class KenaikanPangkatMonitoringService
{
    public function getUpcomingKenaikanPangkat(?string $periode = null, int $perPage = 15): LengthAwarePaginator
    {
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
            ->orderBy('nama_lengkap');

        // Filter periode di level query (April = bulan 1-4, Oktober = bulan 5-10)
        if ($normalizedPeriode === 'april') {
            $query->whereRaw('MONTH(DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)) <= 4');
        } elseif ($normalizedPeriode === 'oktober') {
            $query->whereRaw('MONTH(DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)) BETWEEN 5 AND 10');
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

    public function getKpStats(?string $periode = null): array
    {
        $normalizedPeriode = $periode !== null ? strtolower($periode) : null;
        $today = Carbon::today()->toDateString();

        $query = Pegawai::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR) <= '{$today}' THEN 1 ELSE 0 END) as sudah_eligible,
                SUM(CASE WHEN DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR) > '{$today}'
                    AND DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR) <= DATE_ADD('{$today}', INTERVAL 6 MONTH)
                    THEN 1 ELSE 0 END) as mendekati_eligible,
                SUM(CASE WHEN DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR) > DATE_ADD('{$today}', INTERVAL 6 MONTH)
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
            ]);

        if ($normalizedPeriode === 'april') {
            $query->whereRaw('MONTH(DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)) <= 4');
        } elseif ($normalizedPeriode === 'oktober') {
            $query->whereRaw('MONTH(DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)) BETWEEN 5 AND 10');
        }

        $row = $query->first();

        return [
            'total'             => (int) ($row?->total ?? 0),
            'sudahEligible'     => (int) ($row?->sudah_eligible ?? 0),
            'mendekatiEligible' => (int) ($row?->mendekati_eligible ?? 0),
            'belumEligible'     => (int) ($row?->belum_eligible ?? 0),
        ];
    }

    public function getKpStatus(Pegawai $pegawai): array
    {
        $riwayatPangkatAktif = $pegawai->riwayatPangkat
            ->firstWhere('is_aktif', true)
            ?? $pegawai->riwayatPangkat()
                ->aktif()
                ->orderByDesc('tmt')
                ->first();

        if ($riwayatPangkatAktif === null || $riwayatPangkatAktif->tmt === null) {
            throw new \RuntimeException('Pegawai tidak memiliki riwayat pangkat aktif.');
        }

        $today = Carbon::today();
        $tmtKpBerikutnya = $riwayatPangkatAktif->tmt->copy()->addYears(4)->startOfDay();

        ['periode_usul' => $periodeUsul, 'batas_usul' => $batasUsul] = $this->resolvePeriodeUsulDanBatas($tmtKpBerikutnya);

        $isEligible = $tmtKpBerikutnya->lessThanOrEqualTo($today);
        $isNearEligible = $tmtKpBerikutnya->lessThanOrEqualTo($today->copy()->addMonthsNoOverflow(6));

        return [
            'eligible'        => $isEligible,
            'tmt_kp_berikutnya'=> $tmtKpBerikutnya,
            'periode_usul'    => $periodeUsul,
            'batas_usul'      => $batasUsul,
            'sisa_hari_usul'  => $today->diffInDays($batasUsul, false),
            'status'          => $isEligible
                ? 'Sudah Eligible'
                : ($isNearEligible ? 'Mendekati Eligible' : 'Belum Eligible'),
        ];
    }

    private function resolvePeriodeUsulDanBatas(CarbonInterface $tmtKpBerikutnya): array
    {
        $year = $tmtKpBerikutnya->year;
        $month = $tmtKpBerikutnya->month;

        if ($month <= 4) {
            return [
                'periode_usul' => sprintf('April %d', $year),
                'batas_usul' => Carbon::create($year - 1, 10, 1)->startOfDay(),
            ];
        }

        if ($month <= 10) {
            return [
                'periode_usul' => sprintf('Oktober %d', $year),
                'batas_usul' => Carbon::create($year, 4, 1)->startOfDay(),
            ];
        }

        return [
            'periode_usul' => sprintf('April %d', $year + 1),
            'batas_usul' => Carbon::create($year, 10, 1)->startOfDay(),
        ];
    }
}
```

- [ ] **Step 2.4: Update `MonitoringKenaikanPangkatController`**

Ganti seluruh isi `app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php`:

```php
<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringKenaikanPangkatController extends Controller
{
    public function index(Request $request, KenaikanPangkatMonitoringService $service): Response
    {
        $periode = $request->string('periode')->toString();
        $periode = $periode !== '' ? $periode : null;
        $perPage = $request->integer('per_page', 15);

        return Inertia::render('kepegawaian/monitoring/kenaikan-pangkat/index', [
            'pegawaiList'    => $service->getUpcomingKenaikanPangkat($periode, $perPage),
            'selectedPeriode'=> $periode,
            'kpStats'        => $service->getKpStats($periode),
        ]);
    }
}
```

- [ ] **Step 2.5: Update TypeScript types dan tambah pagination di frontend KP**

Di `resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx`, lakukan perubahan berikut:

Tambahkan import:
```tsx
import { PaginationWrapper } from '@/components/pagination-wrapper';
import type { PaginatedData } from '@/types';
```

Ubah type `Props`:
```tsx
type Props = {
    pegawaiList: PaginatedData<PegawaiMonitoringRow>;
    selectedPeriode: string | null;
    kpStats: {
        total: number;
        sudahEligible: number;
        mendekatiEligible: number;
        belumEligible: number;
    };
};
```

Ubah semua referensi array `pegawaiList` menjadi `pegawaiList.data` (dalam JSX dan useMemo jika ada).

Tambahkan setelah tabel:
```tsx
<PaginationWrapper meta={pegawaiList.meta} />
```

- [ ] **Step 2.6: Update test lama KP yang menggunakan struktur array flat**

Baca `tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php`, lalu update semua assertion yang mengakses `pegawaiList` menjadi `pegawaiList.data`.

- [ ] **Step 2.7: Jalankan semua test monitoring KP, pastikan semua PASS**

```bash
php artisan test tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 2.8: Commit**

```bash
git add app/Services/KenaikanPangkatMonitoringService.php \
        app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php \
        resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx \
        tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php
git commit -m "perf: pagination KP monitoring — filter periode ke DB, ganti Collection->get() dengan paginate()"
```

---

## Task 3: Fix N+1 Query di DashboardStatService

**Prerequisite:** Task 1 dan Task 2 harus selesai (karena `getKgbSegeraCount` dan `getKpEligibleCount` memanggil service yang sudah berubah).

**Files:**
- Modify: `app/Services/DashboardStatService.php`
- Modify: `tests/Feature/DashboardTest.php`

---

- [ ] **Step 3.1: Tulis test yang memverifikasi query count berkurang (menggunakan `DB::getQueryLog`)**

Tambahkan test ini di `tests/Feature/DashboardTest.php`:

```php
use Illuminate\Support\Facades\DB;

test('distribusi golongan menggunakan query SQL bukan PHP collection', function () {
    // Buat beberapa pegawai dengan pangkat berbeda golongan
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiGolongan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Hanya 1 query untuk distribusi golongan
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

    // Hanya 1 query untuk distribusi jabatan
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

    // Hanya 1 query untuk distribusi pendidikan
    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});
```

- [ ] **Step 3.2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/DashboardTest.php --filter="distribusi" --stop-on-failure
```

Expected: FAIL — query count lebih dari 1 (karena saat ini load semua pegawai + eager load)

- [ ] **Step 3.3: Refactor `getDistribusiGolongan()`, `getDistribusiJabatan()`, `getDistribusiPendidikan()` di `DashboardStatService`**

Ganti ketiga method tersebut di `app/Services/DashboardStatService.php`:

```php
public function getDistribusiGolongan(): array
{
    $rows = $this->pegawaiAktifQuery()
        ->join('ref_pangkat', 'pegawai.ref_pangkat_id', '=', 'ref_pangkat.id')
        ->selectRaw("SUBSTRING_INDEX(ref_pangkat.kode, '/', 1) as golongan, COUNT(*) as total")
        ->groupByRaw("SUBSTRING_INDEX(ref_pangkat.kode, '/', 1)")
        ->pluck('total', 'golongan');

    return [
        'I'   => (int) ($rows['I'] ?? 0),
        'II'  => (int) ($rows['II'] ?? 0),
        'III' => (int) ($rows['III'] ?? 0),
        'IV'  => (int) ($rows['IV'] ?? 0),
    ];
}

public function getDistribusiJabatan(): Collection
{
    return $this->pegawaiAktifQuery()
        ->join('ref_jabatan', 'pegawai.ref_jabatan_id', '=', 'ref_jabatan.id')
        ->selectRaw('ref_jabatan.nama, COUNT(*) as pegawai_count')
        ->groupBy('pegawai.ref_jabatan_id', 'ref_jabatan.nama')
        ->orderByDesc('pegawai_count')
        ->limit(6)
        ->get()
        ->map(fn ($row) => [
            'nama'         => $row->nama,
            'pegawai_count'=> (int) $row->pegawai_count,
        ]);
}

public function getDistribusiPendidikan(): Collection
{
    return $this->pegawaiAktifQuery()
        ->whereNotNull('pendidikan_terakhir')
        ->selectRaw('pendidikan_terakhir, COUNT(*) as pegawai_count')
        ->groupBy('pendidikan_terakhir')
        ->orderByDesc('pegawai_count')
        ->get()
        ->map(function ($row) {
            $label = JenjangPendidikan::tryFrom($row->pendidikan_terakhir)?->label()
                ?? strtoupper($row->pendidikan_terakhir);

            return [
                'pendidikan'   => $label,
                'pegawai_count'=> (int) $row->pegawai_count,
            ];
        });
}
```

- [ ] **Step 3.4: Update `getKgbSegeraCount()` dan `getKpEligibleCount()` karena return type service sudah berubah**

Ganti dua method ini di `app/Services/DashboardStatService.php`:

```php
public function getKgbSegeraCount(): int
{
    return Container::getInstance()->make(KgbMonitoringService::class)->getKgbStats(2)['total'];
}

public function getKpEligibleCount(): int
{
    return Container::getInstance()->make(KenaikanPangkatMonitoringService::class)
        ->getKpStats()['sudahEligible'];
}
```

- [ ] **Step 3.5: Tambahkan cache pada `getStats()` dengan TTL 5 menit**

Ganti method `getStats()` di `app/Services/DashboardStatService.php`:

```php
use Illuminate\Support\Facades\Cache;

public function getStats(): array
{
    return Cache::remember('dashboard_stats', 300, fn () => [
        'total_pegawai_aktif'    => $this->getTotalPegawaiAktif(),
        'distribusi_golongan'    => $this->getDistribusiGolongan(),
        'distribusi_unit_kerja'  => $this->getDistribusiUnitKerja(),
        'distribusi_jenis_kelamin'=> $this->getDistribusiJenisKelamin(),
        'kgb_segera_count'       => $this->getKgbSegeraCount(),
        'kp_eligible_count'      => $this->getKpEligibleCount(),
        'distribusi_jabatan'     => $this->getDistribusiJabatan(),
        'distribusi_pendidikan'  => $this->getDistribusiPendidikan(),
        'pegawai_baru_bulan_ini' => $this->getPegawaiBaruBulanIni(),
    ]);
}
```

Tambahkan `use Illuminate\Support\Facades\Cache;` di bagian atas file jika belum ada.

- [ ] **Step 3.6: Jalankan seluruh test DashboardTest, pastikan PASS**

```bash
php artisan test tests/Feature/DashboardTest.php --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 3.7: Commit**

```bash
git add app/Services/DashboardStatService.php \
        tests/Feature/DashboardTest.php
git commit -m "perf: ganti PHP collection processing dengan SQL groupBy di DashboardStatService, tambah cache 5 menit"
```

---

## Task 4: Fix Cache IAM Tidak Di-invalidate

**Files:**
- Modify: `app/Http/Controllers/Iam/AplikasiController.php`
- Modify: `app/Http/Controllers/Iam/RoleController.php`
- Modify: `app/Http/Controllers/Iam/PermissionController.php`
- Modify: `app/Http/Controllers/Iam/UserAksesController.php`
- Modify: `tests/Feature/Iam/AplikasiControllerTest.php`

---

- [ ] **Step 4.1: Tulis test yang memverifikasi cache di-invalidate saat aplikasi diupdate**

Tambahkan test ini di `tests/Feature/Iam/AplikasiControllerTest.php`:

```php
use Illuminate\Support\Facades\Cache;

it('menghapus cache iam_app saat aplikasi diupdate', function () {
    $admin = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create(['slug' => 'test-app', 'is_system' => false]);

    // Set cache seolah-olah sudah ada sebelumnya
    Cache::put('iam_app:test-app', $app, 3600);
    expect(Cache::has('iam_app:test-app'))->toBeTrue();

    $this->actingAs($admin)
        ->put("/iam/aplikasi/{$app->id}", [
            'nama'        => 'Updated App',
            'slug'        => 'test-app',
            'url'         => 'https://example.com',
            'is_active'   => true,
            'deskripsi'   => null,
        ]);

    expect(Cache::has('iam_app:test-app'))->toBeFalse();
});

it('menghapus cache iam_app saat aplikasi dihapus', function () {
    $admin = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create(['slug' => 'hapus-app', 'is_system' => false]);

    Cache::put('iam_app:hapus-app', $app, 3600);
    expect(Cache::has('iam_app:hapus-app'))->toBeTrue();

    $this->actingAs($admin)
        ->delete("/iam/aplikasi/{$app->id}");

    expect(Cache::has('iam_app:hapus-app'))->toBeFalse();
});
```

- [ ] **Step 4.2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Iam/AplikasiControllerTest.php --filter="menghapus cache iam_app" --stop-on-failure
```

Expected: FAIL — cache masih ada setelah update/delete

- [ ] **Step 4.3: Tambahkan `Cache::forget` di `AplikasiController`**

Baca `app/Http/Controllers/Iam/AplikasiController.php`, lalu:

1. Tambahkan `use Illuminate\Support\Facades\Cache;` di bagian import
2. Di method `update()`, setelah `$aplikasi->update(...)`, tambahkan:
```php
Cache::forget("iam_app:{$aplikasi->slug}");
```

3. Di method `destroy()`, sebelum `$aplikasi->delete()`, tambahkan:
```php
Cache::forget("iam_app:{$aplikasi->slug}");
```

- [ ] **Step 4.4: Tambahkan `Cache::forget` di `RoleController`, `PermissionController`, `UserAksesController`**

Di setiap method mutasi (store, update, destroy) pada ketiga controller ini, tambahkan cache invalidation untuk aplikasi terkait:

**`RoleController`** — setiap method menerima `IamApplication $aplikasi`. Tambahkan di akhir setiap method sebelum `return back()`:
```php
Cache::forget("iam_app:{$aplikasi->slug}");
```

**`PermissionController`** — sama seperti RoleController:
```php
Cache::forget("iam_app:{$aplikasi->slug}");
```

**`UserAksesController::store()` dan `UserAksesController::destroy()`** — kedua method ini tidak menerima `$aplikasi`, tapi memodifikasi user roles yang bisa mempengaruhi permission checks. Tambahkan cache flush untuk semua app yang user-nya diubah:
```php
// Flush semua iam_app cache (karena tidak tahu app mana yang terdampak)
Cache::flush(); // Atau lebih targeted: flush hanya key iam_app:*
```

> **Catatan:** Gunakan `Cache::flush()` hanya jika driver cache yang digunakan adalah `array` atau `file`. Untuk `redis`, gunakan pattern-based delete. Dalam konteks development, `Cache::flush()` aman. Jika production menggunakan Redis, pertimbangkan menyimpan list app slugs dan menghapus satu per satu.

- [ ] **Step 4.5: Jalankan test IAM cache, pastikan PASS**

```bash
php artisan test tests/Feature/Iam/AplikasiControllerTest.php --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 4.6: Jalankan semua test IAM untuk pastikan tidak ada regresi**

```bash
php artisan test tests/Feature/Iam/ --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 4.7: Commit**

```bash
git add app/Http/Controllers/Iam/AplikasiController.php \
        app/Http/Controllers/Iam/RoleController.php \
        app/Http/Controllers/Iam/PermissionController.php \
        app/Http/Controllers/Iam/UserAksesController.php \
        tests/Feature/Iam/AplikasiControllerTest.php
git commit -m "fix: tambah cache invalidation iam_app saat aplikasi/role/permission diubah atau dihapus"
```

---

## Task 5: Verifikasi Fase 1 Selesai

- [ ] **Step 5.1: Jalankan seluruh test suite**

```bash
php artisan test --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 5.2: Commit final jika ada perubahan yang belum di-commit**

```bash
git status
# Jika ada file yang belum di-commit, tambahkan dan commit
```

- [ ] **Step 5.3: Tag completion Fase 1**

```bash
git log --oneline -5
# Verifikasi semua commit Fase 1 ada
```

---

## Ringkasan Perubahan Fase 1

| Task | Before | After |
|---|---|---|
| KGB Monitoring | `->get()` → filter PHP Collection | `->paginate()` → filter SQL JOIN |
| KP Monitoring | `->get()` → filter PHP Collection | `->paginate()` → filter SQL JOIN |
| Dashboard Stats | Load semua pegawai 3x ke PHP | SQL `groupBy` + `selectRaw`, cache 5 menit |
| IAM Cache | Tidak ada invalidation | `Cache::forget` di semua mutasi |
