import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { IamPaginatedData } from '@/types';
import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

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
    activities: IamPaginatedData<ActivityItem>;
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
            route('activity-log.index'),
            Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')),
            { preserveState: true, replace: true },
        );
    }, [filters]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Activity Log', href: route('activity-log.index') }]}>
            <div className="space-y-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        <Select
                            value={filters.subject_type}
                            onValueChange={(v) => setFilters((f) => ({ ...f, subject_type: v }))}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Semua model" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Semua model</SelectItem>
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
                            onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value }))}
                            placeholder="Dari tanggal"
                        />
                        <Input
                            type="date"
                            className="w-44"
                            value={filters.date_to}
                            onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value }))}
                            placeholder="Sampai tanggal"
                        />
                        <button
                            onClick={applyFilters}
                            className="rounded bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
                        >
                            Terapkan
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-40">Waktu</TableHead>
                                    <TableHead>Oleh</TableHead>
                                    <TableHead className="w-24">Aksi</TableHead>
                                    <TableHead>Model</TableHead>
                                    <TableHead>Perubahan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                            Tidak ada aktivitas
                                        </TableCell>
                                    </TableRow>
                                )}
                                {activities.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="text-sm text-muted-foreground">{item.waktu}</TableCell>
                                        <TableCell>{item.oleh}</TableCell>
                                        <TableCell>
                                            <Badge variant={aksiBadgeVariant[item.aksi] ?? 'outline'}>
                                                {item.aksi}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span className="font-mono text-sm">{item.model}</span>
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                {item.subject_id ? `#${item.subject_id.slice(0, 8)}` : ''}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {item.aksi === 'updated' && item.new && Object.keys(item.new).length > 0 && (
                                                <div className="space-y-1 text-xs">
                                                    {Object.entries(item.new).map(([key, newVal]) => (
                                                        <div key={key} className="flex gap-1">
                                                            <span className="font-mono text-muted-foreground">{key}:</span>
                                                            <span className="line-through text-red-500">{String(item.old[key] ?? '')}</span>
                                                            <span>&rarr;</span>
                                                            <span className="text-green-600">{String(newVal)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                            {item.aksi === 'created' && (
                                                <span className="text-xs text-muted-foreground">Record baru dibuat</span>
                                            )}
                                            {item.aksi === 'deleted' && (
                                                <span className="text-xs text-muted-foreground">Record dihapus</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <PaginationWrapper meta={activities.meta} />
            </div>
        </AppLayout>
    );
}
