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

type PegawaiSummary = {
    id: string;
    nama_lengkap: string;
};

type JenisPenghargaanOption = {
    id: string;
    nama: string;
};

type PenghargaanItem = {
    id: string;
    ref_jenis_penghargaan_id?: string | null;
    jenis_penghargaan?: string | null;
    nama_penghargaan: string;
    no_sk?: string | null;
    tanggal_sk?: string | null;
    pejabat_penetap?: string | null;
    keterangan?: string | null;
    update_url: string;
    delete_url: string;
};

type PenghargaanForm = {
    ref_jenis_penghargaan_id: string;
    nama_penghargaan: string;
    no_sk: string;
    tanggal_sk: string;
    pejabat_penetap: string;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    penghargaan: PenghargaanItem[];
    jenisPenghargaanOptions: JenisPenghargaanOption[];
};

const emptyForm = (): PenghargaanForm => ({
    ref_jenis_penghargaan_id: '',
    nama_penghargaan: '',
    no_sk: '',
    tanggal_sk: '',
    pejabat_penetap: '',
    keterangan: '',
});

const toFormState = (item: PenghargaanItem): PenghargaanForm => ({
    ref_jenis_penghargaan_id: item.ref_jenis_penghargaan_id ?? '',
    nama_penghargaan: item.nama_penghargaan,
    no_sk: item.no_sk ?? '',
    tanggal_sk: item.tanggal_sk ?? '',
    pejabat_penetap: item.pejabat_penetap ?? '',
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: PenghargaanForm) => ({
    ref_jenis_penghargaan_id: form.ref_jenis_penghargaan_id || null,
    nama_penghargaan: form.nama_penghargaan,
    no_sk: form.no_sk || null,
    tanggal_sk: form.tanggal_sk || null,
    pejabat_penetap: form.pejabat_penetap || null,
    keterangan: form.keterangan || null,
});

export default function PenghargaanPage({
    pegawai,
    storeUrl,
    penghargaan,
    jenisPenghargaanOptions,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<PenghargaanItem | null>(
        null,
    );
    const [form, setForm] = useState<PenghargaanForm>(emptyForm);

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
                title: 'Penghargaan',
                href: `/kepegawaian/pegawai/${pegawai.id}/penghargaan`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: PenghargaanItem) => {
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

    const handleDelete = (item: PenghargaanItem) => {
        if (!window.confirm('Hapus data penghargaan ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penghargaan" />

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
                            Tambah penghargaan
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Nama Penghargaan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        No SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pejabat Penetap
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {penghargaan.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada data penghargaan.
                                        </td>
                                    </tr>
                                ) : (
                                    penghargaan.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.nama_penghargaan}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.jenis_penghargaan ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.no_sk ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tanggal_sk ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.pejabat_penetap ?? '-'}
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
                                ? 'Tambah penghargaan'
                                : 'Ubah penghargaan'}
                        </DialogTitle>
                        <DialogDescription>
                            Isi data penghargaan/penghargaan pegawai.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="ref_jenis_penghargaan_id">
                                Jenis Penghargaan
                            </Label>
                            <select
                                id="ref_jenis_penghargaan_id"
                                value={form.ref_jenis_penghargaan_id}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        ref_jenis_penghargaan_id:
                                            event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">
                                    Pilih jenis penghargaan
                                </option>
                                {jenisPenghargaanOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="nama_penghargaan">
                                Nama Penghargaan *
                            </Label>
                            <Input
                                id="nama_penghargaan"
                                value={form.nama_penghargaan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        nama_penghargaan: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_sk">No SK</Label>
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
