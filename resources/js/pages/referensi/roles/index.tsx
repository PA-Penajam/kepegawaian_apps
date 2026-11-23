import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users, ShieldCheck } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import { toUrl } from '@/lib/utils';
import {
    index as rolesIndex,
    update,
    store,
    destroy,
    edit,
} from '@/routes/referensi/roles';
import type {
    BreadcrumbItem,
    RefRole,
    RefPermission,
    PaginatedData,
} from '@/types';

type Props = {
    roles: PaginatedData<RefRole>;
    permissions: RefPermission[];
    filters: {
        search?: string;
    };
};

export default function Index({ roles, permissions, filters }: Props) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [prevSearchFilter, setPrevSearchFilter] = useState(
        filters?.search ?? '',
    );

    if ((filters?.search ?? '') !== prevSearchFilter) {
        setPrevSearchFilter(filters?.search ?? '');
        setSearch(filters?.search ?? '');
    }

    // State for Modals
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<RefRole | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<RefRole | null>(null);
    const deleteForm = useForm({});

    // Form logic for Create
    const {
        data: createData,
        setData: setCreateData,
        post: postCreate,
        processing: processingCreate,
        errors: errorsCreate,
        reset: resetCreate,
        clearErrors: clearErrorsCreate,
    } = useForm({
        nama: '',
        keterangan: '',
        permissions: [] as string[],
    });

    // Form logic for Edit
    const {
        data: editData,
        setData: setEditData,
        put: putEdit,
        processing: processingEdit,
        errors: errorsEdit,
        reset: resetEdit,
        clearErrors: clearErrorsEdit,
    } = useForm({
        nama: '',
        keterangan: '',
        permissions: [] as string[],
    });

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Roles', href: toUrl(rolesIndex()) },
        ],
        [],
    );

    const groupedPermissions = useMemo(() => {
        return (permissions || []).reduce(
            (acc, p) => {
                const group = p.group ?? 'lainnya';

                if (!acc[group]) {
                    acc[group] = [];
                }

                acc[group].push(p);

                return acc;
            },
            {} as Record<string, RefPermission[]>,
        );
    }, [permissions]);

    const handleSearch = useCallback((value: string) => {
        router.get(toUrl(rolesIndex()), value ? { search: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    useEffect(() => {
        const normalizedSearch = search.trim();
        const currentSearch = (filters?.search ?? '').trim();

        if (normalizedSearch === currentSearch) {
            return undefined;
        }

        const timeout = setTimeout(() => {
            handleSearch(normalizedSearch);
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, filters?.search, handleSearch]);

    const handleDelete = (role: RefRole) => {
        setDeleteTarget(role);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        deleteForm.delete(toUrl(destroy(deleteTarget.id)), {
            onSuccess: () => setDeleteTarget(null),
        });
    };

    // Dialog Handlers
    const openCreateModal = () => {
        resetCreate();
        clearErrorsCreate();
        setIsCreateOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateOpen(false);
        resetCreate();
        clearErrorsCreate();
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        postCreate(toUrl(store()), {
            onSuccess: () => closeCreateModal(),
        });
    };

    const openEditModal = (role: RefRole) => {
        setEditingRole(role);
        setEditData({
            nama: role.nama,
            keterangan: role.keterangan || '',
            permissions: role.permissions?.map((p) => p.id) || [],
        });
        clearErrorsEdit();
    };

    const closeEditModal = () => {
        setEditingRole(null);
        resetEdit();
        clearErrorsEdit();
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!editingRole) {
            return;
        }

        putEdit(toUrl(update(editingRole.id)), {
            onSuccess: () => closeEditModal(),
        });
    };

    const togglePermission = (
        permissionId: string,
        formType: 'create' | 'edit',
    ) => {
        if (formType === 'create') {
            const current = createData.permissions;
            setCreateData(
                'permissions',
                current.includes(permissionId)
                    ? current.filter((id) => id !== permissionId)
                    : [...current, permissionId],
            );
        } else {
            const current = editData.permissions;
            setEditData(
                'permissions',
                current.includes(permissionId)
                    ? current.filter((id) => id !== permissionId)
                    : [...current, permissionId],
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Roles
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manajemen hak akses pengguna.
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Cari role berdasarkan nama atau keterangan..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-md"
                    />
                </div>

                <div className="mt-2 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {roles.data.length === 0 ? (
                        <div className="col-span-full rounded-xl border border-dashed border-border bg-muted/30 py-10 text-center">
                            <p className="font-medium text-muted-foreground">
                                Tidak ada data role ditemukan.
                            </p>
                        </div>
                    ) : (
                        roles.data.map((item) => (
                            <Card
                                key={item.id}
                                className="group relative flex flex-col gap-0 overflow-hidden pt-0 transition-shadow hover:shadow-md"
                            >
                                <CardHeader className="border-b border-border bg-muted/30 pt-6 pb-4">
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <CardTitle className="line-clamp-1 text-lg font-semibold capitalize">
                                            {item.nama}
                                        </CardTitle>
                                        {item.is_system && (
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0 font-medium"
                                            >
                                                Sistem
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription className="line-clamp-2 min-h-[40px] text-sm text-muted-foreground">
                                        {item.keterangan ||
                                            (item.nama.toLowerCase() === 'admin'
                                                ? 'Memiliki kontrol penuh atas referensi sistem, parameter, dan tata kelola hak akses pengguna (IAM).'
                                                : item.nama.toLowerCase() ===
                                                    'operator'
                                                  ? 'Memiliki akses operasional untuk menginput, mengubah, dan mengelola master data rekam jejak seluruh pegawai.'
                                                  : item.nama.toLowerCase() ===
                                                      'validator'
                                                    ? 'Memiliki otoritas untuk memverifikasi dan menyetujui setiap pengajuan penyesuaian profil dari layanan mandiri (self-service).'
                                                    : item.nama.toLowerCase() ===
                                                        'viewer'
                                                      ? 'Hanya memiliki izin akses baca (read-only) untuk melihat data agregat dan profil tanpa hak memodifikasi.'
                                                      : 'Tidak ada keterangan spesifik untuk role ini.')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-grow flex-col gap-3 py-4">
                                    <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 p-3 text-sm">
                                        <div className="flex items-center gap-2 font-medium text-muted-foreground">
                                            <Users className="h-4 w-4" />
                                            Total Pegawai
                                        </div>
                                        <div className="tnum text-sm font-semibold">
                                            {item.pegawai_count ?? 0}
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 p-3 text-sm">
                                        <div className="flex items-center gap-2 font-medium text-muted-foreground">
                                            <ShieldCheck className="h-4 w-4" />
                                            Permissions
                                        </div>
                                        <div className="tnum text-sm font-semibold">
                                            {item.permissions_count ?? 0}
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="mt-auto flex justify-between gap-2 px-6 pt-0 pb-6">
                                    <Button
                                        className="h-9 flex-1 gap-2 px-0 text-xs font-medium"
                                        variant="outline"
                                        onClick={() => openEditModal(item)}
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                        Edit
                                    </Button>
                                    <Button
                                        className="h-9 flex-1 gap-2 px-0 text-xs font-medium"
                                        variant="default"
                                        asChild
                                    >
                                        <Link href={toUrl(edit(item.id))}>
                                            <Users className="h-3.5 w-3.5" />
                                            Pegawai
                                        </Link>
                                    </Button>
                                    {!item.is_system && (
                                        <Button
                                            variant="destructive"
                                            className="px-3"
                                            aria-label={`Hapus role ${item.nama}`}
                                            onClick={() => handleDelete(item)}
                                        >
                                            <Trash2
                                                className="h-4 w-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    )}
                                </CardFooter>
                            </Card>
                        ))
                    )}

                    <Card
                        className="group m-0 box-border flex min-h-[300px] cursor-pointer flex-col items-center justify-center gap-0 border border-dashed border-border bg-muted/20 pt-0 shadow-none transition-colors hover:border-primary/60 hover:bg-muted/40"
                        onClick={openCreateModal}
                    >
                        <CardContent className="flex h-full flex-col items-center justify-center p-6 pt-6 text-center select-none">
                            <div className="mb-4 rounded-full border border-primary/20 bg-primary p-4 text-primary-foreground shadow-sm transition-transform group-hover:scale-105">
                                <Plus className="h-8 w-8 stroke-[2.5] text-primary-foreground" />
                            </div>
                            <h3 className="mt-2 text-lg font-semibold tracking-tight">
                                Tambah Role Baru
                            </h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Buat hak akses kustom baru untuk pegawai
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {roles.data.length > 0 && (
                    <PaginationWrapper
                        links={roles.links}
                        lastPage={roles.last_page}
                    />
                )}
            </div>

            <ConfirmDeleteDialog
                open={!!deleteTarget}
                onOpenChange={(open) =>
                    !open && !deleteForm.processing && setDeleteTarget(null)
                }
                title="Hapus Role"
                description="Apakah Anda yakin ingin menghapus role"
                itemName={deleteTarget?.nama}
                onConfirm={confirmDelete}
                processing={deleteForm.processing}
            />

            {/* Modal Tambah Role */}
            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => !open && closeCreateModal()}
            >
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-semibold tracking-tight">
                            Tambah Role Baru
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="mt-2 space-y-6">
                        {Object.keys(errorsCreate).length > 0 && (
                            <AlertError
                                errors={errorsToArray(errorsCreate)}
                                title="Gagal membuat role"
                            />
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="nama">
                                Nama{' '}
                                <span className="text-destructive dark:text-red-400">
                                    *
                                </span>
                            </Label>
                            <Input
                                id="nama"
                                value={createData.nama}
                                onChange={(e) =>
                                    setCreateData('nama', e.target.value)
                                }
                                placeholder="Masukkan nama role"
                            />
                            {errorsCreate.nama && (
                                <p className="text-sm font-medium text-destructive dark:text-red-400">
                                    {errorsCreate.nama}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="keterangan">Keterangan</Label>
                            <Textarea
                                id="keterangan"
                                value={createData.keterangan}
                                onChange={(e) =>
                                    setCreateData('keterangan', e.target.value)
                                }
                                placeholder="Masukkan keterangan (opsional)"
                                rows={3}
                            />
                            {errorsCreate.keterangan && (
                                <p className="text-sm font-medium text-destructive dark:text-red-400">
                                    {errorsCreate.keterangan}
                                </p>
                            )}
                        </div>

                        {(permissions || []).length > 0 && (
                            <div className="space-y-4">
                                <Label>Permissions</Label>
                                <div className="grid gap-4 p-1 md:grid-cols-2">
                                    {Object.entries(groupedPermissions).map(
                                        ([group, perms]) => (
                                            <div
                                                key={group}
                                                className="space-y-2 rounded-xl border border-border bg-muted/30 p-3"
                                            >
                                                <h3 className="text-xs font-semibold tracking-wider text-primary uppercase">
                                                    {group}
                                                </h3>
                                                <div className="mt-2 space-y-2">
                                                    {perms.map((permission) => (
                                                        <div
                                                            key={permission.id}
                                                            className="flex items-start gap-2"
                                                        >
                                                            <Checkbox
                                                                id={`create-perm-${permission.id}`}
                                                                checked={createData.permissions.includes(
                                                                    permission.id,
                                                                )}
                                                                onCheckedChange={() =>
                                                                    togglePermission(
                                                                        permission.id,
                                                                        'create',
                                                                    )
                                                                }
                                                                className="mt-1"
                                                            />
                                                            <Label
                                                                htmlFor={`create-perm-${permission.id}`}
                                                                className="cursor-pointer text-sm leading-snug font-medium"
                                                            >
                                                                {
                                                                    permission.nama
                                                                }
                                                                {permission.keterangan && (
                                                                    <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                                                                        {
                                                                            permission.keterangan
                                                                        }
                                                                    </span>
                                                                )}
                                                            </Label>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        )}
                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeCreateModal}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={processingCreate}>
                                Simpan Role
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Edit Role */}
            <Dialog
                open={!!editingRole}
                onOpenChange={(open) => !open && closeEditModal()}
            >
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-semibold tracking-tight">
                            Edit Role/Hak Akses
                        </DialogTitle>
                    </DialogHeader>
                    {editingRole && (
                        <form onSubmit={submitEdit} className="mt-2 space-y-6">
                            {Object.keys(errorsEdit).length > 0 && (
                                <AlertError
                                    errors={errorsToArray(errorsEdit)}
                                    title="Gagal memperbarui role"
                                />
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="edit-nama">
                                    Nama{' '}
                                    <span className="text-destructive dark:text-red-400">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="edit-nama"
                                    value={editData.nama}
                                    onChange={(e) =>
                                        setEditData('nama', e.target.value)
                                    }
                                    placeholder="Masukkan nama role"
                                    disabled={editingRole.is_system}
                                />
                                {errorsEdit.nama && (
                                    <p className="text-sm font-medium text-destructive dark:text-red-400">
                                        {errorsEdit.nama}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-keterangan">
                                    Keterangan
                                </Label>
                                <Textarea
                                    id="edit-keterangan"
                                    value={editData.keterangan}
                                    onChange={(e) =>
                                        setEditData(
                                            'keterangan',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Masukkan keterangan (opsional)"
                                    rows={3}
                                />
                                {errorsEdit.keterangan && (
                                    <p className="text-sm font-medium text-destructive dark:text-red-400">
                                        {errorsEdit.keterangan}
                                    </p>
                                )}
                            </div>

                            {(permissions || []).length > 0 && (
                                <div className="space-y-4">
                                    <Label>Permissions</Label>
                                    <div className="grid gap-4 p-1 md:grid-cols-2">
                                        {Object.entries(groupedPermissions).map(
                                            ([group, perms]) => (
                                                <div
                                                    key={group}
                                                    className="space-y-2 rounded-xl border border-border bg-muted/30 p-3"
                                                >
                                                    <h3 className="text-xs font-semibold tracking-wider text-primary uppercase">
                                                        {group}
                                                    </h3>
                                                    <div className="mt-2 space-y-2">
                                                        {perms.map(
                                                            (permission) => (
                                                                <div
                                                                    key={
                                                                        permission.id
                                                                    }
                                                                    className="flex items-start gap-2"
                                                                >
                                                                    <Checkbox
                                                                        id={`edit-perm-${permission.id}`}
                                                                        checked={editData.permissions.includes(
                                                                            permission.id,
                                                                        )}
                                                                        onCheckedChange={() =>
                                                                            togglePermission(
                                                                                permission.id,
                                                                                'edit',
                                                                            )
                                                                        }
                                                                        className="mt-1"
                                                                    />
                                                                    <Label
                                                                        htmlFor={`edit-perm-${permission.id}`}
                                                                        className="cursor-pointer text-sm leading-snug font-medium"
                                                                    >
                                                                        {
                                                                            permission.nama
                                                                        }
                                                                        {permission.keterangan && (
                                                                            <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                                                                                {
                                                                                    permission.keterangan
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </Label>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}
                            <DialogFooter className="mt-6 gap-2 border-t border-border pt-4 sm:gap-0">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={closeEditModal}
                                >
                                    Batal
                                </Button>
                                <Button type="submit" disabled={processingEdit}>
                                    Perbarui Role
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
