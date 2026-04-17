import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import type { PengajuanDetail, DiffItem } from '@/types/pengajuan';

type Props = {
    pengajuan: PengajuanDetail;
    diffItems: DiffItem[];
};

export default function SelfServicePengajuanShow({ pengajuan, diffItems }: Props) {
    return (
        <AppLayout breadcrumbs={[
            { title: 'Pengajuan Saya', href: '/self-service/pengajuan' },
            { title: pengajuan.domain, href: `/self-service/pengajuan/${pengajuan.id}` },
        ]}>
            <Head title="Detail Pengajuan" />
            <div className="flex flex-col gap-4 p-4 sm:p-6">
                {pengajuan.alasan_penolakan && (
                    <div className="rounded-lg border border-destructive bg-destructive/10 p-4">
                        <p className="font-medium text-destructive">Alasan Penolakan</p>
                        <p className="text-sm">{pengajuan.alasan_penolakan}</p>
                    </div>
                )}
                {diffItems.map((item) => (
                    <div key={item.field} className="rounded-lg border p-4">
                        <p className="font-medium">{item.label}</p>
                        <p className="text-sm text-muted-foreground">Sebelum: {String(item.before ?? '-')}</p>
                        <p className="text-sm">Sesudah: {String(item.after ?? '-')}</p>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
