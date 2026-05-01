import { Head, Link } from '@inertiajs/react';
import { CalendarPlus, Eye } from 'lucide-react';
import { KartuSaldo } from '@/components/cuti/KartuSaldo';
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
import { cn } from '@/lib/utils';
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

/**
 * Format tanggal ke format Indonesia.
 */
function formatTanggal(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

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
                                                    <Button variant="ghost" size="icon-xs" asChild>
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

                        {/* Pagination sederhana */}
                        {pengajuanList.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-center gap-2">
                                {pengajuanList.links.map((link: { url: string | null; label: string; active: boolean }, i: number) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        className={cn(
                                            'rounded-md border-2 border-foreground px-3 py-1 text-xs font-bold transition-all',
                                            link.active
                                                ? 'bg-primary text-primary-foreground shadow-[2px_2px_0_rgba(0,0,0,1)]'
                                                : 'bg-background hover:bg-accent',
                                            !link.url && 'pointer-events-none opacity-40',
                                        )}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
