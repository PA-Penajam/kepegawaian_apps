import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
        id: number;
        name: string;
        email: string;
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
            { title: user.name, href: `/iam/users/${user.id}/akses` },
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

    // Group akses by application
    const groupedAkses = useMemo(() => {
        const grouped: Record<
            number,
            { app: IamAvailableApp; akses: IamUserAkses[] }
        > = {};

        akses.forEach((a) => {
            const appId = a.role.application.id;

            if (!grouped[appId]) {
                grouped[appId] = {
                    app: {
                        id: a.role.application?.id ?? 0,
                        nama: a.role.application?.nama ?? '',
                        slug: a.role.application?.slug ?? '',
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

        addRoleForm.setData('iam_role_id', selectedRoleId);
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
            <Head title={`Akses ${user.name}`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight uppercase">
                            Akses User: {user.name}
                        </h1>
                        <p className="mt-1 text-sm font-medium text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>

                {/* Form Tambah Akses */}
                <div className="rounded-lg bg-muted/50 p-4">
                    <h2 className="mb-4 text-lg font-medium">
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
                            <label className="text-sm font-medium">
                                Aplikasi
                            </label>
                            <Select
                                value={selectedAppId}
                                onValueChange={setSelectedAppId}
                            >
                                <SelectTrigger className="w-[200px]">
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
                            <label className="text-sm font-medium">Role</label>
                            <Select
                                value={selectedRoleId}
                                onValueChange={setSelectedRoleId}
                                disabled={!selectedAppId}
                            >
                                <SelectTrigger className="w-[200px]">
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
                                    <h3 className="text-lg font-medium">
                                        {app.nama}
                                    </h3>
                                    <Badge variant="outline">
                                        {appAkses.length} role
                                        {appAkses.length !== 1 ? '' : ''}
                                    </Badge>
                                </div>

                                <div className="overflow-hidden rounded-xl border-2 border-black bg-background shadow-[4px_4px_0_rgba(0,0,0,1)]">
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="border-b-2 border-black bg-muted/30 hover:bg-muted/30">
                                                <TableHead className="text-xs font-black tracking-wider uppercase">
                                                    Role
                                                </TableHead>
                                                <TableHead className="text-xs font-black tracking-wider uppercase">
                                                    Permissions
                                                </TableHead>
                                                <TableHead className="text-xs font-black tracking-wider uppercase">
                                                    Diberikan Oleh
                                                </TableHead>
                                                <TableHead className="text-xs font-black tracking-wider uppercase">
                                                    Tanggal
                                                </TableHead>
                                                <TableHead className="w-[100px] text-center text-xs font-black tracking-wider uppercase">
                                                    Aksi
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {appAkses.map((a) => (
                                                <TableRow
                                                    key={a.id}
                                                    className="border-b border-black/10 transition-colors hover:bg-muted/20"
                                                >
                                                    <TableCell className="font-medium">
                                                        {a.role.nama}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap gap-1">
                                                            {a.role
                                                                .permissions &&
                                                            a.role.permissions
                                                                .length > 0 ? (
                                                                a.role.permissions
                                                                    .slice(0, 5)
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
                                                                    Tidak ada
                                                                </span>
                                                            )}
                                                            {a.role
                                                                .permissions &&
                                                                a.role
                                                                    .permissions
                                                                    .length >
                                                                    5 && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs"
                                                                    >
                                                                        +
                                                                        {a.role
                                                                            .permissions
                                                                            .length -
                                                                            5}
                                                                    </Badge>
                                                                )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {a.assignedByUser
                                                            ?.name ?? '-'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {new Date(
                                                            a.assigned_at,
                                                        ).toLocaleDateString(
                                                            'id-ID',
                                                            {
                                                                day: '2-digit',
                                                                month: 'short',
                                                                year: 'numeric',
                                                            },
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center justify-center">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Cabut akses ${a.role.nama}`}
                                                                onClick={() =>
                                                                    handleRevokeAkses(
                                                                        a.role
                                                                            .id,
                                                                        a.role
                                                                            .nama,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2
                                                                    className="h-4 w-4 text-destructive"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
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
                onOpenChange={(open) => !open && setRevokeTarget(null)}
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
