<?php

namespace App\Services\NomorSurat;

use App\Models\NomorSurat\NomorSuratReservation;

/**
 * Kontrak layanan pembuatan nomor surat resmi.
 *
 * Implementasi bertanggung jawab atas:
 * - Pembuatan nomor dengan sequence atomik dan aman concurrent
 * - Reservasi nomor untuk proses approval multi-langkah
 * - Hole policy: nomor yang di-release tidak di-backfill
 */
interface NomorSuratService
{
    /**
     * Generate nomor surat lengkap dan simpan sequence.
     *
     * @param  string  $klasifikasi  Kode klasifikasi surat (contoh: "KP.01.1")
     * @param  int|null  $bulan  Bulan 1-12. Null = bulan sekarang
     * @param  int|null  $tahun  Tahun 4 digit. Null = tahun sekarang
     * @return string Nomor lengkap (contoh: "W1-U1/123/KP.01.1/V/2026")
     */
    public function generate(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): string;

    /**
     * Reserve nomor surat tanpa mengubah sequence.
     */
    public function reserve(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): NomorSuratReservation;

    /**
     * Konfirmasi reservation menjadi nomor resmi terpakai.
     *
     * @param  string  $reservationId  ULID reservation
     */
    public function confirm(string $reservationId): void;

    /**
     * Release reservation yang tidak terpakai.
     * Nomor tidak di-backfill (hole policy).
     *
     * @param  string  $reservationId  ULID reservation
     */
    public function release(string $reservationId): void;
}
