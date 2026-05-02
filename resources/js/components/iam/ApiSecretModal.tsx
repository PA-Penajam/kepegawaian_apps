import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface ApiSecretModalProps {
    apiSecret?: string;
    open: boolean;
    onClose: () => void;
}

export function ApiSecretModal({
    apiSecret,
    open,
    onClose,
}: ApiSecretModalProps) {
    const [copied, setCopied] = useState(false);

    if (!apiSecret) {
return null;
}

    const handleCopy = async () => {
        await navigator.clipboard.writeText(apiSecret);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>API Secret Baru</DialogTitle>
                    <DialogDescription>
                        Simpan secret ini sekarang. Secret tidak akan ditampilkan
                        lagi setelah halaman ini ditutup.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex items-start gap-2 rounded border bg-muted p-3">
                    <code className="flex-1 break-all text-sm">{apiSecret}</code>
                    <Button variant="outline" size="sm" onClick={handleCopy}>
                        {copied ? 'Tersalin!' : 'Salin'}
                    </Button>
                </div>
                <Button onClick={onClose} className="w-full">
                    Tutup &amp; Saya Sudah Menyimpan Secret
                </Button>
            </DialogContent>
        </Dialog>
    );
}
