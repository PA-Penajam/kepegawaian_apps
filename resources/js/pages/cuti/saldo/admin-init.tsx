import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti', href: '#' },
    { title: 'Kelola Saldo', href: route('admin.cuti.saldo.index') },
    { title: 'Inisialisasi', href: '#' },
];

type Props = {
    tahun: number;
};

export default function AdminSaldoInit({ tahun }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        pegawai_nip: '',
        jenis_cuti_kode: 'CT',
        tahun: tahun,
        jumlah_hari: 12,
        keterangan: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(route('admin.cuti.saldo.init.store'));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inisialisasi Saldo Cuti" />

            <div className="mx-auto max-w-xl space-y-4 p-4">
                <Link
                    href={route('admin.cuti.saldo.index')}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali ke Kelola Saldo
                </Link>

                <Card>
                    <CardHeader>
                        <CardTitle>Inisialisasi Saldo Cuti</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <AlertError
                                    errors={errorsToArray(errors)}
                                    title="Gagal menginisialisasi saldo"
                                />
                            )}
                            {/* NIP Pegawai */}
                            <div className="space-y-2">
                                <Label htmlFor="pegawai_nip">NIP Pegawai</Label>
                                <Input
                                    id="pegawai_nip"
                                    placeholder="Masukkan NIP pegawai"
                                    value={data.pegawai_nip}
                                    onChange={(e) => setData('pegawai_nip', e.target.value)}
                                />
                                <InputError message={errors.pegawai_nip} />
                            </div>

                            {/* Jenis Cuti */}
                            <div className="space-y-2">
                                <Label htmlFor="jenis_cuti_kode">Jenis Cuti</Label>
                                <Input
                                    id="jenis_cuti_kode"
                                    value="CT — Cuti Tahunan"
                                    disabled
                                />
                            </div>

                            {/* Tahun */}
                            <div className="space-y-2">
                                <Label htmlFor="tahun">Tahun Hak</Label>
                                <Input
                                    id="tahun"
                                    type="number"
                                    min={2020}
                                    value={data.tahun}
                                    onChange={(e) => setData('tahun', parseInt(e.target.value) || 0)}
                                />
                                <InputError message={errors.tahun} />
                            </div>

                            {/* Jumlah Hari */}
                            <div className="space-y-2">
                                <Label htmlFor="jumlah_hari">Jumlah Hari</Label>
                                <Input
                                    id="jumlah_hari"
                                    type="number"
                                    min={1}
                                    value={data.jumlah_hari}
                                    onChange={(e) => setData('jumlah_hari', parseInt(e.target.value) || 0)}
                                />
                                <InputError message={errors.jumlah_hari} />
                            </div>

                            {/* Keterangan */}
                            <div className="space-y-2">
                                <Label htmlFor="keterangan">Keterangan (opsional)</Label>
                                <Textarea
                                    id="keterangan"
                                    rows={3}
                                    placeholder="Keterangan inisialisasi..."
                                    value={data.keterangan}
                                    onChange={(e) => setData('keterangan', e.target.value)}
                                />
                                <InputError message={errors.keterangan} />
                            </div>

                            <Button type="submit" processing={processing} className="w-full">
                                Inisialisasi Saldo
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
