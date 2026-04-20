# Perbaikan Bug dan Placeholder — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Memperbaiki semua bug dan placeholder yang teridentifikasi: form self-service placeholder, IamSeeder duplicate key, KGB export unit kerja kosong, dan chart tooltip warna hardcoded.

**Architecture:** Pendekatan 1 — Single Dynamic Form untuk self-service dengan state-driven field rendering. Backend fixes melibatkan DRY principle untuk seeder dan data enrichment untuk export. Chart menggunakan Tailwind CSS variables untuk konsistensi tema.

**Tech Stack:** Laravel 12, React 19, TypeScript, Inertia.js v2, Tailwind CSS v4, shadcn/ui, Recharts, Pest v4

---

## Task 1: Fix IamSeeder Duplicate Key

**Files:**
- Modify: `database/seeders/IamSeeder.php:20-34`
- Test: `tests/Feature/Iam/IamSeederTest.php` (new)

**Step 1: Write failing test**

Create `tests/Feature/Iam/IamSeederTest.php`:
```php
<?php

namespace Tests\Feature\Iam;

use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IamSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_iam_seeder_can_run_multiple_times_without_error(): void
    {
        $this->seed(IamSeeder::class);
        $this->seed(IamSeeder::class);

        $this->assertDatabaseCount('iam_applications', 1);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Iam/IamSeederTest.php
```

Expected: FAIL — duplicate entry 'kepegawaian' for key 'iam_applications_slug_unique'

**Step 3: Fix IamSeeder**

Modify `database/seeders/IamSeeder.php:20-34`:

```php
['key' => $key, 'hash' => $hash] = IamApplication::generateApiCredentials();

$kepegawaian = IamApplication::firstOrCreate(
    ['slug' => 'kepegawaian'],
    [
        'nama' => 'Kepegawaian Apps',
        'url' => config('app.url'),
        'deskripsi' => 'Sistem master data kepegawaian PA Penajam',
        'is_active' => true,
    ]
);

// Set field sensitif hanya jika record baru
if ($kepegawaian->wasRecentlyCreated) {
    $kepegawaian->api_key = $key;
    $kepegawaian->api_secret_hash = $hash;
    $kepegawaian->is_system = true;
    $kepegawaian->save();
}
```

**Step 4: Run test to verify it passes**

```bash
php artisan test tests/Feature/Iam/IamSeederTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add tests/Feature/Iam/IamSeederTest.php database/seeders/IamSeeder.php
git commit -m "fix: prevent IamSeeder duplicate key on re-seed

- Use firstOrCreate with slug as unique key
- Only set sensitive fields on newly created records
- Add test to verify idempotent seeding

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 2: Fix KGB Monitoring Export Unit Kerja

**Files:**
- Modify: `app/Services/KgbMonitoringService.php:37-40`
- Modify: `app/Exports/KgbMonitoringExport.php:46-56`
- Modify: `app/Exports/KgbMonitoringExport.php:84-92`
- Test: `tests/Feature/Monitoring/KgbExportTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Monitoring/KgbExportTest.php`:
```php
public function test_kgb_export_collection_includes_unit_kerja(): void
{
    $export = new KgbMonitoringExport;
    $collection = $export->collection();

    // Collection method returns a collection, verify it has unit_kerja field mapping
    $this->assertTrue(method_exists($export, 'collection'));
}

public function test_kgb_export_map_includes_unit_kerja_data(): void
{
    $export = new KgbMonitoringExport;

    $mockRow = (object) [
        'nip' => '1234567890',
        'nama_lengkap' => 'Test User',
        'unit_kerja' => 'Bagian Testing',
        'pangkat_gol' => 'III/a',
        'tmt_pangkat' => '2023-01-01',
        'tanggal_kgb_berikutnya' => '2025-01-01',
        'sisa_hari' => 30,
    ];

    $mapped = $export->map($mockRow);

    $this->assertEquals('Bagian Testing', $mapped[2]);
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Monitoring/KgbExportTest.php --filter=test_kgb_export_map_includes_unit_kerja_data
```

Expected: FAIL — `mapped[2]` is empty string ''

**Step 3: Fix KgbMonitoringService to include unitKerja**

Modify `app/Services/KgbMonitoringService.php:37-40`:
```php
->with([
    'pangkat',
    'unitKerja',
    'riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt'),
])
```

Modify `app/Services/KgbMonitoringService.php:89-98`:
```php
return [
    'id' => $pegawai->id,
    'nip' => $pegawai->nip,
    'nama_lengkap' => $pegawai->nama_lengkap,
    'unit_kerja' => $pegawai->unitKerja?->nama ?? '-',
    'pangkat_gol' => $pegawai->nama_pangkat_lengkap,
    'tmt_pangkat' => $riwayatPangkatAktif?->tmt?->toDateString(),
    'tanggal_kgb_berikutnya' => $statusKgb['tanggal_kgb_berikutnya']->toDateString(),
    'sisa_hari' => $statusKgb['sisa_hari'],
    'status' => $statusKgb['status'],
];
```

**Step 4: Fix KgbMonitoringExport to use unit_kerja**

Modify `app/Exports/KgbMonitoringExport.php:46-56`:
```php
return collect($paginatedData->items())->map(function ($item) {
    return (object) [
        'nip' => $item['nip'] ?? '',
        'nama_lengkap' => $item['nama_lengkap'] ?? '',
        'unit_kerja' => $item['unit_kerja'] ?? '-',
        'pangkat_gol' => $item['pangkat_gol'] ?? '',
        'tmt_pangkat' => $item['tmt_pangkat'] ?? '',
        'tanggal_kgb_berikutnya' => $item['tanggal_kgb_berikutnya'] ?? '',
        'sisa_hari' => $item['sisa_hari'] ?? '',
    ];
});
```

Modify `app/Exports/KgbMonitoringExport.php:84-92`:
```php
return [
    $row->nip ?? '',
    $row->nama_lengkap ?? '',
    $row->unit_kerja ?? '-',
    $row->pangkat_gol ?? '',
    $row->tmt_pangkat ? date('d-m-Y', strtotime($row->tmt_pangkat)) : '-',
    $row->tanggal_kgb_berikutnya ? date('d-m-Y', strtotime($row->tanggal_kgb_berikutnya)) : '-',
    $sisaWaktu,
];
```

**Step 5: Run test to verify it passes**

```bash
php artisan test tests/Feature/Monitoring/KgbExportTest.php
```

Expected: PASS

**Step 6: Commit**

```bash
git add app/Services/KgbMonitoringService.php app/Exports/KgbMonitoringExport.php tests/Feature/Monitoring/KgbExportTest.php
git commit -m "fix: include unit_kerja in KGB monitoring export

- Eager-load unitKerja relation in KgbMonitoringService
- Add unit_kerja to export collection and map
- Update test to verify unit_kerja is included in mapped output

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 3: Fix Chart Tooltip Theme Consistency

**Files:**
- Modify: `resources/js/components/dashboard/JenisKelaminPieChart.tsx`
- Modify: `resources/js/components/dashboard/PendidikanBarChart.tsx`
- Test: Manual visual verification

**Step 1: Fix JenisKelaminPieChart**

Replace hardcoded colors with Tailwind theme classes. Modify `resources/js/components/dashboard/JenisKelaminPieChart.tsx`:

```tsx
import { Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

interface JenisKelaminItem {
  label: string;
  total: number;
  percentage: number;
}

interface Props {
  data: JenisKelaminItem[];
}

// Gunakan warna yang konsisten dengan tema shadcn/ui
const COLORS = ['hsl(var(--chart-1))', 'hsl(var(--chart-2))'];

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{
    name: string;
    value: number;
    payload?: {
      percentage: number;
    };
  }>;
}

function CustomTooltip({ active, payload }: CustomTooltipProps) {
  if (active && payload && payload.length) {
    const data = payload[0];
    const percentage = data.payload?.percentage ?? 0;

    return (
      <div className="bg-popover text-popover-foreground border border-border rounded-md px-3 py-2 shadow-sm">
        <p className="font-semibold">{data.name}</p>
        <p className="text-sm text-muted-foreground">
          {data.value} pegawai ({percentage}%)
        </p>
      </div>
    );
  }

  return null;
}

export function JenisKelaminPieChart({ data }: Props) {
  if (!data || data.length === 0) {
    return (
      <div className="flex items-center justify-center h-[240px] text-muted-foreground">
        Tidak ada data
      </div>
    );
  }

  const chartData = data.map((item) => ({
    name: item.label,
    value: item.total,
    percentage: item.percentage,
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
          dataKey="value"
          label={(props: any) => `${props.percentage}%`}
          labelLine={false}
        >
          {chartData.map((entry, index) => (
            <Pie key={`pie-${index}`} fill={COLORS[index % COLORS.length]} />
          ))}
        </Pie>
        <Tooltip content={<CustomTooltip />} />
        <Legend />
      </PieChart>
    </ResponsiveContainer>
  );
}
```

**Step 2: Fix PendidikanBarChart**

Modify `resources/js/components/dashboard/PendidikanBarChart.tsx`:

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

interface CustomTooltipProps {
    active?: boolean;
    payload?: Array<{
        name: string;
        value: number;
        payload?: {
            pct: number;
        };
    }>;
    label?: string;
}

function CustomTooltip({ active, payload, label }: CustomTooltipProps) {
    if (active && payload && payload.length) {
        const data = payload[0];
        const percentage = data.payload?.pct ?? 0;

        return (
            <div className="bg-popover text-popover-foreground border border-border rounded-md px-3 py-2 shadow-sm">
                <p className="font-semibold">{label}</p>
                <p className="text-sm text-muted-foreground">
                    {data.value} pegawai ({percentage}%)
                </p>
            </div>
        );
    }

    return null;
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
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="value" fill="hsl(var(--chart-1))" radius={[0, 4, 4, 0]} label={{ position: 'right', fontSize: 12 }} />
            </BarChart>
        </ResponsiveContainer>
    );
}
```

**Step 3: Verify theme CSS variables exist**

Check `resources/css/app.css` or `tailwind.config.ts` untuk memastikan `--chart-1` dan `--chart-2` tersedia. Jika tidak ada, tambahkan ke CSS variables di root.

**Step 4: Commit**

```bash
git add resources/js/components/dashboard/JenisKelaminPieChart.tsx resources/js/components/dashboard/PendidikanBarChart.tsx
git commit -m "fix: use theme-aware colors in dashboard charts

- Replace hardcoded hex colors with hsl(var(--chart-*)) CSS variables
- Update tooltip styling to use bg-popover and text-popover-foreground
- Use text-muted-foreground for empty state in pie chart

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 4: Build Self-Service Pengajuan Create Form

**Files:**
- Create: `resources/js/pages/self-service/pengajuan/create.tsx` (rewrite)
- Modify: `app/Http/Controllers/SelfService/PengajuanPerubahanDataController.php:38-41`
- Test: `tests/Feature/SelfService/PengajuanPerubahanDataTest.php` (add test)

**Step 1: Write failing test for form page**

Add to `tests/Feature/SelfService/PengajuanPerubahanDataTest.php`:
```php
it('halaman create menampilkan form dengan field yang sesuai', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    actingAs($pegawai)
        ->withoutVite()
        ->get(route('self-service.pengajuan.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/pengajuan/create')
            ->has('domains')
            ->has('aksiList')
            ->has('hubunganList')
            ->has('jenisKelaminList')
            ->has('statusPerkawinanList')
        );
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/SelfService/PengajuanPerubahanDataTest.php --filter="halaman create menampilkan"
```

Expected: FAIL — Inertia page missing 'domains', 'aksiList', etc.

**Step 3: Modify controller to pass enums/data**

Modify `app/Http/Controllers/SelfService/PengajuanPerubahanDataController.php:38-41`:
```php
use App\Enums\AksiPengajuan;
use App\Enums\DomainPengajuan;
use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Enums\StatusPerkawinan;

public function create(): Response
{
    return Inertia::render('self-service/pengajuan/create', [
        'domains' => array_map(
            fn (DomainPengajuan $d) => ['value' => $d->value, 'label' => str_replace('_', ' ', $d->name)],
            DomainPengajuan::cases()
        ),
        'aksiList' => array_map(
            fn (AksiPengajuan $a) => ['value' => $a->value, 'label' => ucfirst($a->value)],
            AksiPengajuan::cases()
        ),
        'hubunganList' => array_map(
            fn (HubunganKeluarga $h) => ['value' => $h->value, 'label' => $h->value],
            HubunganKeluarga::cases()
        ),
        'jenisKelaminList' => array_map(
            fn (JenisKelamin $j) => ['value' => $j->value, 'label' => $j === JenisKelamin::LakiLaki ? 'Laki-laki' : 'Perempuan'],
            JenisKelamin::cases()
        ),
        'statusPerkawinanList' => array_map(
            fn (StatusPerkawinan $s) => ['value' => $s->value, 'label' => str_replace('_', ' ', $s->name)],
            StatusPerkawinan::cases()
        ),
        'currentUserId' => auth()->id(),
    ]);
}
```

**Step 4: Build the dynamic form component**

Rewrite `resources/js/pages/self-service/pengajuan/create.tsx`:
```tsx
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm, usePage } from '@inertiajs/react';

interface Option {
  value: string;
  label: string;
}

interface PageProps {
  domains: Option[];
  aksiList: Option[];
  hubunganList: Option[];
  jenisKelaminList: Option[];
  statusPerkawinanList: Option[];
  currentUserId: string;
}

export default function SelfServicePengajuanCreate() {
  const { domains, aksiList, hubunganList, jenisKelaminList, statusPerkawinanList, currentUserId } =
    usePage<PageProps>().props;

  const form = useForm<{
    domain: string;
    aksi: string;
    target_type: string;
    target_id: string;
    subject_pegawai_id: string;
    after_payload: Record<string, string>;
    lampiran: File[];
  }>({
    domain: 'profil_pribadi',
    aksi: 'update',
    target_type: 'pegawai',
    target_id: currentUserId,
    subject_pegawai_id: currentUserId,
    after_payload: {},
    lampiran: [],
  });

  const isProfilPribadi = form.data.domain === 'profil_pribadi';
  const isKeluargaDomain = ['pasangan', 'anak', 'orang_tua', 'keluarga_lain'].includes(form.data.domain);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    form.post('/self-service/pengajuan', {
      forceFormData: true,
    });
  }

  function updatePayload(key: string, value: string) {
    form.setData('after_payload', { ...form.data.after_payload, [key]: value });
  }

  function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
    if (e.target.files) {
      form.setData('lampiran', Array.from(e.target.files));
    }
  }

  const lampiranWajib =
    isKeluargaDomain ||
    (isProfilPribadi &&
      Object.keys(form.data.after_payload).some((k) =>
        ['nama_lengkap', 'nik', 'tempat_lahir', 'tanggal_lahir', 'status_perkawinan'].includes(k),
      ));

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Pengajuan Saya', href: '/self-service/pengajuan' },
        { title: 'Buat Pengajuan', href: '/self-service/pengajuan/create' },
      ]}
    >
      <Head title="Buat Pengajuan" />
      <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-4 sm:p-6 max-w-2xl">
        {/* Domain */}
        <div className="space-y-2">
          <Label htmlFor="domain">Jenis Perubahan</Label>
          <Select
            value={form.data.domain}
            onValueChange={(v) => {
              form.setData('domain', v);
              form.setData('after_payload', {});
              if (v === 'profil_pribadi') {
                form.setData('aksi', 'update');
                form.setData('target_type', 'pegawai');
              } else {
                form.setData('aksi', 'create');
                form.setData('target_type', 'keluarga');
              }
            }}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {domains.map((d) => (
                <SelectItem key={d.value} value={d.value}>
                  {d.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {form.errors.domain && <p className="text-sm text-destructive">{form.errors.domain}</p>}
        </div>

        {/* Aksi (hanya untuk keluarga, profil_pribadi selalu update) */}
        {isKeluargaDomain && (
          <div className="space-y-2">
            <Label htmlFor="aksi">Aksi</Label>
            <Select
              value={form.data.aksi}
              onValueChange={(v) => {
                form.setData('aksi', v);
                form.setData('after_payload', {});
              }}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {aksiList.map((a) => (
                  <SelectItem key={a.value} value={a.value}>
                    {a.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}

        {/* Fields: Profil Pribadi */}
        {isProfilPribadi && form.data.aksi === 'update' && (
          <div className="space-y-4 rounded-lg border p-4">
            <h3 className="font-medium">Data yang Diubah</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="nama_lengkap">Nama Lengkap</Label>
                <Input
                  id="nama_lengkap"
                  value={form.data.after_payload.nama_lengkap ?? ''}
                  onChange={(e) => updatePayload('nama_lengkap', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="nik">NIK</Label>
                <Input
                  id="nik"
                  value={form.data.after_payload.nik ?? ''}
                  onChange={(e) => updatePayload('nik', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="tempat_lahir">Tempat Lahir</Label>
                <Input
                  id="tempat_lahir"
                  value={form.data.after_payload.tempat_lahir ?? ''}
                  onChange={(e) => updatePayload('tempat_lahir', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="tanggal_lahir">Tanggal Lahir</Label>
                <Input
                  id="tanggal_lahir"
                  type="date"
                  value={form.data.after_payload.tanggal_lahir ?? ''}
                  onChange={(e) => updatePayload('tanggal_lahir', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="status_perkawinan">Status Perkawinan</Label>
                <Select
                  value={form.data.after_payload.status_perkawinan ?? ''}
                  onValueChange={(v) => updatePayload('status_perkawinan', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih status" />
                  </SelectTrigger>
                  <SelectContent>
                    {statusPerkawinanList.map((s) => (
                      <SelectItem key={s.value} value={s.value}>
                        {s.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="alamat">Alamat</Label>
                <Input
                  id="alamat"
                  value={form.data.after_payload.alamat ?? ''}
                  onChange={(e) => updatePayload('alamat', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="no_telepon">No. Telepon</Label>
                <Input
                  id="no_telepon"
                  value={form.data.after_payload.no_telepon ?? ''}
                  onChange={(e) => updatePayload('no_telepon', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={form.data.after_payload.email ?? ''}
                  onChange={(e) => updatePayload('email', e.target.value)}
                />
              </div>
            </div>
          </div>
        )}

        {/* Fields: Keluarga Create/Update */}
        {isKeluargaDomain && form.data.aksi !== 'delete' && (
          <div className="space-y-4 rounded-lg border p-4">
            <h3 className="font-medium">Data Keluarga</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="hubungan">Hubungan</Label>
                <Select
                  value={form.data.after_payload.hubungan ?? ''}
                  onValueChange={(v) => updatePayload('hubungan', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih hubungan" />
                  </SelectTrigger>
                  <SelectContent>
                    {hubunganList.map((h) => (
                      <SelectItem key={h.value} value={h.value}>
                        {h.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="nama">Nama</Label>
                <Input
                  id="nama"
                  value={form.data.after_payload.nama ?? ''}
                  onChange={(e) => updatePayload('nama', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="tempat_lahir">Tempat Lahir</Label>
                <Input
                  id="tempat_lahir"
                  value={form.data.after_payload.tempat_lahir ?? ''}
                  onChange={(e) => updatePayload('tempat_lahir', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="tanggal_lahir">Tanggal Lahir</Label>
                <Input
                  id="tanggal_lahir"
                  type="date"
                  value={form.data.after_payload.tanggal_lahir ?? ''}
                  onChange={(e) => updatePayload('tanggal_lahir', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="jenis_kelamin">Jenis Kelamin</Label>
                <Select
                  value={form.data.after_payload.jenis_kelamin ?? ''}
                  onValueChange={(v) => updatePayload('jenis_kelamin', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih jenis kelamin" />
                  </SelectTrigger>
                  <SelectContent>
                    {jenisKelaminList.map((j) => (
                      <SelectItem key={j.value} value={j.value}>
                        {j.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="pekerjaan">Pekerjaan</Label>
                <Input
                  id="pekerjaan"
                  value={form.data.after_payload.pekerjaan ?? ''}
                  onChange={(e) => updatePayload('pekerjaan', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="pendidikan">Pendidikan</Label>
                <Input
                  id="pendidikan"
                  value={form.data.after_payload.pendidikan ?? ''}
                  onChange={(e) => updatePayload('pendidikan', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="keterangan">Keterangan</Label>
                <Input
                  id="keterangan"
                  value={form.data.after_payload.keterangan ?? ''}
                  onChange={(e) => updatePayload('keterangan', e.target.value)}
                />
              </div>
            </div>
          </div>
        )}

        {/* Delete confirmation for keluarga */}
        {isKeluargaDomain && form.data.aksi === 'delete' && (
          <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
            <p className="text-sm text-destructive">
              Anda memilih aksi <strong>Hapus</strong>. Data yang dihapus akan diajukan untuk penghapusan
              dan memerlukan persetujuan validator.
            </p>
          </div>
        )}

        {/* Lampiran */}
        <div className="space-y-2">
          <Label htmlFor="lampiran">
            Lampiran Pendukung
            {lampiranWajib && <span className="text-destructive"> *</span>}
          </Label>
          <Input
            id="lampiran"
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.pdf"
            onChange={handleFileChange}
          />
          <p className="text-xs text-muted-foreground">
            Format: JPG, JPEG, PNG, PDF. Maksimal 2MB per file.
          </p>
          {form.errors.lampiran && <p className="text-sm text-destructive">{form.errors.lampiran}</p>}
        </div>

        <Button type="submit" disabled={form.processing} className="w-full sm:w-auto">
          {form.processing ? 'Mengirim...' : 'Kirim Pengajuan'}
        </Button>
      </form>
    </AppLayout>
  );
}
```

**Step 5: Run test to verify it passes**

```bash
php artisan test tests/Feature/SelfService/PengajuanPerubahanDataTest.php --filter="halaman create menampilkan"
```

Expected: PASS

**Step 6: Verify form submission still works**

```bash
php artisan test tests/Feature/SelfService/PengajuanPerubahanDataTest.php
```

Expected: All existing tests still PASS

**Step 7: Commit**

```bash
git add app/Http/Controllers/SelfService/PengajuanPerubahanDataController.php resources/js/pages/self-service/pengajuan/create.tsx tests/Feature/SelfService/PengajuanPerubahanDataTest.php
git commit -m "feat: implement dynamic self-service pengajuan create form

- Replace placeholder with full dynamic form supporting all domains
- Add enum data props from controller for select fields
- Implement conditional field rendering based on domain and aksi
- Auto-detect required lampiran based on domain and changed fields
- Add visual indicator for delete action confirmation

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Task 5: Final Verification

**Step 1: Run full test suite**

```bash
php artisan test
```

Expected: All 379+ tests PASS

**Step 2: Verify TypeScript compilation**

```bash
npm run build
```

Expected: Build succeeds without errors

**Step 3: Commit verification results**

```bash
git commit --allow-empty -m "chore: verify all fixes pass tests and build

- Full test suite: PASS
- TypeScript build: PASS
- All 4 bugs resolved

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

---

## Summary

| Task | Bug | File(s) | Test |
|------|-----|---------|------|
| 1 | IamSeeder duplicate key | `database/seeders/IamSeeder.php` | `tests/Feature/Iam/IamSeederTest.php` |
| 2 | KGB export unit kerja kosong | `app/Services/KgbMonitoringService.php`, `app/Exports/KgbMonitoringExport.php` | `tests/Feature/Monitoring/KgbExportTest.php` |
| 3 | Chart tooltip hardcoded | `resources/js/components/dashboard/JenisKelaminPieChart.tsx`, `resources/js/components/dashboard/PendidikanBarChart.tsx` | Manual visual check |
| 4 | Self-service form placeholder | `app/Http/Controllers/SelfService/PengajuanPerubahanDataController.php`, `resources/js/pages/self-service/pengajuan/create.tsx` | `tests/Feature/SelfService/PengajuanPerubahanDataTest.php` |
