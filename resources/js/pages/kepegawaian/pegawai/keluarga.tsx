import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
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
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem } from '@/types';
import type { HubunganKeluarga, JenisKelamin } from '@/types/kepegawaian';
import {
    HubunganKeluargaLabels,
    JenisKelaminLabels,
} from '@/types/kepegawaian';

type PegawaiSummary = {
    id: string;
    nama_lengkap: string;
};

type KeluargaItem = {
    id: string;
    hubungan: HubunganKeluarga;
    hubungan_label?: string;
    nama: string;
    tempat_lahir?: string | null;
    tanggal_lahir?: string | null;
    jenis_kelamin?: JenisKelamin | null;
    pekerjaan?: string | null;
    pendidikan?: string | null;
    keterangan?: string | null;
    update_url: string;
    delete_url: string;
};

type KeluargaForm = {
    hubungan: HubunganKeluarga | '';
    nama: string;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: JenisKelamin | '';
    pekerjaan: string;
    pendidikan: string;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    keluarga: KeluargaItem[];
};

const emptyForm = (): KeluargaForm => ({
    hubungan: '',
    nama: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    jenis_kelamin: '',
    pekerjaan: '',
    pendidikan: '',
    keterangan: '',
});

const toFormState = (item: KeluargaItem): KeluargaForm => ({
    hubungan: item.hubungan,
    nama: item.nama,
    tempat_lahir: item.tempat_lahir ?? '',
    tanggal_lahir: item.tanggal_lahir ?? '',
    jenis_kelamin: item.jenis_kelamin ?? '',
    pekerjaan: item.pekerjaan ?? '',
    pendidikan: item.pendidikan ?? '',
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: KeluargaForm) => ({
    hubungan: form.hubungan,
    nama: form.nama,
    tempat_lahir: form.tempat_lahir || null,
    tanggal_lahir: form.tanggal_lahir || null,
    jenis_kelamin: form.jenis_kelamin || null,
    pekerjaan: form.pekerjaan || null,
    pendidikan: form.pendidikan || null,
    keterangan: form.keterangan || null,
});

const hubunganOptions: { value: HubunganKeluarga; label: string }[] = [
    { value: 'Suami', label: HubunganKeluargaLabels.Suami },
    { value: 'Istri', label: HubunganKeluargaLabels.Istri },
    { value: 'Anak', label: HubunganKeluargaLabels.Anak },
    { value: 'AyahKandung', label: HubunganKeluargaLabels.AyahKandung },
    { value: 'IbuKandung', label: HubunganKeluargaLabels.IbuKandung },
];

const jenisKelaminOptions: { value: JenisKelamin; label: string }[] = [
    { value: 'laki_laki', label: JenisKelaminLabels.laki_laki },
    { value: 'perempuan', label: JenisKelaminLabels.perempuan },
];

export default function KeluargaPage({ pegawai, storeUrl, keluarga }: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<KeluargaItem | null>(null);
    const [form, setForm] = useState<KeluargaForm>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

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
                title: 'Keluarga',
                href: `/kepegawaian/pegawai/${pegawai.id}/keluarga`,
            },
        ],
        [pegawai.id, pegawai.nama_lengkap],
    );

    const openCreateDialog = () => {
        setEditingItem(null);
        setForm(emptyForm());
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: KeluargaItem) => {
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
            onSuccess: () => {
                setFormErrors({});
                closeDialog();
            },
            onError: (errors: Record<string, string>) => {
                setFormErrors(errors);
            },
        };

        if (editingItem !== null) {
            router.put(editingItem.update_url, toPayload(form), requestOptions);

            return;
        }

        router.post(storeUrl, toPayload(form), requestOptions);
    };

    const handleDelete = (item: KeluargaItem) => {
        if (!window.confirm('Hapus data keluarga ini?')) {
            return;
        }

        router.delete(item.delete_url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Keluarga" />

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
                            Tambah anggota keluarga
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Hubungan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nama
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tempat/Tgl Lahir
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pekerjaan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pendidikan
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {keluarga.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada data keluarga.
                                        </td>
                                    </tr>
                                ) : (
                                    keluarga.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                                    {item.hubungan_label ??
                                                        item.hubungan}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {item.nama}
                                                </div>
                                                {item.jenis_kelamin && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.jenis_kelamin ===
                                                        'laki_laki'
                                                            ? 'Laki-laki'
                                                            : 'Perempuan'}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div>
                                                    {item.tempat_lahir ?? '-'}
                                                </div>
                                                {item.tanggal_lahir && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.tanggal_lahir}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.pekerjaan ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.pendidikan ?? '-'}
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
                                ? 'Tambah anggota keluarga'
                                : 'Ubah data keluarga'}
                        </DialogTitle>
                        <DialogDescription>
                            Isi data lengkap anggota keluarga pegawai.
                        </DialogDescription>
                    </DialogHeader>

                    {Object.keys(formErrors).length > 0 && (
                        <AlertError
                            errors={errorsToArray(formErrors)}
                            title="Gagal menyimpan data keluarga"
                        />
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="hubungan">Hubungan *</Label>
                            <select
                                id="hubungan"
                                value={form.hubungan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        hubungan: event.target
                                            .value as HubunganKeluarga,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih hubungan</option>
                                {hubunganOptions.map((option) => (
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
                            <Label htmlFor="nama">Nama *</Label>
                            <Input
                                id="nama"
                                value={form.nama}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        nama: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tempat_lahir">Tempat Lahir</Label>
                            <Input
                                id="tempat_lahir"
                                value={form.tempat_lahir}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tempat_lahir: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tanggal_lahir">Tanggal Lahir</Label>
                            <Input
                                id="tanggal_lahir"
                                type="date"
                                value={form.tanggal_lahir}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        tanggal_lahir: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="jenis_kelamin">Jenis Kelamin</Label>
                            <select
                                id="jenis_kelamin"
                                value={form.jenis_kelamin}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        jenis_kelamin: event.target
                                            .value as JenisKelamin,
                                    }))
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jenis kelamin</option>
                                {jenisKelaminOptions.map((option) => (
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
                            <Label htmlFor="pekerjaan">Pekerjaan</Label>
                            <Input
                                id="pekerjaan"
                                value={form.pekerjaan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        pekerjaan: event.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="pendidikan">Pendidikan</Label>
                            <Input
                                id="pendidikan"
                                value={form.pendidikan}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        pendidikan: event.target.value,
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
