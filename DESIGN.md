---
name: Kepegawaian PA Penajam
description: Sistem Desain Enterprise & Administrasi Yudisial SIMPEG PA Penajam
colors:
  primary: "oklch(0.32 0.10 155)"
  primary-foreground: "oklch(0.98 0 0)"
  secondary: "oklch(0.95 0.02 155)"
  secondary-foreground: "oklch(0.25 0.05 60)"
  background: "oklch(0.99 0.002 155)"
  foreground: "oklch(0.15 0.02 155)"
  card: "oklch(1 0 0)"
  card-foreground: "oklch(0.15 0.02 155)"
  popover: "oklch(1 0 0)"
  popover-foreground: "oklch(0.15 0.02 155)"
  muted: "oklch(0.96 0.01 155)"
  muted-foreground: "oklch(0.50 0.02 155)"
  accent: "oklch(0.72 0.16 75)"
  accent-foreground: "oklch(0.25 0.05 60)"
  destructive: "oklch(0.577 0.245 27.325)"
  destructive-foreground: "oklch(0.98 0 0)"
  border: "oklch(0.90 0.02 155)"
  input: "oklch(0.90 0.02 155)"
  ring: "oklch(0.50 0.10 155)"
  sidebar: "oklch(0.18 0.06 155)"
  sidebar-foreground: "oklch(0.92 0.02 155)"
  sidebar-primary: "oklch(0.78 0.15 80)"
  sidebar-primary-foreground: "oklch(0.18 0.06 155)"
  sidebar-accent: "oklch(0.25 0.07 155)"
  sidebar-accent-foreground: "oklch(0.92 0.02 155)"
  sidebar-border: "oklch(0.28 0.06 155)"
  sidebar-ring: "oklch(0.50 0.10 155)"
  gold: "oklch(0.72 0.16 75)"
  orange: "oklch(0.65 0.18 55)"
  green-dark: "oklch(0.18 0.06 155)"
typography:
  display:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "-0.015em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "-0.01em"
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: "0.02em"
rounded:
  sm: "calc(0.625rem - 4px)"
  md: "calc(0.625rem - 2px)"
  lg: "0.625rem"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.secondary-foreground}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-destructive:
    backgroundColor: "{colors.destructive}"
    textColor: "{colors.destructive-foreground}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  card-default:
    backgroundColor: "{colors.card}"
    textColor: "{colors.card-foreground}"
    rounded: "{rounded.lg}"
    padding: "16px 20px"
---

# Design System: SIMPEG PA Penajam

## Overview

**Creative North Star: "The Judicial Bastion"**

Sistem desain **Kepegawaian PA Penajam** dibangun untuk menghadirkan pengalaman antarmuka instansi peradilan yang berwibawa, formal, kredibel, dan berintegritas tinggi. Mengusung arsitektur operasional (*Operate* mode), sistem memprioritaskan efisiensi penelaahan berkas birokrasi peradilan, validitas data ASN, dan kejelasan alur persetujuan bertingkat.

Setiap antarmuka dirancang dengan kepadatan data tinggi (*high-density utility*) tanpa mengorbankan kenyamanan keterbacaan aparatur peradilan. Estetika visual memadukan wibawa hijau pinus yudisial (*Judicial Forest Green*) dengan kemilau aksen emas kehormatan (*Mahkamah Gold*) yang diterapkan secara terukur dan disiplin.

**Key Characteristics:**
- **Judicial Gravity**: Nuansa institusional yang formal, tertib hukum, dan berwibawa.
- **High-Density Utility**: Kerapatan tabel data dan formulir terstruktur optimal untuk verifikasi cepat.
- **Tonal Layering & Crisp Precision**: Struktur batas halus 1px dan mikro-shadow daripada drop-shadow tebal.
- **Strict Semantic Integrity**: Indikator status mutlak dan konsisten di seluruh modul layanan kepegawaian.

## Colors

Palet warna SIMPEG PA Penajam mencerminkan kehormatan lembaga peradilan dengan dominasi hijau yudisial, aksen emas institusional, dan kanvas netral berlatar jernih.

### Primary
- **Judicial Forest Green (`oklch(0.32 0.10 155)`)**: Warna jangkar utama institusi. Digunakan pada tombol aksi utama, header navigasi terpilih, dan elemen identitas formal.
- **Pure White Foreground (`oklch(0.98 0 0)`)**: Warna teks kontras tinggi di atas permukaan primary.

### Secondary & Accent
- **Mahkamah Gold (`oklch(0.72 0.16 75)`)**: Aksen emas hangat untuk sorotan status khusus, badge penghargaan, penanda tenggat KGB/KP, dan aksen aktif navigasi.
- **Soft Judicial Mint (`oklch(0.95 0.02 155)`)**: Warna latar sekunder dan tombol netral pendukung aksi utama.

### Neutral
- **Institutional Canvas Background (`oklch(0.99 0.002 155)`)**: Latar belakang aplikasi dengan semburat hijau sejuk ultra-halus untuk kenyamanan mata.
- **Crisp Card Surface (`oklch(1 0 0)`)**: Permukaan kartu putih bersih dengan batas 1px border halus.
- **Slate Text Foreground (`oklch(0.15 0.02 155)`)**: Warna tipografi data dan teks utama berbobot keterbacaan tinggi.
- **Muted Label Slate (`oklch(0.50 0.02 155)`)**: Warna teks metadata, subjudul, dan placeholder formulir.
- **Subtle Border Line (`oklch(0.90 0.02 155)`)**: Garis batas bidang dan pembagi baris tabel data.
- **Judicial Deep Pine Sidebar (`oklch(0.18 0.06 155)`)**: Latar belakang panel navigasi samping yang kontras dan fokus.

### Semantics
- **Approval Emerald (`oklch(0.45 0.12 155)`)**: Status Disetujui, Berkas Lengkap, dan Pegawai Aktif.
- **Warning Amber / Orange (`oklch(0.65 0.18 55)`)**: Peringatan masa tenggang KGB/KP (≤ 2 bulan) dan Menunggu Approval Atasan.
- **Disciplinary Crimson (`oklch(0.577 0.245 27.325)`)**: Permohonan Cuti Ditolak, Hukuman Disiplin, dan Aksi Destruktif/Hapus.

### Named Rules
**The 10% Gold Accent Rule.** Aksen *Mahkamah Gold* digunakan maksimal ≤ 10% dari total area layar. Keistimewaan dan daya tariknya terletak pada eksklusivitas penggunaannya untuk sorotan kritis.

**The Strict Semantic Rule.** Warna semantik (hijau, amber, merah) dilarang digunakan untuk dekorasi murni; setiap warna semantik wajib merepresentasikan status riil data administrasi.

## Typography

**Display & Body Font:** `Instrument Sans` (dengan fallback `ui-sans-serif, system-ui, sans-serif`)  
**Character:** Sans-serif kontemporer dengan proporsi geometris presisi, sudut tajam berwibawa, dan kejelasan glif optimal untuk angka NIP, tanggal TMT, serta teks dokumen legal.

### Hierarchy
- **Display** (`font-bold`, `2.25rem` / 36px, `line-height: 1.2`, `letter-spacing: -0.02em`): Hero judul dashboard dan halaman autentikasi/portal gerbang.
- **Headline** (`font-semibold`, `1.5rem` / 24px, `line-height: 1.3`, `letter-spacing: -0.015em`): Judul modul utama (Daftar Pegawai, Pengajuan Cuti, Usulan KP).
- **Title** (`font-semibold`, `1.125rem` / 18px, `line-height: 1.4`, `letter-spacing: -0.01em`): Header kartu, judul seksi formulir, dan modal title.
- **Body** (`font-normal`, `0.875rem` / 14px, `line-height: 1.5`): Teks isi data tabel, paragraf keterangan, dan deskripsi berkas lampiran.
- **Label** (`font-medium`, `0.75rem` / 12px, `line-height: 1.4`, `letter-spacing: 0.02em`): Label field input formulir, status badge uppercase, dan metadata timestamp.

### Named Rules
**The Data Legibility Rule.** Angka identifikasi kepegawaian (NIP, Nomor SK, Saldo Cuti, TMT) wajib ditampilkan dengan font tabular/standar yang tidak terpotong dan berkontras minimal 4.5:1 terhadap latar belakang.

## Layout

Tata letak mengadopsi struktur shell aplikasi terpusat dengan Sidebar Navigasi Kiri tetap (*collapsible*) dan area konten kerja adaptif dengan padding standar `p-6` (24px) pada desktop dan `p-4` (16px) pada perangkat bergerak.

- **Sistem Grid**: CSS Grid 12-kolom untuk dashboard ringkasan statistik (KPI cards) dan flexbox adaptif untuk bilah aksi filter tabel.
- **High-Density Data**: Tabel administrasi menggunakan padding sel padat `py-2.5 px-3` (10px x 12px) untuk memaksimalkan jumlah baris berkas yang dapat ditelaah dalam satu layar pandang.
- **Formulir Modular**: Input data panjang dipisahkan menjadi kartu fieldset tematik (Biodata, Riwayat Pangkat, Dokumen Lampiran) atau tab navigasi bertahap.

### Named Rules
**The High-Density Table Rule.** Tabel data operasional mengutamakan kerapatan informasi baris dan penataan header rata kiri untuk teks/status serta rata kanan untuk kuota/angka saldo, tanpa elemen ornamen dekoratif yang menyita ruang vertikal.

## Elevation & Depth

SIMPEG PA Penajam mengadopsi prinsip **Tonal Layering & Crisp Precision**. Kedalaman antarmuka dicapai melalui batas garis 1px (`border-border`) dan kontras warna bidang daripada bayangan drop-shadow tebal.

### Shadow Vocabulary
- **Micro Lift (`shadow-xs` / `0 1px 2px 0 rgb(0 0 0 / 0.05)`)**: Memberikan efek taktil mikro pada tombol aksi, search bar, dan input field saat aktif.
- **Surface Elevation (`shadow-sm` / `0 1px 3px 0 rgb(0 0 0 / 0.1)`)**: Elevasi baku kartu ringkasan data dan kontainer tabel.
- **Overlay Elevation (`shadow-lg` / `0 10px 15px -3px rgb(0 0 0 / 0.1)`)**: Elevasi untuk modal verifikasi dokumen, dropdown context menu, dan drawer panel detail berkas.

### Named Rules
**The Border-First Elevation Rule.** Seluruh kontainer data dan kartu wajib memiliki pembatas garis 1px `border-border` sebagai fondasi pemisah bidang sebelum menerapkan efek bayangan.

## Shapes

- **Radius Baku**:
  - `lg` (`0.625rem` / 10px): Sudut kartu kontainer utama, panel tabel, dan modal dialog.
  - `md` (`calc(0.625rem - 2px)` / 8px): Sudut tombol aksi, input field, dan select dropdown.
  - `sm` (`calc(0.625rem - 4px)` / 6px): Sudut elemen mikro, tooltip, dan nested chips.
  - `full` (`9999px`): Badge status pill dan foto profil avatar ASN.
- **Borders**: Ketebalan garis standar 1px `border-border` (`oklch(0.90 0.02 155)`).

### Named Rules
**The Formal Rounded Rule.** Radius elemen dibatasi maksimal 10px (`rounded-xl`) untuk mempertahankan karakter kelembagaan formal yang tegas dan berstruktur.

## Components

### Buttons
- **Shape:** Radius terukur 8px (`rounded-md` / `rounded-lg`).
- **Primary:** Latar `bg-primary` (`oklch(0.32 0.10 155)`), teks putih `text-primary-foreground`, padding `px-4 py-2`, bayangan mikro `shadow-xs`.
- **Hover / Focus:** Transisi halus `hover:bg-primary/90`, focus ring `focus-visible:ring-[3px] focus-visible:ring-ring/50`.
- **Secondary / Outline:** Latar transparan atau `bg-secondary` dengan garis batas `border-input`, teks `text-secondary-foreground`.
- **Destructive:** Latar merah tua `bg-destructive` untuk aksi penolakan berkas dan pembatalan usulan.

### Status Badges (Pills)
- **Style:** Kapsul pill padat berfont tebal `font-semibold` / `font-black`, teks ukuran `0.75rem` (12px), padding `px-2.5 py-0.5`.
- **Variants:**
  - *Disetujui*: Latar hijau lembut dengan teks emerald tua.
  - *Menunggu Verifikasi*: Latar amber lembut dengan teks oranye/gold tua.
  - *Ditolak / Sanksi*: Latar merah muda dengan teks crimson tegas.

### Cards / Containers
- **Corner Style:** Radius 10px (`rounded-xl`).
- **Background:** Putih bersih `bg-card` dengan pembatas `border border-border`.
- **Internal Padding:** `p-6` (24px) untuk kartu form, `p-4` (16px) untuk kartu statistik metrik.

### Inputs / Fields
- **Style:** Garis batas `border-input`, latar `bg-background` atau `bg-card`, tinggi `h-9` (36px), radius 8px, padding teks `px-3 py-1`.
- **Focus:** Efek cincin fokus hijau yudisial `focus-visible:ring-ring/50 focus-visible:border-ring`.
- **Error:** Garis batas merah terang `border-destructive` dengan pesan validasi instan di bawah input.

### Data Tables
- **Header:** Latar `bg-muted/50` dengan border bawah, teks `text-xs font-semibold text-foreground uppercase tracking-wider`.
- **Rows:** Hover interaktif `hover:bg-muted/40` dengan transisi warna instan untuk penelusuran baris data.

### Dialogs & Modals
- **Style:** Radix-based modal dengan backdrop gelap `bg-black/60`, kontainer tengah bersudut 10px `rounded-lg border bg-background p-6 shadow-lg`.
- **Action Hierarchy:** Tombol konfirmasi tegas di sisi kanan bawah berdampingan dengan tombol pembatalan sekunder.

## Do's and Don'ts

### Do:
- **Do** gunakan token warna semantik (`Approval Emerald`, `Warning Amber`, `Disciplinary Crimson`) secara konsisten pada setiap status alur kerja birokrasi (Cuti, KP, KGB, IAM).
- **Do** sediakan state kosong (*Empty State*) informatif dengan ikon tematik, keterangan jelas, dan tombol aksi pembuat data baru.
- **Do** tampilkan skeleton loader yang berkedip halus (*pulsing skeleton*) saat Inertia memuat dataset tabel atau detail berkas pegawai.
- **Do** sertakan modal konfirmasi berlapis sebelum eksekusi penolakan permohonan atau penghapusan arsip digital ASN.
- **Do** gunakan label formulir eksplisit dengan indikator bintang merah untuk kolom wajib sesuai regulasi BKN.

### Don't:
- **Don't** menggunakan gradien teks berwarna-warni atau animasi dekoratif berlebih yang mengurangi wibawa institusi peradilan.
- **Don't** menyembunyikan pesan kesalahan validasi server; selalu render pesan inline di bawah field input terkait.
- **Don't** memakai kontras rendah (abu-abu pudar di atas putih) pada atribut vital aparatur seperti NIP, Tanggal TMT, dan Saldo Cuti.
- **Don't** menempatkan tombol aksi destruktif (Tolak/Hapus) tanpa diferensiasi warna merah yang kontras dari tombol persetujuan (Setujui/Simpan).
