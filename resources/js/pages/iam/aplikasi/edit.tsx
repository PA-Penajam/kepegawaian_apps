import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AplikasiEdit = {
    id: string;
    nama: string;
    slug: string;
    url: string;
    deskripsi: string | null;
    is_active: boolean;
};

type Props = {
    aplikasi: AplikasiEdit;
};

export default function Edit() {
    const { aplikasi } = usePage<Props>().props;

    const form = useForm({
        nama: aplikasi.nama,
        url: aplikasi.url,
        deskripsi: aplikasi.deskripsi ?? '',
        is_active: aplikasi.is_active,
    });

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'IAM', href: '#' },
            { title: 'Aplikasi', href: '/iam/aplikasi' },
            {
                title: aplikasi.nama,
                href: `/iam/aplikasi/${aplikasi.id}`,
            },
            { title: 'Edit', href: `/iam/aplikasi/${aplikasi.id}/edit` },
        ],
        [aplikasi],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/iam/aplikasi/${aplikasi.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${aplikasi.nama}`} />

            <div className="flex flex-col gap-6 p-4 max-w-2xl">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={`/iam/aplikasi/${aplikasi.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Edit Aplikasi
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Ubah detail aplikasi{' '}
                            <span className="font-medium">{aplikasi.nama}</span>
                        </p>
                    </div>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <div className="rounded-lg border p-6 flex flex-col gap-4">
                        {/* Nama */}
                        <div className="grid gap-2">
                            <Label htmlFor="nama">Nama Aplikasi</Label>
                            <Input
                                id="nama"
                                value={form.data.nama}
                                onChange={(e) =>
                                    form.setData('nama', e.target.value)
                                }
                                placeholder="Contoh: Aplikasi Keuangan"
                                required
                            />
                            {form.errors.nama && (
                                <p className="text-sm text-destructive">
                                    {form.errors.nama}
                                </p>
                            )}
                        </div>

                        {/* Slug — readonly karena tidak bisa diubah setelah dibuat */}
                        <div className="grid gap-2">
                            <Label htmlFor="slug">
                                Slug
                                <span className="ml-2 text-xs text-muted-foreground">
                                    (tidak dapat diubah)
                                </span>
                            </Label>
                            <Input
                                id="slug"
                                value={aplikasi.slug}
                                readOnly
                                disabled
                                className="font-mono bg-muted"
                            />
                        </div>

                        {/* URL */}
                        <div className="grid gap-2">
                            <Label htmlFor="url">URL Aplikasi</Label>
                            <Input
                                id="url"
                                type="url"
                                value={form.data.url}
                                onChange={(e) =>
                                    form.setData('url', e.target.value)
                                }
                                placeholder="https://example.com"
                                required
                            />
                            {form.errors.url && (
                                <p className="text-sm text-destructive">
                                    {form.errors.url}
                                </p>
                            )}
                        </div>

                        {/* Deskripsi */}
                        <div className="grid gap-2">
                            <Label htmlFor="deskripsi">
                                Deskripsi (Opsional)
                            </Label>
                            <Textarea
                                id="deskripsi"
                                value={form.data.deskripsi}
                                onChange={(e) =>
                                    form.setData('deskripsi', e.target.value)
                                }
                                placeholder="Deskripsi singkat aplikasi"
                                rows={3}
                            />
                            {form.errors.deskripsi && (
                                <p className="text-sm text-destructive">
                                    {form.errors.deskripsi}
                                </p>
                            )}
                        </div>

                        {/* Status Aktif */}
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <div>
                                <Label htmlFor="is_active" className="font-medium cursor-pointer">
                                    Aplikasi Aktif
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Aplikasi yang nonaktif tidak bisa melakukan SSO
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={form.processing}
                        >
                            <Save className="mr-2 h-4 w-4" />
                            {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/iam/aplikasi/${aplikasi.id}`}>
                                Batal
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
