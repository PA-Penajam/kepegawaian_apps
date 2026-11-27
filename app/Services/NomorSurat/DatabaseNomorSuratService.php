<?php

namespace App\Services\NomorSurat;

use App\Models\NomorSurat\NomorSuratReservation;
use App\Models\NomorSurat\NomorSuratSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DatabaseNomorSuratService
 *
 * @deprecated Penomoran surat dinas resmi kini terpusat di SiSukma melalui `PenomoranSuratGateway`.
 *             Kelas ini dipertahankan sementara hanya untuk kompatibilitas mundur saat `sisukma.enabled=false`.
 *
 * Implementasi produksi NomorSuratService berbasis tabel sequence database.
 * Sequence dinaikkan atomik di dalam transaksi dengan lockForUpdate,
 * sehingga aman terhadap request concurrent.
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
class DatabaseNomorSuratService implements NomorSuratService
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

        return DB::transaction(function () use ($klasifikasi, $bulan, $tahun): string {
            $nomorUrut = $this->reserveNextNumber($klasifikasi, $tahun);
            $romanBulan = $this->toRoman($bulan);

            return "{$this->kodeSatker}/{$nomorUrut}/{$klasifikasi}/{$romanBulan}/{$tahun}";
        });
    }

    /**
     * Reserve nomor (hole policy - sequence tidak naik).
     */
    public function reserve(string $klasifikasi, ?int $bulan = null, ?int $tahun = null): NomorSuratReservation
    {
        [$bulan, $tahun] = $this->resolveBulanTahun($bulan, $tahun);

        return DB::transaction(function () use ($klasifikasi, $bulan, $tahun): NomorSuratReservation {
            $nomorUrut = $this->reserveNextNumber($klasifikasi, $tahun);
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
        DB::transaction(function () use ($reservationId): void {
            $reservation = NomorSuratReservation::query()
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== 'reserved') {
                throw new \DomainException(
                    "Reservasi nomor surat tidak dapat dikonfirmasi karena status saat ini adalah '{$reservation->status}'."
                );
            }

            $reservation->status = 'confirmed';
            $reservation->confirmed_at = now();
            $reservation->save();
        });
    }

    /**
     * Release reservation (hole tetap ada).
     */
    public function release(string $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = NomorSuratReservation::query()
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== 'reserved') {
                throw new \DomainException(
                    "Reservasi nomor surat tidak dapat dilepas karena status saat ini adalah '{$reservation->status}'."
                );
            }

            $reservation->status = 'released';
            $reservation->released_at = now();
            $reservation->save();
        });
    }

    /**
     * Inisialisasi sequence bila belum ada secara race-safe lalu ambil dan naikkan counter.
     */
    private function reserveNextNumber(string $klasifikasi, int $tahun): int
    {
        $now = now();

        NomorSuratSequence::query()->insertOrIgnore([
            'id' => strtolower((string) Str::ulid()),
            'klasifikasi' => $klasifikasi,
            'tahun' => $tahun,
            'next_number' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = NomorSuratSequence::query()
            ->where('klasifikasi', $klasifikasi)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->firstOrFail();

        $nomorUrut = $sequence->next_number;

        $sequence->next_number = $nomorUrut + 1;
        $sequence->save();

        return $nomorUrut;
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

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveBulanTahun(?int $bulan, ?int $tahun): array
    {
        $now = Carbon::now();
        $resolvedBulan = $bulan ?? $now->month;
        $resolvedTahun = $tahun ?? $now->year;

        if ($resolvedBulan < 1 || $resolvedBulan > 12) {
            throw new \InvalidArgumentException(
                "Bulan harus bernilai antara 1 hingga 12, diterima: {$resolvedBulan}."
            );
        }

        return [
            $resolvedBulan,
            $resolvedTahun,
        ];
    }
}
