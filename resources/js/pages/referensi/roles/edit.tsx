import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Search, Save, Shield } from 'lucide-react';
import { useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import { toUrl } from '@/lib/utils';
import {
    edit as editRole,
    index as rolesIndex,
    update as updateRole,
} from '@/routes/referensi/roles';
import type { BreadcrumbItem, RefRole } from '@/types';

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
    role: RefRole;
    pegawaiList: PaginatedPegawai;
    assignedPegawaiIds: string[];
};

export default function EditAssign({
    role,
    pegawaiList,
    assignedPegawaiIds,
}: Props) {
    const { data, setData, put, processing, errors } = useForm({
        // Data minimal yang diperlukan untuk update; nama dan keterangan wajib dikirim sesuai aturan validasi.
        nama: role.nama,
        keterangan: role.keterangan || '',
        pegawai_ids: assignedPegawaiIds ?? [],
    });

    const [searchPegawai, setSearchPegawai] = useState('');

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Roles', href: toUrl(rolesIndex()) },
            { title: 'Assign Pegawai', href: '#' },
        ],
        [],
    );

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
            toUrl(editRole(role.id)),
            { search_pegawai: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    const isAllCurrentPageSelected =
        pegawaiList.data.length > 0 &&
        pegawaiList.data.every((p) => data.pegawai_ids.includes(p.id));

    const handleSelectAll = (e: React.MouseEvent) => {
        e.preventDefault();
        const currentPageIds = pegawaiList.data.map((p) => p.id);
        let newIds = [...data.pegawai_ids];

        if (isAllCurrentPageSelected) {
            newIds = newIds.filter((id) => !currentPageIds.includes(id));
        } else {
            currentPageIds.forEach((id) => {
                if (!newIds.includes(id)) {
                    newIds.push(id);
                }
            });
        }

        setData('pegawai_ids', newIds);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(toUrl(updateRole(role.id)));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Assign Pegawai ke Role" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" asChild>
                            <Link
                                href={toUrl(rolesIndex())}
                                aria-label="Kembali ke daftar role"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Assign Pegawai
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Penugasan role untuk{' '}
                                <span className="font-semibold text-foreground underline">
                                    {role.nama}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="flex flex-col gap-4 md:col-span-1">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5 text-primary" />
                                    Informasi Role
                                </CardTitle>
                                <CardDescription>
                                    Detail properti role yang sedang ditugaskan.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Nama Role
                                    </p>
                                    <p className="mt-1 text-lg font-semibold">
                                        {role.nama}
                                    </p>
                                </div>
                                <div className="rounded-md border border-border bg-muted/40 p-3">
                                    <p className="text-sm text-foreground">
                                        {role.keterangan ||
                                            'Tidak ada keterangan opsional.'}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="md:col-span-2">
                        {Object.keys(errors).length > 0 && (
                            <AlertError
                                errors={errorsToArray(errors)}
                                title="Gagal menyimpan penugasan"
                            />
                        )}
                        <form onSubmit={handleSubmit}>
                            <Card>
                                <CardHeader className="border-b border-border pb-4">
                                    <CardTitle>Daftar Pegawai</CardTitle>
                                    <CardDescription>
                                        Pilih pegawai yang akan diberikan hak
                                        akses ini.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-2">
                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <div className="relative flex-1">
                                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                placeholder="Cari nama atau NIP pegawai..."
                                                value={searchPegawai}
                                                onChange={(e) =>
                                                    handleSearchPegawai(
                                                        e.target.value,
                                                    )
                                                }
                                                className="pl-10"
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant={
                                                isAllCurrentPageSelected
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            onClick={handleSelectAll}
                                            className="whitespace-nowrap"
                                        >
                                            {isAllCurrentPageSelected
                                                ? 'Hapus Pilihan'
                                                : 'Pilih Semua (Halaman Ini)'}
                                        </Button>
                                    </div>

                                    <div className="h-[400px] space-y-1 overflow-y-auto rounded-md border border-border bg-muted/20 p-2">
                                        {pegawaiList.data.length === 0 ? (
                                            <div className="flex h-full items-center justify-center">
                                                <p className="text-center text-sm text-muted-foreground">
                                                    Tidak ada pegawai ditemukan.
                                                </p>
                                            </div>
                                        ) : (
                                            pegawaiList.data.map((pegawai) => (
                                                <div
                                                    key={pegawai.id}
                                                    className="flex items-center gap-3 rounded-lg border border-transparent px-3 py-2 transition-colors hover:border-border hover:bg-muted/50"
                                                >
                                                    <Checkbox
                                                        id={`pegawai-${pegawai.id}`}
                                                        checked={data.pegawai_ids.includes(
                                                            pegawai.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            togglePegawai(
                                                                pegawai.id,
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={`pegawai-${pegawai.id}`}
                                                        className="flex flex-1 cursor-pointer flex-col text-sm font-medium"
                                                    >
                                                        <span>
                                                            {
                                                                pegawai.nama_lengkap
                                                            }
                                                        </span>
                                                        {pegawai.nip && (
                                                            <span className="tnum mt-0.5 text-xs text-muted-foreground">
                                                                NIP:{' '}
                                                                {pegawai.nip}
                                                            </span>
                                                        )}
                                                    </Label>
                                                </div>
                                            ))
                                        )}
                                    </div>

                                    <div className="flex items-center justify-between px-1">
                                        {pegawaiList.last_page > 1 ? (
                                            <div className="flex items-center gap-4">
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            pegawaiList.current_page ===
                                                            1
                                                        }
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            const url =
                                                                pegawaiList
                                                                    .links[0]
                                                                    ?.url;

                                                            if (url) {
                                                                router.get(
                                                                    url,
                                                                    {},
                                                                    {
                                                                        preserveState: true,
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        Sebelumnya
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            pegawaiList.current_page ===
                                                            pegawaiList.last_page
                                                        }
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            const url =
                                                                pegawaiList
                                                                    .links[
                                                                    pegawaiList
                                                                        .links
                                                                        .length -
                                                                        1
                                                                ]?.url;

                                                            if (url) {
                                                                router.get(
                                                                    url,
                                                                    {},
                                                                    {
                                                                        preserveState: true,
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        Berikutnya
                                                    </Button>
                                                </div>
                                                <p className="tnum hidden text-xs font-medium text-muted-foreground sm:block">
                                                    Halaman{' '}
                                                    {pegawaiList.current_page}{' '}
                                                    dari {pegawaiList.last_page}
                                                </p>
                                            </div>
                                        ) : (
                                            <div />
                                        )}
                                        <p className="tnum ml-auto text-sm font-semibold text-primary">
                                            {data.pegawai_ids.length} pegawai
                                            telah dipilih
                                        </p>
                                    </div>
                                </CardContent>
                                <CardFooter className="flex justify-end gap-3 border-t border-border bg-muted/20 pt-6">
                                    <Button variant="outline" asChild>
                                        <Link href={toUrl(rolesIndex())}>
                                            Batal
                                        </Link>
                                    </Button>
                                    <Button
                                        type="submit"
                                        processing={processing}
                                    >
                                        <Save className="h-4 w-4" />
                                        Simpan Penugasan
                                    </Button>
                                </CardFooter>
                            </Card>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
