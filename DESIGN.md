---
name: Kepegawaian PA Penajam
description: Modern Enterprise & Judicial Government SIMPEG Design System
colors:
  primary: "oklch(0.32 0.10 155)"
  primary-foreground: "oklch(0.98 0 0)"
  secondary: "oklch(0.95 0.02 155)"
  secondary-foreground: "oklch(0.25 0.05 60)"
  background: "oklch(0.99 0.002 155)"
  foreground: "oklch(0.15 0.02 155)"
  card: "oklch(1 0 0)"
  card-foreground: "oklch(0.15 0.02 155)"
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
  sidebar-accent: "oklch(0.25 0.07 155)"
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
  card-default:
    backgroundColor: "{colors.card}"
    textColor: "{colors.card-foreground}"
    rounded: "{rounded.lg}"
    padding: "16px 20px"
---

# Design System: SIMPEG PA Penajam

## Overview

Sistem desain **Kepegawaian PA Penajam** dibangun untuk menghadirkan pengalaman antarmuka instansi peradilan yang modern, kredibel, berkinerja tinggi, dan berkepadatan informasi optimal (*high-density utility*). Didesain untuk mode operasional (*Operate*), antarmuka memprioritaskan efisiensi penelaahan berkas, kejelasan alur persetujuan, dan integritas data kepegawaian ASN.

## Colors

- **Primary (`oklch(0.32 0.10 155)`)**: Deep Forest Green bernuansa yudisial/institusional yang melambangkan kewibawaan dan ketertiban.
- **Accent / Gold (`oklch(0.72 0.16 75)`)**: Warm Amber / Gold untuk elemen sorotan, status perhatian, dan aksen navigasi aktif.
- **Sidebar (`oklch(0.18 0.06 155)`)**: Background gelap hijau pinus untuk hierarki navigasi utama yang kontras dan fokus.
- **Neutrals**:
  - `background`: Canvas terang bernuansa soft-tint hijau (`oklch(0.99 0.002 155)`).
  - `card` & `popover`: Putih bersih dengan border lembut (`oklch(0.90 0.02 155)`).
  - `foreground`: Slate gelap berkontras tinggi (`oklch(0.15 0.02 155)`).
- **Semantics**:
  - `success`: Hijau daun untuk status disetujui, selesai, dan aktif.
  - `warning` / `alert`: Oranye/Amber untuk masa tenggang KGB/KP ≤ 2 bulan.
  - `destructive`: Merah tua tegas untuk penolakan permohonan, sanksi disiplin, dan hapus data.

## Typography

- **Font Family**: `Instrument Sans` sebagai font sans-serif utama yang modern dan presisi.
- **Hierarki & Skala**:
  - **Page Title**: `1.5rem` (24px) / `font-bold` dengan tracking `-0.02em`.
  - **Section / Card Header**: `1.125rem` (18px) / `font-semibold`.
  - **Data / Table Cell**: `0.875rem` (14px) / `font-normal` untuk kenyamanan membaca data berbaris.
  - **Metadata & Badges**: `0.75rem` (12px) / `font-medium` dengan uppercase/capsule styling.

## Layout

- **Shell Struktur**: Sidebar kiri tetap (collapsible) dengan sub-menu terorganisir per domain (Utama, Layanan Mandiri, Kepegawaian, Monitoring, IAM & Admin).
- **Kepadatan Data (Data Density)**: Tabel data memanfaatkan padding `py-2.5 px-3.5` agar dapat memuat 15–20 baris per viewport standar tanpa membuat pengguna pusing.
- **Formulir Terstruktur**: Memisahkan entri data panjang ke dalam Card Fieldset atau Tab Navigasi logis daripada satu halaman formulir tanpa ujung.

## Elevation & Depth

- **Tonal Layering**: Mengutamakan pemisahan bidang menggunakan border 1px (`border-border`) dan kontras warna latar belakang daripada drop-shadow tebal.
- **Shadow Minimal**: Shadow halus `shadow-xs` / `shadow-sm` untuk kartu konten dan `shadow-lg` untuk modal dialog dan menu dropdown.

## Shapes

- **Radius Baku**: `0.625rem` (10px) untuk kartu kontainer utama, `calc(0.625rem - 2px)` (8px) untuk input dan tombol, serta `full` untuk badge pill dan avatar.

## Components

- **Data Table**: Dilengkapi search bar instan, filter baris (dropdown unit/golongan/status), sorting header aktif, dan footer paginasi yang jelas.
- **Status Badges**: Capsule pill dengan ikon mikro indikator (contoh: bullet hijau untuk Aktif, jam pasir amber untuk Menunggu Approval, tanda silang merah untuk Ditolak).
- **Action Buttons**: Hierarki tegas antara Primary Action (Solid Green), Secondary Action (Outline/Muted), dan Destructive Action (Red Tone).
- **Modal Dialogs**: Radix-based dialog dengan header deskriptif, area scroll terbatas jika konten panjang, dan footer tombol aksi dengan konfirmasi yang jelas.

## Do's and Don'ts

### Do's
- Gunakan badge status dengan warna semantik yang konsisten di semua modul (Cuti, KP, KGB, IAM).
- Berikan state kosong (*Empty State*) yang informatif dan memiliki tautan aksi langsung.
- Gunakan skeleton loader saat data tabel atau rincian kartu sedang dimuat via Inertia.
- Selalu sediakan tombol konfirmasi modal sebelum aksi penolakan atau penghapusan berkas.

### Don'ts
- Jangan menggunakan efek gradien teks atau animasi mencolok yang mengaburkan data birokrasi.
- Jangan menyembunyikan pesan kesalahan validasi di luar viewport pengguna; tampilkan inline di dekat input terkait.
- Jangan gunakan kontras rendah (abu-abu pudar di atas putih) pada data teks penting seperti NIP, Tanggal TMT, dan Saldo Cuti.
