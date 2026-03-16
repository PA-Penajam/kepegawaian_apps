import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { PegawaiDetail } from '@/types/pegawai-detail';
import {
    AgamaLabels,
    JenisKelaminLabels,
    StatusKepegawaianLabels,
    StatusPegawaiLabels,
    StatusPerkawinanLabels,
} from '@/types/kepegawaian';

export function BiodataTab({ pegawai }: { pegawai: PegawaiDetail }) {
    return (
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
                            {pegawai.email ?? '-'}
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
    );
}
