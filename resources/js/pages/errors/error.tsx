import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Home,
    Lock,
    RefreshCw,
    Search,
    ServerCrash,
    ShieldAlert,
    Wrench,
} from 'lucide-react';
import { motion } from 'motion/react';
import AppLogoIcon from '@/components/app-logo-icon';
import JudicialAuthBackground from '@/components/auth/judicial-background';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

interface ErrorPageProps {
    status: number;
    message?: string;
}

export default function ErrorPage({ status, message }: ErrorPageProps) {
    const errorConfigs: Record<
        number,
        {
            title: string;
            description: string;
            icon: typeof AlertTriangle;
            accentColor: string;
            badgeText: string;
            suggestedAction: string;
        }
    > = {
        403: {
            title: 'Akses Dibatasi',
            description:
                message ||
                'Anda tidak memiliki wewenang atau hak akses untuk membuka halaman atau data ini. Pastikan Anda memiliki peran (role) yang sesuai.',
            icon: Lock,
            accentColor: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
            badgeText: 'Kode 403 • Terlarang',
            suggestedAction: 'Hubungi administrator kepegawaian jika Anda memerlukan akses tambahan.',
        },
        404: {
            title: 'Halaman Tidak Ditemukan',
            description:
                message ||
                'Alamat halaman yang Anda tuju tidak ditemukan atau mungkin telah dipindahkan ke tautan lain.',
            icon: Search,
            accentColor: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
            badgeText: 'Kode 404 • Not Found',
            suggestedAction: 'Periksa kembali URL atau kembali ke dasbor utama SIMPEG.',
        },
        419: {
            title: 'Sesi Telah Kedaluwarsa',
            description:
                message ||
                'Sesi keamanan Anda telah berakhir karena tidak ada aktivitas dalam waktu tertentu atau token CSRF telah diperbarui.',
            icon: Clock,
            accentColor: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
            badgeText: 'Kode 419 • Sesi Kedaluwarsa',
            suggestedAction: 'Muat ulang halaman untuk memperbarui sesi login Anda.',
        },
        429: {
            title: 'Terlalu Banyak Permintaan',
            description:
                message ||
                'Sistem mendeteksi terlalu banyak permintaan dalam waktu singkat. Mohon beri jeda beberapa saat sebelum mencoba kembali.',
            icon: ShieldAlert,
            accentColor: 'text-orange-500 bg-orange-500/10 border-orange-500/20',
            badgeText: 'Kode 429 • Rate Limited',
            suggestedAction: 'Tunggu 10–30 detik lalu muat ulang halaman.',
        },
        500: {
            title: 'Kendala Server Internal',
            description:
                message ||
                'Terjadi kendala teknis pada peladen sistem. Tim pengelola IT & Kepegawaian telah menerima catatan log untuk penanganan segera.',
            icon: ServerCrash,
            accentColor: 'text-destructive bg-destructive/10 border-destructive/20',
            badgeText: 'Kode 500 • Kendala Server',
            suggestedAction: 'Silakan coba beberapa saat lagi atau laporkan ke helpdesk Kepegawaian & Ortala.',
        },
        503: {
            title: 'Layanan Dalam Pemeliharaan',
            description:
                message ||
                'Sistem SIMPEG sedang dalam pemeliharaan berkala atau peningkatan performa layanan. Kami akan segera kembali.',
            icon: Wrench,
            accentColor: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
            badgeText: 'Kode 503 • Pemeliharaan',
            suggestedAction: 'Silakan cek kembali secara berkala dalam beberapa menit.',
        },
    };

    const config = errorConfigs[status] || {
        title: 'Terjadi Kendala',
        description: message || 'Terjadi kesalahan yang tidak terduga pada aplikasi.',
        icon: AlertTriangle,
        accentColor: 'text-destructive bg-destructive/10 border-destructive/20',
        badgeText: `Kode ${status || 'Error'}`,
        suggestedAction: 'Silakan muat ulang halaman atau kembali ke beranda.',
    };

    const IconComponent = config.icon;

    return (
        <div className="relative flex min-h-svh w-full flex-col justify-between overflow-x-hidden px-4 py-8 sm:px-6 md:px-8">
            <Head title={`${status} - ${config.title}`} />

            {/* Latar Belakang Kanvas Deep Forest Green */}
            <JudicialAuthBackground />

            {/* Top spacing bar */}
            <div className="relative z-10 h-4 w-full" aria-hidden="true" />

            {/* Main card center container */}
            <main className="relative z-10 my-auto flex w-full justify-center">
                <motion.div
                    initial={{ opacity: 0, y: 16, scale: 0.98 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
                    className="w-full max-w-[480px]"
                >
                    <div className="flex flex-col gap-6 rounded-2xl border border-white/20 bg-card p-6 shadow-2xl backdrop-blur-md sm:p-8 md:p-9 dark:border-white/10">
                        {/* Header Lambang & Status Badge */}
                        <div className="flex flex-col items-center gap-4 text-center">
                            <Link
                                href={home()}
                                className="group flex flex-col items-center gap-2 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <AppLogoIcon className="size-14 sm:size-16" />
                                <span className="sr-only">Beranda SIMPEG PA Penajam</span>
                            </Link>

                            <div className="flex items-center gap-2">
                                <span
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold tracking-wide ${config.accentColor}`}
                                >
                                    <IconComponent className="size-3.5" />
                                    {config.badgeText}
                                </span>
                            </div>

                            <div className="space-y-1.5">
                                <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                    {config.title}
                                </h1>
                                <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                    {config.description}
                                </p>
                            </div>
                        </div>

                        {/* Kotak Petunjuk Aksi */}
                        <div className="rounded-xl border border-border/80 bg-muted/40 p-3.5 text-xs text-muted-foreground">
                            <p className="font-medium text-foreground">Saran Tindakan:</p>
                            <p className="mt-0.5 leading-relaxed">{config.suggestedAction}</p>
                        </div>

                        {/* Tombol Aksi Pemulihan */}
                        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                            {status === 419 || status === 503 || status === 429 ? (
                                <Button
                                    variant="default"
                                    className="w-full gap-2 font-medium"
                                    onClick={() => window.location.reload()}
                                >
                                    <RefreshCw className="size-4" />
                                    Muat Ulang Halaman
                                </Button>
                            ) : (
                                <Button
                                    variant="default"
                                    className="w-full gap-2 font-medium"
                                    asChild
                                >
                                    <Link href="/dashboard">
                                        <Home className="size-4" />
                                        Buka Dasbor
                                    </Link>
                                </Button>
                            )}

                            <Button
                                variant="outline"
                                className="w-full gap-2 font-medium"
                                onClick={() => {
                                    if (window.history.length > 1) {
                                        window.history.back();
                                    } else {
                                        window.location.href = '/';
                                    }
                                }}
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                        </div>

                        {/* Catatan Helpdesk */}
                        <div className="border-t border-border pt-4 text-center text-xs text-muted-foreground">
                            Kendala berlanjut? Hubungi{' '}
                            <span className="font-medium text-foreground">
                                Kepegawaian & Ortala PA Penajam
                            </span>
                        </div>
                    </div>
                </motion.div>
            </main>

            {/* Footer Institusional */}
            <footer className="relative z-10 mt-8 text-center text-xs font-medium text-emerald-200/70">
                <p>© {new Date().getFullYear()} Pengadilan Agama Penajam • Mahkamah Agung RI</p>
            </footer>
        </div>
    );
}
