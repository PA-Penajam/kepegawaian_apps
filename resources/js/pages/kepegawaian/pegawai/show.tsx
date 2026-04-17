import { Head, Link } from '@inertiajs/react';
import { Award, Briefcase, Building, Edit } from 'lucide-react';
import { PegawaiDetailTabs } from '@/components/pegawai-detail-tabs';
import { FotoUpload } from '@/components/pegawai/FotoUpload';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PegawaiDetail } from '@/types/pegawai-detail';

export default function PegawaiShow({ pegawai }: { pegawai: PegawaiDetail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Pegawai',
            href: '/kepegawaian/pegawai',
        },
        {
            title: pegawai.nama_lengkap,
            href: `/kepegawaian/pegawai/${pegawai.id}`,
        },
    ];

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((item) => item[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pegawai.nama_lengkap} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div className="flex items-start gap-4">
                        <FotoUpload
                            pegawaiId={pegawai.id}
                            currentFotoUrl={pegawai.foto_url}
                            initials={getInitials(pegawai.nama_lengkap)}
                            canUpdate={true}
                        />
                        <div className="space-y-1.5">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {pegawai.nama_lengkap}
                            </h1>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <Badge
                                    variant="secondary"
                                    className="font-normal"
                                >
                                    NIP: {pegawai.nip ?? '-'}
                                </Badge>
                                <span className="flex items-center gap-1">
                                    <Briefcase className="h-3.5 w-3.5" />
                                    {pegawai.jabatan?.nama ??
                                        'Belum ada jabatan'}
                                </span>
                                <span className="hidden md:inline">•</span>
                                <span className="flex items-center gap-1">
                                    <Building className="h-3.5 w-3.5" />
                                    {pegawai.unit_kerja?.nama ??
                                        'Belum ada unit kerja'}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <Award className="h-3.5 w-3.5" />
                                    {pegawai.pangkat
                                        ? `${pegawai.pangkat.nama} (${pegawai.pangkat.golongan}/${pegawai.pangkat.ruang})`
                                        : 'Belum ada pangkat'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/kepegawaian/pegawai">Kembali</Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={`/kepegawaian/pegawai/${pegawai.id}/edit`}
                            >
                                <Edit className="mr-2 h-4 w-4" />
                                Ubah Data
                            </Link>
                        </Button>
                    </div>
                </div>

                <PegawaiDetailTabs pegawai={pegawai} />
            </div>
        </AppLayout>
    );
}
