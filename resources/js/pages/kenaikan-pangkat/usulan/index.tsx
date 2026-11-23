import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { create, edit, index, show } from '@/routes/kenaikan-pangkat/usulan';
import type { BreadcrumbItem, KepegawaianPaginatedData } from '@/types';

type UsulanRow = {
    id: string;
    pegawai: { nip: string | null; nama_lengkap: string } | null;
    pangkat_asal: { nama: string; kode: string } | null;
    pangkat_tujuan: { nama: string; kode: string } | null;
    periode_usul_bulan?: number;
    periode_usul_tahun?: number;
    periode_bulan?: number;
    periode_tahun?: number;
    state: string;
    checklist_progress?: {
        completed: number;
        total: number;
        percentage: number;
    } | null;
};
type Props = {
    usulanList: KepegawaianPaginatedData<UsulanRow>;
    filters: {
        state?: string[] | string | null;
        bulan?: number | null;
        tahun?: number | null;
        search?: string | null;
    };
    stateLabels: Record<string, string>;
    bulanOptions: { value: number; label: string }[];
    tahunOptions: number[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Usulan Kenaikan Pangkat', href: index() },
];
const STATE_BADGE_CLASSES: Record<string, string> = {
    DRAFT: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
    DIAJUKAN:
        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/60 dark:text-blue-200',
    DIVERIFIKASI_KASUBBAG:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-200',
    DIVERIFIKASI_SEKRETARIS:
        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200',
    DITANDATANGANI_KETUA:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-200',
    DIKIRIM_BIRO:
        'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-200',
    MENUNGGU_SK:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
    SELESAI_SK_TERBIT:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200',
    PERLU_PERBAIKAN:
        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-950/60 dark:text-orange-200',
    DITOLAK:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/60 dark:text-red-200',
    DIBATALKAN:
        'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
};

function normalizeState(value: Props['filters']['state']): string {
    if (Array.isArray(value)) {
        return value.join(',');
    }

    return value ?? '';
}

export default function UsulanKenaikanPangkatIndex({
    usulanList,
    filters,
    stateLabels,
    bulanOptions,
    tahunOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [state, setState] = useState(normalizeState(filters.state));
    const [bulan, setBulan] = useState(filters.bulan?.toString() ?? '');
    const [tahun, setTahun] = useState(filters.tahun?.toString() ?? '');

    function applyFilter(changes: Record<string, string | null>) {
        const resolved = { search, state, bulan, tahun, ...changes };
        const params = Object.fromEntries(
            Object.entries(resolved).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );
        router.get(index.url(), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    useEffect(() => {
        if (search.trim() === (filters.search ?? '').trim()) {
            return undefined;
        }

        const timeoutId = window.setTimeout(
            () => applyFilter({ search: search.trim() || null }),
            300,
        );

        return () => window.clearTimeout(timeoutId);
        // applyFilter & filters.search sengaja tidak dimasukkan ke deps agar debounce tidak di-reset setiap render
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usulan Kenaikan Pangkat" />
            <div className="flex flex-1 flex-col gap-6 bg-background p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Usulan Kenaikan Pangkat
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola draft, progres, dan status usulan KP.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="mr-2 h-4 w-4" />
                            Buat Usulan
                        </Link>
                    </Button>
                </div>
                <Card className="py-0">
                    <CardContent className="grid gap-4 p-4 md:grid-cols-4">
                        <div className="relative md:col-span-1">
                            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari pegawai..."
                                className="pl-9"
                            />
                        </div>
                        <Select
                            value={state || 'semua'}
                            onValueChange={(value) => {
                                const next = value === 'semua' ? '' : value;
                                setState(next);
                                applyFilter({ state: next });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Filter State" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="semua">
                                    Semua State
                                </SelectItem>
                                {Object.entries(stateLabels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <Select
                            value={bulan || 'semua'}
                            onValueChange={(value) => {
                                const next = value === 'semua' ? '' : value;
                                setBulan(next);
                                applyFilter({ bulan: next });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Periode bulan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="semua">
                                    Semua Bulan
                                </SelectItem>
                                {bulanOptions.map((item) => (
                                    <SelectItem
                                        key={item.value}
                                        value={item.value.toString()}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={tahun || 'semua'}
                            onValueChange={(value) => {
                                const next = value === 'semua' ? '' : value;
                                setTahun(next);
                                applyFilter({ tahun: next });
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Periode tahun" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="semua">
                                    Semua Tahun
                                </SelectItem>
                                {tahunOptions.map((item) => (
                                    <SelectItem
                                        key={item}
                                        value={item.toString()}
                                    >
                                        {item}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/50 hover:bg-muted/50">
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Pegawai
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Pangkat
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Periode
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    State
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Progres checklist
                                </TableHead>
                                <TableHead className="text-right text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {usulanList.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        Belum ada usulan KP sesuai filter.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                usulanList.data.map((usulan) => {
                                    const progress = usulan.checklist_progress;
                                    const periodeBulan =
                                        usulan.periode_usul_bulan ??
                                        usulan.periode_bulan ??
                                        '-';
                                    const periodeTahun =
                                        usulan.periode_usul_tahun ??
                                        usulan.periode_tahun ??
                                        '-';
                                    const canEdit = [
                                        'DRAFT',
                                        'PERLU_PERBAIKAN',
                                    ].includes(usulan.state);

                                    return (
                                        <TableRow key={usulan.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {usulan.pegawai
                                                        ?.nama_lengkap ?? '-'}
                                                </div>
                                                <div className="tnum text-sm text-muted-foreground">
                                                    {usulan.pegawai?.nip ?? '-'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {usulan.pangkat_asal
                                                        ? `${usulan.pangkat_asal.nama} (${usulan.pangkat_asal.kode})`
                                                        : '-'}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    →{' '}
                                                    {usulan.pangkat_tujuan
                                                        ? `${usulan.pangkat_tujuan.nama} (${usulan.pangkat_tujuan.kode})`
                                                        : '-'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {periodeBulan}/{periodeTahun}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        STATE_BADGE_CLASSES[
                                                            usulan.state
                                                        ] ??
                                                        STATE_BADGE_CLASSES.DRAFT
                                                    }
                                                >
                                                    {stateLabels[
                                                        usulan.state
                                                    ] ?? usulan.state}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {progress ? (
                                                    <div className="min-w-32">
                                                        <div className="text-sm font-medium">
                                                            {progress.completed}
                                                            /{progress.total}{' '}
                                                            berkas
                                                        </div>
                                                        <div className="mt-1 h-2 rounded-full bg-muted">
                                                            <div
                                                                className="h-2 rounded-full bg-emerald-600 dark:bg-emerald-500"
                                                                style={{
                                                                    width: `${progress.percentage}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                ) : (
                                                    '-'
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        size="icon"
                                                        variant="ghost"
                                                    >
                                                        <Link
                                                            href={show(
                                                                usulan.id,
                                                            )}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Lihat
                                                            </span>
                                                        </Link>
                                                    </Button>
                                                    {canEdit && (
                                                        <Button
                                                            asChild
                                                            size="icon"
                                                            variant="ghost"
                                                        >
                                                            <Link
                                                                href={edit(
                                                                    usulan.id,
                                                                )}
                                                            >
                                                                <Pencil className="h-4 w-4" />
                                                                <span className="sr-only">
                                                                    Edit
                                                                </span>
                                                            </Link>
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>
                <PaginationWrapper
                    links={usulanList.links}
                    lastPage={usulanList.last_page}
                />
            </div>
        </AppLayout>
    );
}
