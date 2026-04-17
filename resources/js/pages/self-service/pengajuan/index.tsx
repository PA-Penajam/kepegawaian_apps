import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import type { PengajuanListItem } from '@/types/pengajuan';

type Props = {
    pengajuanList: {
        data: PengajuanListItem[];
    };
};

export default function SelfServicePengajuanIndex({ pengajuanList }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Pengajuan Saya', href: '/self-service/pengajuan' }]}>
            <Head title="Pengajuan Saya" />
            <div className="p-4 sm:p-6">
                <div className="mb-4">
                    <Link
                        href="/self-service/pengajuan/create"
                        className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                    >
                        Buat Pengajuan
                    </Link>
                </div>
                <div className="grid gap-4">
                    {pengajuanList.data.map((item) => (
                        <Link
                            key={item.id}
                            href={`/self-service/pengajuan/${item.id}`}
                            className="rounded-lg border p-4 hover:bg-accent"
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">{item.domain}</p>
                                    <p className="text-sm text-muted-foreground">{item.aksi}</p>
                                </div>
                                <span className={`rounded-full px-2 py-1 text-xs ${
                                    item.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                    item.status === 'approved' ? 'bg-green-100 text-green-800' :
                                    'bg-red-100 text-red-800'
                                }`}>
                                    {item.status}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
