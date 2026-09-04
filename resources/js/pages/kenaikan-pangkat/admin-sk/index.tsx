import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import adminSk from '@/routes/kenaikan-pangkat/admin-sk';
import type { BreadcrumbItem } from '@/types';
import type { KepegawaianPaginatedData } from '@/types/kepegawaian';

const MAX_FILE_SIZE = 10 * 1024 * 1024;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kenaikan Pangkat', href: '#' },
    { title: 'Admin SK', href: adminSk.index().url },
];

type KenaikanPangkatState = 'MENUNGGU_SK' | 'SELESAI_SK_TERBIT';

type Pangkat = {
    kode?: string | null;
    nama?: string | null;
    golongan?: string | null;
    ruang?: string | null;
};

type Pegawai = {
    nama_lengkap?: string | null;
    nama?: string | null;
    nip?: string | null;
};

type UsulanKenaikanPangkat = {
    id: string;
    state: KenaikanPangkatState | string;
    tanggal_kirim_biro?: string | null;
    dikirim_ke_biro_at?: string | null;
    tanggal_dikirim_biro?: string | null;
    submitted_at?: string | null;
    nomor_sk?: string | null;
    tanggal_sk?: string | null;
    sk_file_path?: string | null;
    sk_file_original_name?: string | null;
    pegawai?: Pegawai | null;
    pangkat_asal?: Pangkat | null;
    pangkat_tujuan?: Pangkat | null;
    pangkatAsal?: Pangkat | null;
    pangkatTujuan?: Pangkat | null;
};

type Props = {
    usulan: KepegawaianPaginatedData<UsulanKenaikanPangkat>;
    filters: { state?: KenaikanPangkatState | 'all'; per_page?: number };
};

type PageProps = {
    flash?: { success?: string };
};

type UploadForm = {
    sk_file: File | null;
    nomor_sk: string;
    tanggal_sk: string;
};

function formatTanggal(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value));
}

function getTanggalKirimBiro(item: UsulanKenaikanPangkat): string | null {
    return item.tanggal_kirim_biro ?? item.dikirim_ke_biro_at ?? item.tanggal_dikirim_biro ?? item.submitted_at ?? null;
}

function getUmurHari(item: UsulanKenaikanPangkat): string {
    const tanggalKirim = getTanggalKirimBiro(item);

    if (!tanggalKirim) {
        return '-';
    }

    const diff = Date.now() - new Date(tanggalKirim).getTime();
    const days = Math.max(0, Math.floor(diff / 86_400_000));

    return `${days} hari`;
}

function getNamaPegawai(item: UsulanKenaikanPangkat): string {
    return item.pegawai?.nama_lengkap ?? item.pegawai?.nama ?? '-';
}

function formatPangkat(pangkat?: Pangkat | null): string {
    if (!pangkat) {
        return '-';
    }

    const golongan = [pangkat.golongan, pangkat.ruang].filter(Boolean).join('/');
    const kode = pangkat.kode ? `${pangkat.kode} ` : '';
    const nama = pangkat.nama ?? '-';

    return golongan ? `${kode}${nama} (${golongan})` : `${kode}${nama}`;
}

function getPangkatTujuan(item: UsulanKenaikanPangkat): string {
    return formatPangkat(item.pangkat_tujuan ?? item.pangkatTujuan);
}

function UsulanTable({
    data,
    emptyText,
    mode,
    onUpload,
}: {
    data: UsulanKenaikanPangkat[];
    emptyText: string;
    mode: 'menunggu' | 'terbit';
    onUpload: (item: UsulanKenaikanPangkat) => void;
}) {
    const isTerbit = mode === 'terbit';

    return (
        <Table>
            <TableHeader>
                <TableRow className="border-b-2 border-black bg-muted/30 hover:bg-muted/30 dark:border-white/20">
                    <TableHead className="text-xs font-black tracking-wider uppercase">Pegawai</TableHead>
                    <TableHead className="text-xs font-black tracking-wider uppercase">Pangkat</TableHead>
                    <TableHead className="text-xs font-black tracking-wider uppercase">Tanggal Kirim Biro</TableHead>
                    <TableHead className="text-xs font-black tracking-wider uppercase">Umur</TableHead>
                    {isTerbit && <TableHead className="text-xs font-black tracking-wider uppercase">Nomor SK</TableHead>}
                    {isTerbit && <TableHead className="text-xs font-black tracking-wider uppercase">Tanggal SK</TableHead>}
                    <TableHead className="text-right text-xs font-black tracking-wider uppercase">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={isTerbit ? 7 : 5} className="py-12 text-center font-medium text-muted-foreground">
                            {emptyText}
                        </TableCell>
                    </TableRow>
                )}

                {data.map((item) => (
                    <TableRow key={item.id} className="border-b border-black/10 transition-colors hover:bg-muted/20 dark:border-white/10">
                        <TableCell>
                            <div className="flex flex-col gap-1">
                                <span className="font-semibold">{getNamaPegawai(item)}</span>
                                <span className="text-xs text-muted-foreground">{item.pegawai?.nip ?? 'NIP -'}</span>
                            </div>
                        </TableCell>
                        <TableCell>{getPangkatTujuan(item)}</TableCell>
                        <TableCell>{formatTanggal(getTanggalKirimBiro(item))}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{getUmurHari(item)}</Badge>
                        </TableCell>
                        {isTerbit && <TableCell className="font-mono text-xs">{item.nomor_sk ?? '-'}</TableCell>}
                        {isTerbit && <TableCell>{formatTanggal(item.tanggal_sk)}</TableCell>}
                        <TableCell className="text-right">
                            {item.state === 'MENUNGGU_SK' ? (
                                <Button size="sm" onClick={() => onUpload(item)}>
                                    <Upload className="size-4" />
                                    Upload SK
                                </Button>
                            ) : (
                                <Button size="sm" variant="outline" asChild disabled={!item.sk_file_path}>
                                    <a href={adminSk.downloadSk.url(item)}>
                                        <Download className="size-4" />
                                        Download
                                    </a>
                                </Button>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

export default function AdminSkIndex({ usulan, filters: initialFilters }: Props) {
    const { flash } = usePage<PageProps>().props;
    const [selectedState, setSelectedState] = useState<KenaikanPangkatState | 'all'>(initialFilters.state ?? 'all');
    const [selectedUsulan, setSelectedUsulan] = useState<UsulanKenaikanPangkat | null>(null);
    const [clientError, setClientError] = useState<string | null>(null);
    const { data, setData, post, processing, errors, reset, clearErrors, recentlySuccessful } = useForm<UploadForm>({
        sk_file: null,
        nomor_sk: '',
        tanggal_sk: '',
    });

    const menungguSk = useMemo(() => usulan.data.filter((item) => item.state === 'MENUNGGU_SK'), [usulan.data]);
    const skTerbit = useMemo(() => usulan.data.filter((item) => item.state === 'SELESAI_SK_TERBIT'), [usulan.data]);
    const showSuccessToast = Boolean(flash?.success) || recentlySuccessful;

    function applyStateFilter(nextState: KenaikanPangkatState | 'all') {
        setSelectedState(nextState);
        router.get(
            adminSk.index(),
            nextState === 'all' ? {} : { state: nextState },
            { preserveState: true, replace: true },
        );
    }

    function openUploadModal(item: UsulanKenaikanPangkat) {
        if (item.state !== 'MENUNGGU_SK') {
            return;
        }

        setSelectedUsulan(item);
        setClientError(null);
        clearErrors();
        reset();
    }

    function closeUploadModal() {
        if (processing) {
            return;
        }

        setSelectedUsulan(null);
        setClientError(null);
        clearErrors();
        reset();
    }

    function handleFileChange(file: File | null) {
        setClientError(null);

        if (!file) {
            setData('sk_file', null);

            return;
        }

        if (file.type !== 'application/pdf') {
            setClientError('File SK harus PDF.');
            setData('sk_file', null);

            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            setClientError('Ukuran file SK maksimal 10MB.');
            setData('sk_file', null);

            return;
        }

        setData('sk_file', file);
    }

    function submitUpload(event: { preventDefault: () => void }) {
        event.preventDefault();

        if (!selectedUsulan || selectedUsulan.state !== 'MENUNGGU_SK') {
            return;
        }

        if (!data.sk_file) {
            setClientError('Pilih file SK PDF terlebih dahulu.');

            return;
        }

        post(adminSk.uploadSk.url(selectedUsulan), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeUploadModal();
                router.reload({ only: ['usulan'] });
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin SK Kenaikan Pangkat" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                {showSuccessToast && (
                    <div className="fixed top-4 right-4 z-50 rounded-xl border-2 border-green-700 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800 shadow-[4px_4px_0_rgba(0,0,0,1)] dark:border-green-400 dark:bg-green-950 dark:text-green-100">
                        {flash?.success ?? 'SK kenaikan pangkat berhasil diunggah.'}
                    </div>
                )}

                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight uppercase">Admin SK Kenaikan Pangkat</h1>
                        <p className="mt-1 text-sm font-medium text-muted-foreground">
                            Kelola upload dan arsip SK final usulan kenaikan pangkat.
                        </p>
                    </div>
                    <div className="w-full space-y-1 md:w-64">
                        <Label htmlFor="state-filter">Filter State</Label>
                        <Select value={selectedState} onValueChange={(value) => applyStateFilter(value as KenaikanPangkatState | 'all')}>
                            <SelectTrigger id="state-filter">
                                <SelectValue placeholder="Pilih state" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua</SelectItem>
                                <SelectItem value="MENUNGGU_SK">MenungguSK</SelectItem>
                                <SelectItem value="SELESAI_SK_TERBIT">SelesaiSKTerbit</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">Menunggu SK</CardTitle>
                        <Badge variant="secondary">{menungguSk.length} usulan</Badge>
                    </CardHeader>
                    <CardContent className="p-0">
                        <UsulanTable
                            data={menungguSk}
                            emptyText="Tidak ada usulan MenungguSK"
                            mode="menunggu"
                            onUpload={openUploadModal}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">SK Terbit</CardTitle>
                        <Badge variant="secondary">{skTerbit.length} usulan</Badge>
                    </CardHeader>
                    <CardContent className="p-0">
                        <UsulanTable
                            data={skTerbit}
                            emptyText="Belum ada SK terbit"
                            mode="terbit"
                            onUpload={openUploadModal}
                        />
                    </CardContent>
                </Card>

                <PaginationWrapper links={usulan.links} lastPage={usulan.last_page} />
            </div>

            <Dialog open={Boolean(selectedUsulan)} onOpenChange={(open) => !open && closeUploadModal()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Upload SK Final</DialogTitle>
                        <DialogDescription>
                            Upload 1 file PDF SK untuk {selectedUsulan ? getNamaPegawai(selectedUsulan) : 'usulan ini'}.
                        </DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitUpload}>
                        <div className="space-y-2">
                            <Label htmlFor="sk_file">File SK (PDF, maks 10MB)</Label>
                            <Input
                                id="sk_file"
                                type="file"
                                accept="application/pdf,.pdf"
                                onChange={(event) => handleFileChange(event.target.files?.[0] ?? null)}
                                disabled={processing}
                            />
                            {(clientError || errors.sk_file) && (
                                <p className="text-sm font-medium text-destructive">{clientError ?? errors.sk_file}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="nomor_sk">Nomor SK</Label>
                            <Input
                                id="nomor_sk"
                                value={data.nomor_sk}
                                onChange={(event) => setData('nomor_sk', event.target.value)}
                                disabled={processing}
                                required
                            />
                            {errors.nomor_sk && <p className="text-sm font-medium text-destructive">{errors.nomor_sk}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="tanggal_sk">Tanggal SK</Label>
                            <Input
                                id="tanggal_sk"
                                type="date"
                                value={data.tanggal_sk}
                                onChange={(event) => setData('tanggal_sk', event.target.value)}
                                disabled={processing}
                                required
                            />
                            {errors.tanggal_sk && <p className="text-sm font-medium text-destructive">{errors.tanggal_sk}</p>}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeUploadModal} disabled={processing}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing || selectedUsulan?.state !== 'MENUNGGU_SK'}>
                                {processing ? 'Mengunggah...' : 'Upload SK'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
