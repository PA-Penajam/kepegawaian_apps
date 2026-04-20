import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm, usePage } from '@inertiajs/react';

interface Option {
    value: string;
    label: string;
}

interface PageProps extends Record<string, unknown> {
    domains: Option[];
    aksiList: Option[];
    hubunganList: Option[];
    jenisKelaminList: Option[];
    statusPerkawinanList: Option[];
    currentUserId: string;
}

export default function SelfServicePengajuanCreate() {
    const { domains, aksiList, hubunganList, jenisKelaminList, statusPerkawinanList, currentUserId } =
        usePage<PageProps>().props;

    const form = useForm<{
        domain: string;
        aksi: string;
        target_type: string;
        target_id: string;
        subject_pegawai_id: string;
        after_payload: Record<string, string>;
        lampiran: File[];
    }>({
        domain: 'profil_pribadi',
        aksi: 'update',
        target_type: 'pegawai',
        target_id: currentUserId,
        subject_pegawai_id: currentUserId,
        after_payload: {},
        lampiran: [],
    });

    const isProfilPribadi = form.data.domain === 'profil_pribadi';
    const isKeluargaDomain = ['pasangan', 'anak', 'orang_tua', 'keluarga_lain'].includes(form.data.domain);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/self-service/pengajuan', {
            forceFormData: true,
        });
    }

    function updatePayload(key: string, value: string) {
        form.setData('after_payload', { ...form.data.after_payload, [key]: value });
    }

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        if (e.target.files) {
            form.setData('lampiran', Array.from(e.target.files));
        }
    }

    const lampiranWajib =
        isKeluargaDomain ||
        (isProfilPribadi &&
            Object.keys(form.data.after_payload).some((k) =>
                ['nama_lengkap', 'nik', 'tempat_lahir', 'tanggal_lahir', 'status_perkawinan'].includes(k),
            ));

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pengajuan Saya', href: '/self-service/pengajuan' },
                { title: 'Buat Pengajuan', href: '/self-service/pengajuan/create' },
            ]}
        >
            <Head title="Buat Pengajuan" />
            <form onSubmit={handleSubmit} className="flex flex-col gap-6 p-4 sm:p-6 max-w-2xl">
                {/* Domain */}
                <div className="space-y-2">
                    <Label htmlFor="domain">Jenis Perubahan</Label>
                    <Select
                        value={form.data.domain}
                        onValueChange={(v) => {
                            form.setData('domain', v);
                            form.setData('after_payload', {});
                            if (v === 'profil_pribadi') {
                                form.setData('aksi', 'update');
                                form.setData('target_type', 'pegawai');
                            } else {
                                form.setData('aksi', 'create');
                                form.setData('target_type', 'keluarga');
                            }
                        }}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {domains.map((d) => (
                                <SelectItem key={d.value} value={d.value}>
                                    {d.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.domain && <p className="text-sm text-destructive">{form.errors.domain}</p>}
                </div>

                {/* Aksi (hanya untuk keluarga, profil_pribadi selalu update) */}
                {isKeluargaDomain && (
                    <div className="space-y-2">
                        <Label htmlFor="aksi">Aksi</Label>
                        <Select
                            value={form.data.aksi}
                            onValueChange={(v) => {
                                form.setData('aksi', v);
                                form.setData('after_payload', {});
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {aksiList.map((a) => (
                                    <SelectItem key={a.value} value={a.value}>
                                        {a.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {/* Fields: Profil Pribadi */}
                {isProfilPribadi && form.data.aksi === 'update' && (
                    <div className="space-y-4 rounded-lg border p-4">
                        <h3 className="font-medium">Data yang Diubah</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="nama_lengkap">Nama Lengkap</Label>
                                <Input
                                    id="nama_lengkap"
                                    value={form.data.after_payload.nama_lengkap ?? ''}
                                    onChange={(e) => updatePayload('nama_lengkap', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="nik">NIK</Label>
                                <Input
                                    id="nik"
                                    value={form.data.after_payload.nik ?? ''}
                                    onChange={(e) => updatePayload('nik', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tempat_lahir">Tempat Lahir</Label>
                                <Input
                                    id="tempat_lahir"
                                    value={form.data.after_payload.tempat_lahir ?? ''}
                                    onChange={(e) => updatePayload('tempat_lahir', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tanggal_lahir">Tanggal Lahir</Label>
                                <Input
                                    id="tanggal_lahir"
                                    type="date"
                                    value={form.data.after_payload.tanggal_lahir ?? ''}
                                    onChange={(e) => updatePayload('tanggal_lahir', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="status_perkawinan">Status Perkawinan</Label>
                                <Select
                                    value={form.data.after_payload.status_perkawinan ?? ''}
                                    onValueChange={(v) => updatePayload('status_perkawinan', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusPerkawinanList.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="alamat">Alamat</Label>
                                <Input
                                    id="alamat"
                                    value={form.data.after_payload.alamat ?? ''}
                                    onChange={(e) => updatePayload('alamat', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="no_telepon">No. Telepon</Label>
                                <Input
                                    id="no_telepon"
                                    value={form.data.after_payload.no_telepon ?? ''}
                                    onChange={(e) => updatePayload('no_telepon', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.after_payload.email ?? ''}
                                    onChange={(e) => updatePayload('email', e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* Fields: Keluarga Create/Update */}
                {isKeluargaDomain && form.data.aksi !== 'delete' && (
                    <div className="space-y-4 rounded-lg border p-4">
                        <h3 className="font-medium">Data Keluarga</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="hubungan">Hubungan</Label>
                                <Select
                                    value={form.data.after_payload.hubungan ?? ''}
                                    onValueChange={(v) => updatePayload('hubungan', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih hubungan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {hubunganList.map((h) => (
                                            <SelectItem key={h.value} value={h.value}>
                                                {h.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="nama">Nama</Label>
                                <Input
                                    id="nama"
                                    value={form.data.after_payload.nama ?? ''}
                                    onChange={(e) => updatePayload('nama', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tempat_lahir">Tempat Lahir</Label>
                                <Input
                                    id="tempat_lahir"
                                    value={form.data.after_payload.tempat_lahir ?? ''}
                                    onChange={(e) => updatePayload('tempat_lahir', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tanggal_lahir">Tanggal Lahir</Label>
                                <Input
                                    id="tanggal_lahir"
                                    type="date"
                                    value={form.data.after_payload.tanggal_lahir ?? ''}
                                    onChange={(e) => updatePayload('tanggal_lahir', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="jenis_kelamin">Jenis Kelamin</Label>
                                <Select
                                    value={form.data.after_payload.jenis_kelamin ?? ''}
                                    onValueChange={(v) => updatePayload('jenis_kelamin', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih jenis kelamin" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {jenisKelaminList.map((j) => (
                                            <SelectItem key={j.value} value={j.value}>
                                                {j.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pekerjaan">Pekerjaan</Label>
                                <Input
                                    id="pekerjaan"
                                    value={form.data.after_payload.pekerjaan ?? ''}
                                    onChange={(e) => updatePayload('pekerjaan', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pendidikan">Pendidikan</Label>
                                <Input
                                    id="pendidikan"
                                    value={form.data.after_payload.pendidikan ?? ''}
                                    onChange={(e) => updatePayload('pendidikan', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="keterangan">Keterangan</Label>
                                <Input
                                    id="keterangan"
                                    value={form.data.after_payload.keterangan ?? ''}
                                    onChange={(e) => updatePayload('keterangan', e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* Delete confirmation for keluarga */}
                {isKeluargaDomain && form.data.aksi === 'delete' && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
                        <p className="text-sm text-destructive">
                            Anda memilih aksi <strong>Hapus</strong>. Data yang dihapus akan diajukan untuk penghapusan
                            dan memerlukan persetujuan validator.
                        </p>
                    </div>
                )}

                {/* Lampiran */}
                <div className="space-y-2">
                    <Label htmlFor="lampiran">
                        Lampiran Pendukung
                        {lampiranWajib && <span className="text-destructive"> *</span>}
                    </Label>
                    <Input
                        id="lampiran"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.pdf"
                        onChange={handleFileChange}
                    />
                    <p className="text-xs text-muted-foreground">
                        Format: JPG, JPEG, PNG, PDF. Maksimal 2MB per file.
                    </p>
                    {form.errors.lampiran && <p className="text-sm text-destructive">{form.errors.lampiran}</p>}
                </div>

                <Button type="submit" disabled={form.processing} className="w-full sm:w-auto">
                    {form.processing ? 'Mengirim...' : 'Kirim Pengajuan'}
                </Button>
            </form>
        </AppLayout>
    );
}
