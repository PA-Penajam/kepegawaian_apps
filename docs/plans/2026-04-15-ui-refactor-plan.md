# UI/UX Refactoring — Tema Hijau Tua + Gold + Orange

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Menerapkan tema hijau tua (forest green), gold, dan orange ke seluruh aplikasi kepegawaian dengan animasi Magic UI.

**Architecture:** Token-first approach — ubah CSS variables di `app.css`, lalu komponen shadcn/ui otomatis mengikuti. Magic UI components ditambahkan di atas untuk animasi. Semua warna hardcoded diganti dengan CSS variables.

**Tech Stack:** Tailwind CSS 4 (oklch color space), shadcn/ui (new-york), Magic UI, motion (framer-motion)

---

## Task 1: Install motion dependency

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`

**Step 1: Install motion package**

```bash
npm install motion
```

**Step 2: Verify installation**

```bash
npm ls motion
```

Expected: `motion@X.X.X` terlihat di dependencies

**Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install motion dependency for Magic UI animations"
```

---

## Task 2: Install Magic UI components

**Files:**
- Create: `resources/js/components/ui/shimmer-button.tsx`
- Create: `resources/js/components/ui/number-ticker.tsx`
- Create: `resources/js/components/ui/blur-fade.tsx`
- Create: `resources/js/components/ui/border-beam.tsx`
- Create: `resources/js/components/ui/particles.tsx`

**Step 1: Install shimmer-button**

```bash
npx shadcn@latest add "https://magicui.design/r/shimmer-button.json" --yes
```

**Step 2: Install number-ticker**

```bash
npx shadcn@latest add "https://magicui.design/r/number-ticker.json" --yes
```

**Step 3: Install blur-fade**

```bash
npx shadcn@latest add "https://magicui.design/r/blur-fade.json" --yes
```

**Step 4: Install border-beam**

```bash
npx shadcn@latest add "https://magicui.design/r/border-beam.json" --yes
```

**Step 5: Install particles**

```bash
npx shadcn@latest add "https://magicui.design/r/particles.json" --yes
```

**Step 6: Verify files exist**

```bash
ls resources/js/components/ui/shimmer-button.tsx resources/js/components/ui/number-ticker.tsx resources/js/components/ui/blur-fade.tsx resources/js/components/ui/border-beam.tsx resources/js/components/ui/particles.tsx
```

Expected: semua 5 file terlihat

**Step 7: Run type check**

```bash
npm run types:check
```

Expected: PASS (tidak ada error type baru)

**Step 8: Commit**

```bash
git add resources/js/components/ui/shimmer-button.tsx resources/js/components/ui/number-ticker.tsx resources/js/components/ui/blur-fade.tsx resources/js/components/ui/border-beam.tsx resources/js/components/ui/particles.tsx
git commit -m "feat: add Magic UI components — shimmer-button, number-ticker, blur-fade, border-beam, particles"
```

---

## Task 3: Update CSS theme variables

**Files:**
- Modify: `resources/css/app.css` — seluruh blok `:root` dan `.dark`

**Step 1: Replace `:root` CSS variables**

Ganti seluruh blok `:root { ... }` di `resources/css/app.css` dengan:

```css
:root {
    --background: oklch(0.99 0.002 155);
    --foreground: oklch(0.15 0.02 155);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.15 0.02 155);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.15 0.02 155);
    --primary: oklch(0.32 0.10 155);
    --primary-foreground: oklch(0.98 0 0);
    --secondary: oklch(0.95 0.02 155);
    --secondary-foreground: oklch(0.25 0.05 60);
    --muted: oklch(0.96 0.01 155);
    --muted-foreground: oklch(0.50 0.02 155);
    --accent: oklch(0.72 0.16 75);
    --accent-foreground: oklch(0.25 0.05 60);
    --destructive: oklch(0.577 0.245 27.325);
    --destructive-foreground: oklch(0.98 0 0);
    --border: oklch(0.90 0.02 155);
    --input: oklch(0.90 0.02 155);
    --ring: oklch(0.50 0.10 155);
    --chart-1: oklch(0.45 0.12 155);
    --chart-2: oklch(0.72 0.16 75);
    --chart-3: oklch(0.65 0.18 55);
    --chart-4: oklch(0.50 0.10 195);
    --chart-5: oklch(0.55 0.10 40);
    --radius: 0.625rem;
    --sidebar: oklch(0.18 0.06 155);
    --sidebar-foreground: oklch(0.92 0.02 155);
    --sidebar-primary: oklch(0.78 0.15 80);
    --sidebar-primary-foreground: oklch(0.18 0.06 155);
    --sidebar-accent: oklch(0.25 0.07 155);
    --sidebar-accent-foreground: oklch(0.92 0.02 155);
    --sidebar-border: oklch(0.28 0.06 155);
    --sidebar-ring: oklch(0.50 0.10 155);
    --gold: oklch(0.72 0.16 75);
    --orange: oklch(0.65 0.18 55);
    --green-dark: oklch(0.18 0.06 155);
}
```

**Step 2: Hapus seluruh blok `.dark { ... }`**

Karena kita hanya pakai light mode, hapus seluruh blok `.dark { ... }` dari `app.css`.

**Step 3: Tambahkan CSS variables ke `@theme` block**

Tambahkan baris berikut di dalam blok `@theme { ... }` yang sudah ada:

```css
    --color-gold: var(--gold);
    --color-orange: var(--orange);
    --color-green-dark: var(--green-dark);
```

**Step 4: Tambahkan custom shimmer animation keyframes**

Tambahkan setelah blok `@layer base { ... }`:

```css
@keyframes shimmer-slide {
    0% { translate: -100% 0; }
    100% { translate: 100% 0; }
}

@keyframes spin-around {
    0% { rotate: 0deg; }
    100% { rotate: 360deg; }
}
```

**Step 5: Verifikasi dev server**

```bash
npm run dev
```

Buka browser dan verifikasi:
- Sidebar sekarang berwarna hijau gelap
- Tombol primary berwarna hijau tua
- Gold terlihat di sidebar logo area
- Background tetap terang/white

**Step 6: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: apply hijau-gold-orange color theme to CSS variables"
```

---

## Task 4: Update welcome/landing page

**Files:**
- Modify: `resources/js/pages/welcome.tsx` — rewrite total

**Step 1: Rewrite welcome.tsx**

Ganti seluruh isi `resources/js/pages/welcome.tsx` dengan:

```tsx
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Users,
    TrendingUp,
    Calendar,
    GraduationCap,
    Building2,
} from 'lucide-react';
import { ShimmerButton } from '@/components/ui/shimmer-button';
import { BlurFade } from '@/components/ui/blur-fade';
import { Particles } from '@/components/ui/particles';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Sistem Informasi Kepegawaian" />
            <div className="relative flex min-h-screen flex-col bg-background text-foreground">
                {/* Particles Background */}
                <Particles
                    className="fixed inset-0 z-0"
                    quantity={60}
                    color="#1B5E20"
                    size={0.5}
                    ease={60}
                    staticity={80}
                />

                {/* Header */}
                <header className="relative z-10 w-full border-b border-border/50 bg-background/80 px-6 py-4 backdrop-blur-md">
                    <div className="mx-auto flex max-w-7xl items-center justify-between">
                        <div className="flex items-center gap-2 text-xl font-bold text-primary">
                            <Building2 className="h-6 w-6" />
                            <span>Kepegawaian</span>
                        </div>
                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Masuk
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="relative z-10 flex-1">
                    <section className="relative overflow-hidden px-6 py-24 sm:py-32 lg:px-8">
                        <div className="mx-auto max-w-2xl text-center">
                            <BlurFade delay={0.1} duration={0.6}>
                                <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-6xl">
                                    Sistem Informasi{' '}
                                    <span className="text-primary">
                                        Kepegawaian
                                    </span>
                                </h1>
                            </BlurFade>
                            <BlurFade delay={0.3} duration={0.6}>
                                <p className="mt-6 text-lg leading-8 text-muted-foreground">
                                    Solusi terpadu untuk manajemen data
                                    pegawai, monitoring kenaikan pangkat, dan
                                    pelacakan riwayat karir secara efisien dan
                                    akurat.
                                </p>
                            </BlurFade>
                            <BlurFade delay={0.5} duration={0.6}>
                                <div className="mt-10 flex items-center justify-center gap-x-6">
                                    {auth.user ? (
                                        <ShimmerButton
                                            shimmerColor="#C8A415"
                                            background="oklch(0.32 0.10 155)"
                                            borderRadius="8px"
                                            onClick={() =>
                                                (window.location.href =
                                                    dashboard())
                                            }
                                            className="px-6 py-3"
                                        >
                                            Buka Dashboard
                                        </ShimmerButton>
                                    ) : (
                                        <ShimmerButton
                                            shimmerColor="#C8A415"
                                            background="oklch(0.32 0.10 155)"
                                            borderRadius="8px"
                                            onClick={() =>
                                                (window.location.href =
                                                    login())
                                            }
                                            className="px-6 py-3"
                                        >
                                            Mulai Sekarang
                                        </ShimmerButton>
                                    )}
                                </div>
                            </BlurFade>
                        </div>
                    </section>

                    {/* Features Section */}
                    <section className="mx-auto max-w-7xl px-6 py-16 sm:py-24 lg:px-8">
                        <BlurFade delay={0.2} duration={0.5}>
                            <div className="mx-auto max-w-2xl lg:text-center">
                                <p className="text-base leading-7 font-semibold text-accent">
                                    Fitur Utama
                                </p>
                                <h2 className="mt-2 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                    Kelola SDM dengan Lebih Baik
                                </h2>
                            </div>
                        </BlurFade>
                        <div className="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                            <dl className="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-4">
                                {[
                                    {
                                        icon: Users,
                                        title: 'Manajemen Data Pegawai',
                                        desc: 'Penyimpanan dan pengelolaan data profil pegawai, keluarga, dan dokumen kepegawaian secara terpusat.',
                                        color: 'bg-primary',
                                    },
                                    {
                                        icon: TrendingUp,
                                        title: 'Monitoring Kenaikan Pangkat',
                                        desc: 'Sistem peringatan dini dan pelacakan untuk pegawai yang memenuhi syarat kenaikan pangkat.',
                                        color: 'bg-accent',
                                    },
                                    {
                                        icon: Calendar,
                                        title: 'Tracking Kenaikan Gaji Berkala',
                                        desc: 'Otomatisasi pemantauan jadwal Kenaikan Gaji Berkala (KGB) berdasarkan masa kerja golongan.',
                                        color: 'bg-orange',
                                    },
                                    {
                                        icon: GraduationCap,
                                        title: 'Riwayat Pendidikan & Diklat',
                                        desc: 'Pencatatan komprehensif riwayat pendidikan formal dan pelatihan/diklat yang pernah diikuti pegawai.',
                                        color: 'bg-primary/80',
                                    },
                                ].map((feature, idx) => (
                                    <BlurFade
                                        key={feature.title}
                                        delay={0.3 + idx * 0.15}
                                        duration={0.5}
                                    >
                                        <div className="flex flex-col">
                                            <dt className="flex items-center gap-x-3 text-base leading-7 font-semibold text-foreground">
                                                <div
                                                    className={`flex h-10 w-10 items-center justify-center rounded-lg ${feature.color}`}
                                                >
                                                    <feature.icon
                                                        className="h-6 w-6 text-white"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                                {feature.title}
                                            </dt>
                                            <dd className="mt-4 flex flex-auto flex-col text-base leading-7 text-muted-foreground">
                                                <p className="flex-auto">
                                                    {feature.desc}
                                                </p>
                                            </dd>
                                        </div>
                                    </BlurFade>
                                ))}
                            </dl>
                        </div>
                    </section>
                </main>

                {/* Footer */}
                <footer className="relative z-10 border-t border-border/50 bg-background/80 py-8 backdrop-blur-md">
                    <div className="mx-auto max-w-7xl px-6 lg:px-8">
                        <p className="text-center text-sm leading-5 text-muted-foreground">
                            &copy; {new Date().getFullYear()} Sistem Informasi
                            Kepegawaian. All rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
```

**Step 2: Run type check**

```bash
npm run types:check
```

Expected: PASS

**Step 3: Run lint + format**

```bash
npm run lint && npm run format
```

**Step 4: Verifikasi visual**

Buka welcome page di browser:
- Particles hijau halus bergerak di background
- Hero text muncul dengan animasi blur-in
- Shimmer button berkilau dengan gold shimmer
- Feature cards muncul staggered (satu per satu)
- Semua hardcoded blue-600 sudah hilang

**Step 5: Commit**

```bash
git add resources/js/pages/welcome.tsx
git commit -m "feat: redesign welcome page with Magic UI animations and hijau-gold theme"
```

---

## Task 5: Update auth layouts

**Files:**
- Modify: `resources/js/layouts/auth/auth-simple-layout.tsx`
- Modify: `resources/js/layouts/auth/auth-card-layout.tsx`

**Step 1: Update auth-simple-layout.tsx**

Ganti isi `resources/js/layouts/auth/auth-simple-layout.tsx` dengan:

```tsx
import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { BlurFade } from '@/components/ui/blur-fade';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-background via-muted to-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <BlurFade delay={0.1} duration={0.4}>
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-5 fill-current text-white" />
                                </div>
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-medium">
                                    {title}
                                </h1>
                                <p className="text-center text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                    </BlurFade>
                    <BlurFade delay={0.25} duration={0.4}>
                        {children}
                    </BlurFade>
                </div>
            </div>
        </div>
    );
}
```

**Step 2: Update auth-card-layout.tsx**

Ganti isi `resources/js/layouts/auth/auth-card-layout.tsx` dengan:

```tsx
import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { BlurFade } from '@/components/ui/blur-fade';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-background via-muted to-background p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <BlurFade delay={0.1} duration={0.4}>
                    <Link
                        href={home()}
                        className="flex items-center gap-2 self-center font-medium"
                    >
                        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </div>
                    </Link>
                </BlurFade>

                <BlurFade delay={0.25} duration={0.4}>
                    <Card className="rounded-xl">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-xl">{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">
                            {children}
                        </CardContent>
                    </Card>
                </BlurFade>
            </div>
        </div>
    );
}
```

**Step 3: Run type check**

```bash
npm run types:check
```

**Step 4: Run lint + format**

```bash
npm run lint && npm run format
```

**Step 5: Verifikasi visual**

Buka halaman login:
- Background gradient hijau halus
- Logo muncul dengan animasi blur-in
- Form muncul setelah logo (staggered)
- Logo icon berwarna hijau (bukan hitam)

**Step 6: Commit**

```bash
git add resources/js/layouts/auth/auth-simple-layout.tsx resources/js/layouts/auth/auth-card-layout.tsx
git commit -m "feat: update auth layouts with blur-in animation and gradient background"
```

---

## Task 6: Update auth pages (login, register, dll)

**Files:**
- Modify: `resources/js/pages/auth/login.tsx`
- Modify: `resources/js/pages/auth/register.tsx` (jika ada)
- Modify: `resources/js/pages/auth/forgot-password.tsx`
- Modify: `resources/js/pages/auth/reset-password.tsx`
- Modify: `resources/js/pages/auth/verify-email.tsx`
- Modify: `resources/js/pages/auth/two-factor-challenge.tsx`

**Step 1: Update login.tsx**

Di `resources/js/pages/auth/login.tsx`, ubah:
- Ganti `text-green-600` pada status message menjadi `text-primary`
- Tombol sudah pakai `Button` component yang otomatis mengikuti tema

Cari baris:
```tsx
<div className="mb-4 text-center text-sm font-medium text-green-600">
```

Ganti dengan:
```tsx
<div className="mb-4 text-center text-sm font-medium text-primary">
```

**Step 2: Update semua auth pages lainnya**

Untuk setiap auth page (`forgot-password.tsx`, `reset-password.tsx`, `verify-email.tsx`, `two-factor-challenge.tsx`):
- Cari hardcoded color classes (seperti `text-green-600`, `text-blue-600`, dll)
- Ganti dengan `text-primary` atau `text-accent`

Gunakan grep untuk mencari:
```bash
grep -n "text-green-\|text-blue-\|bg-blue-\|bg-green-" resources/js/pages/auth/*.tsx
```

**Step 3: Run type check + lint + format**

```bash
npm run types:check && npm run lint && npm run format
```

**Step 4: Commit**

```bash
git add resources/js/pages/auth/
git commit -m "feat: update auth pages with theme-aware color classes"
```

---

## Task 7: Update sidebar dan logo

**Files:**
- Modify: `resources/js/components/app-logo.tsx`

**Step 1: Update app-logo.tsx**

Ganti isi `resources/js/components/app-logo.tsx` dengan:

```tsx
import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold text-sidebar-primary">
                    Kepegawaian
                </span>
            </div>
        </>
    );
}
```

**Step 2: Run type check + lint + format**

```bash
npm run types:check && npm run lint && npm run format
```

**Step 3: Verifikasi visual**

- Sidebar background hijau gelap
- Logo icon berlatar gold
- Teks "Kepegawaian" berwarna gold
- Active menu item berlatar lebih gelap dengan teks gold

**Step 4: Commit**

```bash
git add resources/js/components/app-logo.tsx
git commit -m "feat: update app logo with gold accent on dark green sidebar"
```

---

## Task 8: Update dashboard dengan animasi

**Files:**
- Modify: `resources/js/pages/dashboard.tsx` — significant changes

**Step 1: Tambahkan import Magic UI di dashboard.tsx**

Tambahkan import berikut di bagian atas:

```tsx
import { NumberTicker } from '@/components/ui/number-ticker';
import { BorderBeam } from '@/components/ui/border-beam';
import { BlurFade } from '@/components/ui/blur-fade';
```

**Step 2: Update stat cards — ganti angka statis dengan NumberTicker**

Untuk setiap card, ganti bagian angka:

Card "Total Pegawai Aktif" — ganti:
```tsx
<div className="text-2xl font-bold">
    {stats.total_pegawai_aktif}
</div>
```
dengan:
```tsx
<div className="text-2xl font-bold">
    <NumberTicker value={stats.total_pegawai_aktif} />
</div>
```

Card "Pegawai Baru" — sama:
```tsx
<div className="text-2xl font-bold">
    <NumberTicker value={stats.pegawai_baru_bulan_ini} />
</div>
```

Card "KGB Segera" — sama + tambah BorderBeam:
```tsx
<div className="text-2xl font-bold">
    <NumberTicker value={stats.kgb_segera_count} />
</div>
```

Tambahkan `<BorderBeam />` di dalam Card:
```tsx
<Card className="relative overflow-hidden">
    {/* ... isi card ... */}
    <BorderBeam
        size={40}
        duration={4}
        colorFrom="oklch(0.65 0.18 55)"
        colorTo="oklch(0.72 0.16 75)"
    />
</Card>
```

Card "KP Eligible" — sama + BorderBeam dengan warna berbeda:
```tsx
<Card className="relative overflow-hidden">
    {/* ... isi card ... */}
    <BorderBeam
        size={40}
        duration={5}
        colorFrom="oklch(0.45 0.12 155)"
        colorTo="oklch(0.72 0.16 75)"
    />
</Card>
```

**Step 3: Update icon background colors**

Untuk setiap stat card icon, tambahkan background berwarna:

Card "Total Pegawai Aktif":
```tsx
<Users className="h-4 w-4 text-primary" />
```

Card "Pegawai Baru":
```tsx
<UserPlus className="h-4 w-4 text-primary" />
```

Card "KGB Segera":
```tsx
<AlertCircle className="h-4 w-4 text-orange" />
```

Card "KP Eligible":
```tsx
<TrendingUp className="h-4 w-4 text-accent" />
```

**Step 4: Update badge "Eligible"**

Ganti hardcoded `bg-blue-600`:
```tsx
<Badge variant="default" className="bg-blue-600 hover:bg-blue-700">
```
dengan:
```tsx
<Badge variant="default" className="bg-accent text-accent-foreground hover:bg-accent/80">
```

**Step 5: Wrap distribusi cards dengan BlurFade staggered**

Untuk setiap card di baris distribusi kedua, wrap dengan:

```tsx
<BlurFade delay={0.3 + idx * 0.1} duration={0.4} inView>
    <Card>...</Card>
</BlurFade>
```

**Step 6: Run type check + lint + format**

```bash
npm run types:check && npm run lint && npm run format
```

**Step 7: Verifikasi visual**

- Angka statistik naik dari 0 (animated counter)
- Card KGB dan KP punya border beam bergerak (orange→gold dan hijau→gold)
- Icon stats berwarna sesuai tema
- Card distribusi muncul satu per satu saat scroll

**Step 8: Commit**

```bash
git add resources/js/pages/dashboard.tsx
git commit -m "feat: add animated counters, border beams, and staggered animations to dashboard"
```

---

## Task 9: Update halaman kepegawaian (badge colors)

**Files:**
- Cari hardcoded colors di semua halaman kepegawaian

**Step 1: Grep untuk hardcoded colors**

```bash
grep -rn "bg-blue-\|text-blue-\|bg-green-\|text-green-" resources/js/pages/kepegawaian/ resources/js/components/kepegawaian/
```

**Step 2: Ganti hardcoded colors**

Untuk setiap file yang ditemukan:
- `bg-blue-*` → `bg-primary` atau `bg-accent`
- `text-blue-*` → `text-primary`
- `bg-green-*` → `bg-primary`
- `text-green-*` → `text-primary`

**Step 3: Run type check + lint + format**

```bash
npm run types:check && npm run lint && npm run format
```

**Step 4: Commit**

```bash
git add resources/js/pages/kepegawaian/ resources/js/components/kepegawaian/
git commit -m "feat: replace hardcoded colors with theme tokens in kepegawaian pages"
```

---

## Task 10: Update halaman IAM dan referensi

**Files:**
- Cari hardcoded colors di `resources/js/pages/iam/` dan `resources/js/pages/referensi/`

**Step 1: Grep untuk hardcoded colors**

```bash
grep -rn "bg-blue-\|text-blue-\|bg-green-\|text-green-" resources/js/pages/iam/ resources/js/pages/referensi/ resources/js/components/iam/
```

**Step 2: Ganti hardcoded colors** (sama seperti Task 9 Step 2)

**Step 3: Run type check + lint + format**

```bash
npm run types:check && npm run lint && npm run format
```

**Step 4: Commit**

```bash
git add resources/js/pages/iam/ resources/js/pages/referensi/ resources/js/components/iam/
git commit -m "feat: replace hardcoded colors with theme tokens in IAM and referensi pages"
```

---

## Task 11: Update halaman settings dan self-service

**Files:**
- Cari hardcoded colors di `resources/js/pages/settings/` dan `resources/js/pages/self-service/`

**Step 1: Grep untuk hardcoded colors**

```bash
grep -rn "bg-blue-\|text-blue-\|bg-green-\|text-green-" resources/js/pages/settings/ resources/js/pages/self-service/
```

**Step 2: Ganti hardcoded colors** (sama seperti Task 9 Step 2)

**Step 3: Run type check + lint + format**

```bash
npm run types:check && npm run lint && npm run format
```

**Step 4: Commit**

```bash
git add resources/js/pages/settings/ resources/js/pages/self-service/
git commit -m "feat: replace hardcoded colors with theme tokens in settings and self-service"
```

---

## Task 12: Final verification

**Files:**
- Semua file yang diubah

**Step 1: Full type check**

```bash
npm run types:check
```

Expected: PASS, tidak ada error

**Step 2: Full lint + format check**

```bash
npm run lint && npm run format && npm run format:check
```

Expected: PASS

**Step 3: Full test suite**

```bash
php artisan test --compact
```

Expected: semua tests PASS

**Step 4: Visual smoke test**

Buka di browser dan verifikasi:
1. **Welcome page**: particles, shimmer button, blur-in animasi, warna hijau-gold-orange
2. **Login page**: gradient background, blur-in animasi, logo hijau
3. **Dashboard**: animated counters, border beams, progress bars berwarna
4. **Sidebar**: hijau gelap, logo gold, navigasi konsisten
5. **Halaman pegawai**: warna tema konsisten, tidak ada hardcoded blue
6. **Mobile responsive**: sidebar collapse, layout menyesuaikan

**Step 5: Final commit jika ada perbaikan**

```bash
git add -A
git commit -m "chore: final cleanup after UI theme refactoring"
```

---

## Ringkasan Task Order

| Task | Deskripsi | Est. File | Depends On |
|------|-----------|-----------|------------|
| 1 | Install motion dependency | 2 | — |
| 2 | Install Magic UI components | 5 | Task 1 |
| 3 | Update CSS theme variables | 1 | — |
| 4 | Rewrite welcome page | 1 | Task 2, 3 |
| 5 | Update auth layouts | 2 | Task 2, 3 |
| 6 | Update auth pages | 5-6 | Task 3 |
| 7 | Update sidebar & logo | 1 | Task 3 |
| 8 | Update dashboard | 1 | Task 2, 3 |
| 9 | Update kepegawaian pages | ~5 | Task 3 |
| 10 | Update IAM & referensi pages | ~5 | Task 3 |
| 11 | Update settings & self-service | ~3 | Task 3 |
| 12 | Final verification | all | Task 1-11 |

**Parallelizable**: Task 4-11 dapat berjalan paralel setelah Task 3 selesai (CSS variables).
