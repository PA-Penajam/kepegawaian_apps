import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';
import KeluargaController from '@/actions/App/Http/Controllers/Kepegawaian/KeluargaController';
import RiwayatPangkatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPangkatController';
import RiwayatJabatanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatJabatanController';
import RiwayatPendidikanController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatPendidikanController';
import RiwayatDiklatController from '@/actions/App/Http/Controllers/Kepegawaian/RiwayatDiklatController';
import PenghargaanController from '@/actions/App/Http/Controllers/Kepegawaian/PenghargaanController';
import HukumanDisiplinController from '@/actions/App/Http/Controllers/Kepegawaian/HukumanDisiplinController';
import DokumenPegawaiController from '@/actions/App/Http/Controllers/Kepegawaian/DokumenPegawaiController';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import {
    AgamaLabels,
    JenisKelaminLabels,
    JenjangPendidikanLabels,
    StatusKepegawaianLabels,
    StatusPegawaiLabels,
    StatusPerkawinanLabels,
} from '@/types/kepegawaian';

export function PegawaiDetailTabs({ pegawai }: { pegawai: PegawaiDetail }) {
    return (
        <Tabs defaultValue="biodata" className="w-full">
            <div className="overflow-x-auto pb-2">
                <TabsList className="w-full justify-start sm:w-auto">
                    <TabsTrigger value="biodata">Biodata</TabsTrigger>
                    <TabsTrigger value="keluarga">Keluarga</TabsTrigger>
                    <TabsTrigger value="riwayat-pangkat">
                        Riwayat Pangkat
                    </TabsTrigger>
                    <TabsTrigger value="riwayat-jabatan">
                        Riwayat Jabatan
                    </TabsTrigger>
                    <TabsTrigger value="riwayat-pendidikan">
                        Riwayat Pendidikan
                    </TabsTrigger>
                    <TabsTrigger value="riwayat-diklat">
                        Riwayat Diklat
                    </TabsTrigger>
                    <TabsTrigger value="penghargaan">Penghargaan</TabsTrigger>
                    <TabsTrigger value="hukuman-disiplin">
                        Hukuman Disiplin
                    </TabsTrigger>
                    <TabsTrigger value="dokumen">Dokumen</TabsTrigger>
                </TabsList>
            </div>

            <TabsContent value="biodata" className="mt-4 space-y-6">
                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Informasi Pribadi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-3 gap-2 text-sm">
                                <div className="text-muted-foreground">
                                    Tempat, Tgl Lahir
                                </div>
                                <div className="col-span-2 font-medium">
                                    {pegawai.tempat_lahir},{' '}
                                    {pegawai.tanggal_lahir}
                                </div>

                                <div className="text-muted-foreground">
                                    Jenis Kelamin
                                </div>
                                <div className="col-span-2 font-medium">
                                    {JenisKelaminLabels[pegawai.jenis_kelamin]}
                                </div>

                                <div className="text-muted-foreground">
                                    Agama
                                </div>
                                <div className="col-span-2 font-medium">
                                    {AgamaLabels[pegawai.agama]}
                                </div>

                                <div className="text-muted-foreground">
                                    Status Perkawinan
                                </div>
                                <div className="col-span-2 font-medium">
                                    {
                                        StatusPerkawinanLabels[
                                            pegawai.status_perkawinan
                                        ]
                                    }
                                </div>

                                <div className="text-muted-foreground">
                                    Golongan Darah
                                </div>
                                <div className="col-span-2 font-medium">
                                    {pegawai.golongan_darah ?? '-'}
                                </div>

                                <div className="text-muted-foreground">
                                    Alamat
                                </div>
                                <div className="col-span-2 font-medium">
                                    {pegawai.alamat ?? '-'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Kontak & Akun
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-3 gap-2 text-sm">
                                <div className="text-muted-foreground">
                                    No. Telepon
                                </div>
                                <div className="col-span-2 font-medium">
                                    {pegawai.no_telepon ?? '-'}
                                </div>

                                <div className="text-muted-foreground">
                                    Email
                                </div>
                                <div className="col-span-2 font-medium">
                                    {pegawai.email ?? '-'}
                                </div>

                                <div className="text-muted-foreground">
                                    Akun Sistem
                                </div>
                                <div className="col-span-2 font-medium">
                                    {pegawai.user
                                        ? `${pegawai.user.name} (${pegawai.user.email})`
                                        : '-'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Informasi Kepegawaian
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="text-muted-foreground">
                                        Status Pegawai
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        <Badge
                                            variant={
                                                pegawai.status_pegawai ===
                                                'aktif'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {
                                                StatusPegawaiLabels[
                                                    pegawai.status_pegawai
                                                ]
                                            }
                                        </Badge>
                                    </div>

                                    <div className="text-muted-foreground">
                                        Status Kepegawaian
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {
                                            StatusKepegawaianLabels[
                                                pegawai.status_kepegawaian
                                            ]
                                        }
                                    </div>

                                    <div className="text-muted-foreground">
                                        TMT CPNS
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.tmt_cpns ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        TMT PNS
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.tmt_pns ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        Tanggal Masuk
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.tanggal_masuk ?? '-'}
                                    </div>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="text-muted-foreground">
                                        No. Karpeg
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.no_karpeg ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        No. Karis/Karsu
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.no_karis_karsu ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        NPWP
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.npwp ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        BPJS Kesehatan
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.no_bpjs_kesehatan ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        BPJS Ketenagakerjaan
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.no_bpjs_ketenagakerjaan ?? '-'}
                                    </div>

                                    <div className="text-muted-foreground">
                                        Taspen
                                    </div>
                                    <div className="col-span-2 font-medium">
                                        {pegawai.no_taspen ?? '-'}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <TabsContent value="keluarga" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Data Keluarga</CardTitle>
                            <CardDescription>
                                Daftar anggota keluarga pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(KeluargaController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Hubungan</TableHead>
                                    <TableHead>Jenis Kelamin</TableHead>
                                    <TableHead>Tanggal Lahir</TableHead>
                                    <TableHead>Pekerjaan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.keluarga?.length > 0 ? (
                                    pegawai.keluarga.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.nama}
                                            </TableCell>
                                            <TableCell>
                                                {item.hubungan}
                                            </TableCell>
                                            <TableCell>
                                                {item.jenis_kelamin}
                                            </TableCell>
                                            <TableCell>
                                                {item.tanggal_lahir ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.pekerjaan ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada data keluarga.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="riwayat-pangkat" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Riwayat Pangkat</CardTitle>
                            <CardDescription>
                                Daftar riwayat kepangkatan pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(RiwayatPangkatController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pangkat/Golongan</TableHead>
                                    <TableHead>TMT</TableHead>
                                    <TableHead>Masa Kerja</TableHead>
                                    <TableHead>No. SK</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.riwayat_pangkat?.length > 0 ? (
                                    pegawai.riwayat_pangkat.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.pangkat
                                                    ? `${item.pangkat.nama} (${item.pangkat.golongan}/${item.pangkat.ruang})`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.tmt ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.masa_kerja_tahun} thn{' '}
                                                {item.masa_kerja_bulan} bln
                                            </TableCell>
                                            <TableCell>
                                                <div>{item.no_sk}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.tanggal_sk}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {item.is_aktif ? (
                                                    <Badge className="bg-emerald-500">
                                                        Aktif
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Nonaktif
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat pangkat.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="riwayat-jabatan" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Riwayat Jabatan</CardTitle>
                            <CardDescription>
                                Daftar riwayat jabatan pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(RiwayatJabatanController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Jabatan</TableHead>
                                    <TableHead>Unit Kerja</TableHead>
                                    <TableHead>TMT</TableHead>
                                    <TableHead>No. SK</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.riwayat_jabatan?.length > 0 ? (
                                    pegawai.riwayat_jabatan.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.jabatan?.nama ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.unit_kerja?.nama ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.tmt ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div>{item.no_sk}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.tanggal_sk}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {item.is_aktif ? (
                                                    <Badge className="bg-emerald-500">
                                                        Aktif
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Nonaktif
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat jabatan.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="riwayat-pendidikan" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Riwayat Pendidikan</CardTitle>
                            <CardDescription>
                                Daftar riwayat pendidikan formal pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(RiwayatPendidikanController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tingkat</TableHead>
                                    <TableHead>
                                        Nama Sekolah/Institusi
                                    </TableHead>
                                    <TableHead>Jurusan</TableHead>
                                    <TableHead>Tahun Lulus</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.riwayat_pendidikan?.length > 0 ? (
                                    pegawai.riwayat_pendidikan.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {JenjangPendidikanLabels[
                                                    item.jenjang
                                                ] ?? item.jenjang}
                                            </TableCell>
                                            <TableCell>
                                                {item.nama_sekolah}
                                            </TableCell>
                                            <TableCell>
                                                {item.jurusan ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.tahun_lulus}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat pendidikan.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="riwayat-diklat" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Riwayat Diklat</CardTitle>
                            <CardDescription>
                                Daftar riwayat pendidikan dan pelatihan pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(RiwayatDiklatController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama Diklat</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Penyelenggara</TableHead>
                                    <TableHead>Waktu Pelaksanaan</TableHead>
                                    <TableHead>Durasi (JP)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.riwayat_diklat?.length > 0 ? (
                                    pegawai.riwayat_diklat.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.nama_diklat}
                                            </TableCell>
                                            <TableCell>
                                                {item.jenis_diklat?.nama ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.penyelenggara}
                                            </TableCell>
                                            <TableCell>
                                                {item.tanggal_mulai}{' '}
                                                {item.tanggal_selesai
                                                    ? `s/d ${item.tanggal_selesai}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell>
                                                {item.jam_pelajaran ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada riwayat diklat.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="penghargaan" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Penghargaan</CardTitle>
                            <CardDescription>
                                Daftar penghargaan yang pernah diterima pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(PenghargaanController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama Penghargaan</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Tahun</TableHead>
                                    <TableHead>No. SK</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.penghargaan?.length > 0 ? (
                                    pegawai.penghargaan.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.nama_penghargaan}
                                            </TableCell>
                                            <TableCell>
                                                {item.jenis_penghargaan?.nama ??
                                                    '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.tahun ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <div>{item.no_sk ?? '-'}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.tanggal_sk}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada data penghargaan.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="hukuman-disiplin" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Hukuman Disiplin</CardTitle>
                            <CardDescription>
                                Daftar hukuman disiplin pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(HukumanDisiplinController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Jenis Hukuman</TableHead>
                                    <TableHead>Pelanggaran</TableHead>
                                    <TableHead>TMT Berlaku</TableHead>
                                    <TableHead>TMT Selesai</TableHead>
                                    <TableHead>No. SK</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.hukuman_disiplin?.length > 0 ? (
                                    pegawai.hukuman_disiplin.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.jenis_hukuman_disiplin
                                                    ?.nama ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.pelanggaran}
                                            </TableCell>
                                            <TableCell>
                                                {item.tmt_berlaku}
                                            </TableCell>
                                            <TableCell>
                                                {item.tmt_selesai ??
                                                    'Masih Berlaku'}
                                            </TableCell>
                                            <TableCell>
                                                <div>{item.no_sk}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.tanggal_sk}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada data hukuman disiplin.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="dokumen" className="mt-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between">
                        <div>
                            <CardTitle>Dokumen Pegawai</CardTitle>
                            <CardDescription>
                                Daftar dokumen digital pegawai.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={toUrl(DokumenPegawaiController.index(pegawai.id))}>
                                Kelola
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Jenis Dokumen</TableHead>
                                    <TableHead>Nomor Dokumen</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>File</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pegawai.dokumen_pegawai?.length > 0 ? (
                                    pegawai.dokumen_pegawai.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.jenis_dokumen}
                                            </TableCell>
                                            <TableCell>
                                                {item.nomor_dokumen ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.tanggal_dokumen ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.file_path ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-blue-200 bg-blue-50 text-blue-700"
                                                    >
                                                        Ada File
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Belum ada dokumen.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    );
}
