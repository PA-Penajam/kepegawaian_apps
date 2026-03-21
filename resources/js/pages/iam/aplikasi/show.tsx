import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IamApplication } from '@/types';

type Props = {
    aplikasi: IamApplication;
};

export default function Show() {
    const { aplikasi } = usePage<Props>().props;

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'Aplikasi', href: '/iam/aplikasi' },
            { title: aplikasi.nama, href: `/iam/aplikasi/${aplikasi.id}` },
        ],
        [aplikasi],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={aplikasi.nama} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">{aplikasi.nama}</h1>
                <p>Slug: {aplikasi.slug}</p>
            </div>
        </AppLayout>
    );
}
