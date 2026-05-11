import { Head, router, usePage } from '@inertiajs/react';
import { Inbox as InboxIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { toUrl } from '@/lib/utils';
import {
    inbox as inboxRoute,
    kirimBiro,
    mintaPerbaikan,
    tandaTanganKetua,
    tolak,
    verifikasiKasubbag,
    verifikasiSekretaris,
} from '@/routes/kenaikan-pangkat/approval';
import type { Auth, BreadcrumbItem } from '@/types';
import type { KepegawaianPaginatedData } from '@/types/kepegawaian';

type ApprovalRole = 'kasubbag' | 'sekretaris' | 'ketua' | 'biro';
type ApprovalAction = 'approve' | 'revise' | 'reject';

type PegawaiSummary = {
    id: string;
    nip: string | null;
    nama_lengkap: string;
};

type PangkatSummary = {
    id: string;
    kode: string | null;
    nama: string | null;
};

type UsulanKenaikanPangkat = {
    id: string;
    nomor_usulan?: string | null;
    state: string;
    submitted_at: string | null;
    pegawai?: PegawaiSummary | null;
    pangkat_asal?: PangkatSummary | null;
    pangkatAsal?: PangkatSummary | null;
    pangkat_tujuan?: PangkatSummary | null;
    pangkatTujuan?: PangkatSummary | null;
};

type Props = {
    usulan: KepegawaianPaginatedData<UsulanKenaikanPangkat>;
    current_role: ApprovalRole | null;
};

type PageProps = {
    auth: Auth;
    flash?: {
        success?: string;
    };
};

type TabConfig = {
    role: ApprovalRole;
    permission: string;
    state: string;
    label: string;
    approveLabel: string;
};

type SelectedAction = {
    usulan: UsulanKenaikanPangkat;
    tab: TabConfig;
    action: ApprovalAction;
};

const tabs: TabConfig[] = [
    {
        role: 'kasubbag',
        permission: 'kenaikan-pangkat.usulan.verifikasi-kasubbag',
        state: 'DIAJUKAN',
        label: 'Verifikasi Kasubbag',
        approveLabel: 'Setuju',
    },
    {
        role: 'sekretaris',
        permission: 'kenaikan-pangkat.usulan.verifikasi-sekretaris',
        state: 'DIVERIFIKASI_KASUBBAG',
        label: 'Verifikasi Sekretaris',
        approveLabel: 'Setuju',
    },
    {
        role: 'ketua',
        permission: 'kenaikan-pangkat.usulan.tanda-tangan-ketua',
        state: 'DIVERIFIKASI_SEKRETARIS',
        label: 'Tanda Tangan Ketua',
        approveLabel: 'Setuju',
    },
    {
        role: 'biro',
        permission: 'kenaikan-pangkat.usulan.kirim-biro',
        state: 'DITANDATANGANI_KETUA',
        label: 'Kirim Biro',
        approveLabel: 'Setuju',
    },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kenaikan Pangkat', href: '/kenaikan-pangkat' },
    { title: 'Inbox Approval', href: toUrl(inboxRoute()) },
];

function formatTanggal(tanggal: string | null): string {
    if (!tanggal) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(tanggal));
}

function resolvePangkat(item: UsulanKenaikanPangkat): string {
    const asal = item.pangkat_asal ?? item.pangkatAsal;
    const tujuan = item.pangkat_tujuan ?? item.pangkatTujuan;

    return `${asal?.kode ?? asal?.nama ?? '-'} → ${tujuan?.kode ?? tujuan?.nama ?? '-'}`;
}

function actionTitle(action: ApprovalAction): string {
    return {
        approve: 'Setujui Usulan',
        revise: 'Minta Perbaikan',
        reject: 'Tolak Usulan',
    }[action];
}

function submitUrl(selected: SelectedAction): string {
    if (selected.action === 'revise') {
        return mintaPerbaikan.url(selected.usulan.id);
    }

    if (selected.action === 'reject') {
        return tolak.url(selected.usulan.id);
    }

    if (selected.tab.role === 'kasubbag') {
        return verifikasiKasubbag.url(selected.usulan.id);
    }

    if (selected.tab.role === 'sekretaris') {
        return verifikasiSekretaris.url(selected.usulan.id);
    }

    if (selected.tab.role === 'ketua') {
        return tandaTanganKetua.url(selected.usulan.id);
    }

    return kirimBiro.url(selected.usulan.id);
}

function submitPayload(selected: SelectedAction, catatan: string): Record<string, string | boolean> {
    if (selected.action === 'reject') {
        return { alasan: catatan };
    }

    if (selected.action === 'revise') {
        return { catatan };
    }

    if (selected.tab.role === 'kasubbag' || selected.tab.role === 'sekretaris') {
        return { setuju: true, catatan };
    }

    if (selected.tab.role === 'biro') {
        return { catatan };
    }

    return {};
}

export default function ApprovalInboxPage({ usulan, current_role }: Props) {
    const { auth, flash } = usePage<PageProps>().props;
    const permissions = auth.user.permissions ?? [];
    const visibleTabs = tabs.filter((tab) => permissions.includes(tab.permission));
    const defaultTab = current_role ?? visibleTabs[0]?.role ?? 'kasubbag';
    const [activeTab, setActiveTab] = useState<ApprovalRole>(defaultTab);
    const [selected, setSelected] = useState<SelectedAction | null>(null);
    const [catatan, setCatatan] = useState('');
    const [processing, setProcessing] = useState(false);
    const [successMessage, setSuccessMessage] = useState(flash?.success ?? '');

    const itemsByState = useMemo(() => {
        return visibleTabs.reduce<Record<string, UsulanKenaikanPangkat[]>>((result, tab) => {
            result[tab.state] = usulan.data.filter((item) => item.state === tab.state);

            return result;
        }, {});
    }, [usulan.data, visibleTabs]);

    function openAction(usulanItem: UsulanKenaikanPangkat, tab: TabConfig, action: ApprovalAction): void {
        setSelected({ usulan: usulanItem, tab, action });
        setCatatan('');
    }

    function closeAction(): void {
        setSelected(null);
        setCatatan('');
        setProcessing(false);
    }

    const handleSubmit: React.ComponentProps<'form'>['onSubmit'] = (event) => {
        event.preventDefault();

        if (!selected) {
            return;
        }

        if ((selected.action === 'revise' || selected.action === 'reject') && catatan.trim() === '') {
            return;
        }

        setProcessing(true);
        router.post(submitUrl(selected), submitPayload(selected, catatan), {
            only: ['usulan', 'current_role', 'flash'],
            preserveScroll: true,
            onSuccess: () => {
                setSuccessMessage('Aksi approval berhasil diproses.');
                closeAction();
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inbox Approval Kenaikan Pangkat" />

            <div className="flex flex-1 flex-col gap-6 bg-background p-4 md:p-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Inbox Approval Kenaikan Pangkat
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Daftar usulan kenaikan pangkat yang menunggu tindakan sesuai kewenangan Anda.
                    </p>
                    {successMessage && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300">
                            {successMessage}
                        </div>
                    )}
                </div>

                {visibleTabs.length > 0 ? (
                    <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as ApprovalRole)}>
                        <TabsList className="flex h-auto w-full flex-wrap justify-start gap-2 p-1">
                            {visibleTabs.map((tab) => (
                                <TabsTrigger key={tab.role} value={tab.role} className="grow-0">
                                    {tab.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {visibleTabs.map((tab) => {
                            const items = itemsByState[tab.state] ?? [];

                            return (
                                <TabsContent key={tab.role} value={tab.role} className="mt-4">
                                    {items.length > 0 ? (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Pegawai</TableHead>
                                                    <TableHead>Pangkat</TableHead>
                                                    <TableHead>Tanggal Diajukan</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead className="text-right">Aksi</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {items.map((item) => (
                                                    <TableRow key={item.id}>
                                                        <TableCell>
                                                            <div className="flex flex-col gap-1">
                                                                <span className="font-medium">
                                                                    {item.pegawai?.nama_lengkap ?? '-'}
                                                                </span>
                                                                <span className="text-xs text-muted-foreground">
                                                                    {item.pegawai?.nip ?? '-'}
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>{resolvePangkat(item)}</TableCell>
                                                        <TableCell>{formatTanggal(item.submitted_at)}</TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline">{tab.label}</Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-wrap justify-end gap-2">
                                                                <Button
                                                                    size="sm"
                                                                    className="bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                                                                    onClick={() => openAction(item, tab, 'approve')}
                                                                >
                                                                    {tab.approveLabel}
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    className="bg-amber-400 text-amber-950 hover:bg-amber-500 dark:bg-amber-500 dark:text-amber-950 dark:hover:bg-amber-400"
                                                                    onClick={() => openAction(item, tab, 'revise')}
                                                                >
                                                                    Perlu Perbaikan
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    onClick={() => openAction(item, tab, 'reject')}
                                                                >
                                                                    Tolak
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-dashed p-12 text-center dark:border-border">
                                            <InboxIcon className="h-12 w-12 text-muted-foreground/50" />
                                            <div>
                                                <p className="font-medium text-muted-foreground">
                                                    Tidak ada usulan pada tahap {tab.label}.
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground/70">
                                                    Semua usulan pada tahap ini sudah ditindaklanjuti.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                ) : (
                    <div className="flex flex-col items-center justify-center gap-4 rounded-lg border border-dashed p-12 text-center dark:border-border">
                        <InboxIcon className="h-12 w-12 text-muted-foreground/50" />
                        <div>
                            <p className="font-medium text-muted-foreground">
                                Anda tidak memiliki permission approval kenaikan pangkat.
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground/70">
                                Tab approval hanya ditampilkan sesuai permission user.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <Dialog open={selected !== null} onOpenChange={(open) => !open && closeAction()}>
                <DialogContent>
                    {selected && (
                        <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
                            <DialogHeader>
                                <DialogTitle>{actionTitle(selected.action)}</DialogTitle>
                                <DialogDescription>
                                    Konfirmasi aksi untuk usulan {selected.usulan.pegawai?.nama_lengkap ?? '-'}.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2">
                                <label className="text-sm font-medium" htmlFor="catatan">
                                    Catatan{selected.action === 'revise' || selected.action === 'reject' ? ' *' : ''}
                                </label>
                                <Textarea
                                    id="catatan"
                                    name={selected.action === 'reject' ? 'alasan' : 'catatan'}
                                    value={catatan}
                                    onChange={(event) => setCatatan(event.target.value)}
                                    required={selected.action === 'revise' || selected.action === 'reject'}
                                    placeholder={
                                        selected.action === 'approve'
                                            ? 'Catatan opsional'
                                            : 'Tuliskan catatan yang perlu ditindaklanjuti'
                                    }
                                />
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={closeAction} disabled={processing}>
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    processing={processing}
                                    className={
                                        selected.action === 'approve'
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600'
                                            : selected.action === 'revise'
                                              ? 'bg-amber-400 text-amber-950 hover:bg-amber-500 dark:bg-amber-500 dark:text-amber-950 dark:hover:bg-amber-400'
                                              : undefined
                                    }
                                    variant={selected.action === 'reject' ? 'destructive' : 'default'}
                                >
                                    {selected.action === 'approve'
                                        ? 'Setuju'
                                        : selected.action === 'revise'
                                          ? 'Perlu Perbaikan'
                                          : 'Tolak'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
