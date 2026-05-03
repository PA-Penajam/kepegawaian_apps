# Design Spec: UI/UX Consistency — Standarisasi Seluruh Modul

**Tanggal:** 2026-05-03
**Status:** Draft
**Scope:** Seluruh halaman index di kepegawaian-apps

---

## 1. Latar Belakang

Audit UI/UX menemukan **7 area inkonsistensi** di seluruh modul kepegawaian-apps:

| # | Area | Jumlah Variasi | Modul Terdampak |
|---|------|----------------|-----------------|
| 1 | Heading Style | 6 variasi | Semua |
| 2 | Table Container | 4 variasi | Semua |
| 3 | Table Header | 2 variasi | Semua |
| 4 | Padding & Gap | 3 variasi | Semua |
| 5 | Pagination | 2 variasi | IAM Users vs lainnya |
| 6 | Button Filter | 2 variasi | Activity Log vs lainnya |
| 7 | Search Input | 2 variasi | Referensi vs IAM |

---

## 2. Keputusan Desain

### 2.1 Pendekatan Visual: Kombinasi

**Retro Brutalism** untuk elemen struktural utama:
- Heading (h1 + description)
- Table container (border-2, shadow, rounded-xl)
- Search input (border-2, shadow)

**shadcn Default** untuk elemen interaktif minor:
- Button (variant outline/default/desctructive)
- Badge
- Pagination (PaginationWrapper)
- Dialog/Modal
- Select/Dropdown

### 2.2 Standar Spacing: Responsive

```tsx
// Wrapper utama setiap halaman index
<div className="flex flex-col gap-6 p-4 md:p-6">
  {/* Konten */}
</div>
```

- **Mobile (≤768px):** `p-4` (16px)
- **Desktop (≥768px):** `p-6` (24px)
- **Gap antar section:** `gap-6` (24px) konsisten

### 2.3 Standar Heading

```tsx
<div>
  <h1 className="text-2xl font-bold uppercase tracking-tight">
    NAMA HALAMAN
  </h1>
  <p className="text-sm text-muted-foreground mt-1 font-medium">
    Deskripsi singkat tentang halaman ini.
  </p>
</div>
```

- **Ukuran:** `text-2xl` (20px) — konsisten untuk semua halaman index
- **Weight:** `font-bold` (700) — bukan font-semibold atau font-black
- **Transform:** `uppercase` — semua heading huruf kapital
- **Tracking:** `tracking-tight` (-0.025em)
- **Deskripsi:** `text-sm text-muted-foreground mt-1 font-medium`

### 2.4 Standar Table Container

```tsx
<div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
  <Table>
    {/* ... */}
  </Table>
</div>
```

- **Border radius:** `rounded-xl` (12px)
- **Border:** `border-2 border-black`
- **Shadow:** `shadow-[4px_4px_0_rgba(0,0,0,1)]`
- **Background:** `bg-background`
- **Overflow:** `overflow-hidden`

### 2.5 Standar Table Header

```tsx
<TableHeader>
  <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
    <TableHead className="font-black uppercase text-xs tracking-wider">
      NAMA KOLOM
    </TableHead>
  </TableRow>
</TableHeader>
```

- **Weight:** `font-black` (900)
- **Transform:** `uppercase`
- **Ukuran:** `text-xs` (12px)
- **Tracking:** `tracking-wider` (0.05em)
- **Background row:** `bg-muted/30 border-b-2 border-black hover:bg-muted/30`

### 2.6 Standar Table Row

```tsx
<TableRow className="border-b border-black/10 hover:bg-muted/20 transition-colors">
  <TableCell className="font-bold">Data</TableCell>
  <TableCell className="text-muted-foreground">Keterangan</TableCell>
</TableRow>
```

- **Border bawah:** `border-b border-black/10`
- **Hover:** `hover:bg-muted/20 transition-colors`
- **Cell utama:** `font-bold`
- **Cell sekunder:** `text-muted-foreground`

### 2.7 Standar Search Input

```tsx
<Input
  placeholder="Cari..."
  className="max-w-md border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)] focus-visible:shadow-none focus-visible:translate-y-[2px] focus-visible:translate-x-[2px] transition-all"
/>
```

- **Max width:** `max-w-md` (448px)
- **Border:** `border-2 border-black`
- **Shadow:** `shadow-[2px_2px_0_rgba(0,0,0,1)]`
- **Focus:** `focus-visible:shadow-none focus-visible:translate-y-[2px] focus-visible:translate-x-[2px] transition-all`

### 2.8 Standar Filter Bar (Hybrid)

**Inline Filter** — untuk halaman dengan search saja:
```tsx
<div className="flex items-center gap-2">
  <Input placeholder="Cari..." className="max-w-md border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)] ..." />
</div>
```

**Card Filter** — untuk halaman dengan multiple filter fields:
```tsx
<Card>
  <CardHeader>
    <CardTitle className="text-base">Filter</CardTitle>
  </CardHeader>
  <CardContent className="flex flex-wrap items-end gap-3">
    {/* Filter fields */}
    <Button variant="outline" size="sm">Terapkan</Button>
  </CardContent>
</Card>
```

### 2.9 Standar Pagination

```tsx
<PaginationWrapper meta={data.meta} />
```

Gunakan `<PaginationWrapper>` component yang sudah ada. **Tidak boleh** ada pagination custom manual.

### 2.10 Standar Empty State

```tsx
<TableRow>
  <TableCell colSpan={jumlahKolom} className="text-center py-12 font-medium text-muted-foreground">
    Tidak ada data [nama entity] ditemukan.
  </TableCell>
</TableRow>
```

- **Padding:** `py-12`
- **Text:** `text-center font-medium text-muted-foreground`
- **Pattern:** "Tidak ada data [nama entity] ditemukan."

### 2.11 Standar Action Buttons

```tsx
<div className="flex items-center justify-center gap-2">
  <Button variant="ghost" size="icon" asChild>
    <Link href={edit(item.id)}>
      <Pencil className="h-4 w-4" />
    </Link>
  </Button>
  <Button variant="ghost" size="icon" onClick={() => handleDelete(item.id, item.nama)}>
    <Trash2 className="h-4 w-4 text-destructive" />
  </Button>
</div>
```

- **Container:** `flex items-center justify-center gap-2`
- **Edit button:** `variant="ghost" size="icon"` dengan `Pencil` icon
- **Delete button:** `variant="ghost" size="icon"` dengan `Trash2` icon + `text-destructive`

---

## 3. Daftar Halaman yang Perlu Diperbaiki

### 3.1 Modul Referensi

| Halaman | Perubahan |
|---------|-----------|
| `referensi/jenis-dokumen/index.tsx` | Sudah konsisten (contoh standar) |
| `referensi/status-pegawai/index.tsx` | Sudah konsisten (contoh standar) |
| `referensi/status-kepegawaian/index.tsx` | Perlu dicek |
| `referensi/roles/index.tsx` | Layout card-based, perlu penyesuaian minor |

### 3.2 Modul IAM

| Halaman | Perubahan |
|---------|-----------|
| `iam/aplikasi/index.tsx` | **Kritis:** Table container `rounded-md border` → standar retro |
| `iam/users/index.tsx` | **Kritis:** Pagination custom → PaginationWrapper |
| `iam/users/akses.tsx` | **Sedang:** Table container + header style |

### 3.3 Modul Cuti

| Halaman | Perubahan |
|---------|-----------|
| `cuti/admin/audit.tsx` | **Sedang:** Heading lebih kecil, Card filter |
| `cuti/saldo/admin-index.tsx` | Perlu dicek |
| `cuti/saldo/admin-init.tsx` | Perlu dicek |
| `cuti/saldo/my-dashboard.tsx` | Perlu dicek |

### 3.4 Modul Lainnya

| Halaman | Perubahan |
|---------|-----------|
| `activity-log/index.tsx` | **Kritis:** Tidak ada heading, button filter bukan component, pagination meta |

---

## 4. Template Halaman Index Standar

```tsx
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Props = {
  items: PaginatedData<Entity>;
  filters: { search?: string };
};

export default function Index({ items, filters }: Props) {
  const [search, setSearch] = useState(filters.search ?? '');
  const [deleteTarget, setDeleteTarget] = useState<{ id: string; nama: string } | null>(null);
  const deleteForm = useForm({});

  const breadcrumbs: BreadcrumbItem[] = useMemo(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Modul', href: '#' },
    { title: 'Halaman', href: '/route' },
  ], []);

  // Debounced search
  const handleSearch = useCallback(() => {
    router.get('/route', { search }, { preserveState: true, preserveScroll: true });
  }, [search]);

  useEffect(() => {
    const timeout = setTimeout(() => handleSearch(), 300);
    return () => clearTimeout(timeout);
  }, [search, handleSearch]);

  const handleDelete = (id: string, nama: string) => setDeleteTarget({ id, nama });

  const confirmDelete = () => {
    if (!deleteTarget) return;
    deleteForm.delete(`/route/${deleteTarget.id}`, {
      onSuccess: () => setDeleteTarget(null),
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Judul Halaman" />

      <div className="flex flex-col gap-6 p-4 md:p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold uppercase tracking-tight">JUDUL HALAMAN</h1>
            <p className="text-sm text-muted-foreground mt-1 font-medium">Deskripsi singkat.</p>
          </div>
          <Button asChild>
            <Link href="/create">
              <Plus className="mr-2 h-4 w-4" />
              Tambah
            </Link>
          </Button>
        </div>

        {/* Search */}
        <div className="flex items-center gap-2">
          <Input
            placeholder="Cari..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="max-w-md border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)] focus-visible:shadow-none focus-visible:translate-y-[2px] focus-visible:translate-x-[2px] transition-all"
          />
        </div>

        {/* Table */}
        <div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
                <TableHead className="font-black uppercase text-xs tracking-wider">NAMA</TableHead>
                <TableHead className="font-black uppercase text-xs tracking-wider">KETERANGAN</TableHead>
                <TableHead className="font-black uppercase text-xs tracking-wider text-center w-[100px]">AKSI</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="text-center py-12 font-medium text-muted-foreground">
                    Tidak ada data ditemukan.
                  </TableCell>
                </TableRow>
              ) : (
                items.data.map((item) => (
                  <TableRow key={item.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
                    <TableCell className="font-bold">{item.nama}</TableCell>
                    <TableCell className="text-muted-foreground">{item.keterangan ?? '-'}</TableCell>
                    <TableCell>
                      <div className="flex items-center justify-center gap-2">
                        <Button variant="ghost" size="icon" asChild>
                          <Link href={`/edit/${item.id}`}><Pencil className="h-4 w-4" /></Link>
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => handleDelete(item.id, item.nama)}>
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* Pagination */}
        <PaginationWrapper meta={items.meta} />
      </div>

      <ConfirmDeleteDialog
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title="Hapus Item"
        description="Apakah Anda yakin ingin menghapus"
        itemName={deleteTarget?.nama}
        onConfirm={confirmDelete}
        processing={deleteForm.processing}
      />
    </AppLayout>
  );
}
```

---

## 5. Checklist Verifikasi

Setelah perbaikan, verifikasi setiap halaman memiliki:

- [ ] Heading: `text-2xl font-bold uppercase tracking-tight`
- [ ] Deskripsi: `text-sm text-muted-foreground mt-1 font-medium`
- [ ] Wrapper: `flex flex-col gap-6 p-4 md:p-6`
- [ ] Table container: `rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden`
- [ ] Table header: `font-black uppercase text-xs tracking-wider` + `bg-muted/30 border-b-2 border-black`
- [ ] Table row: `border-b border-black/10 hover:bg-muted/20 transition-colors`
- [ ] Search input: `max-w-md border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)] ...`
- [ ] Pagination: Menggunakan `<PaginationWrapper>`
- [ ] Action buttons: `flex items-center justify-center gap-2` dengan `variant="ghost" size="icon"`
- [ ] Empty state: `text-center py-12 font-medium text-muted-foreground`
- [ ] Button filter (jika ada): Menggunakan `<Button>` component, bukan raw `<button>`

---

## 6. Out of Scope

- Halaman create/edit form (bukan index)
- Halaman dashboard
- Halaman auth (login, register, dll)
- Halaman settings
- Modul kepegawaian (pegawai, dokumen) — akan di-review terpisah
- Mobile responsiveness selain padding
