import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function DashboardDistribusiSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 5 }).map((_, i) => (
                <Card key={i} className={i === 1 || i === 2 ? 'col-span-1 lg:col-span-2' : 'col-span-1'}>
                    <CardHeader>
                        <Skeleton className="h-5 w-44" />
                        <Skeleton className="mt-1 h-4 w-32" />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {Array.from({ length: 4 }).map((_, j) => (
                            <div key={j} className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <Skeleton className="h-3.5 w-28" />
                                    <Skeleton className="h-3.5 w-16" />
                                </div>
                                <Skeleton className="h-2 w-full rounded-full" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
