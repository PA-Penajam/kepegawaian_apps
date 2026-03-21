import { Head, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, User } from '@/types';

type Props = {
    users: User[];
};

export default function Index() {
    const { users } = usePage<Props>().props;

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'User Akses', href: '/iam/users' },
        ],
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Akses IAM" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">User Akses IAM</h1>
            </div>
        </AppLayout>
    );
}
