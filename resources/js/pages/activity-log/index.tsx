import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import activityLog from '@/routes/activity-log';
import type { IamPaginatedData } from '@/types';

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
            activityLog.index(),
            Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')),
            { preserveState: true, replace: true },
        );
    }, [filters]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Activity Log', href: activityLog.index().url }]}>
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold uppercase tracking-tight">ACTIVITY LOG</h1>
                    <p className="text-sm text-muted-foreground mt-1 font-medium">
                        Lacak semua aktivitas perubahan data dalam sistem.
                    </p>
                </div>

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
                        <Button variant="outline" size="sm" onClick={applyFilters}>
                            Terapkan
                        </Button>
                    </CardContent>
                </Card>

                <div className="rounded-xl border-2 border-black shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/30 border-b-2 border-black hover:bg-muted/30">
                                <TableHead className="font-black uppercase text-xs tracking-wider w-40">WAKTU</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">OLEH</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider w-24">AKSI</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">MODEL</TableHead>
                                <TableHead className="font-black uppercase text-xs tracking-wider">PERUBAHAN</TableHead>
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
                                <TableRow key={item.id} className="border-b border-black/10 hover:bg-muted/20 transition-colors">
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
                </div>

                <PaginationWrapper meta={activities.meta} />
            </div>
        </AppLayout>
    );
}
