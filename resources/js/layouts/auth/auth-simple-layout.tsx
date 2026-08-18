import { Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import AppLogoIcon from '@/components/app-logo-icon';
import JudicialAuthBackground from '@/components/auth/judicial-background';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const shouldReduceMotion = useReducedMotion();

    return (
        <div className="relative flex min-h-svh w-full flex-col justify-between overflow-x-hidden px-4 py-8 sm:px-6 md:px-8">
            {/* Latar Belakang Kanvas Deep Forest Green */}
            <JudicialAuthBackground />

            {/* Top spacing bar */}
            <div className="relative z-10 h-4 w-full" aria-hidden="true" />

            {/* Main card center container */}
            <main className="relative z-10 my-auto flex w-full justify-center">
                <motion.div
                    initial={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, y: 14, scale: 0.99 }}
                    animate={shouldReduceMotion ? { opacity: 1 } : { opacity: 1, y: 0, scale: 1 }}
                    transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
                    className="w-full max-w-[420px]"
                >
                    <div className="flex flex-col gap-6 rounded-2xl border border-white/20 bg-card p-6 shadow-2xl backdrop-blur-md transition-all sm:p-8 md:p-9 dark:border-white/10">
                        <div className="flex flex-col items-center gap-3.5 text-center">
                            <Link
                                href={home()}
                                className="group flex flex-col items-center gap-2 rounded-lg font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <motion.div
                                    whileHover={shouldReduceMotion ? undefined : { scale: 1.05 }}
                                    whileTap={shouldReduceMotion ? undefined : { scale: 0.95 }}
                                    transition={{ duration: 0.2 }}
                                    className="flex items-center justify-center"
                                >
                                    <AppLogoIcon className="size-14 sm:size-16" />
                                </motion.div>
                                <span className="sr-only">Kembali ke Beranda SIMPEG PA Penajam</span>
                            </Link>

                            <div className="space-y-1">
                                <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                    {title}
                                </h1>
                                <p className="text-xs text-muted-foreground sm:text-sm">
                                    {description}
                                </p>
                            </div>
                        </div>

                        {children}
                    </div>
                </motion.div>
            </main>

            {/* Footer institusional dengan kontras elegan di atas kanvas hijau */}
            <footer className="relative z-10 mt-8 text-center text-xs font-medium text-emerald-200/70">
                <p>© {new Date().getFullYear()} Pengadilan Agama Penajam • Mahkamah Agung RI</p>
            </footer>
        </div>
    );
}