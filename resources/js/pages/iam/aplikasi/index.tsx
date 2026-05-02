import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import AlertError from '@/components/alert-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ApiSecretModal } from '@/components/iam/ApiSecretModal';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem, IamApplication } from '@/types';

type Props = {
    aplikasi: IamApplication[];
    flash?: {
        api_secret_once?: string;
    };
};

export default function Index() {
    const { aplikasi, flash } = usePage<Props>().props;
    const [showApiSecretModal, setShowApiSecretModal] = useState(false);
    const [apiSecret, setApiSecret] = useState<string | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<{
        id: number;
        nama: string;
    } | null>(null);
    const [showCreateDialog, setShowCreateDialog] = useState(false);

    // Form untuk create aplikasi
    const createForm = useForm({
        nama: '',
        slug: '',
        url: '',
        deskripsi: '',
    });

    // Form untuk delete aplikasi
    const deleteForm = useForm({});

    // Tampilkan modal jika ada flash api_secret_once
    useEffect(() => {
        if (flash?.api_secret_once) {
            setApiSecret(flash.api_secret_once);
            setShowApiSecretModal(true);
        }
    }, [flash]);

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'Aplikasi', href: '/iam/aplikasi' },
        ],
        [],
    );

    const handleDelete = useCallback((id: number, nama: string) => {
        setDeleteConfirm({ id, nama });
    }, []);

    const confirmDelete = useCallback(() => {
        if (!deleteConfirm) return;
        deleteForm.delete(`/iam/aplikasi/${deleteConfirm.id}`, {
            onSuccess: () => {
                setDeleteConfirm(null);
            },
        });
    }, [deleteConfirm, deleteForm]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelola Aplikasi IAM" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">
                        Kelola Aplikasi IAM
                    </h1>
                    <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Daftarkan Aplikasi
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Daftarkan Aplikasi Baru
                                </DialogTitle>
                                <DialogDescription>
                                    Lengkapi form berikut untuk mendaftarkan
                                    aplikasi baru.
                                </DialogDescription>
                            </DialogHeader>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    createForm.post('/iam/aplikasi', {
                                        onSuccess: () => {
                                            createForm.reset();
                                            setShowCreateDialog(false);
                                        },
                                    });
                                }}
                            >
                                {Object.keys(createForm.errors).length > 0 && (
                                    <AlertError
                                        errors={errorsToArray(createForm.errors)}
                                        title="Gagal mendaftarkan aplikasi"
                                    />
                                )}
                                <div className="grid gap-4 py-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="nama">Nama Aplikasi</Label>
                                        <Input
                                            id="nama"
                                            value={createForm.data.nama}
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'nama',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Contoh: Aplikasi Keuangan"
                                            required
                                        />
                                        {createForm.errors.nama && (
                                            <p className="text-sm text-destructive">
                                                {createForm.errors.nama}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="slug">Slug</Label>
                                        <Input
                                            id="slug"
                                            value={createForm.data.slug}
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'slug',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Contoh: aplikasi-keuangan"
                                            pattern="[a-z0-9-]+"
                                            title="Hanya huruf kecil, angka, dan tanda hubung"
                                            required
                                        />
                                        {createForm.errors.slug && (
                                            <p className="text-sm text-destructive">
                                                {createForm.errors.slug}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="url">URL Aplikasi</Label>
                                        <Input
                                            id="url"
                                            value={createForm.data.url}
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'url',
                                                    e.target.value,
                                                )
                                            }
                                            type="url"
                                            placeholder="https://example.com"
                                            required
                                        />
                                        {createForm.errors.url && (
                                            <p className="text-sm text-destructive">
                                                {createForm.errors.url}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="deskripsi">
                                            Deskripsi (Opsional)
                                        </Label>
                                        <Input
                                            id="deskripsi"
                                            value={createForm.data.deskripsi}
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'deskripsi',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Deskripsi singkat aplikasi"
                                        />
                                        {createForm.errors.deskripsi && (
                                            <p className="text-sm text-destructive">
                                                {createForm.errors.deskripsi}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="outline" type="button">
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        disabled={createForm.processing}
                                    >
                                        {createForm.processing
                                            ? 'Menyimpan...'
                                            : 'Simpan'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>URL</TableHead>
                                <TableHead className="text-center">
                                    Jumlah Role
                                </TableHead>
                                <TableHead className="text-center">
                                    Status
                                </TableHead>
                                <TableHead className="text-center">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {aplikasi.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center"
                                    >
                                        Tidak ada aplikasi yang terdaftar
                                    </TableCell>
                                </TableRow>
                            ) : (
                                aplikasi.map((app) => (
                                    <TableRow key={app.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                {app.nama}
                                                {app.is_system && (
                                                    <Badge variant="secondary">
                                                        Sistem
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <a
                                                href={app.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-primary hover:underline"
                                            >
                                                {app.url}
                                            </a>
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {app.roles_count ?? 0}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge
                                                variant={
                                                    app.is_active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {app.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Lihat detail ${app.nama}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/iam/aplikasi/${app.id}`}
                                                    >
                                                        <Eye
                                                            className="h-4 w-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                                {!app.is_system && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Edit ${app.nama}`}
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/iam/aplikasi/${app.id}/edit`}
                                                            >
                                                                <Pencil
                                                                    className="h-4 w-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Hapus ${app.nama}`}
                                                            onClick={() =>
                                                                handleDelete(
                                                                    app.id,
                                                                    app.nama,
                                                                )
                                                            }
                                                        >
                                                            <Trash2
                                                                className="h-4 w-4 text-destructive"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                        <AlertDialog
                                                            open={
                                                                deleteConfirm?.id ===
                                                                    app.id ||
                                                                false
                                                            }
                                                            onOpenChange={(
                                                                open: boolean,
                                                            ) =>
                                                                !open &&
                                                                setDeleteConfirm(
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            <AlertDialogContent>
                                                                <AlertDialogHeader>
                                                                    <AlertDialogTitle>
                                                                        Hapus Aplikasi
                                                                    </AlertDialogTitle>
                                                                    <AlertDialogDescription>
                                                                        Apakah
                                                                        Anda
                                                                        yakin
                                                                        ingin
                                                                        menghapus
                                                                        aplikasi
                                                                        "
                                                                        {
                                                                            deleteConfirm?.nama
                                                                        }
                                                                        "? Tindakan
                                                                        ini
                                                                        tidak
                                                                        dapat
                                                                        dibatalkan.
                                                                    </AlertDialogDescription>
                                                                </AlertDialogHeader>
                                                                <AlertDialogFooter>
                                                                    <AlertDialogCancel>
                                                                        Batal
                                                                    </AlertDialogCancel>
                                                                    <AlertDialogAction
                                                                        onClick={
                                                                            confirmDelete
                                                                        }
                                                                        disabled={
                                                                            deleteForm.processing
                                                                        }
                                                                    >
                                                                        {deleteForm.processing
                                                                            ? 'Menghapus...'
                                                                            : 'Hapus'}
                                                                    </AlertDialogAction>
                                                                </AlertDialogFooter>
                                                            </AlertDialogContent>
                                                        </AlertDialog>
                                                    </>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Modal API Secret — gunakan komponen shared */}
            <ApiSecretModal
                apiSecret={apiSecret ?? undefined}
                open={showApiSecretModal}
                onClose={() => setShowApiSecretModal(false)}
            />
        </AppLayout>
    );
}
