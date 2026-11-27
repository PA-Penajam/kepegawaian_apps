import { Head, router } from '@inertiajs/react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CutiJenisMaster } from '@/types/cuti';

type SignerOption = {
    value: 'ketua' | 'wakil_ketua' | 'panitera' | 'sekretaris';
    label: string;
};

type Props = {
    jenisList: CutiJenisMaster[];
    signerOptions: SignerOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti', href: '/cuti/pengajuan' },
    { title: 'Penandatangan Surat Cuti', href: '/admin/cuti/jenis' },
];

export default function JenisCutiAdmin({ jenisList, signerOptions }: Props) {
    const [savingKode, setSavingKode] = useState<string | null>(null);
    const [selectedMap, setSelectedMap] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            jenisList.map((item) => [
                item.kode,
                item.penandatangan_surat ?? 'ketua',
            ]),
        ),
    );

    const handleSave = (kode: string) => {
        setSavingKode(kode);
        router.patch(
            `/admin/cuti/jenis/${kode}`,
            { penandatangan_surat: selectedMap[kode] ?? 'ketua' },
            {
                preserveScroll: true,
                onFinish: () => setSavingKode(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penandatangan Surat Izin Cuti" />
            <div className="space-y-6 p-4 md:p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Konfigurasi Pejabat Penandatangan Surat Izin Cuti
                        </CardTitle>
                        <CardDescription>
                            Menentukan pejabat penandatangan default yang
                            dikirim ke SiSukma untuk setiap jenis cuti.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y">
                            {jenisList.map((jenis) => {
                                const currentValue =
                                    selectedMap[jenis.kode] ??
                                    jenis.penandatangan_surat ??
                                    'ketua';
                                const isDirty =
                                    currentValue !==
                                    (jenis.penandatangan_surat ?? 'ketua');

                                return (
                                    <div
                                        key={jenis.kode}
                                        className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">
                                                    {jenis.kode}
                                                </Badge>
                                                <span className="font-medium">
                                                    {jenis.nama}
                                                </span>
                                                {jenis.aktif === false && (
                                                    <Badge variant="secondary">
                                                        Nonaktif
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Penandatangan aktif:{' '}
                                                {jenis.penandatangan_surat ??
                                                    'ketua'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Select
                                                value={currentValue}
                                                onValueChange={(value) =>
                                                    setSelectedMap((prev) => ({
                                                        ...prev,
                                                        [jenis.kode]: value,
                                                    }))
                                                }
                                            >
                                                <SelectTrigger className="w-56">
                                                    <SelectValue placeholder="Pilih penandatangan" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {signerOptions.map(
                                                        (opt) => (
                                                            <SelectItem
                                                                key={opt.value}
                                                                value={
                                                                    opt.value
                                                                }
                                                            >
                                                                {opt.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                size="sm"
                                                disabled={
                                                    savingKode === jenis.kode ||
                                                    !isDirty
                                                }
                                                onClick={() =>
                                                    handleSave(jenis.kode)
                                                }
                                            >
                                                {savingKode === jenis.kode
                                                    ? 'Menyimpan...'
                                                    : 'Simpan'}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
