import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    ArrowRight,
    BookOpen,
    CheckCircle2,
    Clock,
    Code2,
    Copy,
    Database,
    ExternalLink,
    FileText,
    KeyRound,
    ListChecks,
    LockKeyhole,
    Pencil,
    Plus,
    RefreshCcw,
    ShieldCheck,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { SyncTokenModal } from '@/components/iam/SyncTokenModal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { errorsToArray } from '@/lib/form-errors';
import type {
    BreadcrumbItem,
    PegawaiSyncPull,
    SyncConnectionTest,
    SyncConsumer,
    SyncTokenOnce,
} from '@/types';

type Props = {
    konsumen: SyncConsumer[];
    recentPulls: PegawaiSyncPull[];
    stats: {
        total_konsumen: number;
        aktif: number;
        pull_24h: number;
        pegawai_total: number;
    };
    flash?: {
        sync_token_once?: SyncTokenOnce;
        success?: string;
        error?: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'IAM', href: '#' },
    { title: 'Klien Sinkronisasi', href: '/iam/sinkronisasi' },
];

const CANONICAL_EXAMPLE = [
    'GET:/api/v1/pegawai/sync?page=1=1&per_page=100:',
    '<sha256(body)>:<timestamp>',
].join('');

const CURL_EXAMPLE = `curl -G "https://kepegawaian-apps.test/api/v1/pegawai/sync" \\
  -H "Authorization: Bearer <TOKEN_SYNC>" \\
  -H "Accept: application/json" \\
  -H "X-Timestamp: $(date +%s)" \\
  -H "X-Signature: <HMAC-SHA256 canonical-string>" \\
  --data-urlencode "page=1" \\
  --data-urlencode "per_page=100"`;

function timeAgo(value: string | null): string {
    if (!value) {
        return 'Belum pernah';
    }

    const diffMs = Date.now() - new Date(value).getTime();
    const diffMinutes = Math.floor(diffMs / 60000);

    if (diffMinutes < 1) {
        return 'Baru saja';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes} menit lalu`;
    }

    const diffHours = Math.floor(diffMinutes / 60);

    if (diffHours < 24) {
        return `${diffHours} jam lalu`;
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatNumber(value: number): string {
    return value.toLocaleString('id-ID');
}

const ONBOARDING_STORAGE_KEY = 'sinkronisasi-onboarding-dismissed';

export default function SyncConsumerIndex() {
    const { konsumen, recentPulls, stats, flash } = usePage<Props>().props;
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [editTarget, setEditTarget] = useState<SyncConsumer | null>(null);
    const [tokenModal, setTokenModal] = useState<SyncTokenOnce | null>(
        () => flash?.sync_token_once ?? null,
    );
    const [deleteTarget, setDeleteTarget] = useState<SyncConsumer | null>(null);
    const [testingSlug, setTestingSlug] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState('konsumen');
    const [onboardingDismissed, setOnboardingDismissed] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.localStorage.getItem(ONBOARDING_STORAGE_KEY) === '1';
    });
    const [testResult, setTestResult] = useState<
        Record<string, SyncConnectionTest>
    >({});

    const hasConsumer = konsumen.length > 0;
    const hasTestSuccess =
        konsumen.some(
            (item) => item.last_connection_test_status === 'success',
        ) || Object.values(testResult).some((item) => item?.success);
    const hasPull =
        recentPulls.length > 0 ||
        konsumen.some((item) => Boolean(item.last_pull_at));
    const onboardingSteps = [
        {
            id: 'daftar',
            title: 'Daftarkan aplikasi client',
            description: 'Nama dan slug unik, token langsung diterbitkan.',
            done: hasConsumer,
        },
        {
            id: 'uji',
            title: 'Uji koneksi bertanda tangan',
            description: 'Simulasi HMAC-SHA256 ke endpoint sync.',
            done: hasTestSuccess,
        },
        {
            id: 'pantau',
            title: 'Pantau tarikan pertama',
            description: 'Riwayat pull tercatat otomatis per halaman.',
            done: hasPull,
        },
    ];
    const onboardingDoneCount = onboardingSteps.filter(
        (step) => step.done,
    ).length;
    const onboardingComplete = onboardingDoneCount === onboardingSteps.length;
    const showOnboarding = !onboardingDismissed && !onboardingComplete;

    const dismissOnboarding = () => {
        setOnboardingDismissed(true);

        try {
            window.localStorage.setItem(ONBOARDING_STORAGE_KEY, '1');
        } catch {
            // Abaikan kegagalan penyimpanan lokal.
        }
    };

    const createForm = useForm({
        nama: '',
        slug: '',
        base_url: '',
        deskripsi: '',
    });

    const editForm = useForm({
        nama: '',
        slug: '',
        base_url: '',
        deskripsi: '',
        is_active: true,
    });

    const tokenRegenForm = useForm({});

    const handleStore = () => {
        createForm.post('/iam/sinkronisasi', {
            preserveScroll: true,
            onSuccess: (page) => {
                const nextFlash = (page.props.flash ?? {}) as {
                    sync_token_once?: SyncTokenOnce;
                };

                if (nextFlash.sync_token_once) {
                    setTokenModal(nextFlash.sync_token_once);
                }

                setShowCreateDialog(false);
                createForm.reset();
            },
        });
    };

    const handleUpdate = () => {
        editForm.put(`/iam/sinkronisasi/${editTarget?.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditTarget(null);
                editForm.reset();
            },
        });
    };

    const handleDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(`/iam/sinkronisasi/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const handleTestConnection = (consumer: SyncConsumer) => {
        setTestingSlug(consumer.slug);
        setTestResult((prev) => ({
            ...prev,
            [consumer.slug]: undefined as unknown as SyncConnectionTest,
        }));

        router.post(
            `/iam/sinkronisasi/${consumer.id}/test-connection`,
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props.flash ?? {}) as {
                        test_connection?: { success: boolean; message: string };
                    };

                    if (flash.test_connection) {
                        setTestResult((prev) => ({
                            ...prev,
                            [consumer.slug]:
                                flash.test_connection as SyncConnectionTest,
                        }));
                    }

                    setTestingSlug(null);
                },
                onError: () => {
                    setTestResult((prev) => ({
                        ...prev,
                        [consumer.slug]: {
                            success: false,
                            message:
                                'Gagal menguji koneksi. Periksa log server.',
                        },
                    }));
                    setTestingSlug(null);
                },
            },
        );
    };

    const handleRegenerateToken = (consumer: SyncConsumer) => {
        tokenRegenForm.post(
            `/iam/sinkronisasi/${consumer.id}/regenerate-token`,
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props.flash ?? {}) as {
                        sync_token_once?: SyncTokenOnce;
                    };

                    if (flash.sync_token_once) {
                        setTokenModal(flash.sync_token_once);
                    }

                    tokenRegenForm.reset();
                },
            },
        );
    };

    const handleRegenerateSecret = (consumer: SyncConsumer) => {
        tokenRegenForm.post(
            `/iam/sinkronisasi/${consumer.id}/regenerate-secret`,
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props.flash ?? {}) as {
                        sync_token_once?: SyncTokenOnce;
                    };

                    if (flash.sync_token_once) {
                        setTokenModal(flash.sync_token_once);
                    }

                    tokenRegenForm.reset();
                },
            },
        );
    };

    const statsCards = useMemo(
        () => [
            {
                label: 'Total Konsumen',
                value: formatNumber(stats.total_konsumen),
                icon: ArrowLeftRight,
                tone: 'text-primary',
            },
            {
                label: 'Konsumen Aktif',
                value: formatNumber(stats.aktif),
                icon: ShieldCheck,
                tone: 'text-emerald-600',
            },
            {
                label: 'Pull 24 Jam Terakhir',
                value: formatNumber(stats.pull_24h),
                icon: Activity,
                tone: 'text-amber-600',
            },
            {
                label: 'Data Pegawai',
                value: formatNumber(stats.pegawai_total),
                icon: Database,
                tone: 'text-sky-600',
            },
        ],
        [stats],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Klien Sinkronisasi" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Klien Sinkronisasi
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Kelola aplikasi yang menarik data pegawai melalui
                            API sinkronisasi beserta kredensial dan
                            kesehatannya.
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateDialog(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Konsumen
                    </Button>
                </div>

                {showOnboarding && (
                    <Card className="border-primary/20 shadow-xs">
                        <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-center md:gap-6 md:p-5">
                            <div className="flex min-w-0 flex-1 items-start gap-3">
                                <span
                                    aria-hidden="true"
                                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                >
                                    <ListChecks className="h-5 w-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-sm font-bold tracking-tight">
                                            Mulai sinkronisasi dalam 3 langkah
                                        </h2>
                                        <Badge variant="secondary">
                                            ±2 menit
                                        </Badge>
                                        <span className="tnum text-xs text-muted-foreground">
                                            {onboardingDoneCount} dari 3 selesai
                                        </span>
                                    </div>
                                    <div
                                        role="progressbar"
                                        aria-valuenow={onboardingDoneCount}
                                        aria-valuemin={0}
                                        aria-valuemax={3}
                                        aria-label="Kemajuan aktivasi sinkronisasi"
                                        className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            className="h-full rounded-full bg-primary transition-[width] duration-200"
                                            style={{
                                                width: `${(onboardingDoneCount / 3) * 100}%`,
                                            }}
                                        />
                                    </div>
                                    <ol className="mt-3 grid gap-2 md:grid-cols-3">
                                        {onboardingSteps.map((step, index) => (
                                            <li
                                                key={step.id}
                                                className="flex items-start gap-2.5 rounded-lg border bg-muted/40 p-2.5"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                                        step.done
                                                            ? 'bg-primary text-primary-foreground'
                                                            : index ===
                                                                onboardingDoneCount
                                                              ? 'border border-[color:var(--gold)] bg-card text-foreground'
                                                              : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {step.done ? (
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    ) : (
                                                        index + 1
                                                    )}
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block text-xs font-semibold">
                                                        {step.title}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                                                        {step.description}
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {!hasConsumer ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setShowCreateDialog(true)
                                        }
                                    >
                                        Daftarkan pertama
                                        <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                                    </Button>
                                ) : !hasTestSuccess ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setActiveTab('konsumen')}
                                    >
                                        Uji koneksi
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setActiveTab('dokumentasi')
                                        }
                                    >
                                        <BookOpen className="mr-1.5 h-3.5 w-3.5" />
                                        Lihat cara pull
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={dismissOnboarding}
                                >
                                    Lewati
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {statsCards.map((card) => (
                        <Card key={card.label} className="shadow-xs">
                            <CardContent className="flex items-center gap-3 p-4">
                                <card.icon
                                    className={`h-7 w-7 shrink-0 ${card.tone}`}
                                />
                                <div className="min-w-0">
                                    <p className="tnum text-xl font-black tracking-tight">
                                        {card.value}
                                    </p>
                                    <p className="truncate text-xs font-medium text-muted-foreground">
                                        {card.label}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="w-full justify-start md:w-auto">
                        <TabsTrigger value="konsumen">
                            Konsumen ({konsumen.length})
                        </TabsTrigger>
                        <TabsTrigger value="riwayat">
                            Riwayat Pull ({recentPulls.length})
                        </TabsTrigger>
                        <TabsTrigger value="dokumentasi">
                            <FileText className="mr-1.5 h-4 w-4" />
                            Dokumentasi
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="konsumen" className="mt-4">
                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Konsumen</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Pull Terakhir</TableHead>
                                            <TableHead>Uji Koneksi</TableHead>
                                            <TableHead className="text-right">
                                                Aksi
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {konsumen.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={5}
                                                    className="h-40 text-center"
                                                >
                                                    <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                                        <span
                                                            aria-hidden="true"
                                                            className="flex h-16 w-16 items-center justify-center rounded-2xl border bg-muted/50"
                                                        >
                                                            <svg
                                                                width="36"
                                                                height="36"
                                                                viewBox="0 0 36 36"
                                                                fill="none"
                                                                role="presentation"
                                                            >
                                                                <rect
                                                                    x="7"
                                                                    y="5"
                                                                    width="16"
                                                                    height="22"
                                                                    rx="2"
                                                                    stroke="var(--primary)"
                                                                    strokeWidth="1.8"
                                                                />
                                                                <rect
                                                                    x="11"
                                                                    y="9"
                                                                    width="16"
                                                                    height="22"
                                                                    rx="2"
                                                                    fill="var(--card)"
                                                                    stroke="var(--border)"
                                                                    strokeWidth="1.5"
                                                                />
                                                                <path
                                                                    d="M15 14h8M15 18h8M15 22h5"
                                                                    stroke="var(--muted-foreground)"
                                                                    strokeWidth="1.5"
                                                                    strokeLinecap="round"
                                                                />
                                                                <circle
                                                                    cx="24"
                                                                    cy="25"
                                                                    r="6"
                                                                    fill="var(--primary)"
                                                                    stroke="var(--gold)"
                                                                    strokeWidth="1.5"
                                                                />
                                                                <path
                                                                    d="M21.8 25.2l1.6 1.6 3-3.2"
                                                                    stroke="var(--primary-foreground)"
                                                                    strokeWidth="1.6"
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                />
                                                            </svg>
                                                        </span>
                                                        <div className="max-w-md">
                                                            <p className="text-sm font-bold tracking-tight">
                                                                Belum ada
                                                                aplikasi client
                                                                terdaftar
                                                            </p>
                                                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                                Di sini akan
                                                                tampil setiap
                                                                aplikasi yang
                                                                menarik data
                                                                pegawai lewat
                                                                API. Daftarkan
                                                                client pertama
                                                                agar token, uji
                                                                koneksi, dan
                                                                riwayat pull
                                                                terpantau dalam
                                                                satu buku
                                                                register.
                                                            </p>
                                                        </div>
                                                        <ol className="grid w-full max-w-lg gap-2 text-left md:grid-cols-3">
                                                            <li className="rounded-lg border bg-muted/40 p-2.5 text-xs">
                                                                <span className="font-bold">
                                                                    1. Daftarkan
                                                                </span>
                                                                <span className="mt-0.5 block leading-relaxed text-muted-foreground">
                                                                    Nama + slug,
                                                                    token terbit
                                                                    otomatis.
                                                                </span>
                                                            </li>
                                                            <li className="rounded-lg border bg-muted/40 p-2.5 text-xs">
                                                                <span className="font-bold">
                                                                    2. Salin
                                                                    token
                                                                </span>
                                                                <span className="mt-0.5 block leading-relaxed text-muted-foreground">
                                                                    Hanya tampil
                                                                    sekali,
                                                                    simpan di
                                                                    client.
                                                                </span>
                                                            </li>
                                                            <li className="rounded-lg border bg-muted/40 p-2.5 text-xs">
                                                                <span className="font-bold">
                                                                    3. Uji
                                                                    koneksi
                                                                </span>
                                                                <span className="mt-0.5 block leading-relaxed text-muted-foreground">
                                                                    Pastikan
                                                                    HMAC lolos
                                                                    sebelum
                                                                    pull.
                                                                </span>
                                                            </li>
                                                        </ol>
                                                        <div className="flex flex-wrap items-center justify-center gap-2">
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    setShowCreateDialog(
                                                                        true,
                                                                    )
                                                                }
                                                            >
                                                                <Plus className="mr-2 h-3.5 w-3.5" />
                                                                Tambah Konsumen
                                                                Pertama
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setActiveTab(
                                                                        'dokumentasi',
                                                                    )
                                                                }
                                                            >
                                                                <BookOpen className="mr-2 h-3.5 w-3.5" />
                                                                Lihat cara
                                                                menghubungkan
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            konsumen.map((consumer) => {
                                                const result =
                                                    testResult[consumer.slug];

                                                return (
                                                    <TableRow key={consumer.id}>
                                                        <TableCell>
                                                            <div className="flex flex-col gap-0.5">
                                                                <span className="flex items-center gap-2 font-semibold">
                                                                    {
                                                                        consumer.nama
                                                                    }
                                                                    {consumer.base_url && (
                                                                        <a
                                                                            href={
                                                                                consumer.base_url
                                                                            }
                                                                            target="_blank"
                                                                            rel="noreferrer"
                                                                            className="text-muted-foreground hover:text-primary"
                                                                            aria-label={`Buka ${consumer.base_url}`}
                                                                        >
                                                                            <ExternalLink className="h-3.5 w-3.5" />
                                                                        </a>
                                                                    )}
                                                                </span>
                                                                <span className="font-mono text-xs text-muted-foreground">
                                                                    {
                                                                        consumer.slug
                                                                    }
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant={
                                                                    consumer.is_active
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {consumer.is_active
                                                                    ? 'Aktif'
                                                                    : 'Nonaktif'}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-col gap-0.5">
                                                                <span className="text-sm">
                                                                    {timeAgo(
                                                                        consumer.last_pull_at,
                                                                    )}
                                                                </span>
                                                                {consumer.last_pull_at && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {formatNumber(
                                                                            consumer.last_pull_rows,
                                                                        )}{' '}
                                                                        baris
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div
                                                                className="flex max-w-55 flex-col gap-1"
                                                                aria-live="polite"
                                                            >
                                                                {testingSlug ===
                                                                consumer.slug ? (
                                                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                        <RefreshCcw className="h-3.5 w-3.5 animate-spin" />
                                                                        Menyiapkan
                                                                        signature
                                                                        HMAC →
                                                                        menghubungi
                                                                        endpoint…
                                                                    </span>
                                                                ) : result ? (
                                                                    <>
                                                                        <span
                                                                            className={`flex items-center gap-1.5 text-xs font-medium ${result.success ? 'text-emerald-600' : 'text-destructive'}`}
                                                                        >
                                                                            {result.success ? (
                                                                                <CheckCircle2 className="h-3.5 w-3.5 shrink-0" />
                                                                            ) : (
                                                                                <XCircle className="h-3.5 w-3.5 shrink-0" />
                                                                            )}
                                                                            {result.success
                                                                                ? 'Tersambung — disahkan'
                                                                                : 'Gagal tersambung'}
                                                                        </span>
                                                                        <span
                                                                            title={
                                                                                result.message
                                                                            }
                                                                            className="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                                                        >
                                                                            {
                                                                                result.message
                                                                            }
                                                                        </span>
                                                                    </>
                                                                ) : consumer.last_connection_test_status ? (
                                                                    <>
                                                                        <span
                                                                            className={`flex items-center gap-1.5 text-xs font-medium ${consumer.last_connection_test_status === 'success' ? 'text-emerald-600' : 'text-destructive'}`}
                                                                        >
                                                                            {consumer.last_connection_test_status ===
                                                                            'success' ? (
                                                                                <CheckCircle2 className="h-3.5 w-3.5 shrink-0" />
                                                                            ) : (
                                                                                <XCircle className="h-3.5 w-3.5 shrink-0" />
                                                                            )}
                                                                            {consumer.last_connection_test_status ===
                                                                            'success'
                                                                                ? 'Tersambung — disahkan'
                                                                                : 'Uji terakhir gagal'}
                                                                        </span>
                                                                        {consumer.last_connection_test_message && (
                                                                            <span
                                                                                title={
                                                                                    consumer.last_connection_test_message
                                                                                }
                                                                                className="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                                                            >
                                                                                {
                                                                                    consumer.last_connection_test_message
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </>
                                                                ) : (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        Belum
                                                                        diuji —
                                                                        uji dulu
                                                                        sebelum
                                                                        pull
                                                                        perdana.
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleTestConnection(
                                                                            consumer,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        testingSlug ===
                                                                        consumer.slug
                                                                    }
                                                                    title="Uji koneksi"
                                                                >
                                                                    <RefreshCcw className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Uji
                                                                        koneksi
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleRegenerateToken(
                                                                            consumer,
                                                                        )
                                                                    }
                                                                    title="Terbitkan token baru (HMAC secret tidak berubah)"
                                                                >
                                                                    <KeyRound className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Terbitkan
                                                                        token
                                                                        baru
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleRegenerateSecret(
                                                                            consumer,
                                                                        )
                                                                    }
                                                                    title="Putar HMAC secret baru (token tidak berubah)"
                                                                >
                                                                    <LockKeyhole className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Putar
                                                                        HMAC
                                                                        secret
                                                                        baru
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => {
                                                                        setEditTarget(
                                                                            consumer,
                                                                        );
                                                                        editForm.setData(
                                                                            {
                                                                                nama: consumer.nama,
                                                                                slug: consumer.slug,
                                                                                base_url:
                                                                                    consumer.base_url ??
                                                                                    '',
                                                                                deskripsi:
                                                                                    consumer.deskripsi ??
                                                                                    '',
                                                                                is_active:
                                                                                    consumer.is_active,
                                                                            },
                                                                        );
                                                                    }}
                                                                    title="Edit"
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Edit
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        setDeleteTarget(
                                                                            consumer,
                                                                        )
                                                                    }
                                                                    title="Hapus"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                    <span className="sr-only">
                                                                        Hapus
                                                                    </span>
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="riwayat" className="mt-4">
                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Waktu</TableHead>
                                            <TableHead>Konsumen</TableHead>
                                            <TableHead>Baris</TableHead>
                                            <TableHead>Halaman</TableHead>
                                            <TableHead>Durasi</TableHead>
                                            <TableHead>Token</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recentPulls.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={6}
                                                    className="h-40 text-center text-muted-foreground"
                                                >
                                                    <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                                        <span
                                                            aria-hidden="true"
                                                            className="flex h-12 w-12 items-center justify-center rounded-full border bg-muted/50"
                                                        >
                                                            <Clock className="h-6 w-6 text-muted-foreground" />
                                                        </span>
                                                        <div className="max-w-md">
                                                            <p className="text-sm font-bold tracking-tight text-foreground">
                                                                Buku register
                                                                masih kosong
                                                            </p>
                                                            <p className="mt-1 text-xs leading-relaxed">
                                                                Setiap konsumen
                                                                yang memanggil{' '}
                                                                <code className="font-mono text-foreground">
                                                                    GET
                                                                    /api/v1/pegawai/sync
                                                                </code>{' '}
                                                                tercatat di sini
                                                                — waktu, jumlah
                                                                baris, halaman,
                                                                dan durasi.
                                                                Gunakan sebagai
                                                                bukti bahwa pull
                                                                perdana sudah
                                                                berjalan.
                                                            </p>
                                                        </div>
                                                        {konsumen.length > 0 ? (
                                                            <p className="max-w-md text-xs leading-relaxed">
                                                                Langkah
                                                                berikutnya: uji
                                                                koneksi salah
                                                                satu konsumen,
                                                                lalu jalankan
                                                                pull pertama
                                                                dari aplikasi
                                                                client. Simpan{' '}
                                                                <code className="font-mono text-foreground">
                                                                    synced_at
                                                                </code>{' '}
                                                                sebagai{' '}
                                                                <code className="font-mono text-foreground">
                                                                    since
                                                                </code>{' '}
                                                                untuk pull
                                                                delta.
                                                            </p>
                                                        ) : (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setShowCreateDialog(
                                                                        true,
                                                                    )
                                                                }
                                                            >
                                                                <Plus className="mr-2 h-3.5 w-3.5" />
                                                                Daftarkan
                                                                konsumen dulu
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            recentPulls.map((pull) => (
                                                <TableRow key={pull.id}>
                                                    <TableCell>
                                                        <span className="text-sm">
                                                            {new Date(
                                                                pull.pulled_at,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        {pull.consumer ? (
                                                            <span className="font-medium">
                                                                {
                                                                    pull
                                                                        .consumer
                                                                        .nama
                                                                }
                                                            </span>
                                                        ) : (
                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                {pull.token_name ??
                                                                    'tidak teridentifikasi'}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="font-mono">
                                                            {formatNumber(
                                                                pull.rows_returned,
                                                            )}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="font-mono">
                                                            {pull.page}/
                                                            {pull.per_page}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="font-mono">
                                                            {pull.duration_ms}{' '}
                                                            ms
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            {pull.token_name ??
                                                                '—'}
                                                        </span>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="dokumentasi" className="mt-4">
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardContent className="flex flex-col gap-3 p-5">
                                    <h3 className="flex items-center gap-2 text-sm font-bold">
                                        <ExternalLink className="h-4 w-4 text-primary" />
                                        Endpoint
                                    </h3>
                                    <div className="rounded-lg border bg-muted/50 p-3">
                                        <code className="text-sm">
                                            GET /api/v1/pegawai/sync
                                        </code>
                                    </div>
                                    <p className="text-xs leading-relaxed text-muted-foreground">
                                        Ekspor data pegawai terpaginasi.
                                        Parameter:{' '}
                                        <code className="text-foreground">
                                            page
                                        </code>
                                        ,{' '}
                                        <code className="text-foreground">
                                            per_page
                                        </code>{' '}
                                        (max 500),{' '}
                                        <code className="text-foreground">
                                            since
                                        </code>{' '}
                                        (pull delta via updated_at).
                                    </p>
                                    <p className="rounded-lg border bg-muted/40 p-2.5 text-xs leading-relaxed text-muted-foreground">
                                        Alur yang disarankan: pull penuh halaman
                                        1…n, simpan{' '}
                                        <code className="font-mono text-foreground">
                                            synced_at
                                        </code>
                                        , lalu pull berikutnya cukup kirim{' '}
                                        <code className="font-mono text-foreground">
                                            ?since=synced_at
                                        </code>{' '}
                                        untuk delta.
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="flex flex-col gap-3 p-5">
                                    <h3 className="flex items-center gap-2 text-sm font-bold">
                                        <ShieldCheck className="h-4 w-4 text-primary" />
                                        Autentikasi
                                    </h3>
                                    <ul className="flex flex-col gap-2 text-xs leading-relaxed text-muted-foreground">
                                        <li>
                                            <Badge
                                                variant="outline"
                                                className="mr-2"
                                            >
                                                Bearer
                                            </Badge>
                                            Sanctum token konsumen ({' '}
                                            <code className="text-foreground">
                                                Authorization: Bearer
                                            </code>{' '}
                                            )
                                        </li>
                                        <li>
                                            <Badge
                                                variant="outline"
                                                className="mr-2"
                                            >
                                                X-Timestamp
                                            </Badge>
                                            epoch second (±5 menit, anti-replay)
                                        </li>
                                        <li>
                                            <Badge
                                                variant="outline"
                                                className="mr-2"
                                            >
                                                X-Signature
                                            </Badge>
                                            HMAC-SHA256 dari string kanonik di
                                            bawah, memakai secret unik per
                                            konsumen (putar via ikon gembok di
                                            tabel)
                                        </li>
                                    </ul>
                                </CardContent>
                            </Card>

                            <Card className="lg:col-span-2">
                                <CardContent className="flex flex-col gap-3 p-5">
                                    <div className="flex items-center justify-between">
                                        <h3 className="flex items-center gap-2 text-sm font-bold">
                                            <Code2 className="h-4 w-4 text-primary" />
                                            String Kanonik
                                        </h3>
                                        <CopyableCodeBlock
                                            text={CANONICAL_EXAMPLE}
                                        />
                                    </div>
                                    <div className="rounded-lg border bg-muted/50 p-4">
                                        <pre className="overflow-x-auto text-xs leading-relaxed">
                                            <code>{CANONICAL_EXAMPLE}</code>
                                        </pre>
                                    </div>
                                    <h3 className="mt-2 flex items-center gap-2 text-sm font-bold">
                                        <Code2 className="h-4 w-4 text-primary" />
                                        Contoh Panggilan
                                    </h3>
                                    <CopyableCodeBlock text={CURL_EXAMPLE} />
                                    <div className="rounded-lg border bg-muted/50 p-4">
                                        <pre className="overflow-x-auto text-xs leading-relaxed">
                                            <code>{CURL_EXAMPLE}</code>
                                        </pre>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Modal tambah konsumen */}
            <Dialog
                open={showCreateDialog}
                onOpenChange={(open) => !open && setShowCreateDialog(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Konsumen Baru</DialogTitle>
                        <DialogDescription>
                            Daftarkan aplikasi client yang akan menarik data
                            pegawai. Token API otomatis diterbitkan dan hanya
                            ditampilkan sekali.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleStore();
                        }}
                        className="flex flex-col gap-4"
                    >
                        {createForm.errors && (
                            <AlertError
                                errors={errorsToArray(createForm.errors)}
                            />
                        )}
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="nama">Nama Konsumen</Label>
                            <Input
                                id="nama"
                                value={createForm.data.nama}
                                onChange={(e) =>
                                    createForm.setData('nama', e.target.value)
                                }
                                placeholder="mis. WFA Task"
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                value={createForm.data.slug}
                                onChange={(e) =>
                                    createForm.setData('slug', e.target.value)
                                }
                                placeholder="mis. wfa-task"
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="base_url">
                                Base URL (opsional)
                            </Label>
                            <Input
                                id="base_url"
                                type="url"
                                value={createForm.data.base_url}
                                onChange={(e) =>
                                    createForm.setData(
                                        'base_url',
                                        e.target.value,
                                    )
                                }
                                placeholder="https://wfa-task.test"
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="deskripsi">
                                Deskripsi (opsional)
                            </Label>
                            <Textarea
                                id="deskripsi"
                                value={createForm.data.deskripsi}
                                onChange={(e) =>
                                    createForm.setData(
                                        'deskripsi',
                                        e.target.value,
                                    )
                                }
                                placeholder="Keterangan singkat"
                                rows={3}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowCreateDialog(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                {createForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan & Terbitkan Token'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal edit konsumen */}
            <Dialog
                open={!!editTarget}
                onOpenChange={(open) => !open && setEditTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Konsumen</DialogTitle>
                        <DialogDescription>
                            Ubah metadata konsumen. Untuk kredensial, gunakan
                            aksi token di tabel.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleUpdate();
                        }}
                        className="flex flex-col gap-4"
                    >
                        {editForm.errors && (
                            <AlertError
                                errors={errorsToArray(editForm.errors)}
                            />
                        )}
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="edit_nama">Nama Konsumen</Label>
                            <Input
                                id="edit_nama"
                                value={editForm.data.nama}
                                onChange={(e) =>
                                    editForm.setData('nama', e.target.value)
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="edit_slug">Slug</Label>
                            <Input
                                id="edit_slug"
                                value={editForm.data.slug}
                                onChange={(e) =>
                                    editForm.setData('slug', e.target.value)
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="edit_base_url">Base URL</Label>
                            <Input
                                id="edit_base_url"
                                type="url"
                                value={editForm.data.base_url}
                                onChange={(e) =>
                                    editForm.setData('base_url', e.target.value)
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="edit_deskripsi">Deskripsi</Label>
                            <Textarea
                                id="edit_deskripsi"
                                value={editForm.data.deskripsi}
                                onChange={(e) =>
                                    editForm.setData(
                                        'deskripsi',
                                        e.target.value,
                                    )
                                }
                                rows={3}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditTarget(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                {editForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal kredensial sekali tampil (remount tiap penerbitan) */}
            <SyncTokenModal
                key={
                    tokenModal
                        ? `${tokenModal.consumer_slug}:${tokenModal.plaintext}:${tokenModal.hmac_secret}`
                        : 'none'
                }
                token={tokenModal}
                onClose={() => setTokenModal(null)}
            />

            {/* Konfirmasi hapus */}
            <ConfirmDeleteDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Hapus Konsumen"
                description="Apakah Anda yakin ingin menghapus konsumen ini? Token API yang dimilikinya akan kehilangan akses ke endpoint sinkronisasi."
                itemName={deleteTarget?.nama}
                onConfirm={handleDelete}
                processing={false}
            />
        </AppLayout>
    );
}

function CopyableCodeBlock({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        await navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={handleCopy}
            className="h-7 gap-1.5 text-xs"
        >
            {copied ? (
                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
            ) : (
                <Copy className="h-3.5 w-3.5" />
            )}
            {copied ? 'Tersalin' : 'Salin'}
        </Button>
    );
}
