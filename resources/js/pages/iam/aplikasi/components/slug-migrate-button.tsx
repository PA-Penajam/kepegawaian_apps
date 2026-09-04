import { router, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

interface Props {
    aplikasiId: string;
    permissionId: string;
    slug: string;
}

export function SlugMigrateButton({ aplikasiId, permissionId, slug }: Props) {
    const [open, setOpen] = useState(false);
    const iam = (usePage().props as unknown as { iam: { slug_pattern: string } }).iam;
    const regex = useMemo(() => new RegExp(iam.slug_pattern), [iam.slug_pattern]);

    const suggested = useMemo(() => {
        // Jika slug sudah valid, tidak perlu migrate
        if (regex.test(slug)) {
return null;
}

        // Hanya tangani kasus yang bisa disarankan otomatis
        if (slug.includes('.') || slug.includes('_') || !slug.includes('-')) {
return null;
}

        const pos = slug.lastIndexOf('-');
        const candidate = slug.substring(0, pos) + '.' + slug.substring(pos + 1);

        return regex.test(candidate) ? candidate : null;
    }, [slug, regex]);

    if (!suggested) {
return null;
}

    const handleMigrate = () => {
        router.post(
            `/iam/aplikasi/${aplikasiId}/permissions/${permissionId}/migrate-slug`,
            {},
            { onFinish: () => setOpen(false) },
        );
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <Button size="sm" variant="outline" className="h-7 gap-1 text-xs">
                    Migrate <ArrowRight className="h-3 w-3" /> <code className="font-mono">{suggested}</code>
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Konfirmasi Migrasi Slug</AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-2">
                            <p>
                                Ubah slug: <code className="font-mono">{slug}</code> →{' '}
                                <code className="font-mono">{suggested}</code>
                            </p>
                            <p>Akan dilakukan:</p>
                            <ol className="ml-4 list-decimal text-sm">
                                <li>Rename slug di tabel iam_permissions</li>
                                <li>Group auto-update jadi <code className="font-mono">{suggested.split('.')[0]}</code></li>
                                <li>Audit log mencatat perubahan</li>
                            </ol>
                            <p className="rounded-md border border-amber-400 bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                ⚠ Reference di kode (route middleware, policy) yang masih pakai{' '}
                                <code className="font-mono">{slug}</code> HARUS di-grep & update manual oleh developer.
                                Migrasi ini hanya mengubah database.
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction onClick={handleMigrate}>Ya, Migrate</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
