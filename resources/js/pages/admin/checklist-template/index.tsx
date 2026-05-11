import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ChecklistTemplate = {
    id: string;
    kode: string;
    nama: string;
    jenis: string;
    aktif: boolean;
    items_count: number;
};

type PaginatedData<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
    };
};

type Props = {
    templates: PaginatedData<ChecklistTemplate>;
    filters: {
        jenis?: string;
    };
    domains: string[];
};

const routeBase = '/admin/checklist-template';

export default function Index({ templates, filters, domains }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<ChecklistTemplate | null>(null);
    const deleteForm = useForm({});

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Admin', href: '#' },
            { title: 'Checklist Template', href: routeBase },
        ],
        [],
    );

    const handleFilter = (jenis: string) => {
        router.get(
            routeBase,
            { jenis: jenis === 'semua' ? undefined : jenis },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        deleteForm.delete(`${routeBase}/${deleteTarget.id}`, {
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Checklist Template" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold uppercase tracking-tight">Checklist Template</h1>
                        <p className="mt-1 text-sm font-medium text-muted-foreground">
                            Kelola template dan item checklist berkas.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={`${routeBase}/create`}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Template
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    <Select value={filters.jenis ?? 'semua'} onValueChange={handleFilter}>
                        <SelectTrigger className="w-full max-w-xs border-2 border-black shadow-[2px_2px_0_rgba(0,0,0,1)]">
                            <SelectValue placeholder="Filter jenis" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="semua">Semua Jenis</SelectItem>
                            {domains.map((domain) => (
                                <SelectItem key={domain} value={domain}>
                                    {domain}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border-2 border-black bg-background shadow-[4px_4px_0_rgba(0,0,0,1)]">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b-2 border-black bg-muted/30 hover:bg-muted/30">
                                <TableHead className="text-xs font-black uppercase tracking-wider">Kode</TableHead>
                                <TableHead className="text-xs font-black uppercase tracking-wider">Nama</TableHead>
                                <TableHead className="text-xs font-black uppercase tracking-wider">Jenis</TableHead>
                                <TableHead className="text-xs font-black uppercase tracking-wider">Aktif</TableHead>
                                <TableHead className="text-xs font-black uppercase tracking-wider">Items</TableHead>
                                <TableHead className="w-[100px] text-center text-xs font-black uppercase tracking-wider">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {templates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-12 text-center font-medium text-muted-foreground">
                                        Tidak ada template checklist ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                templates.data.map((template) => (
                                    <TableRow key={template.id} className="border-b border-black/10 hover:bg-muted/20">
                                        <TableCell className="font-mono font-bold">{template.kode}</TableCell>
                                        <TableCell className="font-bold">{template.nama}</TableCell>
                                        <TableCell>{template.jenis}</TableCell>
                                        <TableCell>{template.aktif ? 'Aktif' : 'Nonaktif'}</TableCell>
                                        <TableCell>{template.items_count}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={`${routeBase}/${template.id}/edit`}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" onClick={() => setDeleteTarget(template)}>
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

                <PaginationWrapper meta={templates.meta} />
            </div>

            <ConfirmDeleteDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Hapus Checklist Template"
                description="Apakah Anda yakin ingin menghapus checklist template"
                itemName={deleteTarget?.nama}
                onConfirm={confirmDelete}
                processing={deleteForm.processing}
            />
        </AppLayout>
    );
}
