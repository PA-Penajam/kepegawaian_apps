# Dashboard UI/UX Refactor (Storytelling Data) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Memutakhirkan UI/UX halaman Dashboard (Pendekatan C: Storytelling Data & Insight Bersih) agar lebih interaktif, modern, dan memberikan kesimpulan visual yang jelas bagi pimpinan/pengguna akhir.

**Architecture:** 
1. Pendekatan *Storytelling Data*: Alih-alih hanya tumpukan grafik, tambahkan kartu wawasan (*insight cards*) di atas grafik yang menyoroti tren utama.
2. Penambahan *Welcome Section* dinamis.
3. Struktur komponen akan di-refactor: grafik dipindahkan ke grid model komprehensif, ditambahkan animasi dan hover state interaktif.

**Tech Stack:** React, Inertia.js, Tailwind CSS (v4), shadcn/ui.

---

### Task 1: Membuat Komponen DashboardHeader
**Files:**
- Create: `resources/js/components/dashboard/DashboardHeader.tsx`

**Step 1: Write initial structure (TDD/UI-Driven)**
Buat komponen untuk menampilkan waktu dan sapaan dinamis (Selamat Pagi/Siang/Sore), serta statistik sangat cepat.

**Step 2: Implementasi DashboardHeader**
Isi dengan layout menggunakan grid dan efek `BlurFade`.

**Step 3: Verifikasi komponen visual**
Run visual test/HMR.

**Step 4: Commit**
`git add resources/js/components/dashboard/DashboardHeader.tsx`
`git commit -m "feat: add interactive DashboardHeader"`

---

### Task 2: Refactor Dashboard.tsx
**Files:**
- Modify: `resources/js/pages/dashboard.tsx`

**Step 1: Update struktur layout utama**
Pasang `DashboardHeader` di atas daftar metrik. Modifikasi `FastStats` agar bentuk visualnya menceritakan sebuah narasi (contoh: status kenaikan pangkat disortir).

**Step 2: Verifikasi perenderan**
Buka URL `/dashboard` dan pastikan tidak berantakan.

**Step 3: Commit**
`git commit -am "refactor: restructure main Dashboard layout"`

---

### Task 3: Refactor DashboardHeavySection.tsx (Storytelling)
**Files:**
- Modify: `resources/js/components/dashboard/DashboardHeavySection.tsx`

**Step 1: Ubah susunan grafik menjadi pola bento/grid dinamis yang terencana**
Tambahkan sub-teks (insight otomatis) pada data yang menonjol untuk membantu pembaca.

**Step 2: Terapkan interaksi hover**
Tambahkan animasi perbesar pada saat kartu grafik di *hover*.

**Step 3: Verifikasi Visual**
Pastikan DashboardHeavySection berjalan lancar tanpa freeze.

**Step 4: Commit**
`git commit -am "feat: implement storytelling dataviz and interactivity on HeavySection"`

---
