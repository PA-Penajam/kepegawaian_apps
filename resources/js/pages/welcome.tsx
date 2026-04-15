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
