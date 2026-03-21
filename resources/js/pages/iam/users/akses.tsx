import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
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
    const [revokeConfirm, setRevokeConfirm] = useState<{
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

        addRoleForm.post(`/iam/users/${user.id}/akses`, {
            onSuccess: () => {
                addRoleForm.reset();
                setSelectedAppId('');
                setSelectedRoleId('');
            },
        });
    }, [user.id, selectedRoleId, addRoleForm]);

    const handleRevokeAkses = useCallback(
        (roleId: number, roleName: string) => {
            setRevokeConfirm({ roleId, roleName });
        },
        [],
    );

    const confirmRevoke = useCallback(() => {
        if (!revokeConfirm) return;
        deleteForm.delete(
            `/iam/users/${user.id}/akses/${revokeConfirm.roleId}`,
            {
                onSuccess: () => {
                    setRevokeConfirm(null);
                },
            },
        );
    }, [revokeConfirm, user.id, deleteForm]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Akses ${user.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Akses User: {user.name}
                        </h1>
                        <p className="text-muted-foreground">{user.email}</p>
                    </div>
                </div>

                {/* Form Tambah Akses */}
                <div className="rounded-lg bg-muted/50 p-4">
                    <h2 className="mb-4 text-lg font-medium">
                        Tambah Akses Baru
                    </h2>
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
                    <div className="py-12 text-center text-muted-foreground">
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

                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Role</TableHead>
                                                <TableHead>
                                                    Permissions
                                                </TableHead>
                                                <TableHead>
                                                    Diberikan Oleh
                                                </TableHead>
                                                <TableHead>Tanggal</TableHead>
                                                <TableHead className="w-[100px] text-center">
                                                    Aksi
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {appAkses.map((a) => (
                                                <TableRow key={a.id}>
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
                                                                        a.role.id,
                                                                        a.role.nama,
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
                                                                    revokeConfirm?.roleId ===
                                                                    a.role.id
                                                                }
                                                                onOpenChange={(
                                                                    open: boolean,
                                                                ) =>
                                                                    !open &&
                                                                    setRevokeConfirm(
                                                                        null,
                                                                    )
                                                                }
                                                            >
                                                                <AlertDialogContent>
                                                                    <AlertDialogHeader>
                                                                        <AlertDialogTitle>
                                                                            Cabut
                                                                            Akses
                                                                        </AlertDialogTitle>
                                                                        <AlertDialogDescription>
                                                                            Apakah
                                                                            Anda
                                                                            yakin
                                                                            ingin
                                                                            mencabut
                                                                            akses
                                                                            "
                                                                            {
                                                                                revokeConfirm?.roleName
                                                                            }
                                                                            "
                                                                            dari
                                                                            user
                                                                            ini?
                                                                            Tindakan
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
                                                                                confirmRevoke
                                                                            }
                                                                            disabled={
                                                                                deleteForm.processing
                                                                            }
                                                                        >
                                                                            {deleteForm.processing
                                                                                ? 'Mencabut...'
                                                                                : 'Cabut'}
                                                                        </AlertDialogAction>
                                                                    </AlertDialogFooter>
                                                                </AlertDialogContent>
                                                            </AlertDialog>
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
        </AppLayout>
    );
}
