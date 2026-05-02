import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { cancel } from '@/actions/App/Http/Controllers/Cuti/ApprovalController';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatTanggal } from '@/lib/cuti-utils';
import type { CutiPengajuan } from '@/types/cuti';
import { CutiStateLabels } from '@/types/cuti';

type Props = {
    pengajuan: CutiPengajuan;
    open: boolean;
    onClose: () => void;
};


export function DialogCancel({ pengajuan, open, onClose }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        alasan: '',
    });

    // Cek apakah cuti sudah berjalan (state DISETUJUI)
    const isSudahBerjalan = pengajuan.state === 'DISETUJUI';

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(cancel.url(pengajuan.id), {
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
                    <DialogTitle>{isSudahBerjalan ? 'Cabut Cuti yang Disetujui' : 'Batalkan Pengajuan Cuti'}</DialogTitle>
                    <DialogDescription>
                        {isSudahBerjalan
                            ? 'Anda akan mencabut cuti yang sudah disetujui. Saldo yang belum terpakai akan dikembalikan secara proporsional.'
                            : 'Anda akan membatalkan pengajuan cuti berikut.'}
                    </DialogDescription>
                </DialogHeader>

                {/* Ringkasan pengajuan */}
                <div className="space-y-2 rounded-lg border p-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Nomor</span>
                        <span className="font-medium">{pengajuan.nomor_pengajuan}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Pegawai</span>
                        <span className="font-medium">
                            {pengajuan.pegawai?.nama_lengkap ?? '-'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Jenis Cuti</span>
                        <span className="font-medium">
                            {pengajuan.jenis_cuti?.nama ?? '-'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Tanggal</span>
                        <span className="font-medium">
                            {formatTanggal(pengajuan.tanggal_mulai)} — {formatTanggal(pengajuan.tanggal_selesai)}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Status</span>
                        <span className="font-medium">
                            {CutiStateLabels[pengajuan.state] ?? pengajuan.state}
                        </span>
                    </div>
                </div>

                {/* Peringatan jika cuti sudah berjalan */}
                {isSudahBerjalan && (
                    <div className="flex items-start gap-3 rounded-lg border border-orange-300 bg-orange-50 p-3 text-sm dark:border-orange-800 dark:bg-orange-950">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-orange-600 dark:text-orange-400" />
                        <p className="text-orange-800 dark:text-orange-200">
                            Perhatian: Cuti ini sudah berjalan. Saldo yang belum
                            terpakai akan dikembalikan secara proporsional.
                        </p>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="alasan-cancel">
                            Alasan Pembatalan <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="alasan-cancel"
                            value={data.alasan}
                            onChange={(e) => setData('alasan', e.target.value)}
                            placeholder="Tuliskan alasan pembatalan..."
                            rows={3}
                            className={errors.alasan ? 'border-destructive' : ''}
                        />
                        {errors.alasan && (
                            <p className="text-xs text-destructive">{errors.alasan}</p>
                        )}
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
                        <Button type="submit" variant="destructive" processing={processing}>
                            {isSudahBerjalan ? 'Cabut Cuti' : 'Batalkan Cuti'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
