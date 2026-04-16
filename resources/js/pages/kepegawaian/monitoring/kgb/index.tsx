import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import type { PaginatedData } from '@/types/kepegawaian';

type KgbStatus = 'Sudah Jatuh Tempo' | 'Segera' | 'Mendekati' | 'Aman';
type StatusFilter = 'semua' | 'jatuh-tempo' | 'segera' | 'mendekati' | 'aman';

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

type Props = {
    pegawaiList: PaginatedData<PegawaiMonitoringKgb>;
    kgbStats: {
        total: number;
        jatuhTempo: number;
        segera: number;
        mendekati: number;
        aman: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Monitoring KGB',
        href: '/kepegawaian/monitoring/kgb',
    },
];

const filterMap: Record<StatusFilter, KgbStatus | null> = {
    semua: null,
    'jatuh-tempo': 'Sudah Jatuh Tempo',
    segera: 'Segera',
    mendekati: 'Mendekati',
    aman: 'Aman',
};

const statusBadgeClass: Record<KgbStatus, string> = {
    'Sudah Jatuh Tempo':
        'bg-red-100 text-red-700 border-red-200 hover:bg-red-100',
    Segera: 'bg-orange-100 text-orange-700 border-orange-200 hover:bg-orange-100',
    Mendekati:
        'bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-100',
    Aman: 'bg-emerald-100 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
};

function formatDate(date: string | null): string {
    if (date === null) {
        return '-';
    }

    const parsedDate = new Date(date);

    return Number.isNaN(parsedDate.getTime())
        ? '-'
        : new Intl.DateTimeFormat('id-ID', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          }).format(parsedDate);
}

export default function MonitoringKgbIndex({ pegawaiList, kgbStats }: Props) {
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('semua');

    const filteredPegawai = useMemo(() => {
        const selectedStatus = filterMap[statusFilter];

        if (selectedStatus === null) {
            return pegawaiList.data;
        }

        return pegawaiList.data.filter(
            (pegawai) => pegawai.status === selectedStatus,
        );
    }, [pegawaiList.data, statusFilter]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Monitoring KGB" />

            <div className="space-y-6 p-4 sm:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Monitoring Kenaikan Gaji Berkala
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pantau pegawai yang mendekati atau sudah jatuh tempo
                        KGB.
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
                        <CardDescription>
                            Data pegawai disusun berdasarkan sisa hari terdekat.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex w-full flex-col gap-2 sm:w-64">
                            <label
                                htmlFor="status-filter"
                                className="text-sm font-medium"
                            >
                                Filter status
                            </label>
                            <select
                                id="status-filter"
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(
                                        event.target.value as StatusFilter,
                                    )
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="semua">Semua</option>
                                <option value="jatuh-tempo">Jatuh Tempo</option>
                                <option value="segera">Segera</option>
                                <option value="mendekati">Mendekati</option>
                                <option value="aman">Aman</option>
                            </select>
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
                                    {filteredPegawai.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                Tidak ada data monitoring KGB.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filteredPegawai.map((pegawai) => (
                                            <TableRow key={pegawai.id}>
                                                <TableCell className="font-medium">
                                                    {pegawai.nip ?? '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {pegawai.nama_lengkap}
                                                </TableCell>
                                                <TableCell>
                                                    {pegawai.pangkat_gol || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        pegawai.tmt_pangkat,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        pegawai.tanggal_kgb_berikutnya,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {pegawai.sisa_hari}
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
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
