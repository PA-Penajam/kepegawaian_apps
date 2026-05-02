import { Head, usePage } from '@inertiajs/react';
import { Inbox as InboxIcon } from 'lucide-react';
import { useState } from 'react';
import { DialogApprove } from '@/components/cuti/DialogApprove';
import { DialogReject } from '@/components/cuti/DialogReject';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { toUrl } from '@/lib/utils';
import { inbox as inboxRoute } from '@/routes/cuti';
import type { BreadcrumbItem, Auth } from '@/types';
import type { CutiPengajuan, ApproverRole, CutiState } from '@/types/cuti';
import { CutiStateLabels, CutiStateBadgeVariant } from '@/types/cuti';
import type { KepegawaianPaginatedData } from '@/types/kepegawaian';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti', href: '/cuti/saya' },
    { title: 'Inbox Persetujuan', href: toUrl(inboxRoute()) },
];

type Props = {
    pengajuanList: KepegawaianPaginatedData<CutiPengajuan>;
};

/**
 * Menentukan role approver berdasarkan permissions user.
 */
function resolveRole(permissions: string[]): ApproverRole {
    if (permissions.includes('cuti.pengajuan.approve-pejabat')) {
        return 'pejabat_berwenang';
    }

    if (permissions.includes('cuti.pengajuan.approve-langsung')) {
        return 'atasan_langsung';
    }

    return 'petugas_kepegawaian';
}

/**
 * Format tanggal ke format singkat Indonesia.
 */

/**
 * Label tombol aksi berdasarkan role.
 */
function getApproveButtonLabel(role: ApproverRole): string {
    if (role === 'petugas_kepegawaian') {
return 'Verifikasi';
}

    return 'Setujui';
}

export default function InboxPage({ pengajuanList }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const role = resolveRole(auth.user.permissions ?? []);

    // State untuk dialog approve & reject
    const [selectedPengajuan, setSelectedPengajuan] = useState<CutiPengajuan | null>(null);
    const [showApproveDialog, setShowApproveDialog] = useState(false);
    const [showRejectDialog, setShowRejectDialog] = useState(false);

    function handleOpenApprove(pengajuan: CutiPengajuan) {
        setSelectedPengajuan(pengajuan);
        setShowApproveDialog(true);
    }

    function handleOpenReject(pengajuan: CutiPengajuan) {
        setSelectedPengajuan(pengajuan);
        setShowRejectDialog(true);
    }

    function handleCloseDialogs() {
        setShowApproveDialog(false);
        setShowRejectDialog(false);
        setSelectedPengajuan(null);
    }

    const items = pengajuanList.data;
    const hasItems = items.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inbox Persetujuan Cuti" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Inbox Persetujuan Cuti
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Daftar pengajuan cuti yang menunggu tindakan Anda.
                    </p>
                </div>

                {hasItems ? (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nomor</TableHead>
                                    <TableHead>Pegawai</TableHead>
                                    <TableHead>Jenis Cuti</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead className="text-center">Durasi</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            {item.nomor_pengajuan}
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                <p className="font-medium">
                                                    {item.pegawai?.nama_lengkap ?? '-'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.pegawai?.nip ?? '-'}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {item.jenis_cuti?.nama ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <span className="text-sm">
                                                {formatTanggal(item.tanggal_mulai)} — {formatTanggal(item.tanggal_selesai)}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {item.jumlah_hari_kerja} hari
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={CutiStateBadgeVariant[item.state as CutiState] ?? 'outline'}
                                            >
                                                {CutiStateLabels[item.state as CutiState] ?? item.state}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() => handleOpenApprove(item)}
                                                >
                                                    {getApproveButtonLabel(role)}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => handleOpenReject(item)}
                                                >
                                                    Tolak
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <PaginationWrapper
                            links={pengajuanList.links}
                            lastPage={pengajuanList.last_page}
                        />
                    </>
                ) : (
                    /* Empty state */
                    <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-dashed p-12">
                        <InboxIcon className="h-12 w-12 text-muted-foreground/50" />
                        <div className="text-center">
                            <p className="font-medium text-muted-foreground">
                                Tidak ada pengajuan yang menunggu persetujuan
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground/70">
                                Semua pengajuan cuti sudah ditindaklanjuti.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {/* Dialog Approve / Verifikasi */}
            {selectedPengajuan && showApproveDialog && (
                <DialogApprove
                    pengajuan={selectedPengajuan}
                    role={role}
                    open={showApproveDialog}
                    onClose={handleCloseDialogs}
                />
            )}

            {/* Dialog Reject */}
            {selectedPengajuan && showRejectDialog && (
                <DialogReject
                    pengajuan={selectedPengajuan}
                    open={showRejectDialog}
                    onClose={handleCloseDialogs}
                />
            )}
        </AppLayout>
    );
}
