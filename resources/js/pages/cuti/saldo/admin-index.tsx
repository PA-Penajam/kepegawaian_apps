import { Head, router, useForm } from '@inertiajs/react';
import { Settings2, Plus } from 'lucide-react';
import { useCallback, useState } from 'react';
import AlertError from '@/components/alert-error';
import { DialogAdjustSaldo } from '@/components/cuti/DialogAdjustSaldo';
import InputError from '@/components/input-error';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import adminCuti from '@/routes/admin/cuti';
import type { BreadcrumbItem } from '@/types';
import type { AlokasiPaginated, AlokasiListItem } from '@/types/cuti';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti', href: '#' },
    { title: 'Kelola Saldo', href: adminCuti.saldo.index().url },
];

type Props = {
    alokasiList: AlokasiPaginated;
    tahun: number;
};

export default function AdminSaldoIndex({ alokasiList, tahun }: Props) {
    // State untuk filter tahun
    const [filterTahun, setFilterTahun] = useState(tahun);

    // State untuk dialog adjust
    const [adjustTarget, setAdjustTarget] = useState<{
        nip: string;
        nama: string;
        saldo: number;
    } | null>(null);

    // Form untuk bulk init
    const bulkForm = useForm({
        pegawai_nip: '',
        jenis_cuti_kode: 'CT',
        tahun: tahun,
        jumlah_hari: 12,
        keterangan: 'Inisialisasi saldo cuti tahunan',
    });

    // Terapkan filter tahun
    const applyFilter = useCallback(() => {
        router.get(
            adminCuti.saldo.index(),
            { tahun: filterTahun },
            { preserveState: true, replace: true },
        );
    }, [filterTahun]);

    // Submit bulk init
    function handleBulkInit(e: React.FormEvent) {
        e.preventDefault();
        bulkForm.post(adminCuti.saldo.init.store(), {
            preserveScroll: true,
            onSuccess: () => bulkForm.reset(),
        });
    }

    // Buka dialog adjust untuk satu pegawai
    function openSesuaikanDialog(item: AlokasiListItem) {
        setAdjustTarget({
            nip: item.pegawai_nip,
            nama: item.pegawai?.nama_lengkap ?? item.pegawai_nip,
            saldo: item.saldo_saat_ini,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelola Saldo Cuti" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold uppercase tracking-tight">Kelola Saldo Cuti</h1>
                    <p className="text-sm text-muted-foreground mt-1 font-medium">
                        Kelola dan sesuaikan saldo cuti pegawai.
                    </p>
                </div>
                {/* Filter Bar */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Filter</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-end gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="filter-tahun">Tahun</Label>
                            <Input
                                id="filter-tahun"
                                type="number"
                                className="w-28"
                                value={filterTahun}
                                onChange={(e) => setFilterTahun(parseInt(e.target.value) || tahun)}
                            />
                        </div>
                        <Button variant="outline" size="sm" onClick={applyFilter}>
                            Terapkan
                        </Button>
                    </CardContent>
                </Card>

                {/* Tabel Saldo */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">
                            Saldo Cuti Tahun {tahun}
                        </CardTitle>
                        <Badge variant="secondary">{alokasiList.total} data</Badge>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
                                    <TableHead className="font-black uppercase text-xs tracking-wider">NIP</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider">Nama Pegawai</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider">Jenis</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider text-right">Hak Awal</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider text-right">Saldo Saat Ini</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {alokasiList.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center py-12 font-medium text-muted-foreground">
                                            Belum ada data alokasi untuk tahun {tahun}
                                        </TableCell>
                                    </TableRow>
                                )}
                                {alokasiList.data.map((item) => (
                                    <TableRow key={item.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
                                        <TableCell className="font-mono text-sm">
                                            {item.pegawai_nip}
                                        </TableCell>
                                        <TableCell>
                                            {item.pegawai?.nama_lengkap ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{item.jenis_cuti_kode}</Badge>
                                        </TableCell>
                                        <TableCell className="text-right">{item.hak_awal}</TableCell>
                                        <TableCell className="text-right font-semibold">
                                            {item.saldo_saat_ini}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="xs"
                                                onClick={() => openSesuaikanDialog(item)}
                                            >
                                                <Settings2 className="mr-1 h-3 w-3" />
                                                Sesuaikan
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <PaginationWrapper
                    links={alokasiList.links}
                    lastPage={alokasiList.last_page}
                />

                {/* Form Inisialisasi Saldo */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Inisialisasi Saldo Pegawai</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleBulkInit} className="flex flex-wrap items-end gap-3">
                            {Object.keys(bulkForm.errors).length > 0 && (
                                <div className="w-full">
                                    <AlertError
                                        errors={errorsToArray(bulkForm.errors)}
                                        title="Gagal menginisialisasi saldo"
                                    />
                                </div>
                            )}
                            <div className="space-y-1">
                                <Label htmlFor="init-nip">NIP Pegawai</Label>
                                <Input
                                    id="init-nip"
                                    className="w-48"
                                    placeholder="Masukkan NIP"
                                    value={bulkForm.data.pegawai_nip}
                                    onChange={(e) => bulkForm.setData('pegawai_nip', e.target.value)}
                                />
                                <InputError message={bulkForm.errors.pegawai_nip} />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="init-tahun">Tahun</Label>
                                <Input
                                    id="init-tahun"
                                    type="number"
                                    className="w-28"
                                    value={bulkForm.data.tahun}
                                    onChange={(e) => bulkForm.setData('tahun', parseInt(e.target.value) || 0)}
                                />
                                <InputError message={bulkForm.errors.tahun} />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="init-jumlah">Jumlah Hari</Label>
                                <Input
                                    id="init-jumlah"
                                    type="number"
                                    className="w-24"
                                    min={1}
                                    value={bulkForm.data.jumlah_hari}
                                    onChange={(e) => bulkForm.setData('jumlah_hari', parseInt(e.target.value) || 0)}
                                />
                                <InputError message={bulkForm.errors.jumlah_hari} />
                            </div>
                            <Button type="submit" processing={bulkForm.processing}>
                                <Plus className="mr-1 h-4 w-4" />
                                Inisialisasi
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            {/* Dialog Sesuaikan Saldo */}
            {adjustTarget && (
                <DialogAdjustSaldo
                    pegawai={{ nip: adjustTarget.nip, nama: adjustTarget.nama }}
                    currentSaldo={adjustTarget.saldo}
                    open={!!adjustTarget}
                    onClose={() => setAdjustTarget(null)}
                />
            )}
        </AppLayout>
    );
}
