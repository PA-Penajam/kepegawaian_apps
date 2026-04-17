import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';

export default function SelfServicePengajuanCreate() {
    const form = useForm<{
        domain: string;
        aksi: string;
        target_type: string;
        target_id: string;
        subject_pegawai_id?: string;
        after_payload: Record<string, string>;
        lampiran: File[];
    }>({
        domain: 'profil_pribadi',
        aksi: 'update',
        target_type: 'pegawai',
        target_id: '',
        after_payload: {},
        lampiran: [],
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/self-service/pengajuan', {
            forceFormData: true,
        });
    }

    return (
        <AppLayout breadcrumbs={[
            { title: 'Pengajuan Saya', href: '/self-service/pengajuan' },
            { title: 'Buat Pengajuan', href: '/self-service/pengajuan/create' },
        ]}>
            <Head title="Buat Pengajuan" />
            <form onSubmit={handleSubmit} className="flex flex-col gap-4 p-4 sm:p-6">
                {form.errors.domain && <p className="text-sm text-destructive">{form.errors.domain}</p>}
                {form.errors.lampiran && <p className="text-sm text-destructive">{form.errors.lampiran}</p>}
                <p className="text-sm text-muted-foreground">Form detail akan dikembangkan di sprint berikutnya.</p>
                <button type="submit" disabled={form.processing} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                    Kirim Pengajuan
                </button>
            </form>
        </AppLayout>
    );
}
