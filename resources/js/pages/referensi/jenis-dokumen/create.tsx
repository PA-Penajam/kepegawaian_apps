import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import {
    index as jenisDokumenIndex,
    store as storeJenisDokumen,
} from '@/routes/referensi/jenis-dokumen';
import type { BreadcrumbItem } from '@/types';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        nama: '',
        keterangan: '',
    });

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Referensi', href: '#' },
            { title: 'Jenis Dokumen', href: '/referensi/jenis-dokumen' },
            { title: 'Tambah', href: '#' },
        ],
        [],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(storeJenisDokumen.url());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Jenis Dokumen" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={jenisDokumenIndex()}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">
                        Tambah Jenis Dokumen
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-md space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="nama">
                            Nama <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="nama"
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            placeholder="Masukkan nama jenis dokumen"
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
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={jenisDokumenIndex()}>Batal</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
