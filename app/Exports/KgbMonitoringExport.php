<?php

namespace App\Exports;

use App\Services\KgbMonitoringService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KgbMonitoringExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?string $unitKerjaId;
    protected ?string $golongan;
    protected ?string $status;
    protected int $months;

    public function __construct(
        ?string $unitKerjaId = null,
        ?string $golongan = null,
        ?string $status = null,
        int $months = 3
    ) {
        $this->unitKerjaId = $unitKerjaId;
        $this->golongan = $golongan;
        $this->status = $status;
        $this->months = $months;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $service = new KgbMonitoringService();
        
        // Get paginated data first
        $paginatedData = $service->getUpcomingKgb(
            $this->months,
            100, // per page cukup besar untuk ekspor
            $this->unitKerjaId,
            $this->golongan,
            $this->status
        );
        
        // Convert to simple collection for export
        return collect($paginatedData->items())->map(function ($item) {
            return (object) [
                'nip' => $item['nip'] ?? '',
                'nama_lengkap' => $item['nama_lengkap'] ?? '',
                'pangkat_gol' => $item['pangkat_gol'] ?? '',
                'tmt_pangkat' => $item['tmt_pangkat'] ?? '',
                'tanggal_kgb_berikutnya' => $item['tanggal_kgb_berikutnya'] ?? '',
                'sisa_hari' => $item['sisa_hari'] ?? '',
                'status' => $item['status'] ?? '',
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'NIP',
            'Nama Lengkap',
            'Unit Kerja',
            'Golongan',
            'Kenaikan Gaji Berkala Sebelumnya',
            'Kenaikan Gaji Berkala Berikutnya',
            'Sisa Waktu',
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        // Konversi sisa_hari ke format "X hari" atau "X bulan Y hari"
        $sisaWaktu = $this->formatSisaWaktu($row->sisa_hari ?? 0);
        
        return [
            $row->nip ?? '',
            $row->nama_lengkap ?? '',
            '', // Unit Kerja - perlu diambil dari pegawai jika ada relasi
            $row->pangkat_gol ?? '',
            $row->tmt_pangkat ? date('d-m-Y', strtotime($row->tmt_pangkat)) : '-',
            $row->tanggal_kgb_berikutnya ? date('d-m-Y', strtotime($row->tanggal_kgb_berikutnya)) : '-',
            $sisaWaktu,
        ];
    }
    
    /**
     * Format sisa waktu dari hari ke format yang lebih mudah dibaca
     */
    protected function formatSisaWaktu(int $sisaHari): string
    {
        if ($sisaHari <= 0) {
            return 'Sudah jatuh tempo';
        }
        
        if ($sisaHari < 30) {
            return "{$sisaHari} hari";
        }
        
        $bulan = floor($sisaHari / 30);
        $hari = $sisaHari % 30;
        
        if ($hari === 0) {
            return "{$bulan} bulan";
        }
        
        return "{$bulan} bulan {$hari} hari";
    }
}