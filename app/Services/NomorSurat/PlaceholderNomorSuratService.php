<?php

namespace App\Services\NomorSurat;

use App\Models\NomorSurat\NomorSuratReservation;
use App\Models\NomorSurat\NomorSuratSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PlaceholderNomorSuratService
 *
 * Generat nomor surat dengan format MA:
 * {kode_satker}/{nomor_urut}/{klasifikasi}/{bulan_romawi}/{tahun}
 *
 * Contoh: W1-U1/123/KP.01.1/V/2026
 *
 * Pola reset:
 * - Reset per TAHUN per klasifikasi (bukan per bulan)
 * - Bulan romawi adalah bagian format, bukan trigger reset
 * - Sequence unik pada (klasifikasi, tahun)
 *
 * Hole policy:
 * - Nomor yang di-release hanya ditandai released, tidak di-reassign
 */
class PlaceholderNomorSuratService implements NomorSuratService
{
    public function __construct(
        private readonly string $kodeSatker = 'W1-U1',
    ) {}

    /**
     * Generate nomor surat lengkap + naikkan sequence.
     */
    public function generate(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): string
    {
        [$bulan, $tahun] = $this->resolveBulanTahun($bulan, $tahun);

        return DB::transaction(function () use ($klasifikasi, $bulan, $tahun) {
            $sequence = NomorSuratSequence::query()
                ->where('klasifikasi', $klasifikasi)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->firstOrCreate([
                    'klasifikasi' => $klasifikasi,
                    'tahun' => $tahun,
                ], [
                    'next_number' => 1,
                ]);

            $nomorUrut = $sequence->next_number;

            $sequence->next_number = $nomorUrut + 1;
            $sequence->save();

            $romanBulan = $this->toRoman($bulan);
            $nomorLengkap = "{$this->kodeSatker}/{$nomorUrut}/{$klasifikasi}/{$romanBulan}/{$tahun}";

            return $nomorLengkap;
        });
    }

    /**
     * Reserve nomor (hole policy - sequence tidak naik).
     */
    public function reserve(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): NomorSuratReservation
    {
        [$bulan, $tahun] = $this->resolveBulanTahun($bulan, $tahun);

        return DB::transaction(function () use ($klasifikasi, $bulan, $tahun) {
            $sequence = NomorSuratSequence::query()
                ->where('klasifikasi', $klasifikasi)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->firstOrCreate([
                    'klasifikasi' => $klasifikasi,
                    'tahun' => $tahun,
                ], [
                    'next_number' => 1,
                ]);

            $nomorUrut = $sequence->next_number;

            $sequence->next_number = $nomorUrut + 1;
            $sequence->save();

            $romanBulan = $this->toRoman($bulan);
            $nomorLengkap = "{$this->kodeSatker}/{$nomorUrut}/{$klasifikasi}/{$romanBulan}/{$tahun}";

            return NomorSuratReservation::create([
                'nomor_urut' => $nomorUrut,
                'nomor_lengkap' => $nomorLengkap,
                'klasifikasi' => $klasifikasi,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'status' => 'reserved',
                'reserved_at' => now(),
            ]);
        });
    }

    /**
     * Konfirmasi reservation.
     */
    public function confirm(string $reservationId): void
    {
        NomorSuratReservation::where('id', $reservationId)->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Release reservation (hole tetap ada).
     */
    public function release(string $reservationId): void
    {
        NomorSuratReservation::where('id', $reservationId)->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    /**
     * Konversi integer 1-12 ke romawi.
     */
    private function toRoman(int $n): string
    {
        $map = [
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $roman) {
            while ($n >= $value) {
                $result .= $roman;
                $n -= $value;
            }
        }

        return $result;
    }

    private function resolveBulanTahun(?int $bulan, ?int $tahun): array
    {
        $now = Carbon::now();

        return [
            $bulan ?? $now->month,
            $tahun ?? $now->year,
        ];
    }
}
