import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface DetailTabCardProps {
    title: string;
    description: string;
    manageUrl: string;
    columns: string[];
    emptyMessage: string;
    isEmpty: boolean;
    colSpan: number;
    children: ReactNode;
}

export function DetailTabCard({
    title,
    description,
    manageUrl,
    columns,
    emptyMessage,
    isEmpty,
    colSpan,
    children,
}: DetailTabCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between">
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <Button asChild>
                    <Link href={manageUrl}>Kelola</Link>
                </Button>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((col) => (
                                <TableHead key={col}>{col}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isEmpty ? (
                            <TableRow>
                                <TableCell
                                    colSpan={colSpan}
                                    className="py-6 text-center text-muted-foreground"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            children
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
