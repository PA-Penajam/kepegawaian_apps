/**
 * Utilitas bersama untuk domain Cuti.
 * File ini adalah single source of truth untuk format tanggal yang digunakan di seluruh modul cuti.
 * Fungsi-fungsi ini menggantikan duplikat formatTanggal / formatDate di masing-masing komponen.
 */

/** Nama bulan dalam Bahasa Indonesia (index 0 = Januari) */
const BULAN_ID = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
] as const;

/**
 * Format string tanggal ke format lokal Indonesia: "02 Mei 2026".
 *
 * Mendukung format input:
 * - "2026-05-02"              (date-only, paling umum dari backend)
 * - "2026-05-02T14:30:00"    (ISO 8601 dengan waktu)
 * - "2026-05-02 14:30:00"    (format datetime MySQL)
 *
 * Penting: untuk string date-only, akan ditambahkan "T00:00:00" sebelum parsing
 * agar browser tidak menginterpretasikan sebagai UTC (yang bisa geser hari).
 *
 * @param dateStr - String tanggal dari backend (bisa null atau undefined)
 * @returns Tanggal dalam format "DD Bulan YYYY", atau string kosong jika input tidak valid
 */
export function formatTanggal(dateStr: string | null | undefined): string {
    // Kembalikan string kosong jika input kosong atau falsy
    if (!dateStr) {
        return '';
    }

    // Tambahkan "T00:00:00" untuk string date-only agar tidak terjadi timezone shift
    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(dateStr.trim())
        ? `${dateStr.trim()}T00:00:00`
        : dateStr.trim();

    const date = new Date(normalized);

    // Kembalikan string kosong jika hasil parsing tidak valid
    if (isNaN(date.getTime())) {
        return '';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = BULAN_ID[date.getMonth()];
    const year = date.getFullYear();

    return `${day} ${month} ${year}`;
}

/**
 * Format string tanggal-waktu ke format lokal Indonesia: "02 Mei 2026, 14:30".
 *
 * Mendukung format input yang sama dengan formatTanggal.
 *
 * @param dateStr - String tanggal-waktu dari backend (bisa null atau undefined)
 * @returns Tanggal dan waktu dalam format "DD Bulan YYYY, HH:mm", atau string kosong jika input tidak valid
 */
export function formatTanggalDateTime(dateStr: string | null | undefined): string {
    // Kembalikan string kosong jika input kosong atau falsy
    if (!dateStr) {
        return '';
    }

    // Tambahkan "T00:00:00" untuk string date-only agar tidak terjadi timezone shift
    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(dateStr.trim())
        ? `${dateStr.trim()}T00:00:00`
        : dateStr.trim();

    const date = new Date(normalized);

    // Kembalikan string kosong jika hasil parsing tidak valid
    if (isNaN(date.getTime())) {
        return '';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = BULAN_ID[date.getMonth()];
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day} ${month} ${year}, ${hours}:${minutes}`;
}
