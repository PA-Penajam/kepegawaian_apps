import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface CrudFormCardProps {
    title: string;
    description: string;
    children: ReactNode;
    onSubmit: (e: React.FormEvent) => void;
    onCancel?: () => void;
    submitLabel?: string;
    cancelLabel?: string;
    isEditing?: boolean;
    processing?: boolean;
}

export function CrudFormCard({
    title,
    description,
    children,
    onSubmit,
    onCancel,
    submitLabel = 'Simpan',
    cancelLabel = 'Batal',
    isEditing = false,
    processing = false,
}: CrudFormCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <form className="space-y-4" onSubmit={onSubmit}>
                    {children}
                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Menyimpan...' : submitLabel}
                        </Button>
                        {isEditing && onCancel && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancel}
                                disabled={processing}
                            >
                                {cancelLabel}
                            </Button>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
