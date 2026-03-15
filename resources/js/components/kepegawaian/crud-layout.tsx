import { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types/navigation';

interface CrudLayoutProps {
    children: ReactNode;
    breadcrumbs: BreadcrumbItem[];
    title: string;
}

export function CrudLayout({ children, breadcrumbs, title }: CrudLayoutProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">{title}</h1>
                </div>
                {children}
            </div>
        </AppLayout>
    );
}
