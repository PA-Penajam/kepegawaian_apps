import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ReferenceOption = {
    id: string;
    kode: string;
    nama: string;
};

type PegawaiSummary = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
    jabatan: ReferenceOption | null;
    unit_kerja: ReferenceOption | null;
};

type RiwayatJabatanItem = {
    id: string;
    pegawai_id: string;
    ref_jabatan_id: string | null;
    ref_unit_kerja_id: string | null;
    no_sk: string;
    tanggal_sk: string;
    tmt: string;
    pejabat_penetap: string | null;
    is_aktif: boolean;
    keterangan: string | null;
    jabatan: ReferenceOption | null;
    unit_kerja: ReferenceOption | null;
    update_url: string;
    delete_url: string;
};

type RiwayatJabatanForm = {
    ref_jabatan_id: string;
    ref_unit_kerja_id: string;
    no_sk: string;
    tanggal_sk: string;
    tmt: string;
    pejabat_penetap: string;
    is_aktif: boolean;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    riwayatJabatan: RiwayatJabatanItem[];
    referensi: {
        jabatan: ReferenceOption[];
        unit_kerja: ReferenceOption[];
    };
};

const emptyForm = (): RiwayatJabatanForm => ({
    ref_jabatan_id: '',
    ref_unit_kerja_id: '',
    no_sk: '',
    tanggal_sk: '',
    tmt: '',
    pejabat_penetap: '',
    is_aktif: false,
    keterangan: '',
});

const toFormState = (item: RiwayatJabatanItem): RiwayatJabatanForm => ({
    ref_jabatan_id: item.ref_jabatan_id ?? '',
    ref_unit_kerja_id: item.ref_unit_kerja_id ?? '',
    no_sk: item.no_sk,
    tanggal_sk: item.tanggal_sk,
    tmt: item.tmt,
    pejabat_penetap: item.pejabat_penetap ?? '',
    is_aktif: item.is_aktif,
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: RiwayatJabatanForm) => ({
    ref_jabatan_id: form.ref_jabatan_id || null,
    ref_unit_kerja_id: form.ref_unit_kerja_id || null,
    no_sk: form.no_sk,
    tanggal_sk: form.tanggal_sk,
    tmt: form.tmt,
    pejabat_penetap: form.pejabat_penetap || null,
    is_aktif: form.is_aktif,
    keterangan: form.keterangan || null,
});

export default function RiwayatJabatanPage({
    pegawai,
    storeUrl,
    riwayatJabatan,
    referensi,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<RiwayatJabatanItem | null>(
        null,
    );
    const [form, setForm] = useState<RiwayatJabatanForm>(emptyForm);

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
                title: 'Riwayat Jabatan',
                href: `/kepegawaian/pegawai/${pegawai.id}/riwayat-jabatan`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: RiwayatJabatanItem) => {
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

    const handleDelete = (item: RiwayatJabatanItem) => {
        if (!window.confirm('Hapus riwayat jabatan ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Riwayat Jabatan" />

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
                                NIP: {pegawai.nip ?? '-'}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(`/kepegawaian/pegawai/${pegawai.id}`)
                            }
                        >
                            Kembali
                        </Button>
                        <div className="text-right">
                            <p className="text-xs text-muted-foreground">
                                Jabatan saat ini
                            </p>
                            <p className="font-medium">
                                {pegawai.jabatan?.nama ?? '-'}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-muted-foreground">
                                Unit Kerja
                            </p>
                            <p className="font-medium">
                                {pegawai.unit_kerja?.nama ?? '-'}
                            </p>
                        </div>
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
                                        Jabatan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Unit Kerja
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        No SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        TMT
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
                                {riwayatJabatan.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat jabatan.
                                        </td>
                                    </tr>
                                ) : (
                                    riwayatJabatan.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.jabatan?.nama ?? '-'}
                                                </div>
                                                {item.pejabat_penetap && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.pejabat_penetap}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.unit_kerja?.nama ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.no_sk}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tanggal_sk}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tmt}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${
                                                        item.is_aktif
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-gray-100 text-gray-700'
                                                    }`}
                                                >
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
                                ? 'Tambah riwayat jabatan'
                                : 'Ubah riwayat jabatan'}
                        </DialogTitle>
                        <DialogDescription>
                            Simpan riwayat jabatan pegawai dan tandai sebagai
                            jabatan aktif bila diperlukan.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="ref_jabatan_id">Jabatan</Label>
                            <select
                                id="ref_jabatan_id"
                                value={form.ref_jabatan_id}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        ref_jabatan_id: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jabatan</option>
                                {referensi.jabatan.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ref_unit_kerja_id">
                                Unit Kerja
                            </Label>
                            <select
                                id="ref_unit_kerja_id"
                                value={form.ref_unit_kerja_id}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        ref_unit_kerja_id: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih unit kerja</option>
                                {referensi.unit_kerja.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_sk">Nomor SK *</Label>
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
                            <Label htmlFor="tanggal_sk">Tanggal SK *</Label>
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
                            <Label htmlFor="tmt">TMT *</Label>
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
                                Pejabat Penetap
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
                            <input
                                id="is_aktif"
                                type="checkbox"
                                checked={form.is_aktif}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        is_aktif: event.target.checked,
                                    }))
                                }
                                className="size-4"
                            />
                            <Label htmlFor="is_aktif">
                                Jadikan sebagai jabatan aktif pegawai
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
