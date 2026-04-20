<?php

namespace App\Exports;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KgbMonitoringExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly ?string $unitKerjaId = null,
        private readonly ?string $golongan = null,
        private readonly ?string $status = null,
        private readonly int $months = 3,
    ) {}

    public function collection(): Collection
    {
        $service = app(KgbMonitoringService::class);
        $maxSisaHari = $this->months * 30;
        $maxKgbDate = Carbon::today()->addDays($maxSisaHari)->toDateString();

        $query = Pegawai::query()
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->with([
                'riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt'),
            ])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->when($this->unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $this->unitKerjaId))
            ->when($this->golongan !== null, fn ($q) => $q->byGolongan($this->golongan));

        $driver = DB::connection()->getDriverName();
        $kgbDateExpr = $driver === 'mysql'
            ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
            : "date(rp_kgb.tmt, '+2 years')";

        if ($this->status === null) {
            $query->whereRaw("{$kgbDateExpr} <= ?", [$maxKgbDate]);
        } else {
            $today = Carbon::today()->toDateString();
            match ($this->status) {
                'jatuh-tempo' => $query->whereRaw("{$kgbDateExpr} <= ?", [$today]),
                'segera' => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                    $today,
                    Carbon::today()->addDays(60)->toDateString(),
                ]),
                'mendekati' => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                    Carbon::today()->addDays(60)->toDateString(),
                    Carbon::today()->addDays(90)->toDateString(),
                ]),
                'aman' => $query->whereRaw("{$kgbDateExpr} > ?", [
                    Carbon::today()->addDays(90)->toDateString(),
                ]),
                default => $query->whereRaw("{$kgbDateExpr} <= ?", [$maxKgbDate]),
            };
        }

        return $query->get()->map(function (Pegawai $pegawai) use ($service): array {
            $kgbStatus = $service->getKgbStatus($pegawai);
            $riwayatAktif = $pegawai->riwayatPangkat->first();

            return [
                'nip' => $pegawai->nip ?? '-',
                'nama_lengkap' => $pegawai->nama_lengkap,
                'pangkat_gol' => $pegawai->nama_pangkat_lengkap ?? '-',
                'tmt_pangkat' => $riwayatAktif?->tmt?->toDateString() ?? '-',
                'tanggal_kgb_berikutnya' => $kgbStatus['tanggal_kgb_berikutnya']->toDateString(),
                'sisa_hari' => $kgbStatus['sisa_hari'],
                'status' => $kgbStatus['status'],
            ];
        });
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Lengkap',
            'Pangkat/Golongan',
            'TMT Pangkat',
            'KGB Berikutnya',
            'Sisa Hari',
            'Status',
        ];
    }

    public function map($row): array
    {
        return array_values($row);
    }
}
