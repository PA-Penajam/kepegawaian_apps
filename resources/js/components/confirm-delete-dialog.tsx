import { useEffect, useRef } from 'react';
import type { MouseEvent } from 'react';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: string;
    description?: string;
    itemName?: string;
    onConfirm: () => void;
    processing?: boolean;
    confirmLabel?: string;
    cancelLabel?: string;
};

export function ConfirmDeleteDialog({
    open,
    onOpenChange,
    title = 'Konfirmasi Hapus',
    description = 'Apakah Anda yakin ingin menghapus',
    itemName,
    onConfirm,
    processing,
    confirmLabel = 'Hapus',
    cancelLabel = 'Batal',
}: Props) {
    const hasProcessingProp = processing !== undefined;
    const isProcessing = processing ?? false;
    const wasProcessingRef = useRef(false);
    const onOpenChangeRef = useRef(onOpenChange);

    useEffect(() => {
        onOpenChangeRef.current = onOpenChange;
    }, [onOpenChange]);

    useEffect(() => {
        if (!open) {
            wasProcessingRef.current = false;

            return;
        }

        if (isProcessing) {
            wasProcessingRef.current = true;

            return;
        }

        if (wasProcessingRef.current) {
            // Tutup dialog otomatis setelah proses async selesai agar tidak nyangkut jika pemanggil gagal/lupa menutup.
            wasProcessingRef.current = false;
            onOpenChangeRef.current(false);
        }
    }, [open, isProcessing]);

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen && isProcessing) {
            return;
        }

        onOpenChange(nextOpen);
    };

    const handleConfirm = (event: MouseEvent<HTMLButtonElement>) => {
        if (hasProcessingProp) {
            // Cegah auto-close sinkron Radix agar indikator "Menghapus..." dan kunci disabled sempat tampil selama request berjalan.
            event.preventDefault();
        }

        if (isProcessing) {
            return;
        }

        onConfirm();
    };

    const trimmedDescription = description.trim();
    const endsWithPunctuation = /[?!.]$/.test(trimmedDescription);

    return (
        <AlertDialog open={open} onOpenChange={handleOpenChange}>
            <AlertDialogContent
                onEscapeKeyDown={(event) => {
                    if (isProcessing) {
                        event.preventDefault();
                    }
                }}
            >
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {endsWithPunctuation ? (
                            <>
                                {trimmedDescription}
                                {itemName ? (
                                    <>
                                        {' '}
                                        (<strong>"{itemName}"</strong>).
                                    </>
                                ) : null}{' '}
                                Tindakan ini tidak dapat dibatalkan.
                            </>
                        ) : (
                            <>
                                {trimmedDescription}
                                {itemName ? (
                                    <>
                                        {' '}
                                        <strong>"{itemName}"</strong>
                                    </>
                                ) : null}
                                {'? '}
                                Tindakan ini tidak dapat dibatalkan.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <Button variant="outline" disabled={isProcessing}>
                            {cancelLabel}
                        </Button>
                    </AlertDialogCancel>
                    <AlertDialogAction asChild>
                        <Button
                            variant="destructive"
                            onClick={handleConfirm}
                            disabled={isProcessing}
                        >
                            {isProcessing ? 'Menghapus...' : confirmLabel}
                        </Button>
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
