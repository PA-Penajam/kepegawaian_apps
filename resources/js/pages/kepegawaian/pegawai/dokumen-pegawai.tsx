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
    nip: string | null;
    nama_lengkap: string;
};

type DokumenItem = {
    id: string;
    jenis_dokumen: string;
    nomor_dokumen?: string | null;
    tanggal_dokumen?: string | null;
    file_path?: string | null;
    keterangan?: string | null;
    update_url: string;
    delete_url: string;
};

type DokumenForm = {
    jenis_dokumen: string;
    nomor_dokumen: string;
    tanggal_dokumen: string;
    file_path: string;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    dokumen: DokumenItem[];
};

const emptyForm = (): DokumenForm => ({
    jenis_dokumen: '',
    nomor_dokumen: '',
    tanggal_dokumen: '',
    file_path: '',
    keterangan: '',
});

const toFormState = (item: DokumenItem): DokumenForm => ({
    jenis_dokumen: item.jenis_dokumen,
    nomor_dokumen: item.nomor_dokumen ?? '',
    tanggal_dokumen: item.tanggal_dokumen ?? '',
    file_path: item.file_path ?? '',
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: DokumenForm) => ({
    jenis_dokumen: form.jenis_dokumen,
    nomor_dokumen: form.nomor_dokumen || null,
    tanggal_dokumen: form.tanggal_dokumen || null,
    file_path: form.file_path || null,
    keterangan: form.keterangan || null,
});

const jenisDokumenOptions = [
    { value: 'KTP', label: 'KTP' },
    { value: 'NPWP', label: 'NPWP' },
    { value: 'IJAZAH', label: 'Ijazah' },
    { value: 'SK_CPNS', label: 'SK CPNS' },
    { value: 'SK_PNS', label: 'SK PNS' },
    { value: 'SK_Pangkat', label: 'SK Pangkat' },
    { value: 'SK_Jabatan', label: 'SK Jabatan' },
    { value: 'Kartu_Pegawai', label: 'Kartu Pegawai' },
    { value: 'KARIS_KARSU', label: 'KARIS/KARSU' },
    { value: 'BPJS', label: 'BPJS' },
    { value: 'Lainnya', label: 'Lainnya' },
];

export default function DokumenPegawaiPage({
    pegawai,
    storeUrl,
    dokumen,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<DokumenItem | null>(null);
    const [form, setForm] = useState<DokumenForm>(emptyForm);

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
                title: 'Dokumen',
                href: `/kepegawaian/pegawai/${pegawai.id}/dokumen`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: DokumenItem) => {
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

    const handleDelete = (item: DokumenItem) => {
        if (!window.confirm('Hapus dokumen ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dokumen Pegawai" />

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
                            Tambah dokumen
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis Dokumen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nomor Dokumen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal Dokumen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        File
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {dokumen.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada data dokumen.
                                        </td>
                                    </tr>
                                ) : (
                                    dokumen.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.jenis_dokumen}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.nomor_dokumen ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tanggal_dokumen ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.file_path ? (
                                                    <a
                                                        href={item.file_path}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-primary hover:underline"
                                                    >
                                                        Lihat File
                                                    </a>
                                                ) : (
                                                    '-'
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
                                ? 'Tambah dokumen'
                                : 'Ubah dokumen'}
                        </DialogTitle>
                        <DialogDescription>
                            Isi data dokumen pegawai.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="jenis_dokumen">
                                Jenis Dokumen *
                            </Label>
                            <select
                                id="jenis_dokumen"
                                value={form.jenis_dokumen}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        jenis_dokumen: event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jenis dokumen</option>
                                {jenisDokumenOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="nomor_dokumen">Nomor Dokumen</Label>
                            <Input
                                id="nomor_dokumen"
                                value={form.nomor_dokumen}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        nomor_dokumen: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_dokumen">
                                Tanggal Dokumen
                            </Label>
                            <Input
                                id="tanggal_dokumen"
                                type="date"
                                value={form.tanggal_dokumen}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_dokumen: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="file_path">File Path</Label>
                            <Input
                                id="file_path"
                                value={form.file_path}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        file_path: event.target.value,
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
