import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
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
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem } from '@/types';

type PegawaiSummary = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
};

type RefJenisDokumenOption = {
    id: string;
    nama: string;
};

type DokumenItem = {
    id: string;
    ref_jenis_dokumen_id?: string | null;
    jenis_dokumen: string;
    nomor_dokumen?: string | null;
    tanggal_dokumen?: string | null;
    file_path?: string | null;
    has_file?: boolean;
    download_url?: string | null;
    keterangan?: string | null;
    update_url: string;
    delete_url: string;
};

type DokumenForm = {
    ref_jenis_dokumen_id: string;
    jenis_dokumen: string;
    nomor_dokumen: string;
    tanggal_dokumen: string;
    file: File | null;
    keterangan: string;
};

type Props = {
    pegawai: PegawaiSummary;
    storeUrl: string;
    dokumen: DokumenItem[];
    refJenisDokumen?: RefJenisDokumenOption[];
};

const emptyForm = (): DokumenForm => ({
    ref_jenis_dokumen_id: '',
    jenis_dokumen: '',
    nomor_dokumen: '',
    tanggal_dokumen: '',
    file: null,
    keterangan: '',
});

const toFormState = (item: DokumenItem): DokumenForm => ({
    ref_jenis_dokumen_id: item.ref_jenis_dokumen_id ?? '',
    jenis_dokumen: item.jenis_dokumen,
    nomor_dokumen: item.nomor_dokumen ?? '',
    tanggal_dokumen: item.tanggal_dokumen ?? '',
    file: null,
    keterangan: item.keterangan ?? '',
});

const toPayload = (form: DokumenForm) => ({
    ref_jenis_dokumen_id: form.ref_jenis_dokumen_id || '',
    jenis_dokumen: form.jenis_dokumen,
    nomor_dokumen: form.nomor_dokumen || '',
    tanggal_dokumen: form.tanggal_dokumen || '',
    keterangan: form.keterangan || '',
    ...(form.file !== null ? { file: form.file } : {}),
});

const stripControlChars = (value: string): string =>
    Array.from(value)
        .filter((char) => {
            const code = char.charCodeAt(0);

            return code > 0x1f && code !== 0x7f;
        })
        .join('');

/**
 * Menyaring tautan unduhan agar skema berbahaya (javascript:, data:, vbscript:)
 * tidak pernah dirender sebagai link aktif di DOM dan hanya path internal relatif
 * yang diizinkan.
 */
const resolveSafeDownloadUrl = (item: DokumenItem): string | null => {
    const rawUrl = item.download_url ?? '';
    const sanitized = stripControlChars(rawUrl).trim();

    if (
        sanitized === '' ||
        /^(?:javascript|data|vbscript):/i.test(sanitized.replace(/\s+/g, ''))
    ) {
        return null;
    }

    return /^\/(?![/\\])/.test(sanitized) ? sanitized : null;
};

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
    refJenisDokumen = [],
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<DokumenItem | null>(null);
    const [form, setForm] = useState<DokumenForm>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<DokumenItem | null>(null);
    const [deleting, setDeleting] = useState(false);

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
        setFormErrors({});
        setIsDialogOpen(true);
    };

    const openEditDialog = (item: DokumenItem) => {
        setEditingItem(item);
        setForm(toFormState(item));
        setFormErrors({});
        setIsDialogOpen(true);
    };

    const closeDialog = () => {
        setIsDialogOpen(false);
        setEditingItem(null);
        setForm(emptyForm());
        setFormErrors({});
    };

    const submitForm = () => {
        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setSubmitting(true),
            onFinish: () => setSubmitting(false),
            onSuccess: () => {
                setFormErrors({});
                closeDialog();
            },
            onError: (errors: Record<string, string>) => {
                setFormErrors(errors);
            },
        };

        if (editingItem !== null) {
            router.post(
                editingItem.update_url,
                {
                    ...toPayload(form),
                    _method: 'put',
                },
                requestOptions,
            );

            return;
        }

        router.post(storeUrl, toPayload(form), requestOptions);
    };

    const confirmDelete = () => {
        if (deleteTarget === null) {
            return;
        }

        router.delete(deleteTarget.delete_url, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: () => setDeleteTarget(null),
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
                                    dokumen.map((item) => {
                                        const safeDownloadUrl =
                                            resolveSafeDownloadUrl(item);

                                        return (
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
                                                    {item.tanggal_dokumen ??
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {safeDownloadUrl !==
                                                    null ? (
                                                        <a
                                                            href={
                                                                safeDownloadUrl
                                                            }
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
                                                                openEditDialog(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Ubah
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Hapus
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog
                open={isDialogOpen}
                onOpenChange={(open) => {
                    if (!open && !submitting) {
                        closeDialog();
                    }
                }}
            >
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

                    {Object.keys(formErrors).length > 0 && (
                        <AlertError
                            errors={errorsToArray(formErrors)}
                            title="Gagal menyimpan dokumen pegawai"
                        />
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="jenis_dokumen">
                                Jenis Dokumen *
                            </Label>
                            <select
                                id="jenis_dokumen"
                                value={form.jenis_dokumen}
                                onChange={(event) => {
                                    const selectedNama = event.target.value;
                                    const matchedRef = refJenisDokumen.find(
                                        (opt) =>
                                            opt.nama.toLowerCase() ===
                                            selectedNama
                                                .replace(/_/g, ' ')
                                                .toLowerCase(),
                                    );
                                    setForm((current) => ({
                                        ...current,
                                        jenis_dokumen: selectedNama,
                                        ref_jenis_dokumen_id:
                                            matchedRef?.id ?? '',
                                    }));
                                }}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Pilih jenis dokumen</option>
                                {refJenisDokumen.length > 0
                                    ? refJenisDokumen.map((option) => (
                                          <option
                                              key={option.id}
                                              value={option.nama}
                                          >
                                              {option.nama}
                                          </option>
                                      ))
                                    : jenisDokumenOptions.map((option) => (
                                          <option
                                              key={option.value}
                                              value={option.value}
                                          >
                                              {option.label}
                                          </option>
                                      ))}
                            </select>
                            <InputError message={formErrors.jenis_dokumen} />
                            <InputError
                                message={formErrors.ref_jenis_dokumen_id}
                            />
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
                            <InputError message={formErrors.nomor_dokumen} />
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
                            <InputError message={formErrors.tanggal_dokumen} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="file">
                                Berkas Dokumen (PDF, JPG, PNG, maks. 10 MB)
                                {editingItem === null ? ' *' : ''}
                            </Label>
                            <Input
                                id="file"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        file: event.target.files?.[0] ?? null,
                                    }))
                                }
                            />
                            {editingItem !== null && (
                                <p className="text-xs text-muted-foreground">
                                    Biarkan kosong jika tidak ingin mengganti
                                    berkas yang sudah tersimpan.
                                </p>
                            )}
                            <InputError message={formErrors.file} />
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
                            <InputError message={formErrors.keterangan} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
                            disabled={submitting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            onClick={submitForm}
                            processing={submitting}
                        >
                            {submitting ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setDeleteTarget(null);
                    }
                }}
                title="Hapus Dokumen Pegawai"
                description="Apakah Anda yakin ingin menghapus dokumen"
                itemName={
                    deleteTarget?.nomor_dokumen
                        ? `${deleteTarget.jenis_dokumen} (${deleteTarget.nomor_dokumen})`
                        : deleteTarget?.jenis_dokumen
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </AppLayout>
    );
}
