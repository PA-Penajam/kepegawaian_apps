# UI/UX Consistency — Standarisasi Seluruh Modul

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menstandarisasi UI/UX di seluruh halaman index agar konsisten dengan pola Retro Brutalism + shadcn.

**Architecture:** Pendekatan Kombinasi — Retro Brutalism untuk elemen struktural (heading, table container, search input), shadcn default untuk elemen interaktif (button, badge, pagination). Spacing responsive `p-4 md:p-6` + `gap-6`.

**Tech Stack:** React, Inertia.js, Tailwind CSS, shadcn/ui

---

## File Structure

| File | Perubahan |
|------|-----------|
| `resources/js/pages/iam/aplikasi/index.tsx` | Table container, header, heading, deskripsi |
| `resources/js/pages/iam/users/index.tsx` | Pagination → PaginationWrapper, heading |
| `resources/js/pages/iam/users/akses.tsx` | Table container, header, heading |
| `resources/js/pages/activity-log/index.tsx` | Heading, button filter, wrapper |
| `resources/js/pages/cuti/admin/audit.tsx` | Heading |
| `resources/js/pages/cuti/saldo/admin-index.tsx` | Heading, table header, pagination props |
| `resources/js/pages/referensi/status-kepegawaian/index.tsx` | Wrapper gap |

---

## Task 1: Fix `activity-log/index.tsx`

**Prioritas:** Kritis — Tidak ada heading, button filter bukan component

**Files:**
- Modify: `resources/js/pages/activity-log/index.tsx`

- [ ] **Step 1: Tambah heading dan perbaiki wrapper**

Ganti bagian return dari `<AppLayout>` sampai `<div className="space-y-4 p-4">`:

```tsx
return (
    <AppLayout breadcrumbs={[{ title: 'Activity Log', href: activityLog.index().url }]}>
        <Head title="Activity Log" />

        <div className="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 className="text-2xl font-bold uppercase tracking-tight">ACTIVITY LOG</h1>
                <p className="text-sm text-muted-foreground mt-1 font-medium">
                    Lacak semua aktivitas perubahan data dalam sistem.
                </p>
            </div>
```

- [ ] **Step 2: Perbaiki button filter**

Ganti `<button>` menjadi `<Button>` component. Tambah import `Button` jika belum ada:

```tsx
import { Button } from '@/components/ui/button';
```

Ganti button filter:

```tsx
<Button variant="outline" size="sm" onClick={applyFilters}>
    Terapkan
</Button>
```

- [ ] **Step 3: Perbaiki table header**

Ganti `<TableHeader>` menjadi:

```tsx
<TableHeader>
    <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
        <TableHead className="font-black uppercase text-xs tracking-wider w-40">WAKTU</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">OLEH</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider w-24">AKSI</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">MODEL</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">PERUBAHAN</TableHead>
    </TableRow>
</TableHeader>
```

- [ ] **Step 4: Perbaiki table container**

Bungkus `<Table>` dengan container retro:

```tsx
<div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
    <Table>
        {/* ... */}
    </Table>
</div>
```

- [ ] **Step 5: Verifikasi**

Buka halaman Activity Log di browser. Pastikan:
- Heading muncul dengan style `text-2xl font-bold uppercase`
- Button filter menggunakan component `<Button>`
- Table container memiliki border-2 dan shadow
- Table header uppercase dan bold

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/activity-log/index.tsx
git commit -m "fix(activity-log): standarisasi UI heading, table, dan button filter"
```

---

## Task 2: Fix `iam/aplikasi/index.tsx`

**Prioritas:** Kritis — Table container `rounded-md border`, header default

**Files:**
- Modify: `resources/js/pages/iam/aplikasi/index.tsx`

- [ ] **Step 1: Perbaiki heading**

Ganti heading:

```tsx
<div className="flex items-center justify-between">
    <div>
        <h1 className="text-2xl font-bold uppercase tracking-tight">
            KELOLA APLIKASI IAM
        </h1>
        <p className="text-sm text-muted-foreground mt-1 font-medium">
            Daftarkan dan kelola aplikasi yang terintegrasi dengan sistem IAM.
        </p>
    </div>
    {/* Dialog trigger tetap */}
</div>
```

- [ ] **Step 2: Perbaiki wrapper**

Ganti wrapper:

```tsx
<div className="flex flex-col gap-6 p-4 md:p-6">
```

- [ ] **Step 3: Perbaiki table container**

Ganti `<div className="rounded-md border">` menjadi:

```tsx
<div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
```

- [ ] **Step 4: Perbaiki table header**

Ganti `<TableHeader>`:

```tsx
<TableHeader>
    <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
        <TableHead className="font-black uppercase text-xs tracking-wider">NAMA</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">URL</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-center">JUMLAH ROLE</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-center">STATUS</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-center">AKSI</TableHead>
    </TableRow>
</TableHeader>
```

- [ ] **Step 5: Perbaiki table row**

Ganti styling `<TableRow>` dan `<TableCell>`:

```tsx
<TableRow key={app.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
    <TableCell className="font-bold">
        {/* Nama */}
    </TableCell>
    {/* Cell lainnya */}
</TableRow>
```

- [ ] **Step 6: Perbaiki empty state**

```tsx
<TableRow>
    <TableCell colSpan={5} className="text-center py-12 font-medium text-muted-foreground">
        Tidak ada aplikasi yang terdaftar.
    </TableCell>
</TableRow>
```

- [ ] **Step 7: Verifikasi**

Buka halaman IAM Aplikasi. Pastikan:
- Heading uppercase bold dengan deskripsi
- Table container retro dengan shadow
- Table header uppercase bold
- Empty state centered dengan padding

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/iam/aplikasi/index.tsx
git commit -m "fix(iam-aplikasi): standarisasi UI table container, header, heading"
```

---

## Task 3: Fix `iam/users/index.tsx`

**Prioritas:** Kritis — Pagination custom manual, heading terlalu besar

**Files:**
- Modify: `resources/js/pages/iam/users/index.tsx`

- [ ] **Step 1: Perbaiki heading**

Ganti heading:

```tsx
<div>
    <h1 className="text-2xl font-bold uppercase tracking-tight">USER AKSES</h1>
    <p className="text-sm text-muted-foreground mt-1 font-medium">
        Kelola hak akses role untuk setiap pengguna.
    </p>
</div>
```

- [ ] **Step 2: Perbaiki wrapper**

Ganti wrapper:

```tsx
<div className="flex flex-col gap-6 p-4 md:p-6">
```

- [ ] **Step 3: Hapus pagination custom**

Hapus seluruh block pagination custom (sekitar baris 212-232):

```tsx
{/* Hapus ini */}
{meta.last_page > 1 && (
    <div className="flex items-center justify-center gap-3">
        {/* ... */}
    </div>
)}
```

- [ ] **Step 4: Tambah PaginationWrapper**

Tambah import:

```tsx
import { PaginationWrapper } from '@/components/pagination-wrapper';
```

Tambah setelah table container:

```tsx
<PaginationWrapper meta={meta} />
```

- [ ] **Step 5: Hapus unused meta calculation**

Karena PaginationWrapper menerima `meta` langsung, kita bisa simplify. Tapi karena `users` mungkin tidak punya `meta` property, kita perlu cek. Jika `users.meta` ada, gunakan langsung. Jika tidak, pertahankan normalisasi.

- [ ] **Step 6: Verifikasi**

Buka halaman IAM Users. Pastikan:
- Heading `text-2xl font-bold uppercase`
- Pagination menggunakan PaginationWrapper (shadcn style, bukan custom)
- Wrapper `p-4 md:p-6` + `gap-6`

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/iam/users/index.tsx
git commit -m "fix(iam-users): standarisasi heading, pagination → PaginationWrapper"
```

---

## Task 4: Fix `iam/users/akses.tsx`

**Prioritas:** Sedang — Table container dan header belum retro

**Files:**
- Modify: `resources/js/pages/iam/users/akses.tsx`

- [ ] **Step 1: Perbaiki heading**

Ganti heading:

```tsx
<div>
    <h1 className="text-2xl font-bold uppercase tracking-tight">
        AKSES USER: {user.name}
    </h1>
    <p className="text-sm text-muted-foreground mt-1 font-medium">{user.email}</p>
</div>
```

- [ ] **Step 2: Perbaiki wrapper**

Ganti wrapper:

```tsx
<div className="flex flex-col gap-6 p-4 md:p-6">
```

- [ ] **Step 3: Perbaiki table container di grouped akses**

Ganti `<div className="rounded-md border">` menjadi:

```tsx
<div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
```

- [ ] **Step 4: Perbaiki table header**

Ganti setiap `<TableHeader>` di grouped akses:

```tsx
<TableHeader>
    <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
        <TableHead className="font-black uppercase text-xs tracking-wider">ROLE</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">PERMISSIONS</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">DIBERIKAN OLEH</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">TANGGAL</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-center w-[100px]">AKSI</TableHead>
    </TableRow>
</TableHeader>
```

- [ ] **Step 5: Perbaiki table row**

Ganti styling `<TableRow>`:

```tsx
<TableRow key={a.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
```

- [ ] **Step 6: Verifikasi**

Buka halaman IAM User Akses. Pastikan:
- Heading uppercase bold
- Table container retro dengan shadow
- Table header uppercase bold
- Wrapper responsive

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/iam/users/akses.tsx
git commit -m "fix(iam-user-akses): standarisasi UI table container, header, heading"
```

---

## Task 5: Fix `cuti/admin/audit.tsx`

**Prioritas:** Sedang — Heading lebih kecil dari standar

**Files:**
- Modify: `resources/js/pages/cuti/admin/audit.tsx`

- [ ] **Step 1: Perbaiki heading**

Ganti heading:

```tsx
<div>
    <h1 className="text-2xl font-bold uppercase tracking-tight">AUDIT LOG CUTI</h1>
    <p className="text-sm text-muted-foreground mt-1 font-medium">
        Lacak semua perubahan pada modul cuti.
    </p>
</div>
```

- [ ] **Step 2: Perbaiki table header**

Ganti `<TableHeader>`:

```tsx
<TableHeader>
    <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
        <TableHead className="w-8" />
        <TableHead className="font-black uppercase text-xs tracking-wider w-44">WAKTU</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">AKTOR</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">AKTIVITAS</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">SUBJEK</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">PERUBAHAN</TableHead>
    </TableRow>
</TableHeader>
```

- [ ] **Step 3: Verifikasi**

Buka halaman Cuti Audit. Pastikan:
- Heading `text-2xl font-bold uppercase`
- Table header uppercase bold

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/cuti/admin/audit.tsx
git commit -m "fix(cuti-audit): standarisasi heading dan table header"
```

---

## Task 6: Fix `cuti/saldo/admin-index.tsx`

**Prioritas:** Sedang — Heading, table header, pagination props

**Files:**
- Modify: `resources/js/pages/cuti/saldo/admin-index.tsx`

- [ ] **Step 1: Perbaiki heading**

Ganti heading:

```tsx
<div>
    <h1 className="text-2xl font-bold uppercase tracking-tight">KELOLA SALDO CUTI</h1>
    <p className="text-sm text-muted-foreground mt-1 font-medium">
        Kelola dan sesuaikan saldo cuti pegawai.
    </p>
</div>
```

- [ ] **Step 2: Perbaiki table header**

Ganti `<TableHeader>` di tabel saldo:

```tsx
<TableHeader>
    <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
        <TableHead className="font-black uppercase text-xs tracking-wider">NIP</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">NAMA PEGAWAI</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider">JENIS</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-right">HAK AWAL</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-right">SALDO SAAT INI</TableHead>
        <TableHead className="font-black uppercase text-xs tracking-wider text-right">AKSI</TableHead>
    </TableRow>
</TableHeader>
```

- [ ] **Step 3: Perbaiki table row**

Ganti styling `<TableRow>`:

```tsx
<TableRow key={item.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
```

- [ ] **Step 4: Perbaiki pagination props**

Ganti pagination:

```tsx
<PaginationWrapper meta={alokasiList.meta ?? { total: alokasiList.total, current_page: alokasiList.current_page, last_page: alokasiList.last_page }} />
```

Atau jika `alokasiList` punya `meta` property:

```tsx
<PaginationWrapper meta={alokasiList.meta} />
```

- [ ] **Step 5: Verifikasi**

Buka halaman Kelola Saldo Cuti. Pastikan:
- Heading uppercase bold
- Table header uppercase bold
- Pagination berfungsi dengan PaginationWrapper

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/cuti/saldo/admin-index.tsx
git commit -m "fix(cuti-saldo-admin): standarisasi heading, table header, pagination"
```

---

## Task 7: Fix `referensi/status-kepegawaian/index.tsx`

**Prioritas:** Minor — Wrapper gap belum standar

**Files:**
- Modify: `resources/js/pages/referensi/status-kepegawaian/index.tsx`

- [ ] **Step 1: Perbaiki wrapper**

Ganti wrapper:

```tsx
<div className="flex flex-col gap-6 p-4 md:p-6">
```

- [ ] **Step 2: Verifikasi**

Buka halaman Status Kepegawaian. Pastikan:
- Wrapper menggunakan `gap-6` dan `p-4 md:p-6`

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/referensi/status-kepegawaian/index.tsx
git commit -m "fix(status-kepegawaian): standarisasi wrapper gap dan padding"
```

---

## Checklist Verifikasi Akhir

Setelah semua task selesai, verifikasi setiap halaman memiliki:

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
- [ ] Button filter: Menggunakan `<Button>` component, bukan raw `<button>`

---

## Out of Scope

- `cuti/saldo/admin-init.tsx` — halaman form, bukan index
- `cuti/saldo/my-dashboard.tsx` — halaman dashboard, bukan index
- `referensi/roles/index.tsx` — layout card-based, sudah konsisten dengan pendekatannya sendiri
- Halaman create/edit form
- Halaman dashboard, auth, settings
- Modul kepegawaian (pegawai, dokumen)
