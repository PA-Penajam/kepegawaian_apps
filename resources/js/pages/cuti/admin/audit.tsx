import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useCallback, useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';
import { formatTanggalDateTime } from '@/lib/cuti-utils';
import adminCuti from '@/routes/admin/cuti';
import type { BreadcrumbItem } from '@/types';
import type { ActivityLogEntry } from '@/types/cuti';
import type { KepegawaianPaginatedData } from '@/types/kepegawaian';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cuti', href: '#' },
    { title: 'Audit Log', href: adminCuti.audit.index().url },
];

type Props = {
    activities: KepegawaianPaginatedData<ActivityLogEntry>;
    filters: { from?: string; to?: string; aktor?: string };
};

export default function CutiAuditIndex({ activities, filters: initialFilters }: Props) {
    const [filters, setFilters] = useState({
        from: initialFilters.from ?? '',
        to: initialFilters.to ?? '',
        aktor: initialFilters.aktor ?? '',
    });

    // Baris yang diperluas untuk melihat detail perubahan
    const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

    const applyFilters = useCallback(() => {
        router.get(
            adminCuti.audit.index(),
            Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')),
            { preserveState: true, replace: true },
        );
    }, [filters]);

    function toggleRow(id: number) {
        setExpandedRows((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }

    // Badge variant berdasarkan deskripsi aktivitas
    function getDescriptionBadge(description: string) {
        if (description.includes('created') || description.includes('dibuat')) {
            return <Badge variant="default">{description}</Badge>;
        }

        if (description.includes('updated') || description.includes('diubah')) {
            return <Badge variant="secondary">{description}</Badge>;
        }

        if (description.includes('deleted') || description.includes('dihapus')) {
            return <Badge variant="destructive">{description}</Badge>;
        }

        return <Badge variant="outline">{description}</Badge>;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log Cuti" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold uppercase tracking-tight">Audit Log Cuti</h1>
                    <p className="text-sm text-muted-foreground mt-1 font-medium">
                        Lacak semua perubahan pada modul cuti.
                    </p>
                </div>
                {/* Filter Bar */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Filter</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-end gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="filter-from">Dari Tanggal</Label>
                            <Input
                                id="filter-from"
                                type="date"
                                className="w-44"
                                value={filters.from}
                                onChange={(e) => setFilters((f) => ({ ...f, from: e.target.value }))}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="filter-to">Sampai Tanggal</Label>
                            <Input
                                id="filter-to"
                                type="date"
                                className="w-44"
                                value={filters.to}
                                onChange={(e) => setFilters((f) => ({ ...f, to: e.target.value }))}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="filter-aktor">Aktor (NIP/Nama)</Label>
                            <Input
                                id="filter-aktor"
                                className="w-48"
                                placeholder="Cari aktor..."
                                value={filters.aktor}
                                onChange={(e) => setFilters((f) => ({ ...f, aktor: e.target.value }))}
                            />
                        </div>
                        <Button variant="outline" size="sm" onClick={applyFilters}>
                            Terapkan
                        </Button>
                    </CardContent>
                </Card>

                {/* Tabel Audit Log */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">Aktivitas Modul Cuti</CardTitle>
                        <Badge variant="secondary">{activities.total} log</Badge>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
                                    <TableHead className="w-8" />
                                    <TableHead className="font-black uppercase text-xs tracking-wider w-44">Waktu</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider">Aktor</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider">Aktivitas</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider">Subjek</TableHead>
                                    <TableHead className="font-black uppercase text-xs tracking-wider">Perubahan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center py-12 font-medium text-muted-foreground">
                                            Tidak ada aktivitas
                                        </TableCell>
                                    </TableRow>
                                )}
                                {activities.data.map((item) => {
                                    const isExpanded = expandedRows.has(item.id);
                                    const hasChanges =
                                        item.properties.attributes &&
                                        Object.keys(item.properties.attributes).length > 0;

                                    return (
                                        <>
                                            <TableRow
                                                key={item.id}
                                                className={`border-b border-black/10 hover:bg-muted/20 transition-colors ${hasChanges ? 'cursor-pointer' : ''}`}
                                                onClick={() => hasChanges && toggleRow(item.id)}
                                            >
                                                <TableCell>
                                                    {hasChanges && (
                                                        isExpanded
                                                            ? <ChevronDown className="h-4 w-4" />
                                                            : <ChevronRight className="h-4 w-4" />
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {formatTanggalDateTime(item.created_at)}
                                                </TableCell>
                                                <TableCell>
                                                    {item.causer ? (
                                                        <div>
                                                            <span className="text-sm font-medium">{item.causer.nama}</span>
                                                            <span className="ml-1 text-xs text-muted-foreground">
                                                                ({item.causer.nip})
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">Sistem</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {getDescriptionBadge(item.description)}
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-mono text-xs">{item.subject_type}</span>
                                                    {item.subject_id && (
                                                        <span className="ml-1 text-xs text-muted-foreground">
                                                            #{item.subject_id.slice(0, 8)}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {hasChanges && !isExpanded && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {Object.keys(item.properties.attributes!).length} field diubah
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>

                                            {/* Baris detail perubahan */}
                                            {isExpanded && hasChanges && (
                                                <TableRow key={`${item.id}-detail`}>
                                                    <TableCell />
                                                    <TableCell colSpan={5}>
                                                        <div className="space-y-1 rounded-md bg-muted/50 p-3">
                                                            {Object.entries(item.properties.attributes!).map(([key, newVal]) => (
                                                                <div key={key} className="flex gap-2 text-xs">
                                                                    <span className="w-36 shrink-0 font-mono text-muted-foreground">
                                                                        {key}
                                                                    </span>
                                                                    {item.properties.old && key in item.properties.old ? (
                                                                        <>
                                                                            <span className="text-red-500 line-through">
                                                                                {String(item.properties.old[key] ?? '')}
                                                                            </span>
                                                                            <span>&rarr;</span>
                                                                            <span className="text-green-600">
                                                                                {String(newVal)}
                                                                            </span>
                                                                        </>
                                                                    ) : (
                                                                        <span className="text-green-600">
                                                                            {String(newVal)}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <PaginationWrapper
                    links={activities.links}
                    lastPage={activities.last_page}
                />
            </div>
        </AppLayout>
    );
}
