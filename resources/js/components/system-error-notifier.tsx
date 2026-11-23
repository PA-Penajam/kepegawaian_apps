import { AlertTriangle, WifiOff, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import type { SystemErrorNotification } from '@/lib/inertia-error-listener';
import { subscribeSystemErrors } from '@/lib/inertia-error-listener';

/**
 * Menampilkan dialog non-blocking untuk sesi kedaluwarsa (419)
 * dan banner toast untuk gangguan jaringan/offline pada seluruh layout aplikasi.
 */
export function SystemErrorNotifier() {
    const [sessionExpired, setSessionExpired] =
        useState<SystemErrorNotification | null>(null);
    const [networkErrors, setNetworkErrors] = useState<
        SystemErrorNotification[]
    >([]);

    useEffect(() => {
        return subscribeSystemErrors((notification) => {
            if (notification.kind === 'session-expired') {
                setSessionExpired(notification);

                return;
            }

            setNetworkErrors((prev) => [...prev.slice(-2), notification]);

            setTimeout(() => {
                setNetworkErrors((prev) =>
                    prev.filter((item) => item.id !== notification.id),
                );
            }, 8000);
        });
    }, []);

    return (
        <>
            <AlertDialog
                open={sessionExpired !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSessionExpired(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertTriangle
                                className="size-5 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                            {sessionExpired?.title ?? 'Sesi Kedaluwarsa'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {sessionExpired?.message ??
                                'Sesi Anda telah kedaluwarsa. Muat ulang halaman untuk melanjutkan.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel asChild>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSessionExpired(null)}
                            >
                                Tutup
                            </Button>
                        </AlertDialogCancel>
                        <AlertDialogAction asChild>
                            <Button
                                type="button"
                                onClick={() => window.location.reload()}
                            >
                                Muat Ulang Halaman
                            </Button>
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {networkErrors.length > 0 && (
                <div
                    aria-live="assertive"
                    role="region"
                    aria-label="Peringatan Koneksi"
                    className="pointer-events-none fixed right-4 bottom-5 z-50 flex w-[380px] max-w-[calc(100vw-2rem)] flex-col gap-2.5 sm:right-6 sm:bottom-6"
                >
                    {networkErrors.map((item) => (
                        <div
                            key={item.id}
                            role="alert"
                            className="pointer-events-auto flex items-start gap-3 rounded-xl border border-border bg-card p-3.5 shadow-sm"
                        >
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-destructive/15 text-destructive">
                                <WifiOff
                                    className="size-4.5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="min-w-0 flex-1 space-y-0.5">
                                <p className="text-xs font-semibold text-foreground sm:text-sm">
                                    {item.title}
                                </p>
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    {item.message}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    setNetworkErrors((prev) =>
                                        prev.filter(
                                            (err) => err.id !== item.id,
                                        ),
                                    )
                                }
                                aria-label="Tutup peringatan koneksi"
                                className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
