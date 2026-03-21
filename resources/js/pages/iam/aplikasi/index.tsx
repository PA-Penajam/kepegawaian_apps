import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IamApplication } from '@/types';

type Props = {
    aplikasi: IamApplication[];
};

export default function Index() {
    const { aplikasi } = usePage<Props>().props;

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'Aplikasi', href: '/iam/aplikasi' },
        ],
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelola Aplikasi IAM" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Kelola Aplikasi IAM</h1>
                <p>Total: {aplikasi.length} aplikasi</p>
            </div>
        </AppLayout>
    );
}
