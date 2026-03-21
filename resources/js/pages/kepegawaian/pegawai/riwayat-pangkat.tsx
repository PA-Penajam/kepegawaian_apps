import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type PegawaiSummary = {
    id: string;
    nama_lengkap: string;
    ref_pangkat_id: string | null;
    pangkat: {
        id: string;
        kode: string;
        nama: string;
        label: string;
    } | null;
};

type RefPangkatOption = {
    id: string;
    kode: string;
    nama: string;
    label: string;
};

type RiwayatPangkatItem = {
    id: string;
    ref_pangkat_id: string | null;
    no_sk: string;
    tanggal_sk: string | null;
    tmt: string | null;
    pejabat_penetap: string | null;
    masa_kerja_tahun: number;
    masa_kerja_bulan: number;
    gaji_pokok: string | null;
    is_aktif: boolean;
    keterangan: string | null;
    pangkat: RefPangkatOption | null;
    update_url: string;
    delete_url: string;
};

type RiwayatPangkatForm = {
    ref_pangkat_id: string;
    no_sk: string;
    tanggal_sk: string;
    tmt: string;
    pejabat_penetap: string;
    masa_kerja_tahun: string;
    masa_kerja_bulan: string;
    gaji_pokok: string;
    is_aktif: boolean;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    riwayatPangkat: RiwayatPangkatItem[];
    refPangkatOptions: RefPangkatOption[];
};

const emptyForm = (): RiwayatPangkatForm => ({
    ref_pangkat_id: '',
    no_sk: '',
    tanggal_sk: '',
    tmt: '',
    pejabat_penetap: '',
    masa_kerja_tahun: '0',
    masa_kerja_bulan: '0',
    gaji_pokok: '',
    is_aktif: false,
    keterangan: '',
});

const toFormState = (item: RiwayatPangkatItem): RiwayatPangkatForm => ({
    ref_pangkat_id: item.ref_pangkat_id ?? '',
    no_sk: item.no_sk,
    tanggal_sk: item.tanggal_sk ?? '',
    tmt: item.tmt ?? '',
    pejabat_penetap: item.pejabat_penetap ?? '',
    masa_kerja_tahun: String(item.masa_kerja_tahun),
    masa_kerja_bulan: String(item.masa_kerja_bulan),
    gaji_pokok: item.gaji_pokok ?? '',
    is_aktif: item.is_aktif,
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: RiwayatPangkatForm) => ({
    ref_pangkat_id: form.ref_pangkat_id || null,
    no_sk: form.no_sk,
    tanggal_sk: form.tanggal_sk,
    tmt: form.tmt,
    pejabat_penetap: form.pejabat_penetap || null,
    masa_kerja_tahun: Number(form.masa_kerja_tahun || 0),
    masa_kerja_bulan: Number(form.masa_kerja_bulan || 0),
    gaji_pokok: form.gaji_pokok === '' ? null : Number(form.gaji_pokok),
    is_aktif: form.is_aktif,
    keterangan: form.keterangan || null,
});

export default function RiwayatPangkatPage({
    pegawai,
    storeUrl,
    riwayatPangkat,
    refPangkatOptions,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<RiwayatPangkatItem | null>(
        null,
    );
    const [form, setForm] = useState<RiwayatPangkatForm>(emptyForm);

    const breadcrumbs = useMemo<BreadcrumbItem[]>(
        () => [
            {
                title: 'Data Pegawai',
                href: '/kepegawaian/pegawai',
            },
            {
                title: pegawai.nama_lengkap,
                href: `/kepegawaian/pegawai/${pegawai.id}`,
            },
            {
                title: 'Riwayat Pangkat',
                href: `/kepegawaian/pegawai/${pegawai.id}/riwayat-pangkat`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: RiwayatPangkatItem) => {
        setEditingItem(item);
        setForm(toFormState(item));
        setIsDialogOpen(true);
    };

    const closeDialog = () => {
        setIsDialogOpen(false);
        setEditingItem(null);
        setForm(emptyForm());
    };

    const submitForm = () => {
        const requestOptions = {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        };

        if (editingItem !== null) {
            router.put(editingItem.update_url, toPayload(form), requestOptions);

            return;
        }

        router.post(storeUrl, toPayload(form), requestOptions);
    };

    const handleDelete = (item: RiwayatPangkatItem) => {
        if (!window.confirm('Hapus riwayat pangkat ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Riwayat Pangkat" />

            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 rounded-xl border bg-card p-6 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2">
                        <p className="text-sm font-medium text-muted-foreground">
                            Pegawai
                        </p>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {pegawai.nama_lengkap}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Pangkat aktif:{' '}
                                {pegawai.pangkat?.label ?? 'Belum ditetapkan'}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(`/kepegawaian/pegawai/${pegawai.id}`)
                            }
                        >
                            Kembali
                        </Button>
                        <Button onClick={openCreateDialog}>
                            Tambah riwayat
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Pangkat
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nomor SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        TMT
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Masa kerja
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {riwayatPangkat.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat pangkat.
                                        </td>
                                    </tr>
                                ) : (
                                    riwayatPangkat.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.pangkat?.label ??
                                                        'Belum dipilih'}
                                                </div>
                                                {item.pejabat_penetap && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.pejabat_penetap}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.no_sk}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tanggal_sk ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tmt ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.masa_kerja_tahun} tahun{' '}
                                                {item.masa_kerja_bulan} bulan
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                    {item.is_aktif
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openEditDialog(item)
                                                        }
                                                    >
                                                        Ubah
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            handleDelete(item)
                                                        }
                                                    >
                                                        Hapus
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingItem === null
                                ? 'Tambah riwayat pangkat'
                                : 'Ubah riwayat pangkat'}
                        </DialogTitle>
                        <DialogDescription>
                            Simpan riwayat pangkat pegawai dan tandai satu data
                            sebagai pangkat aktif bila diperlukan.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="ref_pangkat_id">Pangkat</Label>
                            <select
                                id="ref_pangkat_id"
                                value={form.ref_pangkat_id}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        ref_pangkat_id: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih pangkat</option>
                                {refPangkatOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_sk">Nomor SK</Label>
                            <Input
                                id="no_sk"
                                value={form.no_sk}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        no_sk: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_sk">Tanggal SK</Label>
                            <Input
                                id="tanggal_sk"
                                type="date"
                                value={form.tanggal_sk}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_sk: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tmt">TMT</Label>
                            <Input
                                id="tmt"
                                type="date"
                                value={form.tmt}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tmt: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="pejabat_penetap">
                                Pejabat penetap
                            </Label>
                            <Input
                                id="pejabat_penetap"
                                value={form.pejabat_penetap}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        pejabat_penetap: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="masa_kerja_tahun">
                                Masa kerja tahun
                            </Label>
                            <Input
                                id="masa_kerja_tahun"
                                type="number"
                                min={0}
                                value={form.masa_kerja_tahun}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        masa_kerja_tahun: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="masa_kerja_bulan">
                                Masa kerja bulan
                            </Label>
                            <Input
                                id="masa_kerja_bulan"
                                type="number"
                                min={0}
                                max={11}
                                value={form.masa_kerja_bulan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        masa_kerja_bulan: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="gaji_pokok">Gaji pokok</Label>
                            <Input
                                id="gaji_pokok"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.gaji_pokok}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        gaji_pokok: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="keterangan">Keterangan</Label>
                            <textarea
                                id="keterangan"
                                rows={3}
                                value={form.keterangan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        keterangan: event.target.value,
                                    }))
                                }
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </div>

                        <div className="flex items-center gap-3 sm:col-span-2">
                            <Checkbox
                                id="is_aktif"
                                checked={form.is_aktif}
                                onCheckedChange={(checked) =>
                                    setForm((current) => ({
                                        ...current,
                                        is_aktif: checked === true,
                                    }))
                                }
                            />
                            <Label htmlFor="is_aktif">
                                Jadikan pangkat aktif pegawai
                            </Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
                        >
                            Batal
                        </Button>
                        <Button type="button" onClick={submitForm}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
