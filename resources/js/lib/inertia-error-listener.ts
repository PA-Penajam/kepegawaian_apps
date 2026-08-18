import { router } from '@inertiajs/react';

/**
 * Menginisialisasi pendengar event global Inertia untuk menangani kesalahan jaringan,
 * session expired, dan kegagalan respons server secara anggun.
 */
export function setupInertiaErrorListeners(): void {
    // Tangani ketika respons dari server tidak valid (misal status 500 HTML raw, 419, dll)
    router.on('invalid', (event) => {
        const response = event.detail.response;

        if (response.status === 419) {
            // Sesi kedaluwarsa: cegah modal default Inertia dan berikan informasi terarah
            event.preventDefault();
            if (
                window.confirm(
                    'Sesi Anda telah kedaluwarsa demi alasan keamanan. Apakah Anda ingin memuat ulang halaman sekarang untuk login kembali?',
                )
            ) {
                window.location.reload();
            }
        }
    });

    // Tangani error koneksi / offline
    router.on('exception', (event) => {
        const exception = event.detail.exception;

        if (!navigator.onLine) {
            event.preventDefault();
            alert(
                'Koneksi internet terputus. Pastikan perangkat Anda terhubung ke jaringan kantor Pengadilan Agama Penajam atau internet aktif, lalu coba kembali.',
            );
        } else if (exception && exception.message?.includes('NetworkError')) {
            event.preventDefault();
            alert('Gagal menghubungi server SIMPEG. Mohon periksa koneksi jaringan Anda.');
        }
    });
}
