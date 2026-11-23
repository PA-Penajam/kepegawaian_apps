import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    BookOpen,
    Briefcase,
    Building,
    FileText,
    GraduationCap,
    ShieldAlert,
    User,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
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
import { toUrl } from '@/lib/utils';
import { index as selfServiceIndex } from '@/routes/self-service';
import type { BreadcrumbItem } from '@/types';
import type { PegawaiDetail } from '@/types/pegawai-detail';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Data Saya', href: '/self-service' },
    { title: 'Detail', href: '/self-service/detail' },
];

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((item) => item[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '-';
    }

    const parsedDate = new Date(date.includes('T') ? date : `${date}T00:00:00`);

    if (Number.isNaN(parsedDate.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(parsedDate);
}

/**
 * Mengambil label jenis dokumen secara defensif jika bernilai string maupun objek relasi.
 */
function formatJenisDokumen(
    jenisDokumen: string | { nama?: string | null } | null | undefined,
): string {
    if (typeof jenisDokumen === 'string') {
        return jenisDokumen || '-';
    }

    if (
        jenisDokumen !== null &&
        typeof jenisDokumen === 'object' &&
        typeof jenisDokumen.nama === 'string'
    ) {
        return jenisDokumen.nama || '-';
    }

    return '-';
}

function InfoItem({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="space-y-0.5">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value || '-'}</p>
        </div>
    );
}

function SectionCard({
    title,
    description,
    icon,
    children,
}: {
    title: string;
    description: string;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    {icon}
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function SelfServiceDetail({
    pegawai,
}: {
    pegawai: PegawaiDetail;
}) {
    const riwayatPangkat = pegawai.riwayat_pangkat ?? [];
    const riwayatJabatan = pegawai.riwayat_jabatan ?? [];
    const riwayatPendidikan = pegawai.riwayat_pendidikan ?? [];
    const riwayatDiklat = pegawai.riwayat_diklat ?? [];
    const keluarga = pegawai.keluarga ?? [];
    const penghargaan = pegawai.penghargaan ?? [];
    const hukumanDisiplin = pegawai.hukuman_disiplin ?? [];
    const dokumenPegawai = pegawai.dokumen_pegawai ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Detail Data Pegawai" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                                <Avatar className="h-20 w-20 border border-border">
                                    <AvatarImage
                                        src={pegawai.foto_url ?? undefined}
                                        alt={pegawai.nama_lengkap}
                                    />
                                    <AvatarFallback className="text-xl font-semibold">
                                        {getInitials(pegawai.nama_lengkap)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="space-y-2 text-center sm:text-left">
                                    <div>
                                        <h1 className="text-2xl font-semibold tracking-tight">
                                            {pegawai.nama_lengkap}
                                        </h1>
                                        <div className="mt-1 flex flex-wrap items-center justify-center gap-2 text-sm text-muted-foreground sm:justify-start">
                                            <Badge
                                                variant="secondary"
                                                className="tnum"
                                            >
                                                NIP: {pegawai.nip ?? '-'}
                                            </Badge>
                                            <span className="flex items-center gap-1">
                                                <Briefcase className="h-4 w-4" />
                                                {pegawai.jabatan?.nama ??
                                                    'Belum ada jabatan'}
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <Building className="h-4 w-4" />
                                                {pegawai.unit_kerja?.nama ??
                                                    'Belum ada unit kerja'}
                                            </span>
                                        </div>
                                        <div className="mt-1 flex items-center justify-center gap-1 text-sm text-muted-foreground sm:justify-start">
                                            <Award className="h-4 w-4" />
                                            {pegawai.pangkat
                                                ? `${pegawai.pangkat.nama} (${pegawai.pangkat.golongan}/${pegawai.pangkat.ruang})`
                                                : 'Belum ada pangkat'}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Button variant="outline" asChild>
                                <Link href={toUrl(selfServiceIndex())}>
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Kembali
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <SectionCard
                    title="Biodata & Informasi Kepegawaian"
                    description="Ringkasan data pribadi, kontak, dan identitas kepegawaian Anda (baca-saja)."
                    icon={<User className="h-4 w-4" />}
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <InfoItem
                            label="Tempat, Tanggal Lahir"
                            value={`${pegawai.tempat_lahir || '-'}, ${formatDate(pegawai.tanggal_lahir)}`}
                        />
                        <InfoItem
                            label="Jenis Kelamin"
                            value={pegawai.jenis_kelamin}
                        />
                        <InfoItem label="Agama" value={pegawai.agama} />
                        <InfoItem
                            label="Status Perkawinan"
                            value={pegawai.status_perkawinan}
                        />
                        <InfoItem
                            label="Golongan Darah"
                            value={pegawai.golongan_darah}
                        />
                        <InfoItem
                            label="No. Telepon"
                            value={pegawai.no_telepon}
                        />
                        <InfoItem label="Email" value={pegawai.email} />
                        <InfoItem label="Alamat" value={pegawai.alamat} />
                        <InfoItem
                            label="Status Kepegawaian"
                            value={pegawai.status_kepegawaian}
                        />
                        <InfoItem
                            label="Status Pegawai"
                            value={pegawai.status_pegawai}
                        />
                        <InfoItem
                            label="TMT CPNS"
                            value={formatDate(pegawai.tmt_cpns)}
                        />
                        <InfoItem
                            label="TMT PNS"
                            value={formatDate(pegawai.tmt_pns)}
                        />
                        <InfoItem
                            label="Pendidikan Terakhir"
                            value={pegawai.pendidikan_terakhir}
                        />
                        <InfoItem
                            label="Tanggal Masuk"
                            value={formatDate(pegawai.tanggal_masuk)}
                        />
                        <InfoItem
                            label="Tanggal Pensiun (BUP)"
                            value={formatDate(pegawai.tanggal_pensiun_bup)}
                        />
                        <InfoItem
                            label="No. Karpeg"
                            value={pegawai.no_karpeg}
                        />
                        <InfoItem
                            label="No. KARIS/KARSU"
                            value={pegawai.no_karis_karsu}
                        />
                        <InfoItem label="NPWP" value={pegawai.npwp} />
                        <InfoItem
                            label="No. BPJS Kesehatan"
                            value={pegawai.no_bpjs_kesehatan}
                        />
                        <InfoItem
                            label="No. BPJS Ketenagakerjaan"
                            value={pegawai.no_bpjs_ketenagakerjaan}
                        />
                        <InfoItem
                            label="No. Taspen"
                            value={pegawai.no_taspen}
                        />
                    </div>
                </SectionCard>

                <SectionCard
                    title="Riwayat Pangkat"
                    description="Daftar riwayat kenaikan pangkat dan golongan."
                    icon={<Award className="h-4 w-4" />}
                >
                    {riwayatPangkat.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada data riwayat pangkat.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Pangkat / Golongan
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            No. SK
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            TMT
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Masa Kerja
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {riwayatPangkat.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 font-medium">
                                                {item.pangkat
                                                    ? `${item.pangkat.nama} (${item.pangkat.kode ?? `${item.pangkat.golongan}/${item.pangkat.ruang}`})`
                                                    : '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.no_sk}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatDate(item.tmt)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.masa_kerja_tahun} thn{' '}
                                                {item.masa_kerja_bulan} bln
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        item.is_aktif
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {item.is_aktif
                                                        ? 'Aktif'
                                                        : 'Riwayat'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>

                <SectionCard
                    title="Riwayat Jabatan"
                    description="Daftar riwayat penempatan jabatan dan unit kerja."
                    icon={<Briefcase className="h-4 w-4" />}
                >
                    {riwayatJabatan.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada data riwayat jabatan.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Jabatan
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Unit Kerja
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            No. SK
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            TMT
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {riwayatJabatan.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 font-medium">
                                                {item.jabatan?.nama ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.unit_kerja?.nama ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.no_sk}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatDate(item.tmt)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        item.is_aktif
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {item.is_aktif
                                                        ? 'Aktif'
                                                        : 'Riwayat'}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>

                <div className="grid gap-6 lg:grid-cols-2">
                    <SectionCard
                        title="Riwayat Pendidikan"
                        description="Riwayat pendidikan formal."
                        icon={<GraduationCap className="h-4 w-4" />}
                    >
                        {riwayatPendidikan.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada data riwayat pendidikan.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-muted/50 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Jenjang
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Institusi
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Tahun Lulus
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {riwayatPendidikan.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 font-medium">
                                                    {item.jenjang}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.nama_sekolah}
                                                    {item.jurusan
                                                        ? ` — ${item.jurusan}`
                                                        : ''}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.tahun_lulus}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Riwayat Diklat"
                        description="Pelatihan dan pengembangan kompetensi."
                        icon={<BookOpen className="h-4 w-4" />}
                    >
                        {riwayatDiklat.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada data riwayat diklat.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-muted/50 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Nama Diklat
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Penyelenggara
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Tanggal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {riwayatDiklat.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 font-medium">
                                                    {item.nama_diklat}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.penyelenggara}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {formatDate(
                                                        item.tanggal_mulai,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                </div>

                <SectionCard
                    title="Data Keluarga"
                    description="Daftar anggota keluarga terdaftar."
                    icon={<Users className="h-4 w-4" />}
                >
                    {keluarga.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada data keluarga.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Nama
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Hubungan
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Jenis Kelamin
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Tanggal Lahir
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Pekerjaan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {keluarga.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 font-medium">
                                                {item.nama}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.hubungan}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.jenis_kelamin}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatDate(item.tanggal_lahir)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.pekerjaan ?? '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>

                <div className="grid gap-6 lg:grid-cols-2">
                    <SectionCard
                        title="Penghargaan"
                        description="Daftar penghargaan dan tanda kehormatan."
                        icon={<Award className="h-4 w-4" />}
                    >
                        {penghargaan.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada data penghargaan.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-muted/50 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Nama Penghargaan
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                No. SK
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Tanggal SK
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {penghargaan.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 font-medium">
                                                    {item.nama_penghargaan}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.no_sk ?? '-'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {formatDate(
                                                        item.tanggal_sk,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Hukuman Disiplin"
                        description="Catatan riwayat kedisiplinan."
                        icon={<ShieldAlert className="h-4 w-4" />}
                    >
                        {hukumanDisiplin.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Tidak ada catatan hukuman disiplin.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-border text-sm">
                                    <thead className="bg-muted/50 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Jenis
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                No. SK
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Berlaku
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {hukumanDisiplin.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 font-medium">
                                                    {item.jenis_hukuman_disiplin
                                                        ?.nama ?? '-'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.no_sk}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {formatDate(
                                                        item.tmt_berlaku,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </SectionCard>
                </div>

                <SectionCard
                    title="Dokumen Kepegawaian"
                    description="Metadata arsip dokumen kepegawaian yang tercatat (baca-saja)."
                    icon={<FileText className="h-4 w-4" />}
                >
                    {dokumenPegawai.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada dokumen kepegawaian tercatat.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Jenis Dokumen
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Nomor Dokumen
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Tanggal Dokumen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {dokumenPegawai.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 font-medium">
                                                {formatJenisDokumen(
                                                    item.jenis_dokumen,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.nomor_dokumen ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatDate(
                                                    item.tanggal_dokumen,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </SectionCard>
            </div>
        </AppLayout>
    );
}
