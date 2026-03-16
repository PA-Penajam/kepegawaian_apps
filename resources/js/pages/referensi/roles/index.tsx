import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, RefRole, PaginatedData } from '@/types';
import {
    index as rolesIndex,
    create,
    edit,
    destroy,
} from '@/routes/referensi/roles';

type Props = {
    roles: PaginatedData<RefRole>;
    filters: {
        search?: string;
    };
};

export default function Index({ roles, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Roles', href: rolesIndex.url() },
        ],
        [],
    );

    const handleSearch = useCallback(() => {
        router.get(
            rolesIndex.url(),
            { search },
            { preserveState: true, preserveScroll: true },
        );
    }, [search]);

    useEffect(() => {
        const timeout = setTimeout(() => {
            handleSearch();
        }, 300);
        return () => clearTimeout(timeout);
    }, [search, handleSearch]);

    const handleDelete = (id: string) => {
        if (confirm('Apakah Anda yakin ingin menghapus role ini?')) {
            router.delete(destroy.url(id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Roles</h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Cari role..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Keterangan</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead>Sistem</TableHead>
                                <TableHead className="w-[100px]">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center"
                                    >
                                        Tidak ada data
                                    </TableCell>
                                </TableRow>
                            ) : (
                                roles.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            {item.nama}
                                        </TableCell>
                                        <TableCell>
                                            {item.keterangan ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {(item as any)
                                                    .permissions_count ?? 0}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {item.is_system ? (
                                                <Badge variant="secondary">
                                                    Sistem
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    Custom
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link href={edit(item.id)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {!item.is_system && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            handleDelete(
                                                                item.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationWrapper meta={roles.meta} />
            </div>
        </AppLayout>
    );
}
