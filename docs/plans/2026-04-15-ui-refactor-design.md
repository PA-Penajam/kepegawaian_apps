# Design: UI/UX Refactoring — Tema Hijau Tua + Gold + Orange

**Tanggal**: 2026-04-15
**Status**: Approved
**Pendekatan**: Token-First (CSS Variables → auto-propagate ke semua komponen)

## Konteks

Aplikasi kepegawaian ini digunakan oleh **instansi pemerintah (ASN/PNS)**. Tema saat ini
monokrom netral (oklch chroma 0 — tanpa warna). Welcome page masih hardcoded `blue-600`.
Belum ada animasi atau visual effects.

## Tujuan

- Menerapkan identitas warna **Hijau Tua (Forest Green)** sebagai primary, **Gold/Amber**
  sebagai accent, dan **Orange** sebagai highlight.
- Menambahkan animasi dari **Magic UI** untuk meningkatkan UX.
- Light mode saja (dark mode di-disable sementara).

## Palet Warna (oklch)

### Light Mode (`:root`)

| Token              | Oklch                    | Kegunaan                        |
|--------------------|--------------------------|---------------------------------|
| `--primary`        | `oklch(0.32 0.10 155)`  | Tombol utama, link, active      |
| `--primary-fg`     | `oklch(0.98 0 0)`       | Teks di atas primary            |
| `--secondary`      | `oklch(0.95 0.02 155)`  | Background alternatif           |
| `--secondary-fg`   | `oklch(0.25 0.05 60)`   | Teks di atas secondary          |
| `--accent`         | `oklch(0.72 0.16 75)`   | Gold — highlight, CTA sekunder  |
| `--accent-fg`      | `oklch(0.25 0.05 60)`   | Teks di atas gold               |
| `--muted`          | `oklch(0.96 0.01 155)`  | Background cards                |
| `--muted-fg`       | `oklch(0.50 0.02 155)`  | Teks secondary                  |
| `--destructive`    | `oklch(0.577 0.245 27)` | Error (tetap merah)             |
| `--background`     | `oklch(0.99 0 0)`       | Background utama                |
| `--foreground`     | `oklch(0.15 0.02 155)`  | Teks utama                      |
| `--card`           | `oklch(1 0 0)`          | Card background                 |
| `--card-fg`        | `oklch(0.15 0.02 155)`  | Card teks                       |
| `--border`         | `oklch(0.90 0.02 155)`  | Border                          |
| `--input`          | `oklch(0.90 0.02 155)`  | Input border                    |
| `--ring`           | `oklch(0.50 0.10 155)`  | Focus ring                      |
| `--sidebar`        | `oklch(0.18 0.06 155)`  | Sidebar background gelap        |
| `--sidebar-primary`| `oklch(0.78 0.15 80)`   | Logo area, highlight sidebar    |
| `--chart-1`        | `oklch(0.45 0.12 155)`  | Hijau tua                       |
| `--chart-2`        | `oklch(0.72 0.16 75)`   | Gold                            |
| `--chart-3`        | `oklch(0.65 0.18 55)`   | Orange                          |
| `--chart-4`        | `oklch(0.50 0.10 195)`  | Teal                            |
| `--chart-5`        | `oklch(0.55 0.10 40)`   | Coklat                          |

### Custom Variables (tambahan)

```css
--gold: oklch(0.72 0.16 75);
--orange: oklch(0.65 0.18 55);
--green-dark: oklch(0.18 0.06 155);
```

## Hierarki Warna

- **Hijau Tua**: Primary actions, sidebar active state, brand identity
- **Gold**: Accent highlights, sidebar logo, important badges, secondary CTA
- **Orange**: Warning/urgency, KGB segera badge, highlight alerts
- **Merah**: Error/destructive (tidak berubah)

## Magic UI Components

### Yang Digunakan

| Komponen          | Area                | Fungsi                                    |
|-------------------|---------------------|-------------------------------------------|
| `shimmer-button`  | Welcome page CTA    | Tombol berkilau hijau-gold                |
| `text-shimmer`    | Welcome hero title  | Judul berkilau halus                      |
| `blur-in`         | Auth pages, welcome | Elemen muncul dengan blur → sharp         |
| `fade-in`         | Dashboard cards     | Fade-in saat scroll                       |
| `animated-number` | Dashboard stats     | Counter naik dari 0 → nilai               |
| `border-beam`     | KGB/KP cards        | Cahaya bergerak di border card urgent     |
| `shine-border`    | Important cards     | Border berkilau halus                     |
| `particles`       | Welcome background  | Partikel halus hijau/gold                 |
| `marquee`         | (opsional) welcome  | Text running announcements                |

### Penempatan Animasi

**Welcome Page**:
- Background: particles (hijau + gold, subtle)
- Hero title: blur-in + text-shimmer
- CTA button: shimmer-button
- Feature cards: scroll-triggered fade-in (staggered)

**Dashboard**:
- Stat numbers: animated-counter (0 → value)
- KGB/KP cards: border-beam effect
- Progress bars: animated width (0 → %)
- Badge "Perlu Perhatian": orange pulse

**Auth Pages**:
- Logo + title: blur-in (delay 0ms)
- Form fields: blur-in (delay 200ms)
- Button: blur-in (delay 400ms)

## Scope File yang Diubah

### Tema (1 file)
- `resources/css/app.css` — Ganti seluruh CSS variables ke palet baru, hapus `.dark`

### Landing (1 file, rewrite)
- `resources/js/pages/welcome.tsx` — Hapus hardcoded blue-600, tambah animasi Magic UI

### Auth Layouts (2-3 file)
- `resources/js/layouts/auth/auth-simple-layout.tsx` — Gradient background hijau halus
- `resources/js/layouts/auth/auth-split-layout.tsx` — Panel kiri gradient hijau gelap
- `resources/js/layouts/auth/auth-card-layout.tsx` (jika ada) — Card border gold

### Auth Pages (5-6 file, minor)
- `resources/js/pages/auth/login.tsx` — Wrapper animasi blur-in
- `resources/js/pages/auth/register.tsx` — Sama
- `resources/js/pages/auth/forgot-password.tsx` — Sama
- `resources/js/pages/auth/reset-password.tsx` — Sama
- `resources/js/pages/auth/verify-email.tsx` — Sama
- `resources/js/pages/auth/two-factor-challenge.tsx` — Sama

### Dashboard (1 file, significant)
- `resources/js/pages/dashboard.tsx` — Animated counters, border-beam, icon bg berwarna

### Sidebar & Navigasi (2 file)
- `resources/js/components/app-sidebar.tsx` — Sidebar gelap hijau, gold highlight
- `resources/js/components/app-logo.tsx` — Warna ikon hijau tua

### Hooks Baru (1 file)
- `resources/js/hooks/use-stagger-animation.ts` — Hook reusable untuk staggered animation

### Magic UI Components (via CLI)
- Install magicui package
- Add: shimmer-button, blur-in, animated-number, border-beam, particles, text-shimmer, fade-in

## Prinsip Desain

1. **Formal tapi modern** — Instansi pemerintah, bukan startup. Animasi halus, bukan flashy.
2. **Konsistensi via token** — Semua warna melalui CSS variables, tidak ada hardcoded color.
3. **Aksesibilitas** — Kontras warna memenuhi WCAG AA (minimum 4.5:1 ratio).
4. **Performa** — Animasi hanya di viewport (lazy), prefers-reduced-motion dihormati.
5. **YAGNI** — Tidak menambah fitur baru, hanya visual refresh.

## Dependensi Baru

- `magicui` (via CLI, menginstall component files ke `components/magicui/`)
- `framer-motion` (dependency dari Magic UI, untuk animasi)
