import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    pegawai: { nip: string; nama: string };
    currentSaldo: number;
    open: boolean;
    onClose: () => void;
};

export function DialogAdjustSaldo({ pegawai, currentSaldo, open, onClose }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        pegawai_nip: pegawai.nip,
        jenis_cuti_kode: 'CT',
        tahun: new Date().getFullYear(),
        jumlah_hari: 0,
        keterangan: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(route('admin.cuti.saldo.adjust'), {
            onSuccess: () => {
                reset();
                onClose();
            },
            preserveScroll: true,
        });
    }

    // Hitung saldo setelah penyesuaian
    const saldoSetelah = currentSaldo + (data.jumlah_hari ?? 0);

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Penyesuaian Saldo Cuti</DialogTitle>
                    <DialogDescription>
                        Sesuaikan saldo cuti untuk <strong>{pegawai.nama}</strong> ({pegawai.nip})
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Informasi saldo saat ini */}
                    <div className="rounded-lg border bg-muted/50 p-3">
                        <p className="text-sm text-muted-foreground">Saldo saat ini</p>
                        <p className="text-2xl font-bold">{currentSaldo} hari</p>
                    </div>

                    {/* Jenis Cuti - hanya CT pada MVP */}
                    <div className="space-y-2">
                        <Label htmlFor="jenis_cuti_kode">Jenis Cuti</Label>
                        <Input
                            id="jenis_cuti_kode"
                            value="CT — Cuti Tahunan"
                            disabled
                        />
                    </div>

                    {/* Tahun Hak */}
                    <div className="space-y-2">
                        <Label htmlFor="tahun">Tahun Hak</Label>
                        <Input
                            id="tahun"
                            type="number"
                            min={2020}
                            value={data.tahun}
                            onChange={(e) => setData('tahun', parseInt(e.target.value) || 0)}
                        />
                        <InputError message={errors.tahun} />
                    </div>

                    {/* Jumlah Hari */}
                    <div className="space-y-2">
                        <Label htmlFor="jumlah_hari">Jumlah Hari (positif = tambah, negatif = kurang)</Label>
                        <Input
                            id="jumlah_hari"
                            type="number"
                            value={data.jumlah_hari}
                            onChange={(e) => setData('jumlah_hari', parseInt(e.target.value) || 0)}
                        />
                        <InputError message={errors.jumlah_hari} />
                        {data.jumlah_hari !== 0 && (
                            <p className="text-xs text-muted-foreground">
                                Saldo setelah penyesuaian: <strong>{saldoSetelah} hari</strong>
                            </p>
                        )}
                    </div>

                    {/* Keterangan */}
                    <div className="space-y-2">
                        <Label htmlFor="keterangan">Keterangan (wajib, min. 10 karakter)</Label>
                        <Textarea
                            id="keterangan"
                            rows={3}
                            placeholder="Jelaskan alasan penyesuaian saldo..."
                            value={data.keterangan}
                            onChange={(e) => setData('keterangan', e.target.value)}
                        />
                        <InputError message={errors.keterangan} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || data.jumlah_hari === 0 || data.keterangan.length < 10}
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Penyesuaian'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
