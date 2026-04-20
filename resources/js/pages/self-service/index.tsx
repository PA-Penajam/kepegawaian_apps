import { Head, Link } from '@inertiajs/react';
import { Calendar, ChevronRight, Clock3, TrendingUp, User } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { detail as selfServiceDetail } from '@/routes/self-service';
import { toUrl } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { PegawaiDetail } from '@/types/pegawai-detail';

type PegawaiSummary = Pick<
    PegawaiDetail,
    | 'id'
    | 'nip'
    | 'nama_lengkap'
    | 'foto_url'
    | 'pangkat'
    | 'jabatan'
    | 'unit_kerja'
    | 'tmt_pns'
>;

type KgbInfo = {
    tanggal_kgb_berikutnya: string;
    sisa_hari: number;
    status: string;
};

type KpInfo = {
    tmt_kp_berikutnya: string;
    periode_usul: string;
    batas_usul: string;
    sisa_hari_usul: number;
    status: string;
};

type Props = {
    pegawai: PegawaiSummary;
    kgbInfo: KgbInfo | null;
    kpInfo: KpInfo | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self Service', href: '/self-service' },
];

const statusVariantMap: Record<
    string,
    'default' | 'destructive' | 'outline' | 'secondary'
> = {
    'Sudah Jatuh Tempo': 'destructive',
    Segera: 'secondary',
    Mendekati: 'outline',
    Aman: 'default',
    'Sudah Eligible': 'secondary',
    'Mendekati Eligible': 'outline',
    'Belum Eligible': 'default',
};

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((item) => item[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

function getPangkatLabel(pegawai: PegawaiSummary): string {
    if (!pegawai.pangkat) {
        return '-';
    }

    if (!pegawai.pangkat.golongan) {
        return pegawai.pangkat.nama;
    }

    const ruang = pegawai.pangkat.ruang ? `/${pegawai.pangkat.ruang}` : '';

    return `${pegawai.pangkat.nama} (${pegawai.pangkat.golongan}${ruang})`;
}

function formatDate(date: string | null): string {
    if (date === null) {
        return '-';
    }

    const parsedDate = new Date(`${date}T00:00:00`);

    return Number.isNaN(parsedDate.getTime())
        ? '-'
        : new Intl.DateTimeFormat('id-ID', {
              day: '2-digit',
              month: 'long',
              year: 'numeric',
          }).format(parsedDate);
}

function formatHariTersisa(totalHari: number): string {
    if (totalHari < 0) {
        return `${Math.abs(totalHari)} hari terlewat`;
    }

    if (totalHari === 0) {
        return 'Hari ini';
    }

    return `${totalHari} hari lagi`;
}

function getMasaKerja(tmtPns: string | null): string {
    if (tmtPns === null) {
        return 'Belum tersedia';
    }

    const startDate = new Date(`${tmtPns}T00:00:00`);

    if (Number.isNaN(startDate.getTime())) {
        return 'Belum tersedia';
    }

    const today = new Date();
    let years = today.getFullYear() - startDate.getFullYear();
    let months = today.getMonth() - startDate.getMonth();

    if (today.getDate() < startDate.getDate()) {
        months -= 1;
    }

    if (months < 0) {
        years -= 1;
        months += 12;
    }

    if (years < 0) {
        return 'Belum tersedia';
    }

    return `${years} tahun ${months} bulan`;
}

export default function SelfServiceIndex({ pegawai, kgbInfo, kpInfo }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Self Service" />

            <div className="flex flex-col gap-6 p-4 sm:p-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                                <Avatar className="h-20 w-20 text-2xl">
                                    <AvatarImage
                                        src={pegawai.foto_url ?? undefined}
                                        alt={pegawai.nama_lengkap}
                                    />
                                    <AvatarFallback>
                                        {getInitials(pegawai.nama_lengkap)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="space-y-2 text-center sm:text-left">
                                    <div>
                                        <h2 className="text-xl font-semibold">
                                            {pegawai.nama_lengkap}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            NIP: {pegawai.nip ?? 'Belum diisi'}
                                        </p>
                                    </div>

                                    <div className="grid gap-3 text-sm sm:grid-cols-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Pangkat / Golongan
                                            </p>
                                            <p className="font-medium">
                                                {getPangkatLabel(pegawai)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Jabatan
                                            </p>
                                            <p className="font-medium">
                                                {pegawai.jabatan?.nama ?? '-'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Unit Kerja
                                            </p>
                                            <p className="font-medium">
                                                {pegawai.unit_kerja?.nama ??
                                                    '-'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Button variant="outline" asChild>
                                <Link href={toUrl(selfServiceDetail())}>
                                    <User className="mr-2 h-4 w-4" />
                                    Lihat Detail Lengkap
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Calendar className="h-4 w-4" />
                                KGB Berikutnya
                            </CardTitle>
                            <CardDescription>
                                Ringkasan kenaikan gaji berkala pegawai.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {kgbInfo ? (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            Status
                                        </span>
                                        <Badge
                                            variant={
                                                statusVariantMap[
                                                    kgbInfo.status
                                                ] ?? 'default'
                                            }
                                        >
                                            {kgbInfo.status}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            TMT KGB
                                        </span>
                                        <span className="text-sm font-medium">
                                            {formatDate(
                                                kgbInfo.tanggal_kgb_berikutnya,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            Sisa Hari
                                        </span>
                                        <span className="text-sm font-medium">
                                            {formatHariTersisa(
                                                kgbInfo.sisa_hari,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Data KGB belum tersedia untuk pegawai ini.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <TrendingUp className="h-4 w-4" />
                                Kenaikan Pangkat Berikutnya
                            </CardTitle>
                            <CardDescription>
                                Ringkasan periode usul kenaikan pangkat.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {kpInfo ? (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            Status
                                        </span>
                                        <Badge
                                            variant={
                                                statusVariantMap[
                                                    kpInfo.status
                                                ] ?? 'default'
                                            }
                                        >
                                            {kpInfo.status}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            TMT KP
                                        </span>
                                        <span className="text-sm font-medium">
                                            {formatDate(
                                                kpInfo.tmt_kp_berikutnya,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            Periode Usul
                                        </span>
                                        <span className="text-sm font-medium">
                                            {kpInfo.periode_usul}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            Batas Usul
                                        </span>
                                        <span className="text-sm font-medium">
                                            {formatDate(kpInfo.batas_usul)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            Sisa Hari Batas Usul
                                        </span>
                                        <span className="text-sm font-medium">
                                            {formatHariTersisa(
                                                kpInfo.sisa_hari_usul,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Data kenaikan pangkat belum tersedia untuk
                                    pegawai ini.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Clock3 className="h-4 w-4" />
                                Masa Kerja
                            </CardTitle>
                            <CardDescription>
                                Perhitungan masa kerja sejak TMT PNS.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm text-muted-foreground">
                                    TMT PNS
                                </span>
                                <span className="text-sm font-medium">
                                    {formatDate(pegawai.tmt_pns)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm text-muted-foreground">
                                    Masa Kerja
                                </span>
                                <span className="text-sm font-medium">
                                    {getMasaKerja(pegawai.tmt_pns)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-4">
                    <Card className="border-dashed shadow-sm">
                        <CardContent className="flex h-full flex-col justify-between gap-4 pt-6">
                            <div>
                                <p className="font-medium">Detail Profile Pegawai</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Lihat rincian biodata, keluarga, riwayat pangkat, pendidikan, 
                                    serta dokumen-dokumen terkait Anda.
                                </p>
                            </div>
                            <div>
                                <Button asChild className="w-full sm:w-auto">
                                    <Link href={toUrl(selfServiceDetail())}>
                                        Buka Detail Lengkap
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex justify-end">
                    <Button variant="ghost" asChild>
                        <Link href={toUrl(dashboard())}>Kembali ke Dashboard</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
