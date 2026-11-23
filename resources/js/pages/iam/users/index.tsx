import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, ShieldCheck, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PaginatedData, User } from '@/types';

type IamUserRow = User & {
    iam_roles_count?: number;
    iam_roles?: Array<{
        id: number;
        nama: string;
        application?: {
            nama: string;
        } | null;
    } | null>;
};

type Props = {
    users: PaginatedData<IamUserRow>;
    filters: {
        search?: string;
    };
};

export default function Index() {
    const { users, filters } = usePage<Props>().props;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [prevSearchFilter, setPrevSearchFilter] = useState(
        filters?.search ?? '',
    );

    if ((filters?.search ?? '') !== prevSearchFilter) {
        setPrevSearchFilter(filters?.search ?? '');
        setSearch(filters?.search ?? '');
    }

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'User Akses', href: '/iam/users' },
        ],
        [],
    );

    // Debounced search — hanya kirim request saat nilai search berubah dari filter aktif server
    const performSearch = useCallback((value: string) => {
        router.get('/iam/users', value ? { search: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    useEffect(() => {
        const normalizedSearch = search.trim();
        const currentSearch = (filters?.search ?? '').trim();

        if (normalizedSearch === currentSearch) {
            return undefined;
        }

        const timeout = setTimeout(() => performSearch(normalizedSearch), 400);

        return () => clearTimeout(timeout);
    }, [search, filters?.search, performSearch]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Akses IAM" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            User Akses
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Kelola hak akses role untuk setiap pengguna.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge
                            variant="outline"
                            className="tnum px-3 py-1 text-sm font-medium shadow-xs"
                        >
                            <Users className="mr-1.5 h-4 w-4" />
                            {users.total} Pengguna
                        </Badge>
                    </div>
                </div>

                {/* Bilah Pencarian */}
                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Cari berdasarkan nama atau NIP..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                {/* Tabel Daftar Pengguna */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border bg-muted/50 hover:bg-muted/50">
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Nama
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    NIP
                                </TableHead>
                                <TableHead className="text-center text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Jumlah Akses
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Akses Aplikasi
                                </TableHead>
                                <TableHead className="text-center text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-12 text-center font-medium text-muted-foreground"
                                    >
                                        Tidak ada data pengguna ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user) => (
                                    <TableRow
                                        key={user.id}
                                        className="border-b border-border transition-colors hover:bg-muted/40"
                                    >
                                        <TableCell className="font-semibold">
                                            {user.nama_lengkap}
                                        </TableCell>
                                        <TableCell className="tnum font-mono text-sm text-muted-foreground">
                                            {user.nip ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge
                                                variant={
                                                    (user.iam_roles_count ??
                                                        0) > 0
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className={`tnum ${
                                                    (user.iam_roles_count ??
                                                        0) > 0
                                                        ? ''
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                <ShieldCheck className="mr-1 h-3.5 w-3.5" />
                                                {user.iam_roles_count ?? 0}{' '}
                                                akses
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1.5">
                                                {(() => {
                                                    const validRoles = (
                                                        user.iam_roles ?? []
                                                    ).filter(
                                                        (
                                                            role,
                                                        ): role is NonNullable<
                                                            typeof role
                                                        > & {
                                                            application: {
                                                                nama: string;
                                                            };
                                                        } =>
                                                            Boolean(
                                                                role &&
                                                                role.application,
                                                            ),
                                                    );

                                                    if (
                                                        validRoles.length === 0
                                                    ) {
                                                        return (
                                                            <span className="text-sm text-muted-foreground italic">
                                                                Belum ada akses
                                                            </span>
                                                        );
                                                    }

                                                    return (
                                                        <>
                                                            {validRoles
                                                                .slice(0, 3)
                                                                .map((role) => (
                                                                    <Badge
                                                                        key={
                                                                            role.id
                                                                        }
                                                                        variant="secondary"
                                                                        className="text-xs font-medium"
                                                                    >
                                                                        {role
                                                                            .application
                                                                            ?.nama ??
                                                                            '-'}{' '}
                                                                        /{' '}
                                                                        {
                                                                            role.nama
                                                                        }
                                                                    </Badge>
                                                                ))}
                                                            {validRoles.length >
                                                                3 && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-xs font-medium"
                                                                >
                                                                    +
                                                                    {validRoles.length -
                                                                        3}{' '}
                                                                    lagi
                                                                </Badge>
                                                            )}
                                                        </>
                                                    );
                                                })()}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 gap-1.5 text-xs font-medium"
                                                    aria-label={`Lihat akses ${user.nama_lengkap}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/iam/users/${user.id}/akses`}
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                        Detail
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

                <PaginationWrapper
                    links={users.links}
                    lastPage={users.last_page}
                />
            </div>
        </AppLayout>
    );
}
