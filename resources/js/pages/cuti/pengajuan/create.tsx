import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormPengajuan } from '@/components/cuti/FormPengajuan';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CutiJenisMaster } from '@/types/cuti';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti Saya', href: '/cuti/saya' },
    { title: 'Pengajuan Baru', href: '/cuti/pengajuan/baru' },
];

type Props = {
    jenisCutiList: CutiJenisMaster[];
    saldoData: Record<string, number>;
};

export default function PengajuanCreate({ jenisCutiList, saldoData }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajukan Cuti Baru" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon-sm" asChild>
                        <Link href="/cuti/saya">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Ajukan Cuti Baru</h1>
                        <p className="text-sm text-muted-foreground">
                            Isi formulir di bawah untuk mengajukan cuti.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">Formulir Pengajuan Cuti</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FormPengajuan jenisCutiList={jenisCutiList} saldoData={saldoData} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
