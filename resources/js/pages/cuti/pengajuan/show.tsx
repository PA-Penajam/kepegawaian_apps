import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Download,
    ExternalLink,
    FileText,
    RefreshCw,
    Search,
    Undo2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { DialogCancel } from '@/components/cuti/DialogCancel';
import { TimelineApproval } from '@/components/cuti/TimelineApproval';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { formatTanggal, formatTanggalDateTime } from '@/lib/cuti-utils';
import type { BreadcrumbItem } from '@/types';
import type { CutiPengajuan, CutiState, StatusSuratDinas } from '@/types/cuti';
import { CutiStateBadgeVariant, CutiStateLabels } from '@/types/cuti';

type KewenanganEksternalInfo = {
    nama_aplikasi: string | null;
    url_aplikasi: string | null;
    instansi_nama: string;
};

type IncomingMailOption = {
    public_id: string;
    agenda_number: string;
    sender: string;
    sender_agency: string;
    sender_mail_number: string | null;
    mail_date: string | null;
    subject: string;
};

type Props = {
    pengajuan: CutiPengajuan;
    canManageSuratIzin?: boolean;
    kewenanganEksternal?: KewenanganEksternalInfo | null;
};

const STATUS_SURAT_LABELS: Record<StatusSuratDinas, string> = {
    BELUM_TERKIRIM: 'Belum Terkirim',
    MENUNGGU_NOMOR: 'Menunggu Nomor SiSukma',
    PERLU_PERBAIKAN: 'Perlu Perbaikan',
    NOMOR_TERBIT: 'Nomor Terbit',
    FINAL_TERSEDIA: 'Surat Final Tersedia',
    TERKIRIM: 'Terkirim',
    DIBATALKAN: 'Dibatalkan',
};

const PENANDATANGAN_LABELS: Record<string, string> = {
    ketua: 'Ketua',
    wakil_ketua: 'Wakil Ketua',
    panitera: 'Panitera',
    sekretaris: 'Sekretaris',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti Saya', href: '/cuti/saya' },
    { title: 'Detail Pengajuan', href: '#' },
];

/**
 * Format ukuran file ke format yang mudah dibaca.
 */
function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function PengajuanShow({
    pengajuan,
    canManageSuratIzin = false,
    kewenanganEksternal = null,
}: Props) {
    // Mode dialog: null = tertutup, 'cancel' = batalkan, 'revoke' = cabut cuti
    const [cancelMode, setCancelMode] = useState<'cancel' | 'revoke' | null>(
        null,
    );

    const suratIzin = pengajuan.surat_izin_request ?? null;
    const perizinanEksternal = pengajuan.perizinan_eksternal ?? null;
    const [resubmitForm, setResubmitForm] = useState(() => ({
        perihal: suratIzin?.perihal ?? '',
        tujuan_nama: suratIzin?.tujuan_nama ?? '',
        tujuan_instansi: suratIzin?.tujuan_instansi ?? '',
        penandatangan: suratIzin?.penandatangan ?? 'ketua',
    }));
    const [submittingResubmit, setSubmittingResubmit] = useState(false);

    const [regEksternalForm, setRegEksternalForm] = useState(() => ({
        nomor_registrasi_eksternal:
            perizinanEksternal?.nomor_registrasi_eksternal ?? '',
        tanggal_pengajuan_eksternal:
            perizinanEksternal?.tanggal_pengajuan_eksternal?.slice(0, 10) ?? '',
        catatan_pengajuan: perizinanEksternal?.catatan_pengajuan ?? '',
    }));
    const [savingRegEksternal, setSavingRegEksternal] = useState(false);

    const [mailQuery, setMailQuery] = useState('');
    const [mailResults, setMailResults] = useState<IncomingMailOption[]>([]);
    const [searchingMail, setSearchingMail] = useState(false);
    const [keputusanForm, setKeputusanForm] = useState(() => ({
        keputusan: (perizinanEksternal?.keputusan ?? 'disetujui') as
            'disetujui' | 'ditolak',
        sisukma_incoming_mail_public_id:
            perizinanEksternal?.sisukma_incoming_mail_public_id ?? '',
        nomor_sk: perizinanEksternal?.nomor_sk ?? '',
        tanggal_sk: perizinanEksternal?.tanggal_sk?.slice(0, 10) ?? '',
        catatan_keputusan: perizinanEksternal?.catatan_keputusan ?? '',
    }));
    const [savingKeputusan, setSavingKeputusan] = useState(false);

    const handleRegistrasiEksternal = (e: FormEvent) => {
        e.preventDefault();
        setSavingRegEksternal(true);
        router.post(
            `/cuti/pengajuan/${pengajuan.id}/perizinan-eksternal/registrasi`,
            regEksternalForm,
            {
                preserveScroll: true,
                onFinish: () => setSavingRegEksternal(false),
            },
        );
    };

    const handleSearchIncomingMails = async () => {
        setSearchingMail(true);

        try {
            const res = await fetch(
                `/cuti/sisukma/incoming-mails?q=${encodeURIComponent(mailQuery)}`,
                { headers: { Accept: 'application/json' } },
            );

            if (res.ok) {
                const json = (await res.json()) as {
                    data?: IncomingMailOption[];
                };
                setMailResults(json.data ?? []);
            }
        } finally {
            setSearchingMail(false);
        }
    };

    const handleSelectIncomingMail = (mail: IncomingMailOption) => {
        setKeputusanForm((prev) => ({
            ...prev,
            sisukma_incoming_mail_public_id: mail.public_id,
            nomor_sk: mail.sender_mail_number ?? prev.nomor_sk,
            tanggal_sk: mail.mail_date?.slice(0, 10) ?? prev.tanggal_sk,
        }));
    };

    const handleKeputusanEksternal = (e: FormEvent) => {
        e.preventDefault();
        setSavingKeputusan(true);
        router.post(
            `/cuti/pengajuan/${pengajuan.id}/perizinan-eksternal/keputusan`,
            keputusanForm,
            {
                preserveScroll: true,
                onFinish: () => setSavingKeputusan(false),
            },
        );
    };

    const handleResubmitSuratIzin = (e: FormEvent) => {
        e.preventDefault();
        setSubmittingResubmit(true);
        router.post(
            `/cuti/pengajuan/${pengajuan.id}/surat-izin/resubmit`,
            resubmitForm,
            {
                preserveScroll: true,
                onFinish: () => setSubmittingResubmit(false),
            },
        );
    };

    const state = pengajuan.state as CutiState;
    const stateLabel = CutiStateLabels[state] ?? state;

    // Cek apakah bisa dibatalkan (state DRAFT)
    const canCancel = state === 'DRAFT';

    // Cek apakah bisa dicabut (state DISETUJUI dan jenis boleh dicabut)
    const canRevoke = state === 'DISETUJUI';

    // Handler untuk batalkan — buka dialog konfirmasi
    const handleCancel = () => setCancelMode('cancel');

    // Handler untuk cabut cuti — buka dialog konfirmasi
    const handleRevoke = () => setCancelMode('revoke');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Pengajuan ${pengajuan.nomor_pengajuan ?? pengajuan.id}`}
            />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon-sm" asChild>
                            <Link href="/cuti/saya">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                Detail Pengajuan Cuti
                            </h1>
                            <p className="font-mono text-sm text-muted-foreground">
                                {pengajuan.nomor_pengajuan ?? 'Belum ada nomor'}
                            </p>
                        </div>
                    </div>

                    {/* Tombol aksi */}
                    <div className="flex gap-2">
                        {canCancel && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleCancel}
                            >
                                <Ban className="h-4 w-4" />
                                Batalkan
                            </Button>
                        )}
                        {canRevoke && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleRevoke}
                            >
                                <Undo2 className="h-4 w-4" />
                                Cabut Cuti
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={`/cuti/pengajuan/${pengajuan.id}/pdf`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Download className="h-4 w-4" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom utama: detail pengajuan */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        Informasi Pengajuan
                                    </CardTitle>
                                    <Badge
                                        variant={CutiStateBadgeVariant[state]}
                                    >
                                        {stateLabel}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Jenis Cuti
                                        </p>
                                        <p className="text-sm font-medium">
                                            {pengajuan.jenis_cuti?.nama ??
                                                pengajuan.jenis_cuti_kode}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Durasi
                                        </p>
                                        <p className="text-sm font-medium">
                                            {pengajuan.jumlah_hari_kerja} hari
                                            kerja
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Tanggal Mulai
                                        </p>
                                        <p className="text-sm font-medium">
                                            {formatTanggal(
                                                pengajuan.tanggal_mulai,
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Tanggal Selesai
                                        </p>
                                        <p className="text-sm font-medium">
                                            {formatTanggal(
                                                pengajuan.tanggal_selesai,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <Separator />

                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Alasan
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {pengajuan.alasan}
                                    </p>
                                </div>

                                {pengajuan.alamat_selama_cuti && (
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Alamat Selama Cuti
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {pengajuan.alamat_selama_cuti}
                                        </p>
                                    </div>
                                )}

                                {pengajuan.nomor_telp_selama_cuti && (
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            No. Telepon Selama Cuti
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {pengajuan.nomor_telp_selama_cuti}
                                        </p>
                                    </div>
                                )}

                                {pengajuan.rejection_reason && (
                                    <>
                                        <Separator />
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950">
                                            <p className="text-xs font-semibold text-red-600 dark:text-red-400">
                                                Alasan Penolakan
                                            </p>
                                            <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                                                {pengajuan.rejection_reason}
                                            </p>
                                        </div>
                                    </>
                                )}

                                {/* Timestamp */}
                                <Separator />
                                <div className="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                    {pengajuan.submitted_at && (
                                        <p>
                                            Diajukan:{' '}
                                            {formatTanggalDateTime(
                                                pengajuan.submitted_at,
                                            )}
                                        </p>
                                    )}
                                    {pengajuan.approved_at && (
                                        <p>
                                            Disetujui:{' '}
                                            {formatTanggalDateTime(
                                                pengajuan.approved_at,
                                            )}
                                        </p>
                                    )}
                                    {pengajuan.rejected_at && (
                                        <p>
                                            Ditolak:{' '}
                                            {formatTanggalDateTime(
                                                pengajuan.rejected_at,
                                            )}
                                        </p>
                                    )}
                                    {pengajuan.cancelled_at && (
                                        <p>
                                            Dibatalkan:{' '}
                                            {formatTanggalDateTime(
                                                pengajuan.cancelled_at,
                                            )}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Perizinan Instansi Atasan (PTA/MA) */}
                        {perizinanEksternal && (
                            <Card>
                                <CardHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="text-base">
                                            Perizinan Instansi Atasan (
                                            {perizinanEksternal.pemberi_izin.toUpperCase()}
                                            )
                                        </CardTitle>
                                        <Badge variant="outline">
                                            {perizinanEksternal.instansi_nama}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                No. Registrasi Aplikasi PTA/MA
                                            </p>
                                            <p className="font-mono text-sm font-medium">
                                                {perizinanEksternal.nomor_registrasi_eksternal ??
                                                    'Belum dicatat'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                SK / Surat Masuk SiSukma
                                            </p>
                                            <p className="font-mono text-sm font-medium">
                                                {perizinanEksternal.nomor_sk
                                                    ? `${perizinanEksternal.nomor_sk}${perizinanEksternal.sisukma_agenda_number ? ` (${perizinanEksternal.sisukma_agenda_number})` : ''}`
                                                    : 'Menunggu SK masuk'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {kewenanganEksternal?.url_aplikasi && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={
                                                        kewenanganEksternal.url_aplikasi
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <ExternalLink className="h-4 w-4" />
                                                    Buka{' '}
                                                    {kewenanganEksternal.nama_aplikasi ??
                                                        'Aplikasi PTA/MA'}
                                                </a>
                                            </Button>
                                        )}
                                        {perizinanEksternal.sisukma_incoming_mail_public_id && (
                                            <Button size="sm" asChild>
                                                <a
                                                    href={`/cuti/pengajuan/${pengajuan.id}/sk-eksternal`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Download className="h-4 w-4" />
                                                    Unduh SK (Arsip SiSukma)
                                                </a>
                                            </Button>
                                        )}
                                    </div>

                                    {state === 'DITERUSKAN_INSTANSI_ATASAN' &&
                                        canManageSuratIzin && (
                                            <div className="space-y-4 border-t pt-4">
                                                <form
                                                    onSubmit={
                                                        handleRegistrasiEksternal
                                                    }
                                                    className="space-y-3 rounded-lg border p-3"
                                                >
                                                    <p className="text-sm font-medium">
                                                        1. Catat Nomor
                                                        Registrasi Aplikasi
                                                        PTA/MA
                                                    </p>
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div className="space-y-1">
                                                            <Label htmlFor="reg-ext-nomor">
                                                                Nomor Registrasi
                                                            </Label>
                                                            <Input
                                                                id="reg-ext-nomor"
                                                                value={
                                                                    regEksternalForm.nomor_registrasi_eksternal
                                                                }
                                                                onChange={(e) =>
                                                                    setRegEksternalForm(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            nomor_registrasi_eksternal:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label htmlFor="reg-ext-tgl">
                                                                Tanggal
                                                                Pengajuan
                                                            </Label>
                                                            <Input
                                                                id="reg-ext-tgl"
                                                                type="date"
                                                                value={
                                                                    regEksternalForm.tanggal_pengajuan_eksternal
                                                                }
                                                                onChange={(e) =>
                                                                    setRegEksternalForm(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            tanggal_pengajuan_eksternal:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            savingRegEksternal
                                                        }
                                                    >
                                                        {savingRegEksternal
                                                            ? 'Menyimpan...'
                                                            : 'Simpan Nomor Registrasi'}
                                                    </Button>
                                                </form>

                                                <form
                                                    onSubmit={
                                                        handleKeputusanEksternal
                                                    }
                                                    className="space-y-3 rounded-lg border p-3"
                                                >
                                                    <p className="text-sm font-medium">
                                                        2. Tautkan SK Surat
                                                        Masuk SiSukma & Simpan
                                                        Keputusan
                                                    </p>
                                                    <div className="flex gap-2">
                                                        <Input
                                                            placeholder="Cari agenda, nomor SK, atau perihal di SiSukma..."
                                                            value={mailQuery}
                                                            onChange={(e) =>
                                                                setMailQuery(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={
                                                                handleSearchIncomingMails
                                                            }
                                                            disabled={
                                                                searchingMail
                                                            }
                                                        >
                                                            <Search className="h-4 w-4" />
                                                            Cari
                                                        </Button>
                                                    </div>

                                                    {mailResults.length > 0 && (
                                                        <div className="max-h-40 space-y-1 overflow-y-auto rounded border p-2 text-xs">
                                                            {mailResults.map(
                                                                (mail) => (
                                                                    <button
                                                                        key={
                                                                            mail.public_id
                                                                        }
                                                                        type="button"
                                                                        onClick={() =>
                                                                            handleSelectIncomingMail(
                                                                                mail,
                                                                            )
                                                                        }
                                                                        className="flex w-full items-center justify-between rounded px-2 py-1 text-left hover:bg-muted"
                                                                    >
                                                                        <span>
                                                                            <strong className="font-mono">
                                                                                {
                                                                                    mail.agenda_number
                                                                                }
                                                                            </strong>{' '}
                                                                            —{' '}
                                                                            {
                                                                                mail.subject
                                                                            }
                                                                        </span>
                                                                        <span className="text-muted-foreground">
                                                                            {mail.sender_mail_number ??
                                                                                ''}
                                                                        </span>
                                                                    </button>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}

                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div className="space-y-1">
                                                            <Label>
                                                                Keputusan
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    keputusanForm.keputusan
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    setKeputusanForm(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            keputusan:
                                                                                value as
                                                                                    | 'disetujui'
                                                                                    | 'ditolak',
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="disetujui">
                                                                        Disetujui
                                                                    </SelectItem>
                                                                    <SelectItem value="ditolak">
                                                                        Ditolak
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label htmlFor="sk-public-id">
                                                                ID Surat Masuk
                                                                SiSukma
                                                            </Label>
                                                            <Input
                                                                id="sk-public-id"
                                                                value={
                                                                    keputusanForm.sisukma_incoming_mail_public_id
                                                                }
                                                                onChange={(e) =>
                                                                    setKeputusanForm(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            sisukma_incoming_mail_public_id:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label htmlFor="sk-nomor">
                                                                Nomor SK
                                                            </Label>
                                                            <Input
                                                                id="sk-nomor"
                                                                value={
                                                                    keputusanForm.nomor_sk
                                                                }
                                                                onChange={(e) =>
                                                                    setKeputusanForm(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            nomor_sk:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label htmlFor="sk-tgl">
                                                                Tanggal SK
                                                            </Label>
                                                            <Input
                                                                id="sk-tgl"
                                                                type="date"
                                                                value={
                                                                    keputusanForm.tanggal_sk
                                                                }
                                                                onChange={(e) =>
                                                                    setKeputusanForm(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            tanggal_sk:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={
                                                            savingKeputusan
                                                        }
                                                    >
                                                        {savingKeputusan
                                                            ? 'Menyimpan...'
                                                            : 'Simpan Keputusan & Tautkan SK'}
                                                    </Button>
                                                </form>
                                            </div>
                                        )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Surat Izin Cuti (SiSukma) */}
                        {suratIzin && (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-2">
                                        <CardTitle className="text-base">
                                            Surat Izin Cuti (SiSukma)
                                        </CardTitle>
                                        <Badge
                                            variant={
                                                suratIzin.status ===
                                                    'PERLU_PERBAIKAN' ||
                                                suratIzin.status ===
                                                    'DIBATALKAN'
                                                    ? 'destructive'
                                                    : suratIzin.status ===
                                                            'FINAL_TERSEDIA' ||
                                                        suratIzin.status ===
                                                            'TERKIRIM'
                                                      ? 'default'
                                                      : 'secondary'
                                            }
                                        >
                                            {STATUS_SURAT_LABELS[
                                                suratIzin.status
                                            ] ?? suratIzin.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Nomor Agenda
                                            </p>
                                            <p className="font-mono text-sm font-medium">
                                                {suratIzin.nomor_agenda ?? '-'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Nomor Surat Resmi
                                            </p>
                                            <p className="font-mono text-sm font-medium">
                                                {suratIzin.nomor_surat ??
                                                    'Menunggu penerbitan'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Penandatangan
                                            </p>
                                            <p className="text-sm font-medium">
                                                {PENANDATANGAN_LABELS[
                                                    suratIzin.penandatangan
                                                ] ?? suratIzin.penandatangan}
                                            </p>
                                        </div>
                                    </div>

                                    {suratIzin.catatan_revisi && (
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950">
                                            <p className="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                                Catatan Revisi SiSukma
                                            </p>
                                            <p className="mt-1 text-sm text-amber-800 dark:text-amber-200">
                                                {suratIzin.catatan_revisi}
                                            </p>
                                        </div>
                                    )}

                                    {suratIzin.alasan_void && (
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950">
                                            <p className="text-xs font-semibold text-red-700 dark:text-red-300">
                                                Alasan Pembatalan Nomor
                                            </p>
                                            <p className="mt-1 text-sm text-red-800 dark:text-red-200">
                                                {suratIzin.alasan_void}
                                            </p>
                                        </div>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        {(suratIzin.status === 'NOMOR_TERBIT' ||
                                            suratIzin.status ===
                                                'FINAL_TERSEDIA' ||
                                            suratIzin.status ===
                                                'TERKIRIM') && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={`/cuti/pengajuan/${pengajuan.id}/surat-izin/draft`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Download className="h-4 w-4" />
                                                    Unduh Draf Bernomor
                                                </a>
                                            </Button>
                                        )}
                                        {(suratIzin.status ===
                                            'FINAL_TERSEDIA' ||
                                            suratIzin.status ===
                                                'TERKIRIM') && (
                                            <Button size="sm" asChild>
                                                <a
                                                    href={`/cuti/pengajuan/${pengajuan.id}/surat-izin/final`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Download className="h-4 w-4" />
                                                    Unduh Surat Izin Final
                                                </a>
                                            </Button>
                                        )}
                                    </div>

                                    {suratIzin.status === 'PERLU_PERBAIKAN' &&
                                        canManageSuratIzin && (
                                            <form
                                                onSubmit={
                                                    handleResubmitSuratIzin
                                                }
                                                className="space-y-3 rounded-lg border p-4"
                                            >
                                                <p className="text-sm font-medium">
                                                    Ajukan Ulang Surat Izin ke
                                                    SiSukma
                                                </p>
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <div className="space-y-1 sm:col-span-2">
                                                        <Label htmlFor="resubmit-perihal">
                                                            Perihal
                                                        </Label>
                                                        <Input
                                                            id="resubmit-perihal"
                                                            value={
                                                                resubmitForm.perihal
                                                            }
                                                            onChange={(e) =>
                                                                setResubmitForm(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        perihal:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label htmlFor="resubmit-tujuan-nama">
                                                            Tujuan (Nama)
                                                        </Label>
                                                        <Input
                                                            id="resubmit-tujuan-nama"
                                                            value={
                                                                resubmitForm.tujuan_nama
                                                            }
                                                            onChange={(e) =>
                                                                setResubmitForm(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        tujuan_nama:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label htmlFor="resubmit-tujuan-instansi">
                                                            Tujuan (Instansi)
                                                        </Label>
                                                        <Input
                                                            id="resubmit-tujuan-instansi"
                                                            value={
                                                                resubmitForm.tujuan_instansi
                                                            }
                                                            onChange={(e) =>
                                                                setResubmitForm(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        tujuan_instansi:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                            required
                                                        />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <Label>
                                                            Penandatangan
                                                        </Label>
                                                        <Select
                                                            value={
                                                                resubmitForm.penandatangan
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                setResubmitForm(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        penandatangan:
                                                                            value as
                                                                                | 'ketua'
                                                                                | 'wakil_ketua'
                                                                                | 'panitera'
                                                                                | 'sekretaris',
                                                                    }),
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="ketua">
                                                                    Ketua
                                                                </SelectItem>
                                                                <SelectItem value="wakil_ketua">
                                                                    Wakil Ketua
                                                                </SelectItem>
                                                                <SelectItem value="panitera">
                                                                    Panitera
                                                                </SelectItem>
                                                                <SelectItem value="sekretaris">
                                                                    Sekretaris
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        submittingResubmit
                                                    }
                                                >
                                                    <RefreshCw className="h-4 w-4" />
                                                    {submittingResubmit
                                                        ? 'Mengirim Ulang...'
                                                        : 'Ajukan Ulang ke SiSukma'}
                                                </Button>
                                            </form>
                                        )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Lampiran */}
                        {pengajuan.lampiran &&
                            pengajuan.lampiran.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Lampiran
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            {pengajuan.lampiran.map((file) => (
                                                <div
                                                    key={file.id}
                                                    className="flex items-center justify-between rounded-lg border p-3"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <FileText className="h-5 w-5 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    file.nama_file_asli
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {formatFileSize(
                                                                    file.size_bytes,
                                                                )}{' '}
                                                                &middot;{' '}
                                                                {file.mime_type}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                    </div>

                    {/* Kolom samping: timeline approval */}
                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Riwayat Status
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <TimelineApproval
                                    stateHistory={pengajuan.state_history ?? []}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Dialog konfirmasi pembatalan / pencabutan cuti */}
            <DialogCancel
                pengajuan={pengajuan}
                open={cancelMode !== null}
                onClose={() => setCancelMode(null)}
            />
        </AppLayout>
    );
}
