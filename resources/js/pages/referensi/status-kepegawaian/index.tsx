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
    index as statusKepegawaianIndex,
    create,
    edit,
    destroy,
} from '@/routes/referensi/status-kepegawaian';
import type {
    BreadcrumbItem,
    RefStatusKepegawaian,
    PaginatedData,
} from '@/types';

type Props = {
    statusKepegawaian: PaginatedData<RefStatusKepegawaian>;
    filters: {
        search?: string;
    };
};

export default function Index({ statusKepegawaian, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<{
        id: string;
        nama: string;
    } | null>(null);
    const deleteForm = useForm({});

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            {
                title: 'Status Kepegawaian',
                href: statusKepegawaianIndex.url(),
            },
        ],
        [],
    );

    const handleSearch = useCallback(() => {
        router.get(
            statusKepegawaianIndex.url(),
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

    const handleDelete = (id: string, nama: string) => {
        setDeleteTarget({ id, nama });
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        deleteForm.delete(destroy.url(deleteTarget.id), {
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Status Kepegawaian" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold uppercase tracking-tight">Status Kepegawaian</h1>
                        <p className="text-sm text-muted-foreground mt-1 font-medium">Kelola status kepegawaian pegawai dalam sistem.</p>
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
                        placeholder="Cari status kepegawaian..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-md border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)] focus-visible:shadow-none focus-visible:translate-y-[2px] focus-visible:translate-x-[2px] transition-all"
                    />
                </div>

                <div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
                                <TableHead className="font-black uppercase text-xs tracking-wider">Kode</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">Nama</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">Keterangan</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider text-center w-[100px]">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {statusKepegawaian.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-center py-12 font-medium text-muted-foreground"
                                    >
                                        Tidak ada data status kepegawaian ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                statusKepegawaian.data.map((item) => (
                                    <TableRow key={item.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
                                        <TableCell className="font-bold">
                                            {item.kode}
                                        </TableCell>
                                        <TableCell className="font-medium">
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
                                                        handleDelete(item.id, item.nama)
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

                <PaginationWrapper meta={statusKepegawaian.meta} />
            </div>

            <ConfirmDeleteDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Hapus Status Kepegawaian"
                description="Apakah Anda yakin ingin menghapus status kepegawaian"
                itemName={deleteTarget?.nama}
                onConfirm={confirmDelete}
                processing={deleteForm.processing}
            />
        </AppLayout>
    );
}
