import { router } from '@inertiajs/react';

export type SystemErrorNotification = {
    id: number;
    kind: 'session-expired' | 'network-error';
    title: string;
    message: string;
};

type SystemErrorListener = (notification: SystemErrorNotification) => void;

const listeners = new Set<SystemErrorListener>();

function emitSystemError(
    kind: SystemErrorNotification['kind'],
    title: string,
    message: string,
): void {
    const notification: SystemErrorNotification = {
        id: Date.now() + Math.random(),
        kind,
        title,
        message,
    };

    listeners.forEach((listener) => {
        listener(notification);
    });
}

/**
 * Mendaftarkan komponen UI untuk menerima notifikasi error sistem non-blocking.
 */
export function subscribeSystemErrors(
    listener: SystemErrorListener,
): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

type NetworkErrorLike = {
    code?: unknown;
    isAxiosError?: unknown;
    response?: unknown;
    message?: unknown;
};

/**
 * Memeriksa apakah error pada event exception Inertia berasal dari gangguan jaringan
 * atau timeout, termasuk objek AxiosError dari @inertiajs/core.
 */
export function isNetworkException(exception: unknown): boolean {
    if (typeof exception !== 'object' || exception === null) {
        return false;
    }

    const candidate = exception as NetworkErrorLike;

    if (candidate.code === 'ERR_NETWORK' || candidate.code === 'ECONNABORTED') {
        return true;
    }

    if (candidate.isAxiosError === true && !candidate.response) {
        return true;
    }

    if (typeof candidate.message === 'string') {
        const normalizedMessage = candidate.message.toLowerCase();

        return (
            normalizedMessage.includes('network error') ||
            normalizedMessage.includes('networkerror') ||
            normalizedMessage.includes('failed to fetch') ||
            normalizedMessage.includes('load failed') ||
            normalizedMessage.includes('timeout')
        );
    }

    return false;
}

/**
 * Menginisialisasi pendengar event global Inertia untuk menangani kesalahan jaringan,
 * sesi kedaluwarsa, dan kegagalan respons server tanpa dialog blocking browser.
 */
export function setupInertiaErrorListeners(): void {
    // Tangani ketika respons dari server tidak valid (misal status 419 CSRF kedaluwarsa)
    router.on('invalid', (event) => {
        const response = event.detail.response;

        if (response.status === 419) {
            event.preventDefault();
            emitSystemError(
                'session-expired',
                'Sesi Kedaluwarsa',
                'Sesi Anda telah kedaluwarsa demi alasan keamanan. Muat ulang halaman untuk masuk kembali.',
            );
        }
    });

    // Tangani error koneksi atau jaringan offline
    router.on('exception', (event) => {
        const exception: unknown = event.detail.exception;

        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            event.preventDefault();
            emitSystemError(
                'network-error',
                'Koneksi Terputus',
                'Koneksi internet terputus. Pastikan perangkat Anda terhubung ke jaringan kantor Pengadilan Agama Penajam atau internet aktif, lalu coba kembali.',
            );
        } else if (isNetworkException(exception)) {
            event.preventDefault();
            emitSystemError(
                'network-error',
                'Gagal Menghubungi Server',
                'Gagal menghubungi server SIMPEG. Mohon periksa koneksi jaringan Anda.',
            );
        }
    });
}
