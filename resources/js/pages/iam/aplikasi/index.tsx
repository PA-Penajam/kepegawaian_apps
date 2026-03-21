import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
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
        if (confirm(`Apakah Anda yakin ingin menghapus aplikasi "${nama}"?`)) {
            router.delete(`/iam/aplikasi/${id}`);
        }
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelola Aplikasi IAM" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">
                        Kelola Aplikasi IAM
                    </h1>
                    <Dialog>
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
                                    const formData = new FormData(
                                        e.currentTarget,
                                    );
                                    router.post('/iam/aplikasi', {
                                        nama: formData.get('nama'),
                                        slug: formData.get('slug'),
                                        url: formData.get('url'),
                                        deskripsi: formData.get('deskripsi'),
                                    });
                                }}
                            >
                                <div className="grid gap-4 py-4">
                                    <div className="grid gap-2">
                                        <label
                                            htmlFor="nama"
                                            className="text-sm font-medium"
                                        >
                                            Nama Aplikasi
                                        </label>
                                        <Input
                                            id="nama"
                                            name="nama"
                                            placeholder="Contoh: Aplikasi Keuangan"
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <label
                                            htmlFor="slug"
                                            className="text-sm font-medium"
                                        >
                                            Slug
                                        </label>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            placeholder="Contoh: aplikasi-keuangan"
                                            pattern="[a-z0-9-]+"
                                            title="Hanya huruf kecil, angka, dan tanda hubung"
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <label
                                            htmlFor="url"
                                            className="text-sm font-medium"
                                        >
                                            URL Aplikasi
                                        </label>
                                        <Input
                                            id="url"
                                            name="url"
                                            type="url"
                                            placeholder="https://example.com"
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <label
                                            htmlFor="deskripsi"
                                            className="text-sm font-medium"
                                        >
                                            Deskripsi (Opsional)
                                        </label>
                                        <Input
                                            id="deskripsi"
                                            name="deskripsi"
                                            placeholder="Deskripsi singkat aplikasi"
                                        />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="outline" type="button">
                                            Batal
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit">Simpan</Button>
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
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/iam/aplikasi/${app.id}`}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                {!app.is_system && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/iam/aplikasi/${app.id}/edit`}
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    app.id,
                                                                    app.nama,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4 text-destructive" />
                                                        </Button>
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

            {/* Modal API Secret - ditampilkan sekali saja */}
            <Dialog
                open={showApiSecretModal}
                onOpenChange={setShowApiSecretModal}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>API Secret Aplikasi</DialogTitle>
                        <DialogDescription>
                            Simpan API secret berikut. Secret ini hanya
                            ditampilkan sekali dan tidak dapat diambil kembali.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="rounded-md bg-muted p-4 font-mono text-sm break-all">
                        {apiSecret}
                    </div>
                    <DialogFooter>
                        <Button onClick={() => setShowApiSecretModal(false)}>
                            Saya Sudah Menyimpannya
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
