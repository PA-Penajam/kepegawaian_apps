import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';

interface Props {
    slug: string;
}

export function SlugStatusBadge({ slug }: Props) {
    const iam = (usePage().props as unknown as { iam: { slug_pattern: string } }).iam;
    const regex = new RegExp(iam.slug_pattern);
    const isValid = regex.test(slug);

    if (isValid) {
        return (
            <Badge variant="outline" className="border-green-500 text-green-700 dark:text-green-400">
                <CheckCircle2 className="mr-1 h-3 w-3" />
                Canonical
            </Badge>
        );
    }

    const reason = !slug.includes('.')
        ? 'Tidak ada titik pemisah'
        : /[A-Z]/.test(slug)
            ? 'Mengandung uppercase'
            : slug.includes('_')
                ? 'Underscore tidak diizinkan'
                : 'Format tidak sesuai konvensi';

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-400">
                        <AlertTriangle className="mr-1 h-3 w-3" />
                        Legacy
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    <p className="text-xs">{reason}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
