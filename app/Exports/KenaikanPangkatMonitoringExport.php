<?php

namespace App\Exports;

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
        private readonly ?int $periodeBulan = null,
        private readonly ?int $periodeTahun = null,
        private readonly ?string $unitKerjaId = null,
        private readonly ?string $golongan = null,
    ) {}

    public function collection(): Collection
    {
        $service = app(KenaikanPangkatMonitoringService::class);

        return $service
            ->getUpcomingKenaikanPangkatForExport(
                $this->periodeBulan,
                $this->unitKerjaId,
                $this->golongan,
                $this->periodeTahun,
            )
            ->map(fn (array $row): array => [
                'nip' => $row['nip'] ?? '-',
                'nama_lengkap' => $row['nama_lengkap'],
                'pangkat_saat_ini' => $row['pangkat_saat_ini'] ?? '-',
                'tmt_pangkat' => $row['tmt_pangkat'] ?? '-',
                'tmt_kp_berikutnya' => $row['tmt_kp_berikutnya'],
                'periode_usul' => $row['periode_usul'],
                'batas_usul' => $row['batas_usul'],
                'sisa_hari_usul' => $row['sisa_hari_usul'],
                'status' => $row['status'],
            ]);
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
