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

type JenisHukumanOption = {
    id: string;
    nama: string;
};

type HukumanDisiplinItem = {
    id: string;
    ref_jenis_hukuman_disiplin_id?: string | null;
    jenis_hukuman?: string | null;
    no_sk: string;
    tanggal_sk?: string | null;
    tmt_berlaku?: string | null;
    tmt_selesai?: string | null;
    pelanggaran: string;
    pejabat_penetap?: string | null;
    keterangan?: string | null;
    update_url: string;
    delete_url: string;
};

type HukumanDisiplinForm = {
    ref_jenis_hukuman_disiplin_id: string;
    no_sk: string;
    tanggal_sk: string;
    tmt_berlaku: string;
    tmt_selesai: string;
    pelanggaran: string;
    pejabat_penetap: string;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    hukumanDisiplin: HukumanDisiplinItem[];
    jenisHukumanOptions: JenisHukumanOption[];
};

const emptyForm = (): HukumanDisiplinForm => ({
    ref_jenis_hukuman_disiplin_id: '',
    no_sk: '',
    tanggal_sk: '',
    tmt_berlaku: '',
    tmt_selesai: '',
    pelanggaran: '',
    pejabat_penetap: '',
    keterangan: '',
});

const toFormState = (item: HukumanDisiplinItem): HukumanDisiplinForm => ({
    ref_jenis_hukuman_disiplin_id: item.ref_jenis_hukuman_disiplin_id ?? '',
    no_sk: item.no_sk,
    tanggal_sk: item.tanggal_sk ?? '',
    tmt_berlaku: item.tmt_berlaku ?? '',
    tmt_selesai: item.tmt_selesai ?? '',
    pelanggaran: item.pelanggaran,
    pejabat_penetap: item.pejabat_penetap ?? '',
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: HukumanDisiplinForm) => ({
    ref_jenis_hukuman_disiplin_id: form.ref_jenis_hukuman_disiplin_id || null,
    no_sk: form.no_sk,
    tanggal_sk: form.tanggal_sk || null,
    tmt_berlaku: form.tmt_berlaku || null,
    tmt_selesai: form.tmt_selesai || null,
    pelanggaran: form.pelanggaran,
    pejabat_penetap: form.pejabat_penetap || null,
    keterangan: form.keterangan || null,
});

export default function HukumanDisiplinPage({
    pegawai,
    storeUrl,
    hukumanDisiplin,
    jenisHukumanOptions,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<HukumanDisiplinItem | null>(
        null,
    );
    const [form, setForm] = useState<HukumanDisiplinForm>(emptyForm);

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
                title: 'Hukuman Disiplin',
                href: `/kepegawaian/pegawai/${pegawai.id}/hukuman-disiplin`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: HukumanDisiplinItem) => {
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

    const handleDelete = (item: HukumanDisiplinItem) => {
        if (!window.confirm('Hapus data hukuman disiplin ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Hukuman Disiplin" />

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
                            Tambah hukuman disiplin
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        No SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal SK
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        TMT Berlaku
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        TMT Selesai
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pelanggaran
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
                                {hukumanDisiplin.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada data hukuman disiplin.
                                        </td>
                                    </tr>
                                ) : (
                                    hukumanDisiplin.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                {item.no_sk}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tanggal_sk ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tmt_berlaku ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.tmt_selesai ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.pelanggaran}
                                                </div>
                                                {item.jenis_hukuman && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.jenis_hukuman}
                                                    </div>
                                                )}
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
                                ? 'Tambah hukuman disiplin'
                                : 'Ubah hukuman disiplin'}
                        </DialogTitle>
                        <DialogDescription>
                            Isi data hukuman disiplin pegawai.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="ref_jenis_hukuman_disiplin_id">
                                Jenis Hukuman
                            </Label>
                            <select
                                id="ref_jenis_hukuman_disiplin_id"
                                value={form.ref_jenis_hukuman_disiplin_id}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        ref_jenis_hukuman_disiplin_id:
                                            event.target.value,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jenis hukuman</option>
                                {jenisHukumanOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="no_sk">No SK *</Label>
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
                            <Label htmlFor="tmt_berlaku">TMT Berlaku</Label>
                            <Input
                                id="tmt_berlaku"
                                type="date"
                                value={form.tmt_berlaku}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tmt_berlaku: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tmt_selesai">TMT Selesai</Label>
                            <Input
                                id="tmt_selesai"
                                type="date"
                                value={form.tmt_selesai}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tmt_selesai: event.target.value,
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
                            <Label htmlFor="pelanggaran">Pelanggaran *</Label>
                            <textarea
                                id="pelanggaran"
                                rows={3}
                                value={form.pelanggaran}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        pelanggaran: event.target.value,
                                    }))
                                }
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
