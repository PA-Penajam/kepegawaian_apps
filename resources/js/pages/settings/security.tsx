import { Head } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/security';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Keamanan akun',
        href: edit(),
    },
];

export default function Security() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Keamanan akun" />

            <h1 className="sr-only">Keamanan akun</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Keamanan dikelola oleh SSO"
                        description="SIMPEG menggunakan autentikasi terpusat SSO PA Penajam"
                    />

                    <div className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <ShieldCheck
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium text-foreground">
                                    Login satu pintu aktif
                                </p>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Seluruh proses login, verifikasi identitas,
                                    dan multi-factor authentication dikelola
                                    melalui SSO PA Penajam.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start gap-3 border-t border-border pt-4">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                <KeyRound
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium text-foreground">
                                    Password tidak dikelola di SIMPEG
                                </p>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Untuk mengganti password atau memulihkan
                                    akses akun, gunakan layanan SSO PA Penajam
                                    atau hubungi administrator SSO.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
