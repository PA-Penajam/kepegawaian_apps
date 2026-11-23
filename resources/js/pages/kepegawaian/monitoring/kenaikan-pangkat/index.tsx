import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
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
import type { BreadcrumbItem } from '@/types';
import type { KepegawaianPaginatedData } from '@/types/kepegawaian';

type StatusKp = 'Sudah Eligible' | 'Mendekati Eligible' | 'Belum Eligible';

type PegawaiMonitoringRow = {
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

type UnitKerjaOption = { id: string; nama: string };
type KpFilters = {
    unit_kerja: string | null;
    golongan: string | null;
    bulan: number | null;
    tahun: number | null;
};
type KpFilterOptions = {
    unitKerja: UnitKerjaOption[];
    golongan: string[];
};

type Props = {
    pegawaiList: KepegawaianPaginatedData<PegawaiMonitoringRow>;
    stats: {
        total: number;
        sudahEligible: number;
        mendekatiEligible: number;
        belumEligible: number;
    };
    bulanOptions: { value: number; label: string }[];
    tahunOptions: number[];
    filters: KpFilters;
    filterOptions: KpFilterOptions;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Monitoring Kenaikan Pangkat',
        href: '/kepegawaian/monitoring/kenaikan-pangkat',
    },
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

export default function MonitoringKenaikanPangkatPage({
    pegawaiList,
    stats,
    bulanOptions,
    tahunOptions,
    filters,
    filterOptions,
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
    const [unitKerjaFilter, setUnitKerjaFilter] = useState(
        filters.unit_kerja ?? '',
    );
    const [golonganFilter, setGolonganFilter] = useState(
        filters.golongan ?? '',
    );

    function handleFilterChange(newParams: Record<string, string | null>) {
        const params: Record<string, string> = {};
        const resolved = {
            unit_kerja: unitKerjaFilter,
            golongan: golonganFilter,
            bulan: bulanFilter,
            tahun: tahunFilter,
            ...newParams,
        };

        if (resolved.unit_kerja) {
            params.unit_kerja = resolved.unit_kerja;
        }

        if (resolved.golongan) {
            params.golongan = resolved.golongan;
        }

        if (resolved.bulan) {
            params.bulan = resolved.bulan;
        }

        if (resolved.tahun) {
            params.tahun = resolved.tahun;
        }

        router.get('/kepegawaian/monitoring/kenaikan-pangkat', params, {
            preserveState: true,
            replace: true,
        });
    }

    function handleExport() {
        const params = new URLSearchParams();

        if (unitKerjaFilter) {
            params.set('unit_kerja', unitKerjaFilter);
        }

        if (golonganFilter) {
            params.set('golongan', golonganFilter);
        }

        if (bulanFilter) {
            params.set('bulan', bulanFilter);
        }

        if (tahunFilter) {
            params.set('tahun', tahunFilter);
        }

        window.location.href = `/kepegawaian/monitoring/kenaikan-pangkat/export?${params.toString()}`;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monitoring Kenaikan Pangkat" />

            <div className="flex flex-1 flex-col gap-6 bg-background p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Monitoring Kenaikan Pangkat
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Pantau jadwal KP reguler, periode usul, dan status
                        eligibility pegawai.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total pegawai terpantau
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="tnum text-3xl font-bold">
                                {stats.total}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Sudah eligible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="tnum text-3xl font-bold text-emerald-700 dark:text-emerald-400">
                                {stats.sudahEligible}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Mendekati eligible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="tnum text-3xl font-bold text-amber-700 dark:text-amber-400">
                                {stats.mendekatiEligible}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Belum eligible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="tnum text-3xl font-bold text-slate-700 dark:text-slate-300">
                                {stats.belumEligible}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm md:flex-row md:items-end">
                    <div className="grid gap-2">
                        <label className="text-sm font-medium">Bulan</label>
                        <Select
                            value={bulanFilter || 'semua'}
                            onValueChange={(value) => {
                                const normalized =
                                    value === 'semua' ? '' : value;
                                setBulanFilter(normalized);
                                handleFilterChange({ bulan: normalized });
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
                                const normalized =
                                    value === 'semua' ? '' : value;
                                setTahunFilter(normalized);
                                handleFilterChange({ tahun: normalized });
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

                    <div className="grid gap-2">
                        <label
                            htmlFor="unit-kerja"
                            className="text-sm font-medium"
                        >
                            Unit Kerja
                        </label>
                        <select
                            id="unit-kerja"
                            value={unitKerjaFilter}
                            onChange={(e) => {
                                setUnitKerjaFilter(e.target.value);
                                handleFilterChange({
                                    unit_kerja: e.target.value,
                                });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Semua Unit</option>
                            {filterOptions.unitKerja.map((uk) => (
                                <option key={uk.id} value={uk.id}>
                                    {uk.nama}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <label
                            htmlFor="golongan"
                            className="text-sm font-medium"
                        >
                            Golongan
                        </label>
                        <select
                            id="golongan"
                            value={golonganFilter}
                            onChange={(e) => {
                                setGolonganFilter(e.target.value);
                                handleFilterChange({
                                    golongan: e.target.value,
                                });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Semua Gol</option>
                            {filterOptions.golongan.map((gol) => (
                                <option key={gol} value={gol}>
                                    Golongan {gol}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleExport}
                        className="mb-0.5"
                    >
                        <Download className="mr-2 h-4 w-4" />
                        Export Excel
                    </Button>
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
                                    Batas Usul
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
                                        colSpan={9}
                                        className="py-12 text-center font-medium text-muted-foreground"
                                    >
                                        Tidak ada data monitoring yang sesuai.
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
                                            {pegawai.pangkat_saat_ini !== null
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
                                            <div className="flex flex-col">
                                                <span>
                                                    {formatTanggal(
                                                        pegawai.batas_usul,
                                                    )}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {pegawai.sisa_hari_usul >= 0
                                                        ? `${pegawai.sisa_hari_usul} hari lagi`
                                                        : `${Math.abs(pegawai.sisa_hari_usul)} hari terlewati`}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    statusBadgeClass[
                                                        pegawai.status as StatusKp
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
                                                        href={`/kenaikan-pangkat/usulan/create?pegawai_id=${pegawai.id}&bulan=${filters.bulan ?? ''}&tahun=${filters.tahun ?? ''}`}
                                                    >
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
