import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh w-full flex-col justify-between bg-background px-4 py-8 sm:px-6 md:px-8">
            <div className="h-4 w-full" aria-hidden="true" />

            <main className="my-auto flex w-full justify-center">
                <div className="w-full max-w-[420px]">
                    <div className="flex flex-col gap-6 rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8 md:p-9">
                        <div className="flex flex-col items-center gap-3.5 text-center">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 rounded-lg font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <div className="flex items-center justify-center">
                                    <AppLogoIcon className="size-14 sm:size-16" />
                                </div>
                                <span className="sr-only">
                                    Kembali ke Beranda SIMPEG PA Penajam
                                </span>
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
                </div>
            </main>

            <footer className="mt-8 text-center text-xs font-medium text-muted-foreground">
                <p>
                    © {new Date().getFullYear()} Pengadilan Agama Penajam •
                    Mahkamah Agung RI
                </p>
            </footer>
        </div>
    );
}
