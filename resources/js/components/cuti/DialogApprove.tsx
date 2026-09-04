import { useForm } from '@inertiajs/react';
import {
    verify,
    approveAtasan,
    approvePejabat,
} from '@/actions/App/Http/Controllers/Cuti/ApprovalController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatTanggal } from '@/lib/cuti-utils';
import type { CutiPengajuan, ApproverRole } from '@/types/cuti';
import { CutiStateLabels } from '@/types/cuti';

type Props = {
    pengajuan: CutiPengajuan;
    role: ApproverRole;
    open: boolean;
    onClose: () => void;
};

/**
 * Menentukan URL tujuan berdasarkan role approver.
 */
function getApproveUrl(role: ApproverRole, id: string): string {
    switch (role) {
        case 'petugas_kepegawaian':
            return verify.url(id);
        case 'atasan_langsung':
            return approveAtasan.url(id);
        case 'pejabat_berwenang':
            return approvePejabat.url(id);
    }
}

/**
 * Menentukan label aksi berdasarkan role.
 */
function getActionLabel(role: ApproverRole): string {
    switch (role) {
        case 'petugas_kepegawaian':
            return 'Verifikasi';
        case 'atasan_langsung':
            return 'Setujui (Atasan)';
        case 'pejabat_berwenang':
            return 'Setujui (Pejabat)';
    }
}

export function DialogApprove({ pengajuan, role, open, onClose }: Props) {
    const { data, setData, post, processing, reset } = useForm({
        catatan: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(getApproveUrl(role, pengajuan.id), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {getActionLabel(role)} Pengajuan Cuti
                    </DialogTitle>
                    <DialogDescription>
                        Tinjau dan setujui pengajuan cuti berikut.
                    </DialogDescription>
                </DialogHeader>

                {/* Ringkasan pengajuan */}
                <div className="space-y-2 rounded-lg border p-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Nomor</span>
                        <span className="font-medium">
                            {pengajuan.nomor_pengajuan}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Pegawai</span>
                        <span className="font-medium">
                            {pengajuan.pegawai?.nama_lengkap ?? '-'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">
                            Jenis Cuti
                        </span>
                        <span className="font-medium">
                            {pengajuan.jenis_cuti?.nama ?? '-'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Tanggal</span>
                        <span className="font-medium">
                            {formatTanggal(pengajuan.tanggal_mulai)} —{' '}
                            {formatTanggal(pengajuan.tanggal_selesai)}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Durasi</span>
                        <span className="font-medium">
                            {pengajuan.jumlah_hari_kerja} hari kerja
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Status</span>
                        <span className="font-medium">
                            {CutiStateLabels[pengajuan.state] ??
                                pengajuan.state}
                        </span>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="catatan">Catatan (opsional)</Label>
                        <Textarea
                            id="catatan"
                            value={data.catatan}
                            onChange={(e) => setData('catatan', e.target.value)}
                            placeholder="Tambahkan catatan jika diperlukan..."
                            rows={3}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button type="submit" processing={processing}>
                            {getActionLabel(role)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
