import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IamPaginatedData } from '@/types';

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
    periode: string | null;
};
type KpFilterOptions = {
    unitKerja: UnitKerjaOption[];
    golongan: string[];
};

type Props = {
    pegawaiList: IamPaginatedData<PegawaiMonitoringRow>;
    selectedPeriode: string | null;
    kpStats: {
        total: number;
        sudahEligible: number;
        mendekatiEligible: number;
        belumEligible: number;
    };
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
        'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-50',
    'Mendekati Eligible':
        'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-50',
    'Belum Eligible':
        'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-100',
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
    selectedPeriode,
    kpStats,
    filters,
    filterOptions,
}: Props) {
    const [statusFilter, setStatusFilter] = useState<'semua' | StatusKp>(
        'semua',
    );
    const [periodeFilter, setPeriodeFilter] = useState<
        'semua' | 'april' | 'oktober'
    >(
        selectedPeriode === 'april' || selectedPeriode === 'oktober'
            ? selectedPeriode
            : 'semua',
    );
    const [unitKerjaFilter, setUnitKerjaFilter] = useState(
        filters.unit_kerja ?? '',
    );
    const [golonganFilter, setGolonganFilter] = useState(
        filters.golongan ?? '',
    );

    function handleFilterChange(newParams: Record<string, string>) {
        const params: Record<string, string> = {};
        const resolved = {
            unit_kerja: unitKerjaFilter,
            golongan: golonganFilter,
            periode: periodeFilter !== 'semua' ? periodeFilter : '',
            ...newParams,
        };
        if (resolved.unit_kerja) params.unit_kerja = resolved.unit_kerja;
        if (resolved.golongan) params.golongan = resolved.golongan;
        if (resolved.periode) params.periode = resolved.periode;
        router.get('/kepegawaian/monitoring/kenaikan-pangkat', params, {
            preserveState: true,
            replace: true,
        });
    }

    const filteredList = useMemo(() => {
        if (statusFilter === 'semua') {
            return pegawaiList.data;
        }

        return pegawaiList.data.filter((item: PegawaiMonitoringRow) => item.status === statusFilter);
    }, [pegawaiList.data, statusFilter]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monitoring Kenaikan Pangkat" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Monitoring kenaikan pangkat"
                    description="Pantau jadwal KP reguler, periode usul, dan status eligibility pegawai."
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Total pegawai terpantau
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold">
                                {kpStats.total}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Sudah eligible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold text-emerald-700">
                                {kpStats.sudahEligible}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Mendekati eligible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold text-amber-700">
                                {kpStats.mendekatiEligible}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                Belum eligible
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-semibold text-slate-700">
                                {kpStats.belumEligible}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-card p-4 md:flex-row md:items-end">
                    <div className="grid gap-2">
                        <label
                            htmlFor="periode"
                            className="text-sm font-medium"
                        >
                            Filter periode
                        </label>
                        <select
                            id="periode"
                            value={periodeFilter}
                            onChange={(event) => {
                                const value = event.target.value as
                                    | 'semua'
                                    | 'april'
                                    | 'oktober';
                                setPeriodeFilter(value);
                                handleFilterChange({
                                    periode: value === 'semua' ? '' : value,
                                });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="semua">Semua</option>
                            <option value="april">April</option>
                            <option value="oktober">Oktober</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <label htmlFor="unit-kerja" className="text-sm font-medium">
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
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                        <label htmlFor="golongan" className="text-sm font-medium">
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
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Semua Gol</option>
                            {filterOptions.golongan.map((gol) => (
                                <option key={gol} value={gol}>
                                    Golongan {gol}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <label htmlFor="status" className="text-sm font-medium">
                            Filter status
                        </label>
                        <select
                            id="status"
                            value={statusFilter}
                            onChange={(event) =>
                                setStatusFilter(
                                    event.target.value as 'semua' | StatusKp,
                                )
                            }
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="semua">Semua</option>
                            <option value="Sudah Eligible">
                                Sudah Eligible
                            </option>
                            <option value="Mendekati Eligible">
                                Mendekati Eligible
                            </option>
                            <option value="Belum Eligible">
                                Belum Eligible
                            </option>
                        </select>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>NIP</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Pangkat Saat Ini</TableHead>
                                <TableHead>TMT Pangkat</TableHead>
                                <TableHead>TMT KP Berikutnya</TableHead>
                                <TableHead>Periode Usul</TableHead>
                                <TableHead>Batas Usul</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredList.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        Tidak ada data monitoring yang sesuai.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filteredList.map((pegawai) => (
                                    <TableRow key={pegawai.id}>
                                        <TableCell>
                                            {pegawai.nip ?? '-'}
                                        </TableCell>
                                        <TableCell className="font-medium">
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
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationWrapper meta={pegawaiList.meta} />
            </div>
        </AppLayout>
    );
}
