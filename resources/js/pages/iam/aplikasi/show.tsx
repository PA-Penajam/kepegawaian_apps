import { Head, useForm, usePage } from '@inertiajs/react';
import { Copy, Key, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ApiSecretModal } from '@/components/iam/ApiSecretModal';
import AppLayout from '@/layouts/app-layout';
import type {
    BreadcrumbItem,
    IamApplication,
    IamRole,
    IamPermission,
} from '@/types';

type Props = {
    aplikasi: IamApplication & { api_key_display?: string };
    flash?: {
        api_secret_once?: string;
    };
};

export default function Show() {
    const { aplikasi, flash } = usePage<Props>().props;
    const [showApiSecretModal, setShowApiSecretModal] = useState(false);
    const [apiSecret, setApiSecret] = useState<string | null>(null);

    // State untuk controlled dialogs
    const [showAddRoleDialog, setShowAddRoleDialog] = useState(false);
    const [showAddPermissionDialog, setShowAddPermissionDialog] = useState(false);

    // State untuk delete confirmations
    const [regenerateConfirm, setRegenerateConfirm] = useState(false);
    const [deleteRoleConfirm, setDeleteRoleConfirm] = useState<IamRole | null>(
        null,
    );
    const [deletePermissionConfirm, setDeletePermissionConfirm] =
        useState<IamPermission | null>(null);

    // Form untuk tambah role
    const addRoleForm = useForm({
        nama: '',
        deskripsi: '',
    });

    // Form untuk tambah permission
    const addPermissionForm = useForm({
        nama: '',
        deskripsi: '',
    });

    // Form untuk regenerate key
    const regenerateForm = useForm({});

    // Form untuk delete
    const deleteForm = useForm({});

    // Form untuk toggle permission - didefinisikan dengan tipe explicit
    const togglePermissionForm = useForm<{ permission_id: string }>({
        permission_id: '',
    });

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

    // Mask API key — sudah di-mask dari backend, gunakan langsung
    const maskedApiKey = useMemo(() => {
        return aplikasi.api_key_display || '-';
    }, [aplikasi.api_key_display]);

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
        regenerateForm.post(`/iam/aplikasi/${aplikasi.id}/regenerate-key`, {
            onSuccess: () => {
                setRegenerateConfirm(false);
            },
        });
    }, [aplikasi.id, regenerateForm]);

    // Form tambah role
    const handleAddRole = useCallback(() => {
        addRoleForm.post(`/iam/aplikasi/${aplikasi.id}/roles`, {
            onSuccess: () => {
                addRoleForm.reset();
                setShowAddRoleDialog(false);
            },
        });
    }, [aplikasi.id, addRoleForm]);

    // Hapus role
    const handleDeleteRole = useCallback(() => {
        if (!deleteRoleConfirm) return;
        deleteForm.delete(
            `/iam/aplikasi/${aplikasi.id}/roles/${deleteRoleConfirm.id}`,
            {
                onSuccess: () => {
                    setDeleteRoleConfirm(null);
                },
            },
        );
    }, [aplikasi.id, deleteRoleConfirm, deleteForm]);

    // Form tambah permission
    const handleAddPermission = useCallback(() => {
        addPermissionForm.post(
            `/iam/aplikasi/${aplikasi.id}/permissions`,
            {
                onSuccess: () => {
                    addPermissionForm.reset();
                    setShowAddPermissionDialog(false);
                },
            },
        );
    }, [aplikasi.id, addPermissionForm]);

    // Hapus permission
    const handleDeletePermission = useCallback(() => {
        if (!deletePermissionConfirm) return;
        deleteForm.delete(
            `/iam/aplikasi/${aplikasi.id}/permissions/${deletePermissionConfirm.id}`,
            {
                onSuccess: () => {
                    setDeletePermissionConfirm(null);
                },
            },
        );
    }, [aplikasi.id, deletePermissionConfirm, deleteForm]);

    // Toggle permission pada role
    const handleTogglePermission = useCallback(
        (role: IamRole, permission: IamPermission, checked: boolean) => {
            if (checked) {
                togglePermissionForm.setData(
                    'permission_id',
                    permission.id.toString(),
                );
                togglePermissionForm.post(
                    `/iam/aplikasi/${aplikasi.id}/roles/${role.id}/permissions`,
                );
            } else {
                togglePermissionForm.delete(
                    `/iam/aplikasi/${aplikasi.id}/roles/${role.id}/permissions/${permission.id}`,
                );
            }
        },
        [aplikasi.id, togglePermissionForm],
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
                                <Dialog
                                    open={showAddRoleDialog}
                                    onOpenChange={setShowAddRoleDialog}
                                >
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
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                handleAddRole();
                                            }}
                                        >
                                            <div className="grid gap-4 py-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="role-nama">
                                                        Nama Role
                                                    </Label>
                                                    <Input
                                                        id="role-nama"
                                                        value={
                                                            addRoleForm.data.nama
                                                        }
                                                        onChange={(e) =>
                                                            addRoleForm.setData(
                                                                'nama',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Contoh: Admin"
                                                        required
                                                    />
                                                    {addRoleForm.errors.nama && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                addRoleForm
                                                                    .errors.nama
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="role-deskripsi">
                                                        Deskripsi (Opsional)
                                                    </Label>
                                                    <Input
                                                        id="role-deskripsi"
                                                        value={
                                                            addRoleForm.data
                                                                .deskripsi
                                                        }
                                                        onChange={(e) =>
                                                            addRoleForm.setData(
                                                                'deskripsi',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Deskripsi role"
                                                    />
                                                    {addRoleForm.errors
                                                        .deskripsi && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                addRoleForm
                                                                    .errors
                                                                    .deskripsi
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <Button
                                                    variant="outline"
                                                    type="button"
                                                    onClick={() =>
                                                        setShowAddRoleDialog(
                                                            false,
                                                        )
                                                    }
                                                >
                                                    Batal
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        addRoleForm.processing
                                                    }
                                                >
                                                    {addRoleForm.processing
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
                                                                <>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            setDeleteRoleConfirm(
                                                                                role,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                                    </Button>
                                                                    <AlertDialog
                                                                        open={
                                                                            deleteRoleConfirm?.id ===
                                                                            role.id
                                                                        }
                                                                        onOpenChange={(
                                                                            open: boolean,
                                                                        ) =>
                                                                            !open &&
                                                                            setDeleteRoleConfirm(
                                                                                null,
                                                                            )
                                                                        }
                                                                    >
                                                                        <AlertDialogContent>
                                                                            <AlertDialogHeader>
                                                                                <AlertDialogTitle>
                                                                                    Hapus Role
                                                                                </AlertDialogTitle>
                                                                                <AlertDialogDescription>
                                                                                    Apakah
                                                                                    Anda
                                                                                    yakin
                                                                                    ingin
                                                                                    menghapus
                                                                                    role
                                                                                    "
                                                                                    {
                                                                                        deleteRoleConfirm?.nama
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
                                                                                        handleDeleteRole
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
                    </TabsContent>

                    {/* Tab Permissions */}
                    <TabsContent value="permissions" className="mt-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex justify-end">
                                <Dialog
                                    open={showAddPermissionDialog}
                                    onOpenChange={
                                        setShowAddPermissionDialog
                                    }
                                >
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
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                handleAddPermission();
                                            }}
                                        >
                                            <div className="grid gap-4 py-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="perm-nama">
                                                        Nama Permission
                                                    </Label>
                                                    <Input
                                                        id="perm-nama"
                                                        value={
                                                            addPermissionForm
                                                                .data.nama
                                                        }
                                                        onChange={(e) =>
                                                            addPermissionForm.setData(
                                                                'nama',
                                                                e.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Contoh: create-post"
                                                        required
                                                    />
                                                    {addPermissionForm.errors
                                                        .nama && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                addPermissionForm
                                                                    .errors
                                                                    .nama
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="perm-deskripsi">
                                                        Deskripsi (Opsional)
                                                    </Label>
                                                    <Input
                                                        id="perm-deskripsi"
                                                        value={
                                                            addPermissionForm
                                                                .data
                                                                .deskripsi
                                                        }
                                                        onChange={(e) =>
                                                            addPermissionForm.setData(
                                                                'deskripsi',
                                                                e.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Deskripsi permission"
                                                    />
                                                    {addPermissionForm.errors
                                                        .deskripsi && (
                                                        <p className="text-sm text-destructive">
                                                            {
                                                                addPermissionForm
                                                                    .errors
                                                                    .deskripsi
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <Button
                                                    variant="outline"
                                                    type="button"
                                                    onClick={() =>
                                                        setShowAddPermissionDialog(
                                                            false,
                                                        )
                                                    }
                                                >
                                                    Batal
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        addPermissionForm.processing
                                                    }
                                                >
                                                    {addPermissionForm.processing
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
                                                                <>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            setDeletePermissionConfirm(
                                                                                perm,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                                    </Button>
                                                                    <AlertDialog
                                                                        open={
                                                                            deletePermissionConfirm?.id ===
                                                                            perm.id
                                                                        }
                                                                        onOpenChange={(
                                                                            open: boolean,
                                                                        ) =>
                                                                            !open &&
                                                                            setDeletePermissionConfirm(
                                                                                null,
                                                                            )
                                                                        }
                                                                    >
                                                                        <AlertDialogContent>
                                                                            <AlertDialogHeader>
                                                                                <AlertDialogTitle>
                                                                                    Hapus Permission
                                                                                </AlertDialogTitle>
                                                                                <AlertDialogDescription>
                                                                                    Apakah
                                                                                    Anda
                                                                                    yakin
                                                                                    ingin
                                                                                    menghapus
                                                                                    permission
                                                                                    "
                                                                                    {
                                                                                        deletePermissionConfirm?.nama
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
                                                                                        handleDeletePermission
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
                                                copyToClipboard(maskedApiKey)
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
                                            onClick={() => setRegenerateConfirm(true)}
                                        >
                                            <Key className="mr-2 h-4 w-4" />
                                            Regenerasi Key
                                        </Button>
                                        <AlertDialog
                                            open={regenerateConfirm}
                                            onOpenChange={setRegenerateConfirm}
                                        >
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Regenerasi API Key
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        Apakah Anda yakin
                                                        ingin meregenerasi API
                                                        key? API secret lama
                                                        tidak akan berlaku lagi.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Batal
                                                    </AlertDialogCancel>
                                                    <AlertDialogAction
                                                        onClick={
                                                            handleRegenerateKey
                                                        }
                                                        disabled={
                                                            regenerateForm.processing
                                                        }
                                                    >
                                                        {regenerateForm.processing
                                                            ? 'Meregenerasi...'
                                                            : 'Regenerasi'}
                                                    </AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
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

            {/* Modal API Secret — gunakan komponen shared */}
            <ApiSecretModal
                apiSecret={apiSecret ?? undefined}
                open={showApiSecretModal}
                onClose={() => setShowApiSecretModal(false)}
            />
        </AppLayout>
    );
}
