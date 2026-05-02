import { Head, Link } from '@inertiajs/react';
import { CalendarPlus, Eye } from 'lucide-react';
import { KartuSaldo } from '@/components/cuti/KartuSaldo';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatTanggal } from '@/lib/cuti-utils';
import type { BreadcrumbItem } from '@/types';
import type {
    CutiPaginatedData,
    CutiPengajuan,
    CutiState,
    SaldoBucketData,
} from '@/types/cuti';
import { CutiStateBadgeVariant, CutiStateLabels } from '@/types/cuti';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti Saya', href: '/cuti/saya' },
];

type Props = {
    saldo: SaldoBucketData;
    pengajuanList: CutiPaginatedData<CutiPengajuan>;
};

export default function MyDashboard({ saldo, pengajuanList }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuti Saya" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                {/* Header dengan tombol ajukan cuti */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Cuti Saya</h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola pengajuan cuti dan lihat saldo Anda.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/cuti/pengajuan/baru">
                            <CalendarPlus className="h-4 w-4" />
                            Ajukan Cuti Baru
                        </Link>
                    </Button>
                </div>

                {/* Kartu Saldo */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <KartuSaldo saldo={saldo} />
                </div>

                {/* Riwayat Pengajuan */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Riwayat Pengajuan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pengajuanList.data.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Belum ada pengajuan cuti.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nomor</TableHead>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Durasi</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pengajuanList.data.map((p: CutiPengajuan) => {
                                        const state = p.state as CutiState;
                                        const label = CutiStateLabels[state] ?? state;

                                        return (
                                            <TableRow key={p.id}>
                                                <TableCell className="font-mono text-xs">
                                                    {p.nomor_pengajuan ?? '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {p.jenis_cuti?.nama ?? p.jenis_cuti_kode}
                                                </TableCell>
                                                <TableCell>
                                                    {formatTanggal(p.tanggal_mulai)} &ndash;{' '}
                                                    {formatTanggal(p.tanggal_selesai)}
                                                </TableCell>
                                                <TableCell>{p.jumlah_hari_kerja} hari</TableCell>
                                                <TableCell>
                                                    <Badge variant={CutiStateBadgeVariant[state]}>
                                                        {label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-xs"
                                                        asChild
                                                        aria-label="Lihat detail pengajuan"
                                                    >
                                                        <Link href={`/cuti/pengajuan/${p.id}`}>
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}

                        <PaginationWrapper links={pengajuanList.links} lastPage={pengajuanList.last_page} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
