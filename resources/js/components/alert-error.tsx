import { AlertCircleIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    const uniqueErrors = Array.from(
        new Set(
            (Array.isArray(errors) ? errors : []).filter(
                (error): error is string =>
                    typeof error === 'string' && error.trim() !== '',
            ),
        ),
    );

    if (uniqueErrors.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive" role="alert">
            <AlertCircleIcon className="size-4" />
            <AlertTitle>{title || 'Terjadi kesalahan validasi.'}</AlertTitle>
            <AlertDescription>
                <ul className="list-inside list-disc text-sm">
                    {uniqueErrors.map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
