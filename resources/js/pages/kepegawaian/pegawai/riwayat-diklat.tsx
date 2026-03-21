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

type JenisDiklatOption = {
    id: string;
    nama: string;
};

type RiwayatDiklatItem = {
    id: string;
    ref_jenis_diklat_id: string | null;
    jenis_diklat: string | null;
    nama_diklat: string;
    penyelenggara: string;
    tempat: string | null;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jam_pelajaran: number | null;
    no_sertifikat: string | null;
    tanggal_sertifikat: string | null;
    keterangan: string | null;
    update_url: string;
    delete_url: string;
};

type RiwayatDiklatForm = {
    ref_jenis_diklat_id: string;
    nama_diklat: string;
    penyelenggara: string;
    tempat: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jam_pelajaran: string;
    no_sertifikat: string;
    tanggal_sertifikat: string;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    riwayatDiklat: RiwayatDiklatItem[];
    jenisDiklatOptions: JenisDiklatOption[];
};

const emptyForm = (): RiwayatDiklatForm => ({
    ref_jenis_diklat_id: '',
    nama_diklat: '',
    penyelenggara: '',
    tempat: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    jam_pelajaran: '',
    no_sertifikat: '',
    tanggal_sertifikat: '',
    keterangan: '',
});

const toFormState = (item: RiwayatDiklatItem): RiwayatDiklatForm => ({
    ref_jenis_diklat_id: item.ref_jenis_diklat_id ?? '',
    nama_diklat: item.nama_diklat,
    penyelenggara: item.penyelenggara,
    tempat: item.tempat ?? '',
    tanggal_mulai: item.tanggal_mulai,
    tanggal_selesai: item.tanggal_selesai,
    jam_pelajaran: item.jam_pelajaran?.toString() ?? '',
    no_sertifikat: item.no_sertifikat ?? '',
    tanggal_sertifikat: item.tanggal_sertifikat ?? '',
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: RiwayatDiklatForm) => ({
    ref_jenis_diklat_id: form.ref_jenis_diklat_id || null,
    nama_diklat: form.nama_diklat,
    penyelenggara: form.penyelenggara,
    tempat: form.tempat || null,
    tanggal_mulai: form.tanggal_mulai,
    tanggal_selesai: form.tanggal_selesai,
    jam_pelajaran: form.jam_pelajaran ? Number(form.jam_pelajaran) : null,
    no_sertifikat: form.no_sertifikat || null,
    tanggal_sertifikat: form.tanggal_sertifikat || null,
    keterangan: form.keterangan || null,
});

export default function RiwayatDiklatPage({
    pegawai,
    storeUrl,
    riwayatDiklat,
    jenisDiklatOptions,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<RiwayatDiklatItem | null>(
        null,
    );
    const [form, setForm] = useState<RiwayatDiklatForm>(emptyForm);

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
                title: 'Riwayat Diklat',
                href: `/kepegawaian/pegawai/${pegawai.id}/riwayat-diklat`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: RiwayatDiklatItem) => {
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

    const handleDelete = (item: RiwayatDiklatItem) => {
        if (!window.confirm('Hapus riwayat diklat ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Riwayat Diklat" />

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
                                        Nama Diklat
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Penyelenggara
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tempat
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Periode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        JP
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {riwayatDiklat.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat diklat.
                                        </td>
                                    </tr>
                                ) : (
                                    riwayatDiklat.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.nama_diklat}
                                                </div>
                                                {item.jenis_diklat && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.jenis_diklat}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.penyelenggara}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tempat ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div>{item.tanggal_mulai}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    s/d {item.tanggal_selesai}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.jam_pelajaran ?? '-'}
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
                                ? 'Tambah riwayat diklat'
                                : 'Ubah riwayat diklat'}
                        </DialogTitle>
                        <DialogDescription>
                            Isi data lengkap riwayat diklat/pelatihan.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="ref_jenis_diklat_id">
                                Jenis Diklat
                            </Label>
                            <select
                                id="ref_jenis_diklat_id"
                                value={form.ref_jenis_diklat_id}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        ref_jenis_diklat_id: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jenis diklat</option>
                                {jenisDiklatOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="nama_diklat">Nama Diklat *</Label>
                            <Input
                                id="nama_diklat"
                                value={form.nama_diklat}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        nama_diklat: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="penyelenggara">
                                Penyelenggara *
                            </Label>
                            <Input
                                id="penyelenggara"
                                value={form.penyelenggara}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        penyelenggara: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tempat">Tempat</Label>
                            <Input
                                id="tempat"
                                value={form.tempat}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tempat: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_mulai">
                                Tanggal Mulai *
                            </Label>
                            <Input
                                id="tanggal_mulai"
                                type="date"
                                value={form.tanggal_mulai}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_mulai: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_selesai">
                                Tanggal Selesai *
                            </Label>
                            <Input
                                id="tanggal_selesai"
                                type="date"
                                value={form.tanggal_selesai}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_selesai: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="jam_pelajaran">Jam Pelajaran</Label>
                            <Input
                                id="jam_pelajaran"
                                type="number"
                                value={form.jam_pelajaran}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        jam_pelajaran: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_sertifikat">No Sertifikat</Label>
                            <Input
                                id="no_sertifikat"
                                value={form.no_sertifikat}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        no_sertifikat: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_sertifikat">
                                Tanggal Sertifikat
                            </Label>
                            <Input
                                id="tanggal_sertifikat"
                                type="date"
                                value={form.tanggal_sertifikat}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_sertifikat: event.target.value,
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
