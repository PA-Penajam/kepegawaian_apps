import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, RefJenisDokumen, PaginatedData } from '@/types';
import { create, edit, destroy } from '@/routes/referensi/jenis-dokumen';

type Props = {
    jenisDokumen: PaginatedData<RefJenisDokumen>;
    filters: {
        search?: string;
    };
};

export default function Index() {
    const { jenisDokumen, filters } = usePage<Props>().props;
    const [search, setSearch] = useState(filters.search ?? '');

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Jenis Dokumen', href: '/referensi/jenis-dokumen' },
        ],
        [],
    );

    const handleSearch = useCallback(() => {
        router.get(
            '/referensi/jenis-dokumen',
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
        if (confirm('Apakah Anda yakin ingin menghapus jenis dokumen ini?')) {
            router.delete(destroy.url(id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jenis Dokumen" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Jenis Dokumen</h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Cari jenis dokumen..."
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
                                <TableHead className="w-[100px]">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {jenisDokumen.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="text-center"
                                    >
                                        Tidak ada data
                                    </TableCell>
                                </TableRow>
                            ) : (
                                jenisDokumen.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            {item.nama}
                                        </TableCell>
                                        <TableCell>
                                            {item.keterangan ?? '-'}
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
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        handleDelete(item.id)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {jenisDokumen.meta.last_page > 1 && (
                    <Pagination>
                        <PaginationContent>
                            {jenisDokumen.meta.current_page > 1 && (
                                <PaginationItem>
                                    <PaginationPrevious
                                        href={`?page=${jenisDokumen.meta.current_page - 1}`}
                                    />
                                </PaginationItem>
                            )}

                            {Array.from(
                                { length: jenisDokumen.meta.last_page },
                                (_, i) => i + 1,
                            ).map((page) => {
                                if (
                                    page === 1 ||
                                    page === jenisDokumen.meta.last_page ||
                                    (page >=
                                        jenisDokumen.meta.current_page - 1 &&
                                        page <=
                                            jenisDokumen.meta.current_page + 1)
                                ) {
                                    return (
                                        <PaginationItem key={page}>
                                            <PaginationLink
                                                href={`?page=${page}`}
                                                isActive={
                                                    page ===
                                                    jenisDokumen.meta
                                                        .current_page
                                                }
                                            >
                                                {page}
                                            </PaginationLink>
                                        </PaginationItem>
                                    );
                                }
                                if (
                                    page ===
                                        jenisDokumen.meta.current_page - 2 ||
                                    page === jenisDokumen.meta.current_page + 2
                                ) {
                                    return (
                                        <PaginationItem key={page}>
                                            <PaginationEllipsis />
                                        </PaginationItem>
                                    );
                                }
                                return null;
                            })}

                            {jenisDokumen.meta.current_page <
                                jenisDokumen.meta.last_page && (
                                <PaginationItem>
                                    <PaginationNext
                                        href={`?page=${jenisDokumen.meta.current_page + 1}`}
                                    />
                                </PaginationItem>
                            )}
                        </PaginationContent>
                    </Pagination>
                )}
            </div>
        </AppLayout>
    );
}
