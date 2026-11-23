import { Head, router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import activityLog from '@/routes/activity-log';
import type { PaginatedData } from '@/types';

type ActivityItem = {
    id: number;
    waktu: string;
    oleh: string;
    aksi: 'created' | 'updated' | 'deleted';
    model: string;
    subject_id: string;
    old: Record<string, unknown>;
    new: Record<string, unknown>;
};

type Props = {
    activities: PaginatedData<ActivityItem>;
    subjectTypes: string[];
};

const aksiBadgeVariant = {
    created: 'default',
    updated: 'secondary',
    deleted: 'destructive',
} as const;

export default function ActivityLogIndex({ activities, subjectTypes }: Props) {
    const [filters, setFilters] = useState({
        subject_type: '',
        date_from: '',
        date_to: '',
    });

    const applyFilters = useCallback(() => {
        router.get(
            activityLog.index(),
            Object.fromEntries(
                Object.entries(filters).filter(
                    ([, v]) => v !== '' && v !== 'all',
                ),
            ),
            { preserveState: true, replace: true },
        );
    }, [filters]);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Activity Log', href: activityLog.index().url },
            ]}
        >
            <Head title="Activity Log" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Activity Log
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Lacak semua aktivitas perubahan data dalam sistem.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        <Select
                            value={filters.subject_type || 'all'}
                            onValueChange={(v) =>
                                setFilters((f) => ({
                                    ...f,
                                    subject_type: v === 'all' ? '' : v,
                                }))
                            }
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Semua model" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua model</SelectItem>
                                {subjectTypes.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            type="date"
                            className="w-44"
                            value={filters.date_from}
                            onChange={(e) =>
                                setFilters((f) => ({
                                    ...f,
                                    date_from: e.target.value,
                                }))
                            }
                            placeholder="Dari tanggal"
                        />
                        <Input
                            type="date"
                            className="w-44"
                            value={filters.date_to}
                            onChange={(e) =>
                                setFilters((f) => ({
                                    ...f,
                                    date_to: e.target.value,
                                }))
                            }
                            placeholder="Sampai tanggal"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={applyFilters}
                        >
                            Terapkan
                        </Button>
                    </CardContent>
                </Card>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border bg-muted/50 hover:bg-muted/50">
                                <TableHead className="w-40 text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Waktu
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Oleh
                                </TableHead>
                                <TableHead className="w-24 text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Aksi
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Model
                                </TableHead>
                                <TableHead className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                    Perubahan
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {activities.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        Tidak ada aktivitas
                                    </TableCell>
                                </TableRow>
                            )}
                            {activities.data.map((item) => (
                                <TableRow
                                    key={item.id}
                                    className="border-b border-border transition-colors hover:bg-muted/40"
                                >
                                    <TableCell className="tnum text-sm text-muted-foreground">
                                        {item.waktu}
                                    </TableCell>
                                    <TableCell>{item.oleh}</TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                aksiBadgeVariant[item.aksi] ??
                                                'outline'
                                            }
                                        >
                                            {item.aksi}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono text-sm">
                                            {item.model}
                                        </span>
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            {item.subject_id
                                                ? `#${item.subject_id.slice(0, 8)}`
                                                : ''}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {item.aksi === 'updated' &&
                                            item.new &&
                                            Object.keys(item.new).length >
                                                0 && (
                                                <div className="space-y-1 text-xs">
                                                    {Object.entries(
                                                        item.new,
                                                    ).map(([key, newVal]) => (
                                                        <div
                                                            key={key}
                                                            className="flex gap-1"
                                                        >
                                                            <span className="font-mono text-muted-foreground">
                                                                {key}:
                                                            </span>
                                                            <span className="text-red-700 line-through dark:text-red-400">
                                                                {String(
                                                                    item.old[
                                                                        key
                                                                    ] ?? '',
                                                                )}
                                                            </span>
                                                            <span>&rarr;</span>
                                                            <span className="text-emerald-700 dark:text-emerald-400">
                                                                {String(newVal)}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        {item.aksi === 'created' && (
                                            <span className="text-xs text-muted-foreground">
                                                Data baru dibuat
                                            </span>
                                        )}
                                        {item.aksi === 'deleted' && (
                                            <span className="text-xs text-muted-foreground">
                                                Data dihapus
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationWrapper
                    links={activities.links}
                    lastPage={activities.last_page}
                />
            </div>
        </AppLayout>
    );
}
