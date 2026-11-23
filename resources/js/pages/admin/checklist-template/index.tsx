import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
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
import type { BreadcrumbItem, PaginatedData } from '@/types';

type ChecklistTemplate = {
    id: string;
    kode: string;
    nama: string;
    jenis: string;
    aktif: boolean;
    items_count: number;
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
    const [deleteTarget, setDeleteTarget] = useState<ChecklistTemplate | null>(
        null,
    );
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
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Checklist Template
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
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
                    <Select
                        value={filters.jenis ?? 'semua'}
                        onValueChange={handleFilter}
                    >
                        <SelectTrigger className="w-full max-w-xs">
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

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border bg-muted/50 hover:bg-muted/50">
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Kode
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Nama
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Jenis
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aktif
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Jumlah Item
                                </TableHead>
                                <TableHead className="w-[100px] text-center text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {templates.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-12 text-center font-medium text-muted-foreground"
                                    >
                                        Tidak ada template checklist ditemukan.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                templates.data.map((template) => (
                                    <TableRow
                                        key={template.id}
                                        className="border-b border-border transition-colors hover:bg-muted/40"
                                    >
                                        <TableCell className="font-mono font-semibold">
                                            {template.kode}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {template.nama}
                                        </TableCell>
                                        <TableCell>{template.jenis}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    template.aktif
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {template.aktif
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="tnum">
                                            {template.items_count}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Edit ${template.nama}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={`${routeBase}/${template.id}/edit`}
                                                    >
                                                        <Pencil
                                                            className="h-4 w-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Hapus ${template.nama}`}
                                                    onClick={() =>
                                                        setDeleteTarget(
                                                            template,
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
                    links={templates.links}
                    lastPage={templates.last_page}
                />
            </div>

            <ConfirmDeleteDialog
                open={!!deleteTarget}
                onOpenChange={(open) =>
                    !open && !deleteForm.processing && setDeleteTarget(null)
                }
                title="Hapus Checklist Template"
                description="Apakah Anda yakin ingin menghapus checklist template"
                itemName={deleteTarget?.nama}
                onConfirm={confirmDelete}
                processing={deleteForm.processing}
            />
        </AppLayout>
    );
}
