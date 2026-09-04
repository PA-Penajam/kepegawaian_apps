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
    const { users, filters } = usePage<Props>().props;
    const [search, setSearch] = useState(filters?.search ?? '');

    // Normalisasi meta paginasi — Laravel paginate() standar menyimpan di root,
    // sedangkan API Resource membungkusnya di bawah .meta
    const meta = useMemo(() => {
        if (users.meta) {
return users.meta;
}

        const raw = users as any;

        return {
            total: raw.total ?? 0,
            current_page: raw.current_page ?? 1,
            last_page: raw.last_page ?? 1,
        };
    }, [users]);

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'User Akses', href: '/iam/users' },
        ],
        [],
    );

    // Debounced search — sesuai pola di halaman Roles
    const performSearch = useCallback((value: string) => {
        router.get('/iam/users', { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    useEffect(() => {
        const timeout = setTimeout(() => performSearch(search), 400);

        return () => clearTimeout(timeout);
    }, [search, performSearch]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Akses IAM" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                {/* Header — gaya Retro Neo-Brutalism */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold uppercase tracking-tight">User Akses</h1>
                        <p className="text-sm text-muted-foreground mt-1 font-medium">
                            Kelola hak akses role untuk setiap pengguna.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="outline" className="border-2 border-black font-bold text-sm px-3 py-1 shadow-[2px_2px_0_rgba(0,0,0,1)]">
                            <Users className="w-4 h-4 mr-1.5" />
                            {meta.total} Pengguna
                        </Badge>
                    </div>
                </div>

                {/* Search Bar */}
                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Cari berdasarkan nama atau NIP..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-md border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)] focus-visible:shadow-none focus-visible:translate-y-[2px] focus-visible:translate-x-[2px] transition-all"
                    />
                </div>

                {/* Table — border tebal ala Neo-Brutalism */}
                <div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
                                <TableHead className="font-black uppercase text-xs tracking-wider">Nama</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">NIP</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider text-center">Jumlah Akses</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">Akses Aplikasi</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider text-center">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center py-12 font-medium text-muted-foreground"
                                    >
                                        Tidak ada data pengguna ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user) => (
                                    <TableRow key={user.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
                                        <TableCell className="font-bold">
                                            {user.nama_lengkap}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm text-muted-foreground">
                                            {user.nip ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge
                                                variant={(user.iam_roles_count ?? 0) > 0 ? 'default' : 'outline'}
                                                className={`border-2 border-black font-bold shadow-[2px_2px_0_rgba(0,0,0,1)] ${
                                                    (user.iam_roles_count ?? 0) > 0
                                                        ? ''
                                                        : 'bg-background text-muted-foreground'
                                                }`}
                                            >
                                                <ShieldCheck className="w-3.5 h-3.5 mr-1" />
                                                {user.iam_roles_count ?? 0} akses
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1.5">
                                                {user.iam_roles &&
                                                user.iam_roles.length > 0 ? (
                                                    user.iam_roles
                                                        .slice(0, 3)
                                                        .map((role) => (
                                                            <Badge
                                                                key={role.id}
                                                                variant="secondary"
                                                                className="text-xs border-2 border-black/30 font-semibold"
                                                            >
                                                                {role.application.nama} / {role.nama}
                                                            </Badge>
                                                        ))
                                                ) : (
                                                    <span className="text-sm text-muted-foreground italic">
                                                        Belum ada akses
                                                    </span>
                                                )}
                                                {user.iam_roles &&
                                                    user.iam_roles.length > 3 && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs border-2 border-black/30 font-semibold"
                                                        >
                                                            +{user.iam_roles.length - 3} lagi
                                                        </Badge>
                                                    )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="font-bold gap-1.5 border-2 border-black text-xs h-8"
                                                    aria-label={`Lihat akses ${user.nama_lengkap}`}
                                                    asChild
                                                >
                                                    <Link href={`/iam/users/${user.id}/akses`}>
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

                <PaginationWrapper meta={meta} />
            </div>
        </AppLayout>
    );
}
