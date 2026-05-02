import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import {
    index as statusPegawaiIndex,
    update as updateStatusPegawai,
} from '@/routes/referensi/status-pegawai';
import type { BreadcrumbItem, RefStatusPegawai } from '@/types';

type Props = {
    statusPegawai: RefStatusPegawai;
};

export default function Edit({ statusPegawai }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        kode: statusPegawai.kode,
        nama: statusPegawai.nama,
        keterangan: statusPegawai.keterangan ?? '',
    });

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Status Pegawai', href: '/referensi/status-pegawai' },
            { title: 'Edit', href: '#' },
        ],
        [],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(updateStatusPegawai.url(statusPegawai.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Status Pegawai" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={statusPegawaiIndex()}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">
                        Edit Status Pegawai
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-md space-y-4">
                    {Object.keys(errors).length > 0 && (
                        <AlertError
                            errors={errorsToArray(errors)}
                            title="Gagal memperbarui data"
                        />
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="kode">
                            Kode <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="kode"
                            value={data.kode}
                            onChange={(e) => setData('kode', e.target.value)}
                            placeholder="Masukkan kode status pegawai"
                        />
                        {errors.kode && (
                            <p className="text-sm text-destructive">
                                {errors.kode}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="nama">
                            Nama <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="nama"
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            placeholder="Masukkan nama status pegawai"
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

                    <div className="flex gap-2">
                        <Button type="submit" processing={processing}>
                            Simpan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={statusPegawaiIndex()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
