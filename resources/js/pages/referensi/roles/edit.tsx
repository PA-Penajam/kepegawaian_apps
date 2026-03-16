import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, RefPermission, RefRole } from '@/types';
import { index, update } from '@/routes/referensi/roles';
import { useMemo, useState } from 'react';

type PegawaiItem = {
    id: string;
    nama_lengkap: string;
    nip: string | null;
};

type PaginatedPegawai = {
    data: PegawaiItem[];
    current_page: number;
    last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
    role: RefRole & { permissions: RefPermission[] };
    permissions: RefPermission[];
    pegawaiList: PaginatedPegawai;
    assignedPegawaiIds: string[];
};

export default function Edit({
    role,
    permissions,
    pegawaiList,
    assignedPegawaiIds,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        nama: role.nama,
        keterangan: role.keterangan ?? '',
        permissions: role.permissions?.map((p) => p.id) ?? [],
        pegawai_ids: assignedPegawaiIds ?? [],
    });

    const [searchPegawai, setSearchPegawai] = useState('');

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Roles', href: index() },
            { title: 'Edit', href: '#' },
        ],
        [],
    );

    const groupedPermissions = useMemo(() => {
        return permissions.reduce(
            (acc, p) => {
                const group = p.group ?? 'lainnya';
                if (!acc[group]) acc[group] = [];
                acc[group].push(p);
                return acc;
            },
            {} as Record<string, RefPermission[]>,
        );
    }, [permissions]);

    const togglePermission = (permissionId: string) => {
        const current = data.permissions;
        if (current.includes(permissionId)) {
            setData(
                'permissions',
                current.filter((id) => id !== permissionId),
            );
        } else {
            setData('permissions', [...current, permissionId]);
        }
    };

    const togglePegawai = (pegawaiId: string) => {
        const current = data.pegawai_ids;
        if (current.includes(pegawaiId)) {
            setData(
                'pegawai_ids',
                current.filter((id) => id !== pegawaiId),
            );
        } else {
            setData('pegawai_ids', [...current, pegawaiId]);
        }
    };

    const handleSearchPegawai = (value: string) => {
        setSearchPegawai(value);
        router.get(
            route('referensi.roles.edit', role.id),
            { search_pegawai: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(update(role.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Role" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={index()}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">Edit Role</h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="nama">
                            Nama <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="nama"
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            placeholder="Masukkan nama role"
                        />
                        {errors.nama && (
                            <p className="text-sm text-destructive">
                                {errors.nama}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="keterangan">Keterangan</Label>
                        <Textarea
                            id="keterangan"
                            value={data.keterangan}
                            onChange={(e) =>
                                setData('keterangan', e.target.value)
                            }
                            placeholder="Masukkan keterangan (opsional)"
                            rows={4}
                        />
                        {errors.keterangan && (
                            <p className="text-sm text-destructive">
                                {errors.keterangan}
                            </p>
                        )}
                    </div>

                    {permissions.length > 0 && (
                        <div className="space-y-4">
                            <Label>Permissions</Label>
                            <div className="grid gap-4 md:grid-cols-2">
                                {Object.entries(groupedPermissions).map(
                                    ([group, perms]) => (
                                        <div
                                            key={group}
                                            className="space-y-2 rounded-md border p-3"
                                        >
                                            <h3 className="text-sm font-medium capitalize">
                                                {group}
                                            </h3>
                                            <div className="space-y-1">
                                                {perms.map((permission) => (
                                                    <div
                                                        key={permission.id}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <Checkbox
                                                            id={permission.id}
                                                            checked={data.permissions.includes(
                                                                permission.id,
                                                            )}
                                                            onCheckedChange={() =>
                                                                togglePermission(
                                                                    permission.id,
                                                                )
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={
                                                                permission.id
                                                            }
                                                            className="text-sm font-normal"
                                                        >
                                                            {permission.nama}
                                                            {permission.keterangan && (
                                                                <span className="ml-1 text-muted-foreground">
                                                                    {' '}
                                                                    —{' '}
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

                    {/* Section Assign Pegawai */}
                    <div className="space-y-4">
                        <Label>Assign Pegawai</Label>
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Cari nama atau NIP..."
                                value={searchPegawai}
                                onChange={(e) =>
                                    handleSearchPegawai(e.target.value)
                                }
                                className="pl-10"
                            />
                        </div>
                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border p-3">
                            {pegawaiList.data.length === 0 ? (
                                <p className="py-4 text-center text-sm text-muted-foreground">
                                    Tidak ada pegawai ditemukan.
                                </p>
                            ) : (
                                pegawaiList.data.map((pegawai) => (
                                    <div
                                        key={pegawai.id}
                                        className="flex items-center gap-2 rounded px-2 py-1 hover:bg-muted/50"
                                    >
                                        <Checkbox
                                            id={`pegawai-${pegawai.id}`}
                                            checked={data.pegawai_ids.includes(
                                                pegawai.id,
                                            )}
                                            onCheckedChange={() =>
                                                togglePegawai(pegawai.id)
                                            }
                                        />
                                        <Label
                                            htmlFor={`pegawai-${pegawai.id}`}
                                            className="flex-1 cursor-pointer text-sm font-normal"
                                        >
                                            {pegawai.nama_lengkap}
                                            {pegawai.nip && (
                                                <span className="ml-1 text-muted-foreground">
                                                    (NIP: {pegawai.nip})
                                                </span>
                                            )}
                                        </Label>
                                    </div>
                                ))
                            )}
                        </div>
                        {pegawaiList.last_page > 1 && (
                            <p className="text-xs text-muted-foreground">
                                Halaman {pegawaiList.current_page} dari{' '}
                                {pegawaiList.last_page}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            {data.pegawai_ids.length} pegawai di-assign ke role
                            ini
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
