import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { BreadcrumbItem, KepegawaianPaginatedData } from '@/types';

type StatusKp = 'Sudah Eligible' | 'Mendekati Eligible' | 'Belum Eligible';

type PegawaiEligibleRow = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
    pangkat_saat_ini: string | null;
    pangkat_kode: string | null;
    tmt_pangkat: string | null;
    tmt_kp_berikutnya: string;
    periode_usul: string;
    batas_usul: string;
    sisa_hari_usul: number;
    status: StatusKp;
};

type Props = {
    pegawaiList: KepegawaianPaginatedData<PegawaiEligibleRow>;
    stats: {
        total: number;
        sudahEligible: number;
        mendekatiEligible: number;
        belumEligible: number;
    };
    bulanOptions: { value: number; label: string }[];
    tahunOptions: number[];
    filters: {
        bulan: number | null;
        tahun: number | null;
        unit_kerja?: string | null;
        golongan?: string | null;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kenaikan Pangkat', href: '/kenaikan-pangkat/usulan' },
    { title: 'Pegawai Eligible', href: '/kenaikan-pangkat/eligible' },
];

const statusBadgeClass: Record<StatusKp, string> = {
    'Sudah Eligible':
        'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200 dark:hover:bg-emerald-950/60',
    'Mendekati Eligible':
        'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200 dark:hover:bg-amber-950/60',
    'Belum Eligible':
        'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-800',
};

function formatTanggal(tanggal: string | null): string {
    if (tanggal === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${tanggal}T00:00:00`));
}

function createUsulanUrl(
    pegawaiId: string,
    bulan: number | null,
    tahun: number | null,
): string {
    return `/kenaikan-pangkat/usulan/create?pegawai_id=${pegawaiId}&bulan=${bulan ?? ''}&tahun=${tahun ?? ''}`;
}

export default function EligibleKenaikanPangkatPage({
    pegawaiList,
    stats,
    bulanOptions,
    tahunOptions,
    filters,
}: Props) {
    const { auth } = usePage().props;
    const canCreateUsulan = (auth.user.permissions ?? []).includes(
        'kenaikan-pangkat.usulan.create',
    );
    const [bulanFilter, setBulanFilter] = useState(
        filters.bulan?.toString() ?? '',
    );
    const [tahunFilter, setTahunFilter] = useState(
        filters.tahun?.toString() ?? '',
    );

    function applyFilters(changes: Record<string, string | null>) {
        const resolved = { bulan: bulanFilter, tahun: tahunFilter, ...changes };
        const params = Object.fromEntries(
            Object.entries(resolved).filter(([, value]) => value),
        );

        router.get('/kenaikan-pangkat/eligible', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pegawai Eligible Kenaikan Pangkat" />
            <div className="flex flex-1 flex-col gap-6 bg-background p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pegawai Eligible Kenaikan Pangkat
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Daftar pegawai yang siap dibuatkan usulan KP.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Total pegawai', stats.total],
                        ['Sudah eligible', stats.sudahEligible],
                        ['Mendekati eligible', stats.mendekatiEligible],
                        ['Belum eligible', stats.belumEligible],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    {label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="tnum text-3xl font-bold">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm md:flex-row md:items-end">
                    <div className="grid gap-2">
                        <label className="text-sm font-medium">Bulan</label>
                        <Select
                            value={bulanFilter || 'semua'}
                            onValueChange={(value) => {
                                const next = value === 'semua' ? '' : value;
                                setBulanFilter(next);
                                applyFilters({ bulan: next });
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Pilih bulan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="semua">
                                    Semua Bulan
                                </SelectItem>
                                {bulanOptions.map((bulan) => (
                                    <SelectItem
                                        key={bulan.value}
                                        value={bulan.value.toString()}
                                    >
                                        {bulan.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <label className="text-sm font-medium">Tahun</label>
                        <Select
                            value={tahunFilter || 'semua'}
                            onValueChange={(value) => {
                                const next = value === 'semua' ? '' : value;
                                setTahunFilter(next);
                                applyFilters({ tahun: next });
                            }}
                        >
                            <SelectTrigger className="w-32">
                                <SelectValue placeholder="Pilih tahun" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="semua">
                                    Tahun Berjalan
                                </SelectItem>
                                {tahunOptions.map((tahun) => (
                                    <SelectItem
                                        key={tahun}
                                        value={tahun.toString()}
                                    >
                                        {tahun}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/50 hover:bg-muted/50">
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    NIP
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Nama
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Pangkat Saat Ini
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    TMT Pangkat
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    TMT KP Berikutnya
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Periode Usul
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Status
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pegawaiList.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="py-12 text-center text-muted-foreground"
                                    >
                                        Tidak ada pegawai eligible.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                pegawaiList.data.map((pegawai) => (
                                    <TableRow key={pegawai.id}>
                                        <TableCell className="tnum font-mono text-sm">
                                            {pegawai.nip ?? '-'}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {pegawai.nama_lengkap}
                                        </TableCell>
                                        <TableCell>
                                            {pegawai.pangkat_saat_ini
                                                ? `${pegawai.pangkat_saat_ini} (${pegawai.pangkat_kode ?? '-'})`
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {formatTanggal(pegawai.tmt_pangkat)}
                                        </TableCell>
                                        <TableCell>
                                            {formatTanggal(
                                                pegawai.tmt_kp_berikutnya,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {pegawai.periode_usul}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    statusBadgeClass[
                                                        pegawai.status
                                                    ]
                                                }
                                            >
                                                {pegawai.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {pegawai.status ===
                                                'Sudah Eligible' &&
                                            canCreateUsulan ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={createUsulanUrl(
                                                            pegawai.id,
                                                            filters.bulan,
                                                            filters.tahun,
                                                        )}
                                                    >
                                                        <Plus className="mr-2 h-4 w-4" />
                                                        Buat Usulan
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
                <PaginationWrapper
                    links={pegawaiList.links}
                    lastPage={pegawaiList.last_page}
                />
            </div>
        </AppLayout>
    );
}
