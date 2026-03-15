import { Head, Link } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { dashboard } from '@/routes';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Akun Belum Terhubung', href: '/self-service/unlinked' },
];

export default function SelfServiceUnlinked() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Akun Belum Terhubung" />

            <div className="flex flex-1 flex-col items-center justify-center gap-6 p-4 sm:p-6">
                <Card className="w-full max-w-lg">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                            <AlertCircle className="h-8 w-8 text-amber-600" />
                        </div>
                        <CardTitle>Akun Belum Terhubung</CardTitle>
                        <CardDescription>
                            Akun Anda belum dikaitkan dengan data pegawai.
                            Hubungi administrator untuk mengaitkan akun Anda.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col items-center gap-4 text-center text-sm text-muted-foreground">
                        <p>
                            Setelah akun terhubung, menu{' '}
                            <strong>Data Saya</strong> akan menampilkan
                            ringkasan dan detail data kepegawaian Anda secara
                            mandiri.
                        </p>
                        <Button asChild>
                            <Link href={dashboard()}>Kembali ke Dashboard</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
