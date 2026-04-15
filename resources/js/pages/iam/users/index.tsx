import { Head, Link, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IamPaginatedData, User } from '@/types';

type Props = {
    users: IamPaginatedData<
        User & {
            iam_roles_count?: number;
            iam_roles?: Array<{
                id: number;
                nama: string;
                application: {
                    nama: string;
                };
            }>;
        }
    >;
    filters: {
        search?: string;
    };
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
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">User Akses IAM</h1>
                    <p className="text-muted-foreground">
                        Total: {users.meta.total} user
                    </p>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead className="text-center">
                                    Jumlah Akses
                                </TableHead>
                                <TableHead>Akses Aplikasi</TableHead>
                                <TableHead className="text-center">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center"
                                    >
                                        Tidak ada data user
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            {user.nama_lengkap}
                                        </TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant="outline">
                                                {user.iam_roles_count ?? 0}{' '}
                                                akses
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.iam_roles &&
                                                user.iam_roles.length > 0 ? (
                                                    user.iam_roles
                                                        .slice(0, 3)
                                                        .map((role) => (
                                                            <Badge
                                                                key={role.id}
                                                                variant="secondary"
                                                                className="text-xs"
                                                            >
                                                                {
                                                                    role
                                                                        .application
                                                                        .nama
                                                                }{' '}
                                                                / {role.nama}
                                                            </Badge>
                                                        ))
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Tidak ada akses
                                                    </span>
                                                )}
                                                {user.iam_roles &&
                                                    user.iam_roles.length >
                                                        3 && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            +
                                                            {user.iam_roles
                                                                .length -
                                                                3}{' '}
                                                            lagi
                                                        </Badge>
                                                    )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Lihat akses ${user.nama_lengkap}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/iam/users/${user.id}/akses`}
                                                    >
                                                        <Eye
                                                            className="h-4 w-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {users.meta.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {users.meta.current_page > 1 && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`?page=${users.meta.current_page - 1}`}
                                >
                                    Sebelumnya
                                </Link>
                            </Button>
                        )}
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Halaman {users.meta.current_page} dari{' '}
                            {users.meta.last_page}
                        </span>
                        {users.meta.current_page < users.meta.last_page && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`?page=${users.meta.current_page + 1}`}
                                >
                                    Selanjutnya
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
