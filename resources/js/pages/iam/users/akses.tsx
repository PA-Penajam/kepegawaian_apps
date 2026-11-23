import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem, IamAvailableApp, IamUserAkses } from '@/types';

type Props = {
    user: {
        id: string;
        nama_lengkap: string;
        email: string | null;
    };
    akses: IamUserAkses[];
    availableApps: IamAvailableApp[];
};

export default function Akses() {
    const { user, akses, availableApps } = usePage<Props>().props;
    const [selectedAppId, setSelectedAppId] = useState<string>('');
    const [selectedRoleId, setSelectedRoleId] = useState<string>('');

    // State untuk delete confirmation
    const [revokeTarget, setRevokeTarget] = useState<{
        roleId: number;
        roleName: string;
    } | null>(null);

    // Form untuk tambah role
    const addRoleForm = useForm({
        iam_role_id: '',
    });

    // Form untuk delete
    const deleteForm = useForm({});

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'User Akses', href: '/iam/users' },
            { title: user.nama_lengkap, href: `/iam/users/${user.id}/akses` },
        ],
        [user],
    );

    // Filter roles berdasarkan app yang dipilih
    const availableRoles = useMemo(() => {
        if (!selectedAppId) {
            return [];
        }

        const app = availableApps.find(
            (a) => a.id.toString() === selectedAppId,
        );

        return app?.roles ?? [];
    }, [selectedAppId, availableApps]);

    // Group akses by application (abaikan entri yatim tanpa role atau aplikasi valid)
    const groupedAkses = useMemo(() => {
        const grouped: Record<
            string | number,
            { app: IamAvailableApp; akses: IamUserAkses[] }
        > = {};

        (akses ?? []).forEach((a) => {
            if (!a?.role?.application) {
                return;
            }

            const app = a.role.application;
            const appId = app.id;

            if (!grouped[appId]) {
                grouped[appId] = {
                    app: {
                        id: app.id,
                        nama: app.nama ?? '',
                        slug: app.slug ?? '',
                        roles: [],
                    } satisfies IamAvailableApp,
                    akses: [],
                };
            }

            grouped[appId].akses.push(a);
        });

        return Object.values(grouped);
    }, [akses]);

    const handleAddRole = useCallback(() => {
        if (!selectedAppId || !selectedRoleId) {
            return;
        }

        // Gunakan transform agar payload iam_role_id selalu sinkron dengan pilihan terakhir.
        addRoleForm.transform(() => ({
            iam_role_id: selectedRoleId,
        }));
        addRoleForm.post(`/iam/users/${user.id}/akses`, {
            onSuccess: () => {
                addRoleForm.reset();
                setSelectedAppId('');
                setSelectedRoleId('');
            },
        });
    }, [user.id, selectedAppId, selectedRoleId, addRoleForm]);

    const handleRevokeAkses = useCallback(
        (roleId: number, roleName: string) => {
            setRevokeTarget({ roleId, roleName });
        },
        [],
    );

    const confirmRevoke = useCallback(() => {
        if (!revokeTarget) {
            return;
        }

        deleteForm.delete(
            `/iam/users/${user.id}/akses/${revokeTarget.roleId}`,
            {
                onSuccess: () => {
                    setRevokeTarget(null);
                },
            },
        );
    }, [revokeTarget, user.id, deleteForm]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Akses ${user.nama_lengkap}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Akses User: {user.nama_lengkap}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>

                {/* Form Tambah Akses */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold tracking-tight">
                        Tambah Akses Baru
                    </h2>
                    {Object.keys(addRoleForm.errors).length > 0 && (
                        <AlertError
                            errors={errorsToArray(addRoleForm.errors)}
                            title="Gagal menambahkan akses"
                        />
                    )}
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="select-app">Aplikasi</Label>
                            <Select
                                value={selectedAppId}
                                onValueChange={(value) => {
                                    setSelectedAppId(value);
                                    setSelectedRoleId('');
                                    addRoleForm.setData('iam_role_id', '');
                                }}
                            >
                                <SelectTrigger
                                    id="select-app"
                                    className="w-[200px]"
                                >
                                    <SelectValue placeholder="Pilih Aplikasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableApps.map((app) => (
                                        <SelectItem
                                            key={app.id}
                                            value={app.id.toString()}
                                        >
                                            {app.nama}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="select-role">Role</Label>
                            <Select
                                value={selectedRoleId}
                                onValueChange={(value) => {
                                    setSelectedRoleId(value);
                                    addRoleForm.setData('iam_role_id', value);
                                }}
                                disabled={!selectedAppId}
                            >
                                <SelectTrigger
                                    id="select-role"
                                    className="w-[200px]"
                                >
                                    <SelectValue placeholder="Pilih Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableRoles.map((role) => (
                                        <SelectItem
                                            key={role.id}
                                            value={role.id.toString()}
                                        >
                                            {role.nama}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            onClick={handleAddRole}
                            disabled={
                                !selectedAppId ||
                                !selectedRoleId ||
                                addRoleForm.processing
                            }
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            {addRoleForm.processing
                                ? 'Menambahkan...'
                                : 'Tambah'}
                        </Button>
                    </div>
                </div>

                {/* Daftar Akses by Application */}
                {groupedAkses.length === 0 ? (
                    <div className="py-12 text-center font-medium text-muted-foreground">
                        User ini belum memiliki akses IAM.
                    </div>
                ) : (
                    <div className="flex flex-col gap-6">
                        {groupedAkses.map(({ app, akses: appAkses }) => (
                            <div key={app.id} className="flex flex-col gap-3">
                                <div className="flex items-center gap-3">
                                    <h3 className="text-lg font-semibold tracking-tight">
                                        {app.nama}
                                    </h3>
                                    <Badge variant="outline" className="tnum">
                                        {appAkses.length} role
                                    </Badge>
                                </div>

                                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="border-b border-border bg-muted/50 hover:bg-muted/50">
                                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                                    Role
                                                </TableHead>
                                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                                    Permissions
                                                </TableHead>
                                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                                    Diberikan Oleh
                                                </TableHead>
                                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                                    Tanggal
                                                </TableHead>
                                                <TableHead className="w-[100px] text-center text-xs font-semibold tracking-wider text-foreground uppercase">
                                                    Aksi
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {appAkses.map((a) => {
                                                const permissions =
                                                    a.role?.permissions ?? [];

                                                return (
                                                    <TableRow
                                                        key={a.id}
                                                        className="border-b border-border transition-colors hover:bg-muted/40"
                                                    >
                                                        <TableCell className="font-medium">
                                                            {a.role?.nama ??
                                                                '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-wrap gap-1">
                                                                {permissions.length >
                                                                0 ? (
                                                                    permissions
                                                                        .slice(
                                                                            0,
                                                                            5,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                perm,
                                                                            ) => (
                                                                                <Badge
                                                                                    key={
                                                                                        perm.id
                                                                                    }
                                                                                    variant="secondary"
                                                                                    className="text-xs"
                                                                                >
                                                                                    {
                                                                                        perm.nama
                                                                                    }
                                                                                </Badge>
                                                                            ),
                                                                        )
                                                                ) : (
                                                                    <span className="text-sm text-muted-foreground">
                                                                        Tidak
                                                                        ada
                                                                    </span>
                                                                )}
                                                                {permissions.length >
                                                                    5 && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs"
                                                                    >
                                                                        +
                                                                        {permissions.length -
                                                                            5}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            {(
                                                                a.assigned_by_user ??
                                                                a.assignedByUser
                                                            )?.nama_lengkap ??
                                                                '-'}
                                                        </TableCell>
                                                        <TableCell className="tnum">
                                                            {a.assigned_at
                                                                ? new Date(
                                                                      a.assigned_at,
                                                                  ).toLocaleDateString(
                                                                      'id-ID',
                                                                      {
                                                                          day: '2-digit',
                                                                          month: 'short',
                                                                          year: 'numeric',
                                                                      },
                                                                  )
                                                                : '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center justify-center">
                                                                {a.role ? (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label={`Cabut akses ${a.role.nama}`}
                                                                        onClick={() => {
                                                                            if (
                                                                                a.role
                                                                            ) {
                                                                                handleRevokeAkses(
                                                                                    a
                                                                                        .role
                                                                                        .id,
                                                                                    a
                                                                                        .role
                                                                                        .nama,
                                                                                );
                                                                            }
                                                                        }}
                                                                    >
                                                                        <Trash2
                                                                            className="h-4 w-4 text-destructive"
                                                                            aria-hidden="true"
                                                                        />
                                                                    </Button>
                                                                ) : null}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <ConfirmDeleteDialog
                open={!!revokeTarget}
                onOpenChange={(open) =>
                    !open && !deleteForm.processing && setRevokeTarget(null)
                }
                title="Cabut Akses"
                description="Apakah Anda yakin ingin mencabut akses"
                itemName={revokeTarget?.roleName}
                onConfirm={confirmRevoke}
                processing={deleteForm.processing}
                confirmLabel="Cabut"
            />
        </AppLayout>
    );
}
