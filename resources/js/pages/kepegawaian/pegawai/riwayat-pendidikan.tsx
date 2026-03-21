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
import type { JenjangPendidikan } from '@/types/kepegawaian';

type PegawaiSummary = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
    pendidikan_terakhir: string | null;
};

type RiwayatPendidikanItem = {
    id: string;
    jenjang: JenjangPendidikan;
    jenjang_label: string;
    nama_sekolah: string;
    jurusan: string | null;
    tahun_lulus: number;
    no_ijazah: string | null;
    tanggal_ijazah: string | null;
    keterangan: string | null;
    update_url: string;
    delete_url: string;
};

type JenjangOption = {
    value: JenjangPendidikan;
    label: string;
};

type RiwayatPendidikanForm = {
    jenjang: JenjangPendidikan | '';
    nama_sekolah: string;
    jurusan: string;
    tahun_lulus: string;
    no_ijazah: string;
    tanggal_ijazah: string;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    riwayatPendidikan: RiwayatPendidikanItem[];
    jenjangOptions: JenjangOption[];
};

const emptyForm = (): RiwayatPendidikanForm => ({
    jenjang: '',
    nama_sekolah: '',
    jurusan: '',
    tahun_lulus: '',
    no_ijazah: '',
    tanggal_ijazah: '',
    keterangan: '',
});

const toFormState = (item: RiwayatPendidikanItem): RiwayatPendidikanForm => ({
    jenjang: item.jenjang,
    nama_sekolah: item.nama_sekolah,
    jurusan: item.jurusan ?? '',
    tahun_lulus: String(item.tahun_lulus),
    no_ijazah: item.no_ijazah ?? '',
    tanggal_ijazah: item.tanggal_ijazah ?? '',
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: RiwayatPendidikanForm) => ({
    jenjang: form.jenjang,
    nama_sekolah: form.nama_sekolah,
    jurusan: form.jurusan || null,
    tahun_lulus: Number(form.tahun_lulus),
    no_ijazah: form.no_ijazah || null,
    tanggal_ijazah: form.tanggal_ijazah || null,
    keterangan: form.keterangan || null,
});

export default function RiwayatPendidikanPage({
    pegawai,
    storeUrl,
    riwayatPendidikan,
    jenjangOptions,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] =
        useState<RiwayatPendidikanItem | null>(null);
    const [form, setForm] = useState<RiwayatPendidikanForm>(emptyForm);

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
                title: 'Riwayat Pendidikan',
                href: `/kepegawaian/pegawai/${pegawai.id}/riwayat-pendidikan`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: RiwayatPendidikanItem) => {
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

    const handleDelete = (item: RiwayatPendidikanItem) => {
        if (!window.confirm('Hapus riwayat pendidikan ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Riwayat Pendidikan" />

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
                                Pendidikan terakhir:{' '}
                                {pegawai.pendidikan_terakhir ??
                                    'Belum ditetapkan'}
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
                                        Jenjang
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Sekolah
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jurusan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tahun Lulus
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        No. Ijazah
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {riwayatPendidikan.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat pendidikan.
                                        </td>
                                    </tr>
                                ) : (
                                    riwayatPendidikan.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.jenjang_label}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.nama_sekolah}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.jurusan ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tahun_lulus}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div>
                                                    {item.no_ijazah ?? '-'}
                                                </div>
                                                {item.tanggal_ijazah && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.tanggal_ijazah}
                                                    </div>
                                                )}
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
                                ? 'Tambah riwayat pendidikan'
                                : 'Ubah riwayat pendidikan'}
                        </DialogTitle>
                        <DialogDescription>
                            Simpan riwayat pendidikan formal pegawai.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="jenjang">Jenjang *</Label>
                            <select
                                id="jenjang"
                                value={form.jenjang}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        jenjang: event.target
                                            .value as JenjangPendidikan,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jenjang</option>
                                {jenjangOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="nama_sekolah">Nama Sekolah *</Label>
                            <Input
                                id="nama_sekolah"
                                value={form.nama_sekolah}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        nama_sekolah: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="jurusan">Jurusan</Label>
                            <Input
                                id="jurusan"
                                value={form.jurusan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        jurusan: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tahun_lulus">Tahun Lulus *</Label>
                            <Input
                                id="tahun_lulus"
                                type="number"
                                value={form.tahun_lulus}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tahun_lulus: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_ijazah">Nomor Ijazah</Label>
                            <Input
                                id="no_ijazah"
                                value={form.no_ijazah}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        no_ijazah: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_ijazah">
                                Tanggal Ijazah
                            </Label>
                            <Input
                                id="tanggal_ijazah"
                                type="date"
                                value={form.tanggal_ijazah}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_ijazah: event.target.value,
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
