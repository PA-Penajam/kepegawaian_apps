import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { index, store, update } from '@/routes/kenaikan-pangkat/usulan';
import type { BreadcrumbItem } from '@/types';

type PegawaiOption = { id: string; nip: string | null; nama_lengkap: string };
type PangkatOption = { id: string; nama: string; kode: string };
type UsulanFormData = { pegawai_id: string; pangkat_asal_id: string; pangkat_tujuan_id: string; periode_bulan: string; periode_tahun: string; catatan_pengusul: string };
type UsulanProp = Partial<UsulanFormData> & { id: string; pegawai?: PegawaiOption; pangkat_asal?: PangkatOption | null; pangkat_tujuan?: PangkatOption | null };

type Props = {
    usulan?: UsulanProp | null;
    pegawai: PegawaiOption;
    pangkatAsal: PangkatOption | null;
    pangkatOptions: PangkatOption[];
    bulanOptions: { value: number; label: string }[];
    tahunOptions: number[];
    filters?: { bulan?: number | null; tahun?: number | null };
};

function fieldError(message?: string) {
    return message ? <p className="text-sm font-medium text-destructive">{message}</p> : null;
}

export default function UsulanKenaikanPangkatForm({ usulan = null, pegawai, pangkatAsal, pangkatOptions, bulanOptions, tahunOptions, filters }: Props) {
    const isEdit = usulan !== null;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Usulan KP', href: index() },
        { title: isEdit ? 'Edit Usulan' : 'Buat Usulan', href: '#' },
    ];
    const form = useForm<UsulanFormData>({
        pegawai_id: usulan?.pegawai_id ?? pegawai.id,
        pangkat_asal_id: usulan?.pangkat_asal_id ?? pangkatAsal?.id ?? '',
        pangkat_tujuan_id: usulan?.pangkat_tujuan_id ?? usulan?.pangkat_tujuan?.id ?? '',
        periode_bulan: (usulan?.periode_bulan ?? filters?.bulan ?? '').toString(),
        periode_tahun: (usulan?.periode_tahun ?? filters?.tahun ?? new Date().getFullYear()).toString(),
        catatan_pengusul: usulan?.catatan_pengusul ?? '',
    });

    const submit: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        if (isEdit) {
            form.put(update.url(usulan.id), { preserveScroll: true });
            return;
        }

        form.post(store.url(), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'Edit Usulan KP' : 'Buat Usulan KP'} />
            <div className="flex flex-1 flex-col gap-6 bg-background p-4 md:p-6">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon-sm" asChild><Link href={index()}><ArrowLeft className="h-4 w-4" /></Link></Button>
                    <div><h1 className="text-2xl font-semibold tracking-tight">{isEdit ? 'Edit Usulan KP' : 'Buat Usulan KP'}</h1><p className="text-sm text-muted-foreground">Lengkapi data usulan kenaikan pangkat.</p></div>
                </div>

                <Card className="max-w-3xl border-2 border-foreground bg-card shadow-[4px_4px_0_rgba(0,0,0,1)]">
                    <CardHeader><CardTitle>Form Usulan Kenaikan Pangkat</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-5">
                            <div className="grid gap-2"><Label>Pegawai</Label><Input value={`${pegawai.nama_lengkap} (${pegawai.nip ?? '-'})`} readOnly className="bg-muted" />{fieldError(form.errors.pegawai_id)}</div>
                            <div className="grid gap-2"><Label>Pangkat Asal</Label><Input value={pangkatAsal ? `${pangkatAsal.nama} (${pangkatAsal.kode})` : '-'} readOnly className="bg-muted" />{fieldError(form.errors.pangkat_asal_id)}</div>
                            <div className="grid gap-2"><Label htmlFor="pangkat_tujuan_id">Pangkat Tujuan</Label><Select value={form.data.pangkat_tujuan_id} onValueChange={(value) => form.setData('pangkat_tujuan_id', value)}><SelectTrigger id="pangkat_tujuan_id"><SelectValue placeholder="Pilih pangkat tujuan" /></SelectTrigger><SelectContent>{pangkatOptions.map((pangkat) => <SelectItem key={pangkat.id} value={pangkat.id}>{pangkat.nama} ({pangkat.kode})</SelectItem>)}</SelectContent></Select>{fieldError(form.errors.pangkat_tujuan_id)}</div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2"><Label>Periode Bulan</Label><Select value={form.data.periode_bulan} onValueChange={(value) => form.setData('periode_bulan', value)}><SelectTrigger><SelectValue placeholder="Pilih bulan" /></SelectTrigger><SelectContent>{bulanOptions.map((bulan) => <SelectItem key={bulan.value} value={bulan.value.toString()}>{bulan.label}</SelectItem>)}</SelectContent></Select>{fieldError(form.errors.periode_bulan)}</div>
                                <div className="grid gap-2"><Label>Periode Tahun</Label><Select value={form.data.periode_tahun} onValueChange={(value) => form.setData('periode_tahun', value)}><SelectTrigger><SelectValue placeholder="Pilih tahun" /></SelectTrigger><SelectContent>{tahunOptions.map((tahun) => <SelectItem key={tahun} value={tahun.toString()}>{tahun}</SelectItem>)}</SelectContent></Select>{fieldError(form.errors.periode_tahun)}</div>
                            </div>
                            <div className="grid gap-2"><Label htmlFor="catatan_pengusul">Catatan Pengusul</Label><Textarea id="catatan_pengusul" value={form.data.catatan_pengusul} onChange={(event) => form.setData('catatan_pengusul', event.target.value)} placeholder="Tambahkan catatan bila perlu" />{fieldError(form.errors.catatan_pengusul)}</div>
                            <div className="flex justify-end gap-3"><Button type="button" variant="outline" asChild><Link href={index()}>Batal</Link></Button><Button type="submit" disabled={form.processing}><Save className="mr-2 h-4 w-4" />{form.processing ? 'Menyimpan...' : 'Simpan Usulan'}</Button></div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
