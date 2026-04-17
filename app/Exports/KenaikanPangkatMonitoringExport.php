<?php

namespace App\Exports;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Services\KenaikanPangkatMonitoringService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KenaikanPangkatMonitoringExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        private readonly ?string $periode = null,
        private readonly ?string $unitKerjaId = null,
        private readonly ?string $golongan = null,
    ) {}

    public function collection(): Collection
    {
        $service = app(KenaikanPangkatMonitoringService::class);

        $query = Pegawai::query()
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kp', function ($join) {
                $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kp.is_aktif', true);
            })
            ->with([
                'riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat')->orderByDesc('tmt'),
            ])
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->when($this->unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $this->unitKerjaId))
            ->when($this->golongan !== null, fn ($q) => $q->byGolongan($this->golongan))
            ->orderBy('nama_lengkap');

        return $query->get()
            ->filter(fn (Pegawai $p) => $p->riwayatPangkat->isNotEmpty())
            ->map(function (Pegawai $pegawai) use ($service): array {
                $riwayatAktif = $pegawai->riwayatPangkat->first();
                $status = $service->getKpStatus($pegawai);

                return [
                    'nip'                => $pegawai->nip ?? '-',
                    'nama_lengkap'       => $pegawai->nama_lengkap,
                    'pangkat_saat_ini'   => $riwayatAktif->pangkat?->nama ?? '-',
                    'tmt_pangkat'        => $riwayatAktif->tmt?->toDateString() ?? '-',
                    'tmt_kp_berikutnya'  => $status['tmt_kp_berikutnya']->toDateString(),
                    'periode_usul'       => $status['periode_usul'],
                    'batas_usul'         => $status['batas_usul']->toDateString(),
                    'sisa_hari_usul'     => $status['sisa_hari_usul'],
                    'status'             => $status['status'],
                ];
            });
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Lengkap',
            'Pangkat Saat Ini',
            'TMT Pangkat',
            'TMT KP Berikutnya',
            'Periode Usul',
            'Batas Usul',
            'Sisa Hari Usul',
            'Status',
        ];
    }

    public function map($row): array
    {
        return array_values($row);
    }
}