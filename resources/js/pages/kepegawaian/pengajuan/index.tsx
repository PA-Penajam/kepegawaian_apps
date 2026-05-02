import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

type PengajuanItem = {
    id: string;
    nomor_pengajuan: string;
    domain: string;
    aksi: string;
    status: string;
    submitted_at: string | null;
    pengaju?: { id: string; nama_lengkap: string } | null;
};

type Props = {
    pengajuanList: {
        data: PengajuanItem[];
    };
};

export default function ValidatorPengajuanIndex({ pengajuanList }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Validasi Pengajuan', href: '/kepegawaian/pengajuan' }]}>
            <Head title="Validasi Pengajuan" />
            <div className="p-4 sm:p-6">
                <h1 className="mb-4 text-xl font-semibold">Pengajuan Menunggu Validasi</h1>
                <div className="grid gap-4">
                    {pengajuanList.data.map((item) => (
                        <Link
                            key={item.id}
                            href={`/kepegawaian/pengajuan/${item.id}`}
                            className="rounded-lg border p-4 hover:bg-accent"
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">{item.domain}</p>
                                    <p className="text-sm text-muted-foreground">{item.aksi} - {item.nomor_pengajuan}</p>
                                </div>
                                <span className="rounded-full bg-yellow-100 px-2 py-1 text-xs text-yellow-800">
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
