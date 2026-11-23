import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { PaginationWrapper } from '@/components/pagination-wrapper';
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
import {
    index as jenisDokumenIndex,
    create,
    edit,
    destroy,
} from '@/routes/referensi/jenis-dokumen';
import type { BreadcrumbItem, RefJenisDokumen, PaginatedData } from '@/types';

type Props = {
    jenisDokumen: PaginatedData<RefJenisDokumen>;
    filters: {
        search?: string;
    };
};

export default function Index({ jenisDokumen, filters }: Props) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [prevSearchFilter, setPrevSearchFilter] = useState(
        filters?.search ?? '',
    );

    if ((filters?.search ?? '') !== prevSearchFilter) {
        setPrevSearchFilter(filters?.search ?? '');
        setSearch(filters?.search ?? '');
    }

    const [deleteTarget, setDeleteTarget] = useState<{
        id: string;
        nama: string;
    } | null>(null);
    const deleteForm = useForm({});

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Jenis Dokumen', href: jenisDokumenIndex.url() },
        ],
        [],
    );

    const handleSearch = useCallback((value: string) => {
        router.get(jenisDokumenIndex.url(), value ? { search: value } : {}, {
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

        const timeout = setTimeout(() => {
            handleSearch(normalizedSearch);
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, filters?.search, handleSearch]);

    const handleDelete = (id: string, nama: string) => {
        setDeleteTarget({ id, nama });
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        deleteForm.delete(destroy.url(deleteTarget.id), {
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jenis Dokumen" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Jenis Dokumen
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Kelola jenis dokumen yang digunakan dalam sistem.
                        </p>
                    </div>
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
                        className="max-w-md"
                    />
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border bg-muted/50 hover:bg-muted/50">
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Nama
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Keterangan
                                </TableHead>
                                <TableHead className="w-[100px] text-center text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {jenisDokumen.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-12 text-center font-medium text-muted-foreground"
                                    >
                                        Tidak ada data jenis dokumen ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                jenisDokumen.data.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        className="border-b border-border transition-colors hover:bg-muted/40"
                                    >
                                        <TableCell className="font-semibold">
                                            {item.nama}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {item.keterangan ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Edit ${item.nama}`}
                                                    asChild
                                                >
                                                    <Link href={edit(item.id)}>
                                                        <Pencil
                                                            className="h-4 w-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Hapus ${item.nama}`}
                                                    onClick={() =>
                                                        handleDelete(
                                                            item.id,
                                                            item.nama,
                                                        )
                                                    }
                                                >
                                                    <Trash2
                                                        className="h-4 w-4 text-destructive"
                                                        aria-hidden="true"
                                                    />
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
                    links={jenisDokumen.links}
                    lastPage={jenisDokumen.last_page}
                />
            </div>

            <ConfirmDeleteDialog
                open={!!deleteTarget}
                onOpenChange={(open) =>
                    !open && !deleteForm.processing && setDeleteTarget(null)
                }
                title="Hapus Jenis Dokumen"
                description="Apakah Anda yakin ingin menghapus jenis dokumen"
                itemName={deleteTarget?.nama}
                onConfirm={confirmDelete}
                processing={deleteForm.processing}
            />
        </AppLayout>
    );
}
