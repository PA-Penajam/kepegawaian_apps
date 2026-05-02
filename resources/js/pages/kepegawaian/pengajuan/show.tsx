import { Head, Link, useForm, router } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type { DiffItem, PengajuanDetail } from '@/types/pengajuan';

type Props = {
    pengajuan: PengajuanDetail & {
        pengaju?: { id: string; nama_lengkap: string } | null;
    };
    diffItems: DiffItem[];
};

export default function ValidatorPengajuanShow({ pengajuan, diffItems }: Props) {
    const rejectForm = useForm({ alasan_penolakan: '' });

    return (
        <AppLayout breadcrumbs={[
            { title: 'Validasi Pengajuan', href: '/kepegawaian/pengajuan' },
            { title: pengajuan.domain, href: `/kepegawaian/pengajuan/${pengajuan.id}` },
        ]}>
            <Head title="Detail Pengajuan" />
            <div className="flex flex-col gap-4 p-4 sm:p-6">
                <div className="mb-2 text-sm text-muted-foreground">
                    Diajukan oleh: {pengajuan.pengaju?.nama_lengkap ?? '-'}
                </div>
                {diffItems.map((item) => (
                    <div key={item.field} className="rounded-lg border p-4">
                        <p className="font-medium">{item.label}</p>
                        <p className="text-sm text-muted-foreground">Sebelum: {String(item.before ?? '-')}</p>
                        <p className="text-sm">Sesudah: {String(item.after ?? '-')}</p>
                    </div>
                ))}
                {pengajuan.status === 'pending' && (
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => router.post(`/kepegawaian/pengajuan/${pengajuan.id}/approve`)}
                            className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white"
                        >
                            Approve
                        </button>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                rejectForm.post(`/kepegawaian/pengajuan/${pengajuan.id}/reject`);
                            }}
                            className="flex flex-wrap items-center gap-2"
                        >
                            {Object.keys(rejectForm.errors).length > 0 && (
                                <div className="w-full">
                                    <AlertError
                                        errors={errorsToArray(rejectForm.errors)}
                                        title="Gagal menolak pengajuan"
                                    />
                                </div>
                            )}
                            <input
                                value={rejectForm.data.alasan_penolakan}
                                onChange={(e) => rejectForm.setData('alasan_penolakan', e.target.value)}
                                placeholder="Alasan penolakan (min 10 karakter)"
                                className="rounded-md border px-3 py-2 text-sm"
                            />
                            <button
                                type="submit"
                                disabled={rejectForm.processing}
                                className="rounded-md bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground"
                            >
                                Reject
                            </button>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
