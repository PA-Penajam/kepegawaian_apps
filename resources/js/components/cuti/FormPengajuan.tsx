import { useForm } from '@inertiajs/react';
import type { FormEventHandler} from 'react';
import { useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { errorsToArray } from '@/lib/form-errors';
import type { CutiJenisMaster } from '@/types/cuti';

type SaldoData = Record<string, number>;

type Props = {
    jenisCutiList: CutiJenisMaster[];
    saldoData: SaldoData;
};

/**
 * Form pengajuan cuti baru.
 * Menggunakan Inertia useForm untuk submit data termasuk file lampiran.
 */
export function FormPengajuan({ jenisCutiList, saldoData }: Props) {
    const { data, setData, post, processing, errors, reset, progress } = useForm<{
        jenis_cuti_kode: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        alamat_selama_cuti: string;
        nomor_telp_selama_cuti: string;
        lampiran: File[];
    }>({
        jenis_cuti_kode: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        alasan: '',
        alamat_selama_cuti: '',
        nomor_telp_selama_cuti: '',
        lampiran: [],
    });

    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

    // Jenis cuti yang dipilih saat ini
    const selectedJenis = useMemo(
        () => jenisCutiList.find((j) => j.kode === data.jenis_cuti_kode),
        [data.jenis_cuti_kode, jenisCutiList],
    );

    // Saldo tersedia untuk jenis yang dipilih
    const saldoTersedia = data.jenis_cuti_kode ? saldoData[data.jenis_cuti_kode] : undefined;

    // Validasi client-side
    const validateForm = (): boolean => {
        const errs: Record<string, string> = {};

        if (!data.jenis_cuti_kode) {
            errs.jenis_cuti_kode = 'Jenis cuti wajib dipilih.';
        }

        if (!data.tanggal_mulai) {
            errs.tanggal_mulai = 'Tanggal mulai wajib diisi.';
        }

        if (!data.tanggal_selesai) {
            errs.tanggal_selesai = 'Tanggal selesai wajib diisi.';
        }

        if (!data.alasan.trim()) {
            errs.alasan = 'Alasan wajib diisi.';
        }

        // Validasi CT: tanggal_mulai >= today + 3 hari
        if (data.tanggal_mulai && data.jenis_cuti_kode === 'CT') {
            const mulai = new Date(data.tanggal_mulai);
            const minDate = new Date();
            minDate.setDate(minDate.getDate() + 3);
            minDate.setHours(0, 0, 0, 0);

            if (mulai < minDate) {
                errs.tanggal_mulai = 'Cuti tahunan harus diajukan minimal 3 hari sebelumnya.';
            }
        }

        // Validasi cross-year
        if (data.tanggal_mulai && data.tanggal_selesai) {
            const tahunMulai = new Date(data.tanggal_mulai).getFullYear();
            const tahunSelesai = new Date(data.tanggal_selesai).getFullYear();

            if (tahunMulai !== tahunSelesai) {
                errs.tanggal_selesai = 'Tanggal mulai dan selesai harus dalam tahun yang sama.';
            }

            // Selesai >= mulai
            if (new Date(data.tanggal_selesai) < new Date(data.tanggal_mulai)) {
                errs.tanggal_selesai = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            }
        }

        setClientErrors(errs);

        return Object.keys(errs).length === 0;
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        if (!validateForm()) {
return;
}

        post('/cuti/pengajuan', {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;

        if (files) {
            setData('lampiran', Array.from(files));
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {(Object.keys(errors).length > 0 || Object.keys(clientErrors).length > 0) && (
                <AlertError
                    errors={[
                        ...errorsToArray(errors),
                        ...errorsToArray(clientErrors),
                    ]}
                    title="Gagal mengajukan cuti"
                />
            )}
            {/* Jenis Cuti */}
            <div className="space-y-2">
                <Label htmlFor="jenis_cuti_kode">Jenis Cuti</Label>
                <Select
                    value={data.jenis_cuti_kode}
                    onValueChange={(val) => setData('jenis_cuti_kode', val)}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih jenis cuti" />
                    </SelectTrigger>
                    <SelectContent>
                        {jenisCutiList.map((jenis) => (
                            <SelectItem key={jenis.kode} value={jenis.kode}>
                                {jenis.nama} ({jenis.kode})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={clientErrors.jenis_cuti_kode || errors.jenis_cuti_kode} />
                {/* Tampilkan saldo jika jenis saldo_driven */}
                {selectedJenis?.saldo_driven && saldoTersedia !== undefined && (
                    <p className="text-xs text-muted-foreground">
                        Saldo tersedia: <span className="font-semibold">{saldoTersedia} hari</span>
                    </p>
                )}
            </div>

            {/* Tanggal Mulai & Selesai */}
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="tanggal_mulai">Tanggal Mulai</Label>
                    <Input
                        id="tanggal_mulai"
                        type="date"
                        value={data.tanggal_mulai}
                        onChange={(e) => setData('tanggal_mulai', e.target.value)}
                    />
                    <InputError message={clientErrors.tanggal_mulai || errors.tanggal_mulai} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="tanggal_selesai">Tanggal Selesai</Label>
                    <Input
                        id="tanggal_selesai"
                        type="date"
                        value={data.tanggal_selesai}
                        onChange={(e) => setData('tanggal_selesai', e.target.value)}
                    />
                    <InputError message={clientErrors.tanggal_selesai || errors.tanggal_selesai} />
                </div>
            </div>

            {/* Alasan */}
            <div className="space-y-2">
                <Label htmlFor="alasan">Alasan</Label>
                <Textarea
                    id="alasan"
                    value={data.alasan}
                    onChange={(e) => setData('alasan', e.target.value)}
                    placeholder="Tuliskan alasan pengajuan cuti..."
                    rows={3}
                />
                <InputError message={clientErrors.alasan || errors.alasan} />
            </div>

            {/* Alamat Selama Cuti */}
            <div className="space-y-2">
                <Label htmlFor="alamat_selama_cuti">
                    Alamat Selama Cuti <span className="text-muted-foreground">(opsional)</span>
                </Label>
                <Input
                    id="alamat_selama_cuti"
                    value={data.alamat_selama_cuti}
                    onChange={(e) => setData('alamat_selama_cuti', e.target.value)}
                    placeholder="Alamat yang bisa dihubungi selama cuti"
                />
                <InputError message={errors.alamat_selama_cuti} />
            </div>

            {/* Nomor Telepon */}
            <div className="space-y-2">
                <Label htmlFor="nomor_telp_selama_cuti">
                    No. Telepon Selama Cuti <span className="text-muted-foreground">(opsional)</span>
                </Label>
                <Input
                    id="nomor_telp_selama_cuti"
                    value={data.nomor_telp_selama_cuti}
                    onChange={(e) => setData('nomor_telp_selama_cuti', e.target.value)}
                    placeholder="08xxxxxxxxxx"
                />
                <InputError message={errors.nomor_telp_selama_cuti} />
            </div>

            {/* Lampiran - hanya tampil jika jenis butuh lampiran */}
            {selectedJenis?.butuh_lampiran && (
                <div className="space-y-2">
                    <Label htmlFor="lampiran">Lampiran</Label>
                    <Input
                        id="lampiran"
                        type="file"
                        multiple
                        onChange={handleFileChange}
                        accept=".pdf,.jpg,.jpeg,.png"
                    />
                    <p className="text-xs text-muted-foreground">
                        Format: PDF, JPG, PNG. Maksimal beberapa file.
                    </p>
                    <InputError message={errors.lampiran} />
                    {/* Upload progress */}
                    {progress && (
                        <div className="h-2 w-full overflow-hidden rounded-full bg-primary/20">
                            <div
                                className="h-full bg-primary transition-all"
                                style={{ width: `${progress.percentage}%` }}
                            />
                        </div>
                    )}
                </div>
            )}

            {/* Tombol Submit */}
            <div className="flex justify-end gap-3">
                <Button type="submit" processing={processing}>
                    Ajukan Cuti
                </Button>
            </div>
        </form>
    );
}
