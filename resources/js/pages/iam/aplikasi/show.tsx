import { Head, router, usePage } from '@inertiajs/react';
import { Copy, Key, Pencil, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    IamApplication,
    IamRole,
    IamPermission,
} from '@/types';

type Props = {
    aplikasi: IamApplication;
    flash?: {
        api_secret_once?: string;
    };
};

export default function Show() {
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
            { title: aplikasi.nama, href: `/iam/aplikasi/${aplikasi.id}` },
        ],
        [aplikasi],
    );

    // Mask API key
    const maskedApiKey = useMemo(() => {
        if (!aplikasi.api_key) {
            return '-';
        }

        if (aplikasi.api_key.length <= 8) {
            return '********';
        }

        return (
            aplikasi.api_key.substring(0, 4) +
            '********' +
            aplikasi.api_key.substring(aplikasi.api_key.length - 4)
        );
    }, [aplikasi.api_key]);

    const copyToClipboard = useCallback((text: string) => {
        navigator.clipboard.writeText(text).catch(() => {
            // Fallback jika clipboard API tidak tersedia
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        });
    }, []);

    const handleRegenerateKey = useCallback(() => {
        if (
            confirm(
                'Apakah Anda yakin ingin meregenerasi API key? API secret lama tidak akan berlaku lagi.',
            )
        ) {
            router.post(`/iam/aplikasi/${aplikasi.id}/regenerate-key`);
        }
    }, [aplikasi.id]);

    // Form tambah role
    const handleAddRole = useCallback(
        (e: React.FormEvent<HTMLFormElement>) => {
            e.preventDefault();
            const formData = new FormData(e.currentTarget);
            router.post(`/iam/aplikasi/${aplikasi.id}/roles`, {
                nama: formData.get('nama'),
                deskripsi: formData.get('deskripsi'),
            });
        },
        [aplikasi.id],
    );

    // Hapus role
    const handleDeleteRole = useCallback(
        (role: IamRole) => {
            if (
                confirm(
                    `Apakah Anda yakin ingin menghapus role "${role.nama}"?`,
                )
            ) {
                router.delete(`/iam/aplikasi/${aplikasi.id}/roles/${role.id}`);
            }
        },
        [aplikasi.id],
    );

    // Form tambah permission
    const handleAddPermission = useCallback(
        (e: React.FormEvent<HTMLFormElement>) => {
            e.preventDefault();
            const formData = new FormData(e.currentTarget);
            router.post(`/iam/aplikasi/${aplikasi.id}/permissions`, {
                nama: formData.get('nama'),
                deskripsi: formData.get('deskripsi'),
            });
        },
        [aplikasi.id],
    );

    // Hapus permission
    const handleDeletePermission = useCallback(
        (permission: IamPermission) => {
            if (
                confirm(
                    `Apakah Anda yakin ingin menghapus permission "${permission.nama}"?`,
                )
            ) {
                router.delete(
                    `/iam/aplikasi/${aplikasi.id}/permissions/${permission.id}`,
                );
            }
        },
        [aplikasi.id],
    );

    // Toggle permission pada role
    const handleTogglePermission = useCallback(
        (role: IamRole, permission: IamPermission, checked: boolean) => {
            if (checked) {
                router.post(
                    `/iam/aplikasi/${aplikasi.id}/roles/${role.id}/permissions`,
                    {
                        permission_id: permission.id,
                    },
                );
            } else {
                router.delete(
                    `/iam/aplikasi/${aplikasi.id}/roles/${role.id}/permissions/${permission.id}`,
                );
            }
        },
        [aplikasi.id],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={aplikasi.nama} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold">
                            {aplikasi.nama}
                        </h1>
                        <Badge
                            variant={aplikasi.is_active ? 'default' : 'outline'}
                        >
                            {aplikasi.is_active ? 'Aktif' : 'Nonaktif'}
                        </Badge>
                        {aplikasi.is_system && (
                            <Badge variant="secondary">Sistem</Badge>
                        )}
                    </div>
                    {!aplikasi.is_system && (
                        <Button variant="outline" asChild>
                            <a href={`/iam/aplikasi/${aplikasi.id}/edit`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </a>
                        </Button>
                    )}
                </div>

                <Tabs defaultValue="roles" className="w-full">
                    <TabsList>
                        <TabsTrigger value="roles">Roles</TabsTrigger>
                        <TabsTrigger value="permissions">
                            Permissions
                        </TabsTrigger>
                        <TabsTrigger value="info">Info</TabsTrigger>
                    </TabsList>

                    {/* Tab Roles */}
                    <TabsContent value="roles" className="mt-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex justify-end">
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Tambah Role
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah Role Baru
                                            </DialogTitle>
                                            <DialogDescription>
                                                Tambahkan role baru untuk
                                                aplikasi ini.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <form onSubmit={handleAddRole}>
                                            <div className="grid gap-4 py-4">
                                                <div className="grid gap-2">
                                                    <label
                                                        htmlFor="role-nama"
                                                        className="text-sm font-medium"
                                                    >
                                                        Nama Role
                                                    </label>
                                                    <Input
                                                        id="role-nama"
                                                        name="nama"
                                                        placeholder="Contoh: Admin"
                                                        required
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <label
                                                        htmlFor="role-deskripsi"
                                                        className="text-sm font-medium"
                                                    >
                                                        Deskripsi (Opsional)
                                                    </label>
                                                    <Input
                                                        id="role-deskripsi"
                                                        name="deskripsi"
                                                        placeholder="Deskripsi role"
                                                    />
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <DialogClose asChild>
                                                    <Button
                                                        variant="outline"
                                                        type="button"
                                                    >
                                                        Batal
                                                    </Button>
                                                </DialogClose>
                                                <Button type="submit">
                                                    Simpan
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
                                            <TableHead>Nama Role</TableHead>
                                            <TableHead>Permissions</TableHead>
                                            <TableHead className="w-[100px] text-center">
                                                Aksi
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {!aplikasi.roles ||
                                        aplikasi.roles.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={3}
                                                    className="text-center"
                                                >
                                                    Belum ada role
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            aplikasi.roles.map((role) => (
                                                <TableRow key={role.id}>
                                                    <TableCell className="font-medium">
                                                        <div>
                                                            <div>
                                                                {role.nama}
                                                            </div>
                                                            {role.deskripsi && (
                                                                <div className="text-xs text-muted-foreground">
                                                                    {
                                                                        role.deskripsi
                                                                    }
                                                                </div>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap gap-2">
                                                            {role.permissions &&
                                                            role.permissions
                                                                .length > 0 ? (
                                                                role.permissions.map(
                                                                    (perm) => (
                                                                        <Badge
                                                                            key={
                                                                                perm.id
                                                                            }
                                                                            variant="secondary"
                                                                        >
                                                                            {
                                                                                perm.nama
                                                                            }
                                                                        </Badge>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <span className="text-sm text-muted-foreground">
                                                                    Tidak ada
                                                                    permissions
                                                                </span>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center justify-center gap-2">
                                                            {!aplikasi.is_system && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        handleDeleteRole(
                                                                            role,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                                </Button>
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
                    </TabsContent>

                    {/* Tab Permissions */}
                    <TabsContent value="permissions" className="mt-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex justify-end">
                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Tambah Permission
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Tambah Permission Baru
                                            </DialogTitle>
                                            <DialogDescription>
                                                Tambahkan permission baru untuk
                                                aplikasi ini.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <form onSubmit={handleAddPermission}>
                                            <div className="grid gap-4 py-4">
                                                <div className="grid gap-2">
                                                    <label
                                                        htmlFor="perm-nama"
                                                        className="text-sm font-medium"
                                                    >
                                                        Nama Permission
                                                    </label>
                                                    <Input
                                                        id="perm-nama"
                                                        name="nama"
                                                        placeholder="Contoh: create-post"
                                                        required
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <label
                                                        htmlFor="perm-deskripsi"
                                                        className="text-sm font-medium"
                                                    >
                                                        Deskripsi (Opsional)
                                                    </label>
                                                    <Input
                                                        id="perm-deskripsi"
                                                        name="deskripsi"
                                                        placeholder="Deskripsi permission"
                                                    />
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <DialogClose asChild>
                                                    <Button
                                                        variant="outline"
                                                        type="button"
                                                    >
                                                        Batal
                                                    </Button>
                                                </DialogClose>
                                                <Button type="submit">
                                                    Simpan
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
                                            <TableHead>
                                                Nama Permission
                                            </TableHead>
                                            <TableHead>Deskripsi</TableHead>
                                            <TableHead className="w-[100px] text-center">
                                                Aksi
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {!aplikasi.permissions ||
                                        aplikasi.permissions.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={3}
                                                    className="text-center"
                                                >
                                                    Belum ada permission
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            aplikasi.permissions.map((perm) => (
                                                <TableRow key={perm.id}>
                                                    <TableCell className="font-medium">
                                                        {perm.nama}
                                                    </TableCell>
                                                    <TableCell>
                                                        {perm.deskripsi ?? '-'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center justify-center gap-2">
                                                            {!aplikasi.is_system && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        handleDeletePermission(
                                                                            perm,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                                </Button>
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
                    </TabsContent>

                    {/* Tab Info */}
                    <TabsContent value="info" className="mt-4">
                        <div className="flex flex-col gap-6">
                            <div className="grid gap-4">
                                <div>
                                    <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                        Nama Aplikasi
                                    </h3>
                                    <p className="text-lg">{aplikasi.nama}</p>
                                </div>
                                <div>
                                    <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                        Slug
                                    </h3>
                                    <p className="font-mono text-lg">
                                        {aplikasi.slug}
                                    </p>
                                </div>
                                <div>
                                    <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                        URL
                                    </h3>
                                    <a
                                        href={aplikasi.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-lg text-primary hover:underline"
                                    >
                                        {aplikasi.url}
                                    </a>
                                </div>
                                {aplikasi.deskripsi && (
                                    <div>
                                        <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                            Deskripsi
                                        </h3>
                                        <p className="text-lg">
                                            {aplikasi.deskripsi}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                        API Key
                                    </h3>
                                    <div className="flex items-center gap-2">
                                        <code className="rounded bg-muted px-3 py-2 font-mono">
                                            {maskedApiKey}
                                        </code>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                copyToClipboard(
                                                    aplikasi.api_key,
                                                )
                                            }
                                        >
                                            <Copy className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                                {!aplikasi.is_system && (
                                    <div>
                                        <h3 className="mb-1 text-sm font-medium text-muted-foreground">
                                            Regenerasi API Key
                                        </h3>
                                        <Button
                                            variant="outline"
                                            onClick={handleRegenerateKey}
                                        >
                                            <Key className="mr-2 h-4 w-4" />
                                            Regenerasi Key
                                        </Button>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Perlu regenerasi jika API key
                                            tercompromise. Secret baru akan
                                            ditampilkan setelah regenerasi.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Modal API Secret */}
            <Dialog
                open={showApiSecretModal}
                onOpenChange={setShowApiSecretModal}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>API Secret Baru</DialogTitle>
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
