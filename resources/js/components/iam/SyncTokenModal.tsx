import { AlertTriangle, Check, Copy, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SyncTokenOnce } from '@/types';

interface SyncTokenModalProps {
    /**
     * Data kredensial plaintext sekali tampil. null bila modal tidak terbuka.
     * Berisi token Sanctum + HMAC secret server untuk disalin ke client.
     */
    token: SyncTokenOnce | null;
    onClose: () => void;
}

type CopyTarget = 'slug' | 'token' | 'secret' | 'env';

/**
 * Modal kredensial integrasi sinkronisasi ala client-credentials sso-papenajam.
 * Token + HMAC secret hanya tampil SATU KALI setelah penerbitan/regenerasi —
 * hilang setelah modal ditutup dan tidak dapat dilihat kembali. Admin wajib
 * mencentang konfirmasi sudah menyimpan sebelum bisa menutup modal.
 */
export function SyncTokenModal({ token, onClose }: SyncTokenModalProps) {
    const [showToken, setShowToken] = useState(false);
    const [showSecret, setShowSecret] = useState(false);
    const [copied, setCopied] = useState<CopyTarget | null>(null);
    const [confirmed, setConfirmed] = useState(false);

    const envSnippet = useMemo(() => {
        if (!token) {
            return '';
        }

        const baseUrl =
            typeof window === 'undefined' ? '' : window.location.origin;
        // Bagian yang tidak diputar pada aksi ini memakai placeholder agar
        // admin tidak menimpa nilai yang masih berlaku di .env client.
        const shownToken = token.plaintext
            ? token.plaintext
            : '<TOKEN_LAMA_MASIH_BERLAKU>';
        const shownSecret = token.hmac_secret
            ? token.hmac_secret
            : '<SECRET_LAMA_MASIH_BERLAKU>';

        return (
            `# Sinkronisasi Kepegawaian - ${token.consumer_slug}\n` +
            `# Secret unik per konsumen ini — jangan dipakai untuk konsumen lain\n` +
            `KEPEGAWAIAN_BASE_URL=${baseUrl}\n` +
            `KEPEGAWAIAN_SYNC_TOKEN=${shownToken}\n` +
            `KEPEGAWAIAN_HMAC_SECRET=${shownSecret}\n` +
            `# Endpoint: GET ${baseUrl}/api/v1/pegawai/sync + header X-Timestamp & X-Signature`
        );
    }, [token]);

    if (!token) {
        return null;
    }

    const tokenRotated = token.plaintext !== '';
    const secretRotated = token.hmac_secret !== '';

    const handleCopy = async (text: string, target: CopyTarget) => {
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopied(target);
            setTimeout(() => setCopied(null), 2500);
        } catch {
            // Abaikan kegagalan clipboard (mis. konteks non-HTTPS).
        }
    };

    const handleOpenChange = (open: boolean) => {
        // Kunci modal sampai admin mengonfirmasi sudah menyimpan — sama
        // seperti pola client-credentials-modal di sso-papenajam.
        if (!open && !confirmed) {
            return;
        }

        if (!open) {
            onClose();
        }
    };

    return (
        <Dialog open={!!token} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            className="animate-seal-stamp flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[color:var(--gold)] bg-primary text-primary-foreground shadow-xs"
                        >
                            <ShieldCheck className="h-5 w-5" />
                        </span>
                        <span>
                            Kredensial integrasi diterbitkan
                            <span className="mt-0.5 block text-xs font-medium text-muted-foreground">
                                Salin kredensial di bawah ini sebelum menutup
                            </span>
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        <span className="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <AlertTriangle
                                className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                            <span>
                                <strong>PENTING</strong> — Token dan HMAC secret
                                ini <strong>hanya ditampilkan satu kali</strong>{' '}
                                dan langsung di-hash di basis data. Anda tidak
                                akan dapat melihatnya kembali setelah modal ini
                                ditutup.
                            </span>
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-3.5 rounded-xl border bg-muted/40 p-4">
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Slug Konsumen
                            </span>
                            <div className="mt-1 flex items-center gap-2">
                                <input
                                    type="text"
                                    readOnly
                                    value={token.consumer_slug}
                                    aria-label="Slug konsumen"
                                    className="tnum flex-1 rounded-lg border bg-card px-3 py-2 font-mono text-xs select-all focus:outline-none focus-visible:ring-2"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        handleCopy(token.consumer_slug, 'slug')
                                    }
                                >
                                    {copied === 'slug' ? (
                                        <Check className="mr-1.5 h-3.5 w-3.5" />
                                    ) : (
                                        <Copy className="mr-1.5 h-3.5 w-3.5" />
                                    )}
                                    {copied === 'slug' ? 'Tersalin!' : 'Salin'}
                                </Button>
                            </div>
                        </div>

                        {tokenRotated && (
                            <div>
                                <div className="mb-1 flex items-center justify-between">
                                    <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Token Sinkronisasi Baru
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setShowToken((v) => !v)}
                                        className="text-xs text-primary hover:underline"
                                    >
                                        {showToken
                                            ? 'Sembunyikan'
                                            : 'Tampilkan'}
                                    </button>
                                </div>
                                <div className="flex items-center gap-2">
                                    <input
                                        type={showToken ? 'text' : 'password'}
                                        readOnly
                                        value={token.plaintext}
                                        aria-label="Token sinkronisasi baru"
                                        className="tnum flex-1 rounded-lg border bg-card px-3 py-2 font-mono text-xs font-semibold break-all select-all focus:outline-none focus-visible:ring-2"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handleCopy(token.plaintext, 'token')
                                        }
                                    >
                                        {copied === 'token' ? (
                                            <Check className="mr-1.5 h-3.5 w-3.5" />
                                        ) : (
                                            <Copy className="mr-1.5 h-3.5 w-3.5" />
                                        )}
                                        {copied === 'token'
                                            ? 'Tersalin!'
                                            : 'Salin Token'}
                                    </Button>
                                </div>
                            </div>
                        )}

                        {secretRotated && (
                            <div>
                                <div className="mb-1 flex items-center justify-between">
                                    <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        HMAC Secret Baru (unik per konsumen)
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret((v) => !v)}
                                        className="text-xs text-primary hover:underline"
                                    >
                                        {showSecret
                                            ? 'Sembunyikan'
                                            : 'Tampilkan'}
                                    </button>
                                </div>
                                <div className="flex items-center gap-2">
                                    <input
                                        type={showSecret ? 'text' : 'password'}
                                        readOnly
                                        value={token.hmac_secret}
                                        aria-label="HMAC secret baru per konsumen"
                                        className="tnum flex-1 rounded-lg border bg-card px-3 py-2 font-mono text-xs font-semibold break-all select-all focus:outline-none focus-visible:ring-2"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handleCopy(
                                                token.hmac_secret,
                                                'secret',
                                            )
                                        }
                                    >
                                        {copied === 'secret' ? (
                                            <Check className="mr-1.5 h-3.5 w-3.5" />
                                        ) : (
                                            <Copy className="mr-1.5 h-3.5 w-3.5" />
                                        )}
                                        {copied === 'secret'
                                            ? 'Tersalin!'
                                            : 'Salin Secret'}
                                    </Button>
                                </div>
                                <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                                    Secret ini unik untuk{' '}
                                    <code className="font-mono">
                                        {token.consumer_slug}
                                    </code>{' '}
                                    — jangan dipakai untuk konsumen lain. Secret
                                    lama langsung tidak berlaku.
                                </p>
                            </div>
                        )}

                        {!tokenRotated && !secretRotated && (
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                Tidak ada kredensial baru pada aksi ini.
                            </p>
                        )}
                    </div>

                    <div className="overflow-hidden rounded-xl border">
                        <div className="flex items-center justify-between bg-slate-950 px-3.5 py-2">
                            <span className="font-mono text-xs text-slate-400">
                                Format .env Siap Pakai
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                className="h-7 text-xs"
                                onClick={() => handleCopy(envSnippet, 'env')}
                            >
                                {copied === 'env' ? (
                                    <Check className="mr-1.5 h-3.5 w-3.5" />
                                ) : (
                                    <Copy className="mr-1.5 h-3.5 w-3.5" />
                                )}
                                {copied === 'env' ? 'Tersalin!' : 'Salin .env'}
                            </Button>
                        </div>
                        <pre className="overflow-x-auto bg-slate-900 p-3 font-mono text-xs leading-relaxed whitespace-pre text-slate-200">
                            <code>{envSnippet}</code>
                        </pre>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        {tokenRotated
                            ? token.expires_at
                                ? `Token baru berlaku hingga ${new Date(token.expires_at).toLocaleString('id-ID')}.`
                                : 'Token baru ini tidak memiliki tanggal kedaluwarsa.'
                            : 'Token tidak diputar pada aksi ini — yang lama masih berlaku.'}{' '}
                        Tempel blok .env di atas ke aplikasi client, lalu uji
                        dari tabel konsumen sebelum pull berikutnya.
                    </p>

                    <label
                        htmlFor="sync-confirm"
                        className="flex cursor-pointer items-start gap-2.5 rounded-lg border bg-muted/40 p-3 select-none"
                    >
                        <input
                            id="sync-confirm"
                            type="checkbox"
                            checked={confirmed}
                            onChange={(e) => setConfirmed(e.target.checked)}
                            className="mt-0.5 rounded border-input"
                        />
                        <span className="text-xs leading-normal font-medium">
                            Saya telah menyalin dan menyimpan token serta HMAC
                            secret ini di tempat yang aman (.env client atau
                            password manager).
                        </span>
                    </label>
                </div>

                <DialogFooter>
                    <Button
                        onClick={onClose}
                        disabled={!confirmed}
                        className={
                            !confirmed ? 'cursor-not-allowed opacity-50' : ''
                        }
                    >
                        Saya Sudah Menyimpan & Selesai
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
