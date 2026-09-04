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
import type { BreadcrumbItem, IamPaginatedData } from '@/types';

type UsulanRow = {
    id: string;
    pegawai: { nip: string | null; nama_lengkap: string };
    pangkat_asal: { nama: string; kode: string } | null;
    pangkat_tujuan: { nama: string; kode: string } | null;
    periode_bulan: number;
    periode_tahun: number;
    state: string;
    checklist_progress?: {
        completed: number;
        total: number;
        percentage: number;
    } | null;
};
type Props = {
    usulanList: IamPaginatedData<UsulanRow>;
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
const stateClasses = [
    'border-slate-200 bg-slate-100 text-slate-700',
    'border-blue-200 bg-blue-50 text-blue-700',
    'border-amber-200 bg-amber-50 text-amber-700',
    'border-emerald-200 bg-emerald-50 text-emerald-700',
];

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
                <Card className="border-2 border-foreground bg-card shadow-[4px_4px_0_rgba(0,0,0,1)]">
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
                <div className="overflow-hidden rounded-xl border-2 border-foreground bg-background shadow-[4px_4px_0_rgba(0,0,0,1)]">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Pegawai</TableHead>
                                <TableHead>Pangkat</TableHead>
                                <TableHead>Periode</TableHead>
                                <TableHead>State</TableHead>
                                <TableHead>Progres checklist</TableHead>
                                <TableHead className="text-right">
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
                                usulanList.data.map((usulan, indexRow) => {
                                    const progress = usulan.checklist_progress;

                                    return (
                                        <TableRow key={usulan.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {
                                                        usulan.pegawai
                                                            .nama_lengkap
                                                    }
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {usulan.pegawai.nip ?? '-'}
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
                                                {usulan.periode_bulan}/
                                                {usulan.periode_tahun}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        stateClasses[
                                                            indexRow %
                                                                stateClasses.length
                                                        ]
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
                                                                className="h-2 rounded-full bg-emerald-500"
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
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>
                <PaginationWrapper meta={usulanList.meta} />
            </div>
        </AppLayout>
    );
}
