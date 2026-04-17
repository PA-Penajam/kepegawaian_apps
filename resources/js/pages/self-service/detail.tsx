import { Head, Link } from '@inertiajs/react';
import { Award, Briefcase, Building } from 'lucide-react';
import { PegawaiDetailTabs } from '@/components/pegawai-detail-tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
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

export default function SelfServiceDetail({
    pegawai,
}: {
    pegawai: PegawaiDetail;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Detail Data Saya" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div className="flex items-start gap-4">
                        <Avatar className="h-20 w-20 border-2 border-border">
                            <AvatarImage
                                src={pegawai.foto_url ?? undefined}
                                alt={pegawai.nama_lengkap}
                            />
                            <AvatarFallback className="text-2xl">
                                {getInitials(pegawai.nama_lengkap)}
                            </AvatarFallback>
                        </Avatar>
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

                    <Button variant="outline" asChild>
                        <Link href={selfServiceIndex()}>Kembali</Link>
                    </Button>
                </div>

                <PegawaiDetailTabs pegawai={pegawai} />
            </div>
        </AppLayout>
    );
}
