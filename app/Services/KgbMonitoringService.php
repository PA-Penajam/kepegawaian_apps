<?php

namespace App\Services;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class KgbMonitoringService
{
    public function getUpcomingKgb(int $months = 3): Collection
    {
        $maxSisaHari = $months * 30;

        return Pegawai::query()
            ->with([
                'pangkat',
                'riwayatPangkat' => fn ($query) => $query->aktif()->latest('tmt'),
            ])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->get()
            ->map(function (Pegawai $pegawai): ?array {
                $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);

                if ($riwayatPangkatAktif === null) {
                    return null;
                }

                $statusKgb = $this->getKgbStatus($pegawai);

                return [
                    'id' => $pegawai->id,
                    'nip' => $pegawai->nip,
                    'nama_lengkap' => $pegawai->nama_lengkap,
                    'pangkat_gol' => $pegawai->nama_pangkat_lengkap,
                    'tmt_pangkat' => $riwayatPangkatAktif->tmt?->toDateString(),
                    'tanggal_kgb_berikutnya' => $statusKgb['tanggal_kgb_berikutnya']->toDateString(),
                    'sisa_hari' => $statusKgb['sisa_hari'],
                    'status' => $statusKgb['status'],
                    'kgb' => $statusKgb,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null && $item['sisa_hari'] <= $maxSisaHari)
            ->sortBy('sisa_hari')
            ->values();
    }

    public function getKgbStatus(Pegawai $pegawai): array
    {
        $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);

        if ($riwayatPangkatAktif === null || $riwayatPangkatAktif->tmt === null) {
            throw new InvalidArgumentException('Pegawai tidak memiliki riwayat pangkat aktif.');
        }

        $tanggalKgbBerikutnya = Carbon::parse($riwayatPangkatAktif->tmt)->addYears(2)->startOfDay();
        $sisaHari = (int) Carbon::today()->diffInDays($tanggalKgbBerikutnya, false);

        return [
            'tanggal_kgb_berikutnya' => $tanggalKgbBerikutnya,
            'sisa_hari' => $sisaHari,
            'status' => $this->resolveStatusLabel($sisaHari),
        ];
    }

    protected function getRiwayatPangkatAktif(Pegawai $pegawai): ?RiwayatPangkat
    {
        if (! $pegawai->relationLoaded('riwayatPangkat')) {
            $pegawai->load([
                'riwayatPangkat' => fn ($query) => $query->aktif()->latest('tmt'),
            ]);
        }

        return $pegawai->riwayatPangkat->first();
    }

    protected function resolveStatusLabel(int $sisaHari): string
    {
        if ($sisaHari <= 0) {
            return 'Sudah Jatuh Tempo';
        }

        if ($sisaHari <= 60) {
            return 'Segera';
        }

        if ($sisaHari <= 90) {
            return 'Mendekati';
        }

        return 'Aman';
    }
}
