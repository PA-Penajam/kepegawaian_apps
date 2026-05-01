import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Ban, Download, FileText, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { DialogCancel } from '@/components/cuti/DialogCancel';
import { TimelineApproval } from '@/components/cuti/TimelineApproval';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { formatTanggal, formatTanggalDateTime } from '@/lib/cuti-utils';
import type { BreadcrumbItem } from '@/types';
import type { CutiPengajuan, CutiState } from '@/types/cuti';
import { CutiStateBadgeVariant, CutiStateLabels } from '@/types/cuti';

type Props = {
    pengajuan: CutiPengajuan;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti Saya', href: '/cuti/saya' },
    { title: 'Detail Pengajuan', href: '#' },
];

/**
 * Format ukuran file ke format yang mudah dibaca.
 */
function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
return `${bytes} B`;
}

    if (bytes < 1024 * 1024) {
return `${(bytes / 1024).toFixed(1)} KB`;
}

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function PengajuanShow({ pengajuan }: Props) {
    // Mode dialog: null = tertutup, 'cancel' = batalkan, 'revoke' = cabut cuti
    const [cancelMode, setCancelMode] = useState<'cancel' | 'revoke' | null>(null);

    const state = pengajuan.state as CutiState;
    const stateLabel = CutiStateLabels[state] ?? state;

    // Cek apakah bisa dibatalkan (state DRAFT)
    const canCancel = state === 'DRAFT';

    // Cek apakah bisa dicabut (state DISETUJUI dan jenis boleh dicabut)
    const canRevoke = state === 'DISETUJUI';

    // Handler untuk batalkan — buka dialog konfirmasi
    const handleCancel = () => setCancelMode('cancel');

    // Handler untuk cabut cuti — buka dialog konfirmasi
    const handleRevoke = () => setCancelMode('revoke');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pengajuan ${pengajuan.nomor_pengajuan ?? pengajuan.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon-sm" asChild>
                            <Link href="/cuti/saya">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                Detail Pengajuan Cuti
                            </h1>
                            <p className="font-mono text-sm text-muted-foreground">
                                {pengajuan.nomor_pengajuan ?? 'Belum ada nomor'}
                            </p>
                        </div>
                    </div>

                    {/* Tombol aksi */}
                    <div className="flex gap-2">
                        {canCancel && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleCancel}
                            >
                                <Ban className="h-4 w-4" />
                                Batalkan
                            </Button>
                        )}
                        {canRevoke && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleRevoke}
                            >
                                <Undo2 className="h-4 w-4" />
                                Cabut Cuti
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/cuti/pengajuan/${pengajuan.id}/pdf`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Download className="h-4 w-4" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom utama: detail pengajuan */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">Informasi Pengajuan</CardTitle>
                                    <Badge variant={CutiStateBadgeVariant[state]}>
                                        {stateLabel}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Jenis Cuti</p>
                                        <p className="text-sm font-medium">
                                            {pengajuan.jenis_cuti?.nama ?? pengajuan.jenis_cuti_kode}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Durasi</p>
                                        <p className="text-sm font-medium">
                                            {pengajuan.jumlah_hari_kerja} hari kerja
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Tanggal Mulai</p>
                                        <p className="text-sm font-medium">
                                            {formatTanggal(pengajuan.tanggal_mulai)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Tanggal Selesai</p>
                                        <p className="text-sm font-medium">
                                            {formatTanggal(pengajuan.tanggal_selesai)}
                                        </p>
                                    </div>
                                </div>

                                <Separator />

                                <div>
                                    <p className="text-xs text-muted-foreground">Alasan</p>
                                    <p className="mt-1 text-sm">{pengajuan.alasan}</p>
                                </div>

                                {pengajuan.alamat_selama_cuti && (
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Alamat Selama Cuti
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {pengajuan.alamat_selama_cuti}
                                        </p>
                                    </div>
                                )}

                                {pengajuan.nomor_telp_selama_cuti && (
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            No. Telepon Selama Cuti
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {pengajuan.nomor_telp_selama_cuti}
                                        </p>
                                    </div>
                                )}

                                {pengajuan.rejection_reason && (
                                    <>
                                        <Separator />
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950">
                                            <p className="text-xs font-semibold text-red-600 dark:text-red-400">
                                                Alasan Penolakan
                                            </p>
                                            <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                                                {pengajuan.rejection_reason}
                                            </p>
                                        </div>
                                    </>
                                )}

                                {/* Timestamp */}
                                <Separator />
                                <div className="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                    {pengajuan.submitted_at && (
                                        <p>Diajukan: {formatTanggalDateTime(pengajuan.submitted_at)}</p>
                                    )}
                                    {pengajuan.approved_at && (
                                        <p>Disetujui: {formatTanggalDateTime(pengajuan.approved_at)}</p>
                                    )}
                                    {pengajuan.rejected_at && (
                                        <p>Ditolak: {formatTanggalDateTime(pengajuan.rejected_at)}</p>
                                    )}
                                    {pengajuan.cancelled_at && (
                                        <p>Dibatalkan: {formatTanggalDateTime(pengajuan.cancelled_at)}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Lampiran */}
                        {pengajuan.lampiran && pengajuan.lampiran.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Lampiran</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {pengajuan.lampiran.map((file) => (
                                            <div
                                                key={file.id}
                                                className="flex items-center justify-between rounded-lg border p-3"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <FileText className="h-5 w-5 text-muted-foreground" />
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {file.nama_file_asli}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatFileSize(file.size_bytes)} &middot;{' '}
                                                            {file.mime_type}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Kolom samping: timeline approval */}
                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Riwayat Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TimelineApproval
                                    stateHistory={pengajuan.state_history ?? []}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Dialog konfirmasi pembatalan / pencabutan cuti */}
            <DialogCancel
                pengajuan={pengajuan}
                open={cancelMode !== null}
                onClose={() => setCancelMode(null)}
            />
        </AppLayout>
    );
}
