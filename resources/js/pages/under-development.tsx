import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, Construction, LayoutGrid } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { Auth, BreadcrumbItem } from '@/types';

type UnderDevelopmentPageProps = {
    moduleKey: string;
    moduleTitle: string;
    moduleDescription: string;
};

export default function UnderDevelopmentPage({
    moduleKey,
    moduleTitle,
    moduleDescription,
}: UnderDevelopmentPageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth.user?.permissions ?? [];
    const canOpenCuti =
        permissions.includes('cuti.pengajuan.view-own') ||
        permissions.includes('cuti.saldo.view-own');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: moduleTitle,
            href: '#',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${moduleTitle} — Dalam Pengembangan`} />

            <div className="flex flex-1 items-center justify-center p-4 sm:p-6 md:p-8">
                <div
                    className="w-full max-w-xl rounded-xl border border-border bg-card p-6 shadow-xs sm:p-8"
                    data-module-key={moduleKey}
                >
                    <div className="flex flex-col items-center gap-4 text-center">
                        <div className="flex size-14 items-center justify-center rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400">
                            <Construction
                                className="size-7"
                                aria-hidden="true"
                            />
                        </div>

                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold tracking-wide text-amber-700 dark:text-amber-400">
                            Halaman Dalam Pengembangan
                        </span>

                        <div className="space-y-2">
                            <h1 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                                {moduleTitle}
                            </h1>
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {moduleDescription}
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 rounded-lg border border-border bg-muted/40 p-4 text-left text-xs leading-relaxed text-muted-foreground sm:text-sm">
                        <p className="font-medium text-foreground">
                            Layanan yang saat ini sudah aktif digunakan:
                        </p>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            <li>Dashboard ringkasan kepegawaian</li>
                            <li>
                                Modul Cuti (pengajuan, persetujuan inbox, saldo,
                                dan audit)
                            </li>
                            <li>
                                Data Pegawai (master data &amp; riwayat pegawai)
                            </li>
                            <li>
                                Konfigurasi Aplikasi (Pengaturan Akun, IAM, dan
                                Referensi)
                            </li>
                        </ul>
                    </div>

                    <div className="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                        <Button asChild className="gap-2">
                            <Link href={dashboard()}>
                                <LayoutGrid
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Kembali ke Dashboard
                            </Link>
                        </Button>

                        {canOpenCuti ? (
                            <Button asChild variant="outline" className="gap-2">
                                <Link href="/cuti/saya">
                                    <CalendarDays
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Buka Cuti Saya
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
