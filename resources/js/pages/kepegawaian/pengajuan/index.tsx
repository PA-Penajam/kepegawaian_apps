import { Head, Link } from '@inertiajs/react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import AppLayout from '@/layouts/app-layout';
import type { PaginatedData } from '@/types';

type PengajuanItem = {
    id: string;
    nomor_pengajuan: string;
    domain: string;
    aksi: string;
    status: string;
    submitted_at: string | null;
    pengaju?: {
        id: string;
        nama_lengkap: string;
        nip?: string | null;
    } | null;
};

type Props = {
    pengajuanList: PaginatedData<PengajuanItem>;
};

export default function ValidatorPengajuanIndex({ pengajuanList }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Validasi Pengajuan', href: '/kepegawaian/pengajuan' },
            ]}
        >
            <Head title="Validasi Pengajuan" />
            <div className="p-4 sm:p-6">
                <h1 className="mb-4 text-xl font-semibold">
                    Pengajuan Menunggu Validasi
                </h1>
                {pengajuanList.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Tidak ada pengajuan perubahan data yang menunggu
                        validasi.
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {pengajuanList.data.map((item) => (
                            <Link
                                key={item.id}
                                href={`/kepegawaian/pengajuan/${item.id}`}
                                className="rounded-lg border border-border bg-card p-4 transition-colors hover:bg-accent"
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="font-medium">
                                            {item.domain}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.aksi} - {item.nomor_pengajuan}
                                            {item.pengaju?.nama_lengkap
                                                ? ` • ${item.pengaju.nama_lengkap}`
                                                : ''}
                                        </p>
                                    </div>
                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200">
                                        {item.status}
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <PaginationWrapper
                    links={pengajuanList.links}
                    lastPage={pengajuanList.last_page}
                />
            </div>
        </AppLayout>
    );
}
