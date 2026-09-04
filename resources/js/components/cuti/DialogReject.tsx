import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { reject } from '@/actions/App/Http/Controllers/Cuti/ApprovalController';
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
import type { CutiPengajuan } from '@/types/cuti';

type Props = {
    pengajuan: CutiPengajuan;
    open: boolean;
    onClose: () => void;
};

const MIN_ALASAN_LENGTH = 10;

export function DialogReject({ pengajuan, open, onClose }: Props) {
    const [localError, setLocalError] = useState('');
    const { data, setData, post, processing, errors, reset } = useForm({
        alasan: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        // Validasi lokal sebelum kirim
        if (data.alasan.trim().length < MIN_ALASAN_LENGTH) {
            setLocalError(
                `Alasan penolakan minimal ${MIN_ALASAN_LENGTH} karakter.`,
            );

            return;
        }

        setLocalError('');
        post(reject.url(pengajuan.id), {
            onSuccess: () => {
                reset();
                setLocalError('');
                onClose();
            },
        });
    }

    const currentLength = data.alasan.trim().length;
    const displayError = localError || errors.alasan;

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tolak Pengajuan Cuti</DialogTitle>
                    <DialogDescription>
                        Berikan alasan penolakan untuk pengajuan cuti ini.
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
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="alasan">
                            Alasan Penolakan{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="alasan"
                            value={data.alasan}
                            onChange={(e) => {
                                setData('alasan', e.target.value);

                                if (localError) {
                                    setLocalError('');
                                }
                            }}
                            placeholder="Tuliskan alasan penolakan (minimal 10 karakter)..."
                            rows={4}
                            className={displayError ? 'border-destructive' : ''}
                        />
                        <div className="flex items-center justify-between">
                            {displayError ? (
                                <p className="text-xs text-destructive">
                                    {displayError}
                                </p>
                            ) : (
                                <span />
                            )}
                            <p className="text-xs text-muted-foreground">
                                {currentLength}/{MIN_ALASAN_LENGTH} karakter
                            </p>
                        </div>
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
                        <Button
                            type="submit"
                            variant="destructive"
                            processing={processing}
                        >
                            Tolak Pengajuan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
