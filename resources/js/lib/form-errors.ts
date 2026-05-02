/**
 * Mengkonversi object errors dari Inertia menjadi array string.
 * Berguna untuk komponen AlertError yang membutuhkan array string.
 */
export function errorsToArray(errors: Record<string, string | string[]>): string[] {
    const result: string[] = [];

    Object.values(errors).forEach((value) => {
        if (Array.isArray(value)) {
            result.push(...value);
        } else if (typeof value === 'string') {
            result.push(value);
        }
    });

    return result;
}

/**
 * Mengecek apakah ada error dari server (Inertia errors).
 */
export function hasErrors(errors: Record<string, unknown>): boolean {
    return Object.keys(errors).length > 0;
}

/**
 * Pesan error default untuk network error.
 */
export const NETWORK_ERROR_MESSAGE = 'Terjadi kesalahan jaringan. Silakan coba lagi.';

/**
 * Pesan error default untuk server error.
 */
export const SERVER_ERROR_MESSAGE = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
