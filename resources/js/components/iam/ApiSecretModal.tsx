import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, Clock } from 'lucide-react';

interface ApiSecretModalProps {
    apiSecret?: string;
    open: boolean;
    onClose: () => void;
    /** Sisa TTL recovery (detik). Pass 0 atau undefined kalau tidak ada countdown */
    ttlRemainingSecs?: number;
    /** Handler tombol "Saya sudah simpan" — biasanya POST /acknowledge-secret */
    onAcknowledge?: () => void;
    /** Loading state tombol acknowledge */
    acknowledging?: boolean;
}

/**
 * Modal yang menampilkan plaintext API secret sekali setelah create/regenerate.
 * Mendukung recovery selama TTL cache: tampilkan countdown, tombol "Saya sudah simpan"
 * untuk hapus cache secara eksplisit.
 */
export function ApiSecretModal({
    apiSecret,
    open,
    onClose,
    ttlRemainingSecs,
    onAcknowledge,
    acknowledging,
}: ApiSecretModalProps) {
    const [copied, setCopied] = useState(false);
    const [secondsLeft, setSecondsLeft] = useState<number>(ttlRemainingSecs ?? 0);

    // Reset countdown ketika props ttlRemainingSecs berubah (mis. recovery click ulang)
    useEffect(() => {
        setSecondsLeft(ttlRemainingSecs ?? 0);
    }, [ttlRemainingSecs]);

    // Live countdown setiap detik selama modal terbuka
    useEffect(() => {
        if (!open || secondsLeft <= 0) return;

        const intervalId = window.setInterval(() => {
            setSecondsLeft((prev) => Math.max(0, prev - 1));
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, [open, secondsLeft]);

    if (!apiSecret) {
        return null;
    }

    const handleCopy = async () => {
        await navigator.clipboard.writeText(apiSecret);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const formatCountdown = (totalSec: number): string => {
        const m = Math.floor(totalSec / 60);
        const s = totalSec % 60;
        return `${m} menit ${s.toString().padStart(2, '0')} detik`;
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>API Secret Baru</DialogTitle>
                    <DialogDescription>
                        <span className="flex items-start gap-2">
                            <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-600" aria-hidden="true" />
                            <span>
                                <strong>PENTING</strong> — Simpan secret ini sekarang. Setelah 15 menit,
                                secret tidak bisa ditampilkan lagi (kecuali regenerasi).
                            </span>
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-start gap-2 rounded border bg-muted p-3">
                    <code className="flex-1 break-all text-sm">{apiSecret}</code>
                    <Button variant="outline" size="sm" onClick={handleCopy}>
                        {copied ? 'Tersalin!' : 'Salin'}
                    </Button>
                </div>

                {secondsLeft > 0 && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock className="h-4 w-4" aria-hidden="true" />
                        Bisa dipulihkan selama {formatCountdown(secondsLeft)}
                    </p>
                )}

                <DialogFooter className="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" onClick={onClose} className="w-full sm:w-auto">
                        Tutup (tetap bisa recovery)
                    </Button>
                    {onAcknowledge && (
                        <Button
                            onClick={onAcknowledge}
                            disabled={acknowledging}
                            className="w-full sm:w-auto"
                        >
                            {acknowledging ? 'Memproses...' : '✓ Saya sudah simpan (hapus dari cache)'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
