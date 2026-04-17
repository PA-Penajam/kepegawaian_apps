import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Download } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import type { BreadcrumbItem } from '@/types';
import type { KepegawaianPaginatedData } from '@/types/kepegawaian';

type KgbStatus = 'Sudah Jatuh Tempo' | 'Segera' | 'Mendekati' | 'Aman';

type PegawaiMonitoringKgb = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
    pangkat_gol: string;
    tmt_pangkat: string | null;
    tanggal_kgb_berikutnya: string;
    sisa_hari: number;
    status: KgbStatus;
};

type UnitKerjaOption = { id: string; nama: string };
type FilterOptions = {
    unitKerja: UnitKerjaOption[];
    golongan: string[];
};

type Filters = {
    unit_kerja: string | null;
    golongan: string | null;
    status: string | null;
};

type Props = {
    pegawaiList: KepegawaianPaginatedData<PegawaiMonitoringKgb>;
    kgbStats: {
        total: number;
        jatuhTempo: number;
        segera: number;
        mendekati: number;
        aman: number;
    };
    filters: Filters;
    filterOptions: FilterOptions;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Monitoring KGB', href: '/kepegawaian/monitoring/kgb' },
];

const statusBadgeClass: Record<KgbStatus, string> = {
    'Sudah Jatuh Tempo': 'bg-red-100 text-red-700 border-red-200 hover:bg-red-100',
    Segera: 'bg-orange-100 text-orange-700 border-orange-200 hover:bg-orange-100',
    Mendekati: 'bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-100',
    Aman: 'bg-emerald-100 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
};

function formatDate(date: string | null): string {
    if (date === null) return '-';
    const parsed = new Date(date);
    return Number.isNaN(parsed.getTime())
        ? '-'
        : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parsed);
}

function applyFilter(newFilters: Partial<Filters>) {
    const params: Record<string, string> = {};
    const merged = { ...newFilters };
    if (merged.unit_kerja) params.unit_kerja = merged.unit_kerja;
    if (merged.golongan) params.golongan = merged.golongan;
    if (merged.status) params.status = merged.status;
    router.get('/kepegawaian/monitoring/kgb', params, { preserveState: true, replace: true });
}

export default function MonitoringKgbIndex({ pegawaiList, kgbStats, filters, filterOptions }: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    function handleFilterChange(key: keyof Filters, value: string) {
        const updated = { ...localFilters, [key]: value === 'semua' || value === '' ? null : value };
        setLocalFilters(updated);
        applyFilter(updated);
    }

    function handleExport() {
        const params = new URLSearchParams();
        if (localFilters.unit_kerja) params.set('unit_kerja', localFilters.unit_kerja);
        if (localFilters.golongan) params.set('golongan', localFilters.golongan);
        if (localFilters.status) params.set('status', localFilters.status);
        window.location.href = `/kepegawaian/monitoring/kgb/export?${params.toString()}`;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monitoring KGB" />

            <div className="space-y-6 p-4 sm:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Monitoring Kenaikan Gaji Berkala
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pantau pegawai yang mendekati atau sudah jatuh tempo KGB.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total</CardDescription>
                            <CardTitle>{kgbStats.total}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Jatuh Tempo</CardDescription>
                            <CardTitle>{kgbStats.jatuhTempo}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Segera</CardDescription>
                            <CardTitle>{kgbStats.segera}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Mendekati</CardDescription>
                            <CardTitle>{kgbStats.mendekati}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Monitoring KGB</CardTitle>
                        <CardDescription>Data pegawai disusun berdasarkan sisa hari terdekat.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:flex-wrap">
                            <div className="grid gap-1.5">
                                <label htmlFor="filter-unit-kerja" className="text-sm font-medium">
                                    Unit Kerja
                                </label>
                                <select
                                    id="filter-unit-kerja"
                                    value={localFilters.unit_kerja ?? ''}
                                    onChange={(e) => handleFilterChange('unit_kerja', e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-48"
                                >
                                    <option value="">Semua Unit</option>
                                    {filterOptions.unitKerja.map((uk) => (
                                        <option key={uk.id} value={uk.id}>{uk.nama}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <label htmlFor="filter-golongan" className="text-sm font-medium">
                                    Golongan
                                </label>
                                <select
                                    id="filter-golongan"
                                    value={localFilters.golongan ?? ''}
                                    onChange={(e) => handleFilterChange('golongan', e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-36"
                                >
                                    <option value="">Semua Gol</option>
                                    {filterOptions.golongan.map((gol) => (
                                        <option key={gol} value={gol}>Golongan {gol}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <label htmlFor="filter-status" className="text-sm font-medium">
                                    Status
                                </label>
                                <select
                                    id="filter-status"
                                    value={localFilters.status ?? 'semua'}
                                    onChange={(e) => handleFilterChange('status', e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-40"
                                >
                                    <option value="semua">Semua Status</option>
                                    <option value="jatuh-tempo">Jatuh Tempo</option>
                                    <option value="segera">Segera</option>
                                    <option value="mendekati">Mendekati</option>
                                    <option value="aman">Aman</option>
                                </select>
                            </div>

                            <div className="grid gap-1.5">
                                <label className="text-sm font-medium">Aksi</label>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleExport}
                                    className="h-9"
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Export Excel
                                </Button>
                            </div>
                        </div>

                        <div className="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>NIP</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Pangkat/Gol</TableHead>
                                        <TableHead>TMT Pangkat</TableHead>
                                        <TableHead>KGB Berikutnya</TableHead>
                                        <TableHead>Sisa Hari</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pegawaiList.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                Tidak ada data monitoring KGB.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        pegawaiList.data.map((pegawai: PegawaiMonitoringKgb) => (
                                            <TableRow key={pegawai.id}>
                                                <TableCell className="font-medium">
                                                    {pegawai.nip ?? '-'}
                                                </TableCell>
                                                <TableCell>{pegawai.nama_lengkap}</TableCell>
                                                <TableCell>{pegawai.pangkat_gol || '-'}</TableCell>
                                                <TableCell>{formatDate(pegawai.tmt_pangkat)}</TableCell>
                                                <TableCell>{formatDate(pegawai.tanggal_kgb_berikutnya)}</TableCell>
                                                <TableCell>{pegawai.sisa_hari}</TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className={statusBadgeClass[pegawai.status]}
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

                        <PaginationWrapper links={pegawaiList.links} lastPage={pegawaiList.last_page} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
