import { Head, Link, router, useForm } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2, Users, ShieldCheck, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, RefRole, RefPermission, PaginatedData } from '@/types';
import {
    index as rolesIndex,
    update,
    store,
    destroy,
    edit,
} from '@/routes/referensi/roles';

type Props = {
    roles: PaginatedData<RefRole>;
    permissions: RefPermission[];
    filters: {
        search?: string;
    };
};

export default function Index({ roles, permissions, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    // State for Modals
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<RefRole | null>(null);

    // Form logic for Create
    const { data: createData, setData: setCreateData, post: postCreate, processing: processingCreate, errors: errorsCreate, reset: resetCreate, clearErrors: clearErrorsCreate } = useForm({
        nama: '',
        keterangan: '',
        permissions: [] as string[],
    });

    // Form logic for Edit
    const { data: editData, setData: setEditData, put: putEdit, processing: processingEdit, errors: errorsEdit, reset: resetEdit, clearErrors: clearErrorsEdit } = useForm({
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
                if (!acc[group]) acc[group] = [];
                acc[group].push(p);
                return acc;
            },
            {} as Record<string, RefPermission[]>,
        );
    }, [permissions]);

    const handleSearch = useCallback(() => {
        router.get(
            toUrl(rolesIndex()),
            { search },
            { preserveState: true, preserveScroll: true },
        );
    }, [search]);

    useEffect(() => {
        const timeout = setTimeout(() => {
            handleSearch();
        }, 300);
        return () => clearTimeout(timeout);
    }, [search, handleSearch]);

    const handleDelete = (id: string) => {
        if (confirm('Apakah Anda yakin ingin menghapus role ini?')) {
            router.delete(toUrl(destroy(id)));
        }
    };

    // Dialog Handlers
    const openCreateModal = () => {
        resetCreate();
        clearErrorsCreate();
        setIsCreateOpen(true);
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        postCreate(toUrl(store()), {
            onSuccess: () => {
                setIsCreateOpen(false);
                resetCreate();
            }
        });
    };

    const openEditModal = (role: RefRole) => {
        setEditingRole(role);
        setEditData({
            nama: role.nama,
            keterangan: role.keterangan || '',
            permissions: role.permissions?.map(p => p.id) || []
        });
        clearErrorsEdit();
    };

    const closeEditModal = () => {
        setEditingRole(null);
        resetEdit();
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingRole) return;
        putEdit(toUrl(update(editingRole.id)), {
            onSuccess: () => closeEditModal()
        });
    };

    const togglePermission = (permissionId: string, formType: 'create' | 'edit') => {
        if (formType === 'create') {
            const current = createData.permissions;
            setCreateData('permissions', current.includes(permissionId) 
                ? current.filter(id => id !== permissionId) 
                : [...current, permissionId]);
        } else {
            const current = editData.permissions;
            setEditData('permissions', current.includes(permissionId) 
                ? current.filter(id => id !== permissionId) 
                : [...current, permissionId]);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold uppercase tracking-tight">Roles</h1>
                        <p className="text-sm text-muted-foreground mt-1 font-medium">Manajemen hak akses pengguna.</p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Input
                        placeholder="Cari role berdasarkan nama atau keterangan..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-md border-2 border-black drop-shadow-[2px_2px_0_rgba(0,0,0,1)] focus-visible:drop-shadow-none focus-visible:translate-y-[2px] focus-visible:translate-x-[2px] transition-all"
                    />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mt-2">
                    {roles.data.length === 0 ? (
                        <div className="col-span-full py-10 text-center border-2 border-black border-dashed bg-muted/30">
                            <p className="text-muted-foreground font-medium">Tidak ada data role ditemukan.</p>
                        </div>
                    ) : (
                        roles.data.map((item) => (
                            <Card key={item.id} className="relative group overflow-hidden transition-all hover:-translate-y-1 flex flex-col pt-0 gap-0">
                                <CardHeader className="pb-4 pt-6 border-b-2 border-black/10 bg-muted/10">
                                    <div className="flex justify-between items-start gap-2 mb-2">
                                        <CardTitle className="text-xl font-black capitalize line-clamp-1">{item.nama}</CardTitle>
                                        {item.is_system && (
                                            <Badge variant="secondary" className="border-2 border-black shrink-0 shadow-none font-bold">
                                                Sistem
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription className="line-clamp-2 min-h-[40px] text-sm font-medium text-foreground/80">
                                        {item.keterangan || 'Tidak ada keterangan spesifik untuk role ini.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="py-4 flex-grow flex flex-col gap-3">
                                    <div className="flex items-center justify-between text-sm p-3 bg-black/5 rounded-md border-2 border-black/10">
                                        <div className="flex items-center font-bold gap-2 text-foreground/80">
                                            <Users className="w-4 h-4" />
                                            Total Pegawai
                                        </div>
                                        <div className="font-extrabold text-sm">
                                            {(item as any).pegawai_count ?? 0}
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between text-sm p-3 bg-black/5 rounded-md border-2 border-black/10">
                                        <div className="flex items-center font-bold gap-2 text-foreground/80">
                                            <ShieldCheck className="w-4 h-4" />
                                            Permissions
                                        </div>
                                        <div className="font-extrabold text-sm">
                                            {(item as any).permissions_count ?? 0}
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="pt-0 pb-6 px-6 flex justify-between gap-2 mt-auto">
                                    <Button className="flex-1 font-bold gap-2 text-xs h-9 px-0" variant="outline" onClick={() => openEditModal(item)}>
                                        <Pencil className="w-3.5 h-3.5" />
                                        Edit
                                    </Button>
                                    <Button className="flex-1 font-bold gap-2 text-xs h-9 px-0" variant="default" asChild>
                                        <Link href={toUrl(edit(item.id))}>
                                            <Users className="w-3.5 h-3.5" />
                                            Pegawai
                                        </Link>
                                    </Button>
                                    {!item.is_system && (
                                        <Button 
                                            variant="destructive" 
                                            className="px-3" 
                                            onClick={() => handleDelete(item.id)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </CardFooter>
                            </Card>
                        ))
                    )}

                    <Card 
                        className="flex flex-col items-center justify-center min-h-[300px] border-dashed border-4 border-black/30 hover:border-black/60 transition-colors bg-muted/20 cursor-pointer group hover:bg-muted/40 drop-shadow-none box-border m-0 pt-0 gap-0" 
                        onClick={openCreateModal}
                    >
                        <CardContent className="flex flex-col items-center justify-center p-6 text-center select-none pt-6 h-full">
                            <div className="rounded-full bg-primary p-4 drop-shadow-[4px_4px_0_rgba(0,0,0,1)] text-primary-foreground border-2 border-black mb-4 group-hover:scale-110 transition-transform group-hover:drop-shadow-[6px_6px_0_rgba(0,0,0,1)]">
                                <Plus className="h-8 w-8 stroke-[3] text-black" />
                            </div>
                            <h3 className="text-xl font-black uppercase mt-2">Tambah Role Baru</h3>
                            <p className="text-sm text-foreground/70 font-medium mt-2">
                                Buat hak akses kustom baru untuk pegawai
                            </p>
                        </CardContent>
                    </Card>

                </div>

                {roles.data.length > 0 && <PaginationWrapper meta={roles.meta} />}
            </div>

            {/* Modal Tambah Role */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-2xl border-4 border-black drop-shadow-[8px_8px_0_rgba(0,0,0,1)] top-[50%] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-2xl font-black uppercase tracking-tight">Tambah Role Baru</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-6 mt-2">
                        <div className="space-y-2">
                            <Label htmlFor="nama" className="font-bold">
                                Nama <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="nama"
                                value={createData.nama}
                                onChange={(e) => setCreateData('nama', e.target.value)}
                                placeholder="Masukkan nama role"
                                className="border-2 border-black focus-visible:drop-shadow-[2px_2px_0_rgba(0,0,0,1)]"
                            />
                            {errorsCreate.nama && <p className="text-sm text-destructive font-semibold">{errorsCreate.nama}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="keterangan" className="font-bold">Keterangan</Label>
                            <Textarea
                                id="keterangan"
                                value={createData.keterangan}
                                onChange={(e) => setCreateData('keterangan', e.target.value)}
                                placeholder="Masukkan keterangan (opsional)"
                                rows={3}
                                className="border-2 border-black focus-visible:drop-shadow-[2px_2px_0_rgba(0,0,0,1)]"
                            />
                            {errorsCreate.keterangan && <p className="text-sm text-destructive font-semibold">{errorsCreate.keterangan}</p>}
                        </div>

                        {(permissions || []).length > 0 && (
                            <div className="space-y-4">
                                <Label className="font-bold">Permissions</Label>
                                <div className="grid gap-4 md:grid-cols-2 max-h-[40vh] overflow-y-auto p-1">
                                    {Object.entries(groupedPermissions).map(([group, perms]) => (
                                        <div key={group} className="space-y-2 rounded-xl border-2 border-black/20 bg-muted/10 p-3">
                                            <h3 className="text-sm font-black uppercase text-primary tracking-wider">{group}</h3>
                                            <div className="space-y-2 mt-2">
                                                {perms.map((permission) => (
                                                    <div key={permission.id} className="flex items-start gap-2">
                                                        <Checkbox
                                                            id={`create-perm-${permission.id}`}
                                                            checked={createData.permissions.includes(permission.id)}
                                                            onCheckedChange={() => togglePermission(permission.id, 'create')}
                                                            className="mt-1"
                                                        />
                                                        <Label htmlFor={`create-perm-${permission.id}`} className="text-sm font-semibold leading-snug cursor-pointer">
                                                            {permission.nama}
                                                            {permission.keterangan && (
                                                                <span className="block mt-0.5 text-xs text-muted-foreground font-normal">
                                                                    {permission.keterangan}
                                                                </span>
                                                            )}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>Batal</Button>
                            <Button type="submit" disabled={processingCreate}>Simpan Role</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Edit Role */}
            <Dialog open={!!editingRole} onOpenChange={(open) => !open && closeEditModal()}>
                <DialogContent className="max-w-2xl border-4 border-black drop-shadow-[8px_8px_0_rgba(0,0,0,1)] top-[50%] max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-2xl font-black uppercase tracking-tight">Edit Role/Hak Akses</DialogTitle>
                    </DialogHeader>
                    {editingRole && (
                    <form onSubmit={submitEdit} className="space-y-6 mt-2">
                        <div className="space-y-2">
                            <Label htmlFor="edit-nama" className="font-bold">
                                Nama <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-nama"
                                value={editData.nama}
                                onChange={(e) => setEditData('nama', e.target.value)}
                                placeholder="Masukkan nama role"
                                className="border-2 border-black focus-visible:drop-shadow-[2px_2px_0_rgba(0,0,0,1)]"
                                disabled={editingRole.is_system}
                            />
                            {errorsEdit.nama && <p className="text-sm text-destructive font-semibold">{errorsEdit.nama}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-keterangan" className="font-bold">Keterangan</Label>
                            <Textarea
                                id="edit-keterangan"
                                value={editData.keterangan}
                                onChange={(e) => setEditData('keterangan', e.target.value)}
                                placeholder="Masukkan keterangan (opsional)"
                                rows={3}
                                className="border-2 border-black focus-visible:drop-shadow-[2px_2px_0_rgba(0,0,0,1)]"
                            />
                            {errorsEdit.keterangan && <p className="text-sm text-destructive font-semibold">{errorsEdit.keterangan}</p>}
                        </div>

                        {(permissions || []).length > 0 && (
                            <div className="space-y-4">
                                <Label className="font-bold">Permissions</Label>
                                <div className="grid gap-4 md:grid-cols-2 max-h-[40vh] overflow-y-auto p-1">
                                    {Object.entries(groupedPermissions).map(([group, perms]) => (
                                        <div key={group} className="space-y-2 rounded-xl border-2 border-black/20 bg-muted/10 p-3">
                                            <h3 className="text-sm font-black uppercase text-primary tracking-wider">{group}</h3>
                                            <div className="space-y-2 mt-2">
                                                {perms.map((permission) => (
                                                    <div key={permission.id} className="flex items-start gap-2">
                                                        <Checkbox
                                                            id={`edit-perm-${permission.id}`}
                                                            checked={editData.permissions.includes(permission.id)}
                                                            onCheckedChange={() => togglePermission(permission.id, 'edit')}
                                                            className="mt-1"
                                                        />
                                                        <Label htmlFor={`edit-perm-${permission.id}`} className="text-sm font-semibold leading-snug cursor-pointer">
                                                            {permission.nama}
                                                            {permission.keterangan && (
                                                                <span className="block mt-0.5 text-xs text-muted-foreground font-normal">
                                                                    {permission.keterangan}
                                                                </span>
                                                            )}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                        <DialogFooter className="gap-2 sm:gap-0 pt-2 border-t-2 border-black/10 mt-6">
                            <Button type="button" variant="outline" onClick={closeEditModal} className="font-bold">Batal</Button>
                            <Button type="submit" disabled={processingEdit} className="font-bold">Perbarui Role</Button>
                        </DialogFooter>
                    </form>
                    )}
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}

