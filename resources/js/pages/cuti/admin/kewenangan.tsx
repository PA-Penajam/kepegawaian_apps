import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CutiJenisCuti, CutiKewenangan } from '@/types/cuti';

type Option = {
    value: string;
    label: string;
};

type Props = {
    rules: CutiKewenangan[];
    jenisOptions: CutiJenisCuti[];
    jabatanOptions: Option[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti', href: '/cuti/pengajuan' },
    { title: 'Matriks Kewenangan Eksternal', href: '/admin/cuti/kewenangan' },
];

export default function KewenanganAdmin({
    rules,
    jenisOptions,
    jabatanOptions,
}: Props) {
    const [submitting, setSubmitting] = useState(false);
    const [form, setForm] = useState({
        jenis_cuti_kode: jenisOptions[0]?.kode ?? 'CB',
        jenis_jabatan: jabatanOptions[0]?.value ?? 'fungsional',
        pemberi_izin: 'pta' as 'pta' | 'ma',
        instansi_nama: 'Pengadilan Tinggi Agama Samarinda',
        nama_aplikasi: '',
        url_aplikasi: '',
        aktif: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post('/admin/cuti/kewenangan', form, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    };

    const handleToggleActive = (rule: CutiKewenangan) => {
        router.patch(
            `/admin/cuti/kewenangan/${rule.id}`,
            {
                jenis_cuti_kode: rule.jenis_cuti_kode,
                jenis_jabatan: rule.jenis_jabatan,
                pemberi_izin: rule.pemberi_izin,
                instansi_nama: rule.instansi_nama,
                nama_aplikasi: rule.nama_aplikasi ?? '',
                url_aplikasi: rule.url_aplikasi ?? '',
                aktif: !rule.aktif,
            },
            { preserveScroll: true },
        );
    };

    const handleDelete = (id: string) => {
        router.delete(`/admin/cuti/kewenangan/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Matriks Kewenangan Cuti PTA/MA" />
            <div className="space-y-6 p-4 md:p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Tambah Aturan Kewenangan Instansi Atasan
                        </CardTitle>
                        <CardDescription>
                            Kosong secara bawaan (semua cuti diputus internal PA).
                            Tambahkan aturan bila kombinasi jenis cuti dan
                            jabatan harus meminta izin PTA atau Mahkamah Agung.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div className="space-y-1">
                                <Label>Jenis Cuti</Label>
                                <Select
                                    value={form.jenis_cuti_kode}
                                    onValueChange={(value) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            jenis_cuti_kode: value,
                                        }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {jenisOptions.map((item) => (
                                            <SelectItem
                                                key={item.kode}
                                                value={item.kode}
                                            >
                                                {item.kode} — {item.nama}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Jenis Jabatan</Label>
                                <Select
                                    value={form.jenis_jabatan}
                                    onValueChange={(value) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            jenis_jabatan: value,
                                        }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {jabatanOptions.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Pemberi Izin</Label>
                                <Select
                                    value={form.pemberi_izin}
                                    onValueChange={(value) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            pemberi_izin: value as 'pta' | 'ma',
                                        }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pta">
                                            PTA (Pengadilan Tinggi Agama)
                                        </SelectItem>
                                        <SelectItem value="ma">
                                            MA (Mahkamah Agung)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="instansi-nama">
                                    Nama Instansi Atasan
                                </Label>
                                <Input
                                    id="instansi-nama"
                                    value={form.instansi_nama}
                                    onChange={(e) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            instansi_nama: e.target.value,
                                        }))
                                    }
                                    required
                                />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="nama-aplikasi">
                                    Nama Aplikasi PTA/MA (Opsional)
                                </Label>
                                <Input
                                    id="nama-aplikasi"
                                    value={form.nama_aplikasi}
                                    onChange={(e) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            nama_aplikasi: e.target.value,
                                        }))
                                    }
                                />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="url-aplikasi">
                                    URL Aplikasi PTA/MA (Opsional)
                                </Label>
                                <Input
                                    id="url-aplikasi"
                                    type="url"
                                    placeholder="https://..."
                                    value={form.url_aplikasi}
                                    onChange={(e) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            url_aplikasi: e.target.value,
                                        }))
                                    }
                                />
                            </div>

                            <div className="sm:col-span-2 lg:col-span-3">
                                <Button type="submit" disabled={submitting}>
                                    {submitting
                                        ? 'Menyimpan...'
                                        : 'Tambah Aturan'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Aturan Terdaftar</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {rules.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada aturan eksternal. Seluruh pengajuan
                                cuti diputus secara internal.
                            </p>
                        ) : (
                            <div className="divide-y">
                                {rules.map((rule) => (
                                    <div
                                        key={rule.id}
                                        className="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="outline">
                                                    {rule.jenis_cuti_kode}
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {rule.jenis_jabatan}
                                                </Badge>
                                                <span className="font-medium uppercase">
                                                    {rule.pemberi_izin}
                                                </span>
                                                <span className="text-sm">
                                                    — {rule.instansi_nama}
                                                </span>
                                                <Badge
                                                    variant={
                                                        rule.aktif
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {rule.aktif
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
                                            </div>
                                            {(rule.nama_aplikasi ||
                                                rule.url_aplikasi) && (
                                                <p className="text-xs text-muted-foreground">
                                                    Aplikasi:{' '}
                                                    {rule.nama_aplikasi ?? '-'}{' '}
                                                    {rule.url_aplikasi
                                                        ? `(${rule.url_aplikasi})`
                                                        : ''}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleToggleActive(rule)
                                                }
                                            >
                                                {rule.aktif
                                                    ? 'Nonaktifkan'
                                                    : 'Aktifkan'}
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="icon-sm"
                                                onClick={() =>
                                                    handleDelete(rule.id)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
