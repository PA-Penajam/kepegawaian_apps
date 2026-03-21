import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface CrudTableColumn<T> {
    key: string;
    header: string;
    cell: (item: T) => ReactNode;
    className?: string;
}

interface CrudTableProps<T> {
    columns: CrudTableColumn<T>[];
    data: T[];
    onEdit: (item: T) => void;
    onDelete: (item: T) => void;
    emptyMessage?: string;
    getItemId: (item: T) => string;
}

export function CrudTable<T>({
    columns,
    data,
    onEdit,
    onDelete,
    emptyMessage = 'Belum ada data.',
    getItemId,
}: CrudTableProps<T>) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {columns.map((col) => (
                        <TableHead key={col.key} className={col.className}>
                            {col.header}
                        </TableHead>
                    ))}
                    <TableHead className="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {data.length === 0 ? (
                    <TableRow>
                        <TableCell
                            colSpan={columns.length + 1}
                            className="py-8 text-center text-sm text-muted-foreground"
                        >
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                ) : (
                    data.map((item) => (
                        <TableRow key={getItemId(item)}>
                            {columns.map((col) => (
                                <TableCell
                                    key={col.key}
                                    className={col.className}
                                >
                                    {col.cell(item)}
                                </TableCell>
                            ))}
                            <TableCell className="text-right">
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onEdit(item)}
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => onDelete(item)}
                                    >
                                        Hapus
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))
                )}
            </TableBody>
        </Table>
    );
}
