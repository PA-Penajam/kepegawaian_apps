import { Head, Link, router, useForm } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';
import { ArrowLeft, Search, Save, Shield } from 'lucide-react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem, RefRole } from '@/types';
import {
    edit as editRole,
    index as rolesIndex,
    update as updateRole,
} from '@/routes/referensi/roles';
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
        // Minimal data needed for update. We MUST pass nama and keterangan to bypass the validation rule requiring them.
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
            { preserveState: true, preserveScroll: true }
        );
    };

    const isAllCurrentPageSelected = pegawaiList.data.length > 0 && pegawaiList.data.every(p => data.pegawai_ids.includes(p.id));

    const handleSelectAll = (e: React.MouseEvent) => {
        e.preventDefault();
        const currentPageIds = pegawaiList.data.map(p => p.id);
        let newIds = [...data.pegawai_ids];
        
        if (isAllCurrentPageSelected) {
            newIds = newIds.filter(id => !currentPageIds.includes(id));
        } else {
            currentPageIds.forEach(id => {
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

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="icon" className="border-2 border-black" asChild>
                            <Link href={toUrl(rolesIndex())}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold uppercase tracking-tight">Assign Pegawai</h1>
                            <p className="text-sm text-foreground/80 font-medium mt-0.5">Penugasan role untuk <span className="font-bold underline">{role.nama}</span></p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="md:col-span-1 flex flex-col gap-4">
                        <Card className="border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-muted/10">
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="w-5 h-5 text-primary" />
                                    Informasi Role
                                </CardTitle>
                                <CardDescription>Detail properti role yang sedang ditugaskan.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="text-sm font-bold text-muted-foreground uppercase tracking-wide">Nama Role</p>
                                    <p className="font-extrabold text-lg uppercase">{role.nama}</p>
                                </div>
                                <div className="p-3 bg-black/5 border-2 border-black/10 rounded-md">
                                    <p className="text-sm font-semibold">{role.keterangan || 'Tidak ada keterangan opsional.'}</p>
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
                            <Card className="border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)]">
                                <CardHeader className="pb-4 border-b-2 border-black/10 bg-muted/5">
                                    <CardTitle>Daftar Pegawai</CardTitle>
                                    <CardDescription>
                                        Pilih pegawai yang akan diberikan hak akses ini.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="pt-6 space-y-4">
                                    <div className="flex flex-col sm:flex-row gap-3">
                                        <div className="relative flex-1">
                                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                placeholder="Cari nama atau NIP pegawai (tekan enter/tunggu sejenak)..."
                                                value={searchPegawai}
                                                onChange={(e) => handleSearchPegawai(e.target.value)}
                                                className="pl-10 border-2 border-black focus-visible:shadow-[2px_2px_0_rgba(0,0,0,1)] transition-all"
                                            />
                                        </div>
                                        <Button 
                                            variant={isAllCurrentPageSelected ? "secondary" : "outline"}
                                            onClick={handleSelectAll}
                                            className="border-2 border-black font-bold whitespace-nowrap"
                                        >
                                            {isAllCurrentPageSelected ? "Hapus Pilihan" : "Pilih Semua (Halaman Ini)"}
                                        </Button>
                                    </div>
                                    
                                    <div className="h-[400px] space-y-1 overflow-y-auto rounded-md border-2 border-black/20 p-2 bg-muted/5">
                                        {pegawaiList.data.length === 0 ? (
                                            <div className="flex items-center justify-center h-full">
                                                <p className="text-center text-sm font-semibold text-muted-foreground">
                                                    Tidak ada pegawai ditemukan.
                                                </p>
                                            </div>
                                        ) : (
                                            pegawaiList.data.map((pegawai) => (
                                                <div
                                                    key={pegawai.id}
                                                    className="flex items-center gap-3 rounded-lg border-2 border-transparent px-3 py-2 hover:border-primary/30 hover:bg-muted/50 transition-colors"
                                                >
                                                    <Checkbox
                                                        id={`pegawai-${pegawai.id}`}
                                                        checked={data.pegawai_ids.includes(pegawai.id)}
                                                        onCheckedChange={() => togglePegawai(pegawai.id)}
                                                        className="w-5 h-5 border-2 border-black"
                                                    />
                                                    <Label
                                                        htmlFor={`pegawai-${pegawai.id}`}
                                                        className="flex-1 cursor-pointer text-sm font-bold flex flex-col"
                                                    >
                                                        <span>{pegawai.nama_lengkap}</span>
                                                        {pegawai.nip && (
                                                            <span className="text-xs font-semibold text-muted-foreground mt-0.5">
                                                                NIP: {pegawai.nip}
                                                            </span>
                                                        )}
                                                    </Label>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                    
                                    <div className="flex justify-between items-center px-1">
                                        {pegawaiList.last_page > 1 ? (
                                            <div className="flex items-center gap-4">
                                                <div className="flex gap-2">
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        className="h-8 border-2 border-black font-bold"
                                                        disabled={pegawaiList.current_page === 1}
                                                        onClick={(e) => { 
                                                            e.preventDefault(); 
                                                            const url = pegawaiList.links[0]?.url;
                                                            if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
                                                        }}
                                                    >
                                                        Prev
                                                    </Button>
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        className="h-8 border-2 border-black font-bold"
                                                        disabled={pegawaiList.current_page === pegawaiList.last_page}
                                                        onClick={(e) => { 
                                                            e.preventDefault(); 
                                                            const url = pegawaiList.links[pegawaiList.links.length - 1]?.url;
                                                            if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
                                                        }}
                                                    >
                                                        Next
                                                    </Button>
                                                </div>
                                                <p className="text-xs font-bold text-muted-foreground hidden sm:block">
                                                    Halaman {pegawaiList.current_page} dari {pegawaiList.last_page}
                                                </p>
                                            </div>
                                        ) : <div />}
                                        <p className="text-sm font-bold text-primary ml-auto">
                                            {data.pegawai_ids.length} pegawai telah dipilih
                                        </p>
                                    </div>
                                </CardContent>
                                <CardFooter className="bg-muted/10 border-t-2 border-black/10 flex justify-end gap-3 pt-4 pb-4">
                                    <Button variant="outline" asChild className="border-2 border-black font-bold">
                                        <Link href={toUrl(rolesIndex())}>Batal</Link>
                                    </Button>
                                    <Button type="submit" processing={processing} className="font-bold border-2 border-black gap-2">
                                        <Save className="w-4 h-4" />
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
