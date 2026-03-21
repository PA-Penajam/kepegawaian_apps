import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, User } from '@/types';

type Props = {
    user: User;
};

export default function Akses() {
    const { user } = usePage<Props>().props;

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'User Akses', href: '/iam/users' },
            { title: user.name, href: `/iam/users/${user.id}/akses` },
        ],
        [user],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Akses ${user.name}`} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Akses User: {user.name}</h1>
            </div>
        </AppLayout>
    );
}
