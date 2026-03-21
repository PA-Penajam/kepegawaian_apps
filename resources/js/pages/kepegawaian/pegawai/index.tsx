import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { DataTableToolbar } from '@/components/kepegawaian/data-table-toolbar';
import type { DataTableToolbarFilter } from '@/components/kepegawaian/data-table-toolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index, show, create, edit } from '@/routes/kepegawaian/pegawai';
import type { BreadcrumbItem } from '@/types';
import { StatusPegawaiLabels } from '@/types/kepegawaian';
import type {
    PaginatedData,
    Pegawai,
    PegawaiListFilterOptions,
    PegawaiListFilters,
    PegawaiListSortBy,
    RefJabatan,
    StatusPegawai,
} from '@/types/kepegawaian';

type PegawaiIndexSortBy = PegawaiListSortBy | 'jabatan';

type PegawaiIndexFilters = Omit<PegawaiListFilters, 'sort_by'> & {
    jabatan: string | null;
    sort_by: PegawaiIndexSortBy | null;
};

type Props = {
    pegawai: PaginatedData<Pegawai>;
    filters: PegawaiIndexFilters;
    filterOptions: PegawaiListFilterOptions;
    refJabatan: Pick<RefJabatan, 'id' | 'nama'>[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Kepegawaian',
        href: '#',
    },
    {
        title: 'Data Pegawai',
        href: index(),
    },
];

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

type VisitFilters = PegawaiIndexFilters & {
    page?: number | null;
};

const getStatusBadgeVariant = (status: StatusPegawai): BadgeVariant => {
    switch (status) {
        case 'aktif':
            return 'default';
        case 'pensiun':
            return 'secondary';
        case 'mutasi_keluar':
            return 'outline';
        case 'meninggal':
            return 'destructive';
        case 'diberhentikan':
            return 'outline';
        default:
            return 'default';
    }
};

const getSortIndicator = (
    activeColumn: PegawaiIndexSortBy | null,
    activeDirection: PegawaiIndexFilters['sort_dir'],
    column: PegawaiIndexSortBy,
): string => {
    if (activeColumn !== column) {
        return '';
    }

    return activeDirection === 'desc' ? '▼' : '▲';
};

export default function PegawaiIndex({
    pegawai,
    filters,
    filterOptions,
    refJabatan,
}: Props) {
    const { auth } = usePage().props;
    const canEdit = (auth.user.permissions ?? []).includes('pegawai.update');
    const [searchValue, setSearchValue] = useState(filters.search ?? '');

    const applyFilters = useCallback(
        (changes: Partial<VisitFilters>) => {
            const nextFilters: VisitFilters = {
                search: filters.search,
                golongan: filters.golongan,
                jabatan: filters.jabatan,
                unit_kerja: filters.unit_kerja,
                status_pegawai: filters.status_pegawai,
                sort_by: filters.sort_by,
                sort_dir: filters.sort_dir,
                ...changes,
            };

            const params = Object.fromEntries(
                Object.entries(nextFilters).filter(([, value]) => {
                    return (
                        value !== null && value !== '' && value !== undefined
                    );
                }),
            );

            router.get(index.url(), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [filters],
    );

    useEffect(() => {
        setSearchValue(filters.search ?? '');
    }, [filters.search]);

    useEffect(() => {
        const normalizedSearch = searchValue.trim();
        const currentSearch = (filters.search ?? '').trim();

        if (normalizedSearch === currentSearch) {
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            applyFilters({ search: normalizedSearch || null, page: null });
        }, 300);

        return () => window.clearTimeout(timeoutId);
    }, [applyFilters, filters.search, searchValue]);

    const toolbarFilters = useMemo<DataTableToolbarFilter[]>(() => {
        return [
            {
                key: 'golongan',
                label: 'Golongan/Pangkat',
                placeholder: 'Semua Golongan',
                value: filters.golongan,
                options: filterOptions.golongan.map((item) => ({
                    value: item.kode,
                    label: `${item.kode} - ${item.nama}`,
                })),
                onChange: (value) =>
                    applyFilters({ golongan: value, page: null }),
            },
            {
                key: 'jabatan',
                label: 'Jabatan',
                placeholder: 'Semua Jabatan',
                value: filters.jabatan,
                options: refJabatan.map((item) => ({
                    value: item.id,
                    label: item.nama,
                })),
                onChange: (value) =>
                    applyFilters({ jabatan: value, page: null }),
            },
            {
                key: 'unit_kerja',
                label: 'Unit Kerja',
                placeholder: 'Semua Unit Kerja',
                value: filters.unit_kerja,
                options: filterOptions.unitKerja.map((item) => ({
                    value: item.id,
                    label: item.nama,
                })),
                onChange: (value) =>
                    applyFilters({ unit_kerja: value, page: null }),
            },
            {
                key: 'status_pegawai',
                label: 'Status Pegawai',
                placeholder: 'Semua Status',
                value: filters.status_pegawai,
                options: filterOptions.statusPegawai.map((item) => ({
                    value: item,
                    label: StatusPegawaiLabels[item],
                })),
                onChange: (value) =>
                    applyFilters({
                        status_pegawai: value as StatusPegawai | null,
                        page: null,
                    }),
            },
        ];
    }, [
        applyFilters,
        filterOptions,
        filters.golongan,
        filters.jabatan,
        filters.status_pegawai,
        filters.unit_kerja,
        refJabatan,
    ]);

    const hasActiveFilters = Boolean(
        filters.search ||
        filters.golongan ||
        filters.jabatan ||
        filters.unit_kerja ||
        filters.status_pegawai ||
        filters.sort_by ||
        filters.sort_dir,
    );

    const handleSort = (column: PegawaiIndexSortBy) => {
        const nextDirection =
            filters.sort_by === column && filters.sort_dir === 'asc'
                ? 'desc'
                : 'asc';

        applyFilters({
            sort_by: column,
            sort_dir: nextDirection,
            page: null,
        });
    };

    const clearFilters = () => {
        setSearchValue('');
        applyFilters({
            search: null,
            golongan: null,
            jabatan: null,
            unit_kerja: null,
            status_pegawai: null,
            sort_by: null,
            sort_dir: null,
            page: null,
        });
    };

    const emptyStateMessage = hasActiveFilters
        ? 'Tidak ada data pegawai yang sesuai dengan pencarian atau filter aktif.'
        : 'Belum ada data pegawai.';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Pegawai" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 sm:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Data Pegawai
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola data induk pegawai Pengadilan Agama Penajam.
                        </p>
                    </div>
                    {canEdit && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Pegawai
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
                    <DataTableToolbar
                        searchValue={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari NIP atau nama pegawai..."
                        filters={toolbarFilters}
                        showClear={hasActiveFilters}
                        onClear={clearFilters}
                    />

                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        <button
                                            type="button"
                                            className="flex items-center gap-1 text-left font-medium"
                                            onClick={() => handleSort('nip')}
                                        >
                                            NIP
                                            <span className="text-xs text-muted-foreground">
                                                {getSortIndicator(
                                                    filters.sort_by,
                                                    filters.sort_dir,
                                                    'nip',
                                                )}
                                            </span>
                                        </button>
                                    </TableHead>
                                    <TableHead>
                                        <button
                                            type="button"
                                            className="flex items-center gap-1 text-left font-medium"
                                            onClick={() => handleSort('nama')}
                                        >
                                            Nama
                                            <span className="text-xs text-muted-foreground">
                                                {getSortIndicator(
                                                    filters.sort_by,
                                                    filters.sort_dir,
                                                    'nama',
                                                )}
                                            </span>
                                        </button>
                                    </TableHead>
                                    <TableHead>
                                        <button
                                            type="button"
                                            className="flex items-center gap-1 text-left font-medium"
                                            onClick={() =>
                                                handleSort('pangkat')
                                            }
                                        >
                                            Pangkat/Gol
                                            <span className="text-xs text-muted-foreground">
                                                {getSortIndicator(
                                                    filters.sort_by,
                                                    filters.sort_dir,
                                                    'pangkat',
                                                )}
                                            </span>
                                        </button>
                                    </TableHead>
                                    <TableHead>
                                        <button
                                            type="button"
                                            className="flex items-center gap-1 text-left font-medium"
                                            onClick={() =>
                                                handleSort('jabatan')
                                            }
                                        >
                                            Jabatan
                                            <span className="text-xs text-muted-foreground">
                                                {getSortIndicator(
                                                    filters.sort_by,
                                                    filters.sort_dir,
                                                    'jabatan',
                                                )}
                                            </span>
                                        </button>
                                    </TableHead>
                                    <TableHead>Unit Kerja</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="h-32 text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center justify-center gap-2">
                                                <p>{emptyStateMessage}</p>
                                                {!hasActiveFilters &&
                                                canEdit ? (
                                                    <Button
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link href={create()}>
                                                            Tambah Pegawai
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    pegawai.data.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.nip ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.nama_lengkap}
                                            </TableCell>
                                            <TableCell>
                                                {item.pangkat
                                                    ? `${item.pangkat.nama} (${item.pangkat.kode})`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.jabatan?.nama ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.unit_kerja?.nama ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={getStatusBadgeVariant(
                                                        item.status_pegawai,
                                                    )}
                                                    className={
                                                        item.status_pegawai ===
                                                        'mutasi_keluar'
                                                            ? 'border-yellow-300 bg-yellow-50 text-yellow-700 hover:bg-yellow-50'
                                                            : item.status_pegawai ===
                                                                'diberhentikan'
                                                              ? 'border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-50'
                                                              : ''
                                                    }
                                                >
                                                    {
                                                        StatusPegawaiLabels[
                                                            item.status_pegawai
                                                        ]
                                                    }
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                        title="Lihat Detail"
                                                    >
                                                        <Link
                                                            href={show(item.id)}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Lihat
                                                            </span>
                                                        </Link>
                                                    </Button>
                                                    {canEdit ? (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            asChild
                                                            title="Ubah Data"
                                                        >
                                                            <Link
                                                                href={edit(
                                                                    item.id,
                                                                )}
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                                <span className="sr-only">
                                                                    Ubah
                                                                </span>
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <PaginationWrapper
                    links={pegawai.links}
                    lastPage={pegawai.last_page}
                />
            </div>
        </AppLayout>
    );
}
