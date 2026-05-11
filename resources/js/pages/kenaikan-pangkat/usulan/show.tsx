import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock3,
    Download,
    FileText,
    History,
    Pencil,
    RotateCcw,
    Upload,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type PegawaiSummary = {
    id: string;
    nip?: string | null;
    nama_lengkap: string;
};

type PangkatSummary = {
    id: string;
    kode?: string | null;
    nama?: string | null;
    golongan?: string | null;
    ruang?: string | null;
};

type ChecklistItem = {
    id: string;
    nama?: string | null;
    nama_berkas?: string | null;
    status?: string | null;
    status_kelengkapan?: string | null;
    wajib?: boolean | null;
    catatan?: string | null;
    file_original_name?: string | null;
    uploaded_at?: string | null;
};

type ChecklistSubmission = {
    id: string;
    persentase?: number | null;
    persentase_kelengkapan?: number | null;
    status_kelengkapan?: string | null;
    items?: ChecklistItem[];
};

type Lampiran = {
    id: string;
    nama_file_asli?: string | null;
    file_original_name?: string | null;
    jenis_lampiran?: string | null;
    mime_type?: string | null;
    size_bytes?: number | null;
    created_at?: string | null;
};

type TimelineEntry = {
    id: string;
    action?: string | null;
    from_state?: string | null;
    to_state?: string | null;
    state?: string | null;
    catatan?: string | null;
    created_at?: string | null;
    actor?: PegawaiSummary | null;
    approver?: PegawaiSummary | null;
};

type ApprovalStep = {
    id: string;
    urutan?: number | null;
    role?: string | null;
    nama_role?: string | null;
    status?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    processed_at?: string | null;
    approver?: PegawaiSummary | null;
    catatan?: string | null;
};

type PdfFile = {
    id: string;
    jenis_pdf?: string | null;
    nama_file_asli?: string | null;
};

type UsulanKenaikanPangkat = {
    id: string;
    nomor_usulan?: string | null;
    pegawai?: PegawaiSummary | null;
    pangkat_asal?: PangkatSummary | null;
    pangkat_tujuan?: PangkatSummary | null;
    periode_usul_bulan?: number | null;
    periode_usul_tahun?: number | null;
    tmt_pangkat_asal?: string | null;
    tanggal_usulan?: string | null;
    state: string;
    catatan_pengusul?: string | null;
    catatan_penolakan?: string | null;
    nomor_sk?: string | null;
    tanggal_sk?: string | null;
    sk_file_original_name?: string | null;
    submitted_at?: string | null;
    finalized_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    approval_steps?: ApprovalStep[];
    state_history?: TimelineEntry[];
    approver_history?: TimelineEntry[];
    lampiran?: Lampiran[];
    pdfs?: PdfFile[];
    checklist_submission?: ChecklistSubmission | null;
};

type Policy = {
    update?: boolean;
    batalkan?: boolean;
    uploadChecklist?: boolean;
    uploadLampiran?: boolean;
};

type TimelineIconMap = Record<string, string>;

type Props = {
    usulan: UsulanKenaikanPangkat;
    checklist?: ChecklistSubmission | null;
    lampiran?: Lampiran[];
    timeline?: TimelineEntry[];
    policy?: Policy;
    stateLabels?: Record<string, string>;
    stateBadgeVariants?: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'>;
    timelineIcons?: TimelineIconMap;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kenaikan Pangkat', href: '/kenaikan-pangkat/usulan' },
    { title: 'Detail Usulan', href: '#' },
];

const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value));
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatPeriode(month?: number | null, year?: number | null): string {
    if (!month || !year) {
        return '-';
    }

    return `${monthNames[month - 1] ?? month} ${year}`;
}

function formatPangkat(pangkat?: PangkatSummary | null): string {
    if (!pangkat) {
        return '-';
    }

    const golongan = [pangkat.golongan, pangkat.ruang].filter(Boolean).join('/');
    const label = [pangkat.kode, pangkat.nama].filter(Boolean).join(' - ');

    return golongan ? `${label} (${golongan})` : label || '-';
}

function formatFileSize(bytes?: number | null): string {
    if (!bytes) {
        return '-';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function humanize(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return value
        .toLowerCase()
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function statusVariant(status?: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    const normalized = status?.toLowerCase();

    if (['valid', 'approved', 'disetujui', 'selesai', 'lengkap'].includes(normalized ?? '')) {
        return 'default';
    }

    if (['invalid', 'rejected', 'ditolak', 'kurang'].includes(normalized ?? '')) {
        return 'destructive';
    }

    if (['pending', 'menunggu', 'draft'].includes(normalized ?? '')) {
        return 'secondary';
    }

    return 'outline';
}

function iconFor(iconName?: string | null) {
    switch (iconName) {
        case 'check':
            return CheckCircle2;
        case 'x':
            return XCircle;
        case 'clock':
            return Clock3;
        case 'history':
        default:
            return History;
    }
}

function getChronologicalTimeline(
    stateHistory: TimelineEntry[] = [],
    approverHistory: TimelineEntry[] = [],
    explicitTimeline: TimelineEntry[] = [],
): TimelineEntry[] {
    const entries = explicitTimeline.length > 0 ? explicitTimeline : [...stateHistory, ...approverHistory];

    return [...entries].sort((first, second) => {
        const firstTime = new Date(first.created_at ?? '').getTime() || 0;
        const secondTime = new Date(second.created_at ?? '').getTime() || 0;

        return firstTime - secondTime;
    });
}

export default function UsulanKenaikanPangkatShow({
    usulan,
    checklist,
    lampiran,
    timeline,
    policy,
    stateLabels = {},
    stateBadgeVariants = {},
    timelineIcons = {},
}: Props) {
    const checklistSubmission = checklist ?? usulan.checklist_submission;
    const lampiranItems = lampiran ?? usulan.lampiran ?? [];
    const timelineItems = getChronologicalTimeline(
        usulan.state_history ?? [],
        usulan.approver_history ?? [],
        timeline ?? [],
    );
    const progress = Math.round(
        checklistSubmission?.persentase ?? checklistSubmission?.persentase_kelengkapan ?? 0,
    );
    const canEdit = Boolean(policy?.update && ['DRAFT', 'PERLU_PERBAIKAN'].includes(usulan.state));
    const canCancel = Boolean(policy?.batalkan && ['DRAFT', 'DIAJUKAN', 'PERLU_PERBAIKAN'].includes(usulan.state));
    const canUploadChecklist = Boolean(
        policy?.uploadChecklist && ['DRAFT', 'PERLU_PERBAIKAN'].includes(usulan.state),
    );
    const canUploadLampiran = Boolean(
        policy?.uploadLampiran && ['DRAFT', 'PERLU_PERBAIKAN'].includes(usulan.state),
    );
    const stateLabel = stateLabels[usulan.state] ?? humanize(usulan.state);
    const suratPengantar = (usulan.pdfs ?? []).find((pdf) => pdf.jenis_pdf === 'surat_pengantar');
    const hasSkFinal = usulan.state === 'SELESAI_SK_TERBIT' && Boolean(usulan.nomor_sk);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Usulan KP ${usulan.nomor_usulan ?? usulan.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex items-start gap-3">
                        <Button variant="outline" size="icon-sm" asChild>
                            <Link href="/kenaikan-pangkat/usulan">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {usulan.pegawai?.nama_lengkap ?? 'Pegawai tidak tersedia'}
                                </h1>
                                <Badge variant={stateBadgeVariants[usulan.state] ?? 'outline'}>{stateLabel}</Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {formatPangkat(usulan.pangkat_asal)} → {formatPangkat(usulan.pangkat_tujuan)}
                            </p>
                            <p className="font-mono text-sm text-muted-foreground">
                                {formatPeriode(usulan.periode_usul_bulan, usulan.periode_usul_tahun)} ·{' '}
                                {usulan.nomor_usulan ?? 'Belum ada nomor'}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {suratPengantar && usulan.state === 'DITANDATANGANI_KETUA' && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/kenaikan-pangkat/admin-sk/pdf/${suratPengantar.id}/download`}>
                                    <Download className="h-4 w-4" />
                                    Surat Pengantar PDF
                                </a>
                            </Button>
                        )}
                        {hasSkFinal && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/kenaikan-pangkat/admin-sk/${usulan.id}/download-sk`}>
                                    <Download className="h-4 w-4" />
                                    SK Final
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                <Tabs defaultValue="ringkasan" className="gap-4">
                    <TabsList className="grid h-auto w-full grid-cols-2 gap-1 md:grid-cols-5">
                        <TabsTrigger value="ringkasan">Ringkasan</TabsTrigger>
                        <TabsTrigger value="checklist">Checklist</TabsTrigger>
                        <TabsTrigger value="lampiran">Lampiran</TabsTrigger>
                        <TabsTrigger value="timeline">Timeline</TabsTrigger>
                        <TabsTrigger value="approver">Approver</TabsTrigger>
                    </TabsList>

                    <TabsContent value="ringkasan">
                        <Card>
                            <CardHeader>
                                <CardTitle>Ringkasan Usulan</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <Info label="NIP" value={usulan.pegawai?.nip ?? '-'} />
                                    <Info label="Periode" value={formatPeriode(usulan.periode_usul_bulan, usulan.periode_usul_tahun)} />
                                    <Info label="Tanggal Usulan" value={formatDate(usulan.tanggal_usulan)} />
                                    <Info label="Pangkat Asal" value={formatPangkat(usulan.pangkat_asal)} />
                                    <Info label="Pangkat Tujuan" value={formatPangkat(usulan.pangkat_tujuan)} />
                                    <Info label="TMT Pangkat Asal" value={formatDate(usulan.tmt_pangkat_asal)} />
                                    <Info label="Diajukan" value={formatDateTime(usulan.submitted_at)} />
                                    <Info label="Finalisasi" value={formatDateTime(usulan.finalized_at)} />
                                    <Info label="Nomor SK" value={usulan.nomor_sk ?? '-'} />
                                </div>

                                {(usulan.catatan_pengusul || usulan.catatan_penolakan) && <Separator />}
                                {usulan.catatan_pengusul && (
                                    <Info label="Catatan Pengusul" value={usulan.catatan_pengusul} />
                                )}
                                {usulan.catatan_penolakan && (
                                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/40">
                                        <p className="text-xs font-semibold text-red-700 dark:text-red-300">
                                            Catatan Penolakan
                                        </p>
                                        <p className="mt-1 text-sm text-red-800 dark:text-red-200">
                                            {usulan.catatan_penolakan}
                                        </p>
                                    </div>
                                )}

                                <Separator />
                                <div className="flex flex-wrap gap-2">
                                    {canEdit && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/kenaikan-pangkat/usulan/${usulan.id}/edit`}>
                                                <Pencil className="h-4 w-4" />
                                                Edit
                                            </Link>
                                        </Button>
                                    )}
                                    {canCancel && (
                                        <Form action={`/kenaikan-pangkat/usulan/${usulan.id}/batalkan`} method="post">
                                            {({ processing }) => (
                                                <Button variant="destructive" size="sm" disabled={processing}>
                                                    <RotateCcw className="h-4 w-4" />
                                                    Batalkan
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="checklist">
                        <Card>
                            <CardHeader>
                                <CardTitle>Checklist Berkas</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Persentase kelengkapan</span>
                                        <span className="font-medium">{progress}%</span>
                                    </div>
                                    <Progress value={progress} />
                                </div>

                                <div className="space-y-3">
                                    {(checklistSubmission?.items ?? []).map((item) => (
                                        <div key={item.id} className="rounded-lg border p-4 dark:border-border">
                                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                                <div className="space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium">
                                                            {item.nama_berkas ?? item.nama ?? 'Berkas'}
                                                        </p>
                                                        {item.wajib && <Badge variant="secondary">Wajib</Badge>}
                                                        <Badge variant={statusVariant(item.status_kelengkapan ?? item.status)}>
                                                            {humanize(item.status_kelengkapan ?? item.status)}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {item.file_original_name ?? 'Belum ada file'}
                                                    </p>
                                                    {item.catatan && (
                                                        <p className="text-sm text-muted-foreground">Catatan: {item.catatan}</p>
                                                    )}
                                                </div>
                                                {canUploadChecklist && (
                                                    <Form
                                                        action={`/kenaikan-pangkat/usulan/${usulan.id}/checklist/${item.id}/upload`}
                                                        method="post"
                                                        encType="multipart/form-data"
                                                    >
                                                        {({ processing }) => (
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <input
                                                                    type="file"
                                                                    name="file"
                                                                    className="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-input/30"
                                                                />
                                                                <Button size="sm" disabled={processing}>
                                                                    <Upload className="h-4 w-4" />
                                                                    Upload
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </Form>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    {(checklistSubmission?.items ?? []).length === 0 && <EmptyState text="Checklist belum tersedia." />}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="lampiran">
                        <Card>
                            <CardHeader>
                                <CardTitle>Lampiran</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {canUploadLampiran && (
                                    <Form
                                        action={`/kenaikan-pangkat/usulan/${usulan.id}/lampiran`}
                                        method="post"
                                        encType="multipart/form-data"
                                    >
                                        {({ processing }) => (
                                            <div className="flex flex-wrap items-center gap-2 rounded-lg border p-3 dark:border-border">
                                                <input
                                                    type="file"
                                                    name="file"
                                                    className="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-input/30"
                                                />
                                                <Button size="sm" disabled={processing}>
                                                    <Upload className="h-4 w-4" />
                                                    Upload Tambahan
                                                </Button>
                                            </div>
                                        )}
                                    </Form>
                                )}

                                <div className="space-y-3">
                                    {lampiranItems.map((file) => (
                                        <div key={file.id} className="flex items-center justify-between rounded-lg border p-3 dark:border-border">
                                            <div className="flex items-center gap-3">
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {file.nama_file_asli ?? file.file_original_name ?? 'Lampiran'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {humanize(file.jenis_lampiran)} · {formatFileSize(file.size_bytes)} ·{' '}
                                                        {formatDateTime(file.created_at)}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    {lampiranItems.length === 0 && <EmptyState text="Lampiran belum tersedia." />}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="timeline">
                        <Card>
                            <CardHeader>
                                <CardTitle>Timeline</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {timelineItems.map((entry) => {
                                        const Icon = iconFor(timelineIcons[entry.action ?? entry.to_state ?? entry.state ?? '']);

                                        return (
                                            <div key={entry.id} className="flex gap-3">
                                                <div className="mt-0.5 rounded-full bg-muted p-2 text-muted-foreground dark:bg-input/30">
                                                    <Icon className="h-4 w-4" />
                                                </div>
                                                <div className="min-w-0 flex-1 rounded-lg border p-3 dark:border-border">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <p className="font-medium">
                                                            {humanize(entry.action ?? entry.to_state ?? entry.state)}
                                                        </p>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDateTime(entry.created_at)}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {[entry.from_state, entry.to_state].filter(Boolean).map(humanize).join(' → ') ||
                                                            entry.actor?.nama_lengkap ||
                                                            entry.approver?.nama_lengkap ||
                                                            '-'}
                                                    </p>
                                                    {entry.catatan && <p className="mt-2 text-sm">{entry.catatan}</p>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                    {timelineItems.length === 0 && <EmptyState text="Timeline belum tersedia." />}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="approver">
                        <Card>
                            <CardHeader>
                                <CardTitle>Approver</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Langkah</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Approver</TableHead>
                                            <TableHead>Timestamp</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(usulan.approval_steps ?? defaultApprovalSteps()).map((step, index) => (
                                            <TableRow key={step.id ?? step.role ?? index}>
                                                <TableCell>{step.urutan ?? index + 1}</TableCell>
                                                <TableCell>{humanize(step.nama_role ?? step.role)}</TableCell>
                                                <TableCell>
                                                    <Badge variant={statusVariant(step.status)}>{humanize(step.status ?? 'menunggu')}</Badge>
                                                </TableCell>
                                                <TableCell>{step.approver?.nama_lengkap ?? '-'}</TableCell>
                                                <TableCell>
                                                    {formatDateTime(step.processed_at ?? step.approved_at ?? step.rejected_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium">{value}</p>
        </div>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground dark:border-border">
            {text}
        </div>
    );
}

function defaultApprovalSteps(): ApprovalStep[] {
    return [
        { id: 'kasubbag', urutan: 1, role: 'kasubbag', status: 'menunggu' },
        { id: 'sekretaris', urutan: 2, role: 'sekretaris', status: 'menunggu' },
        { id: 'ketua', urutan: 3, role: 'ketua', status: 'menunggu' },
    ];
}
