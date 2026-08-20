<?php

namespace App\Exports;

use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new PegawaiDataSheet,
            new ReferensiJabatanSheet,
            new ReferensiUnitKerjaSheet,
            new ReferensiPangkatSheet,
        ];
    }
}

class PegawaiDataSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Data Pegawai';
    }

    public function headings(): array
    {
        return [
            'NIP (18 Digit)',
            'Nama Lengkap (beserta gelar)',
            'Jabatan',
            'Unit Kerja',
            'Golongan / Pangkat',
            'Email (Opsional)',
            'No WhatsApp (Opsional)',
        ];
    }

    public function collection(): Collection
    {
        return collect([
            [
                'nip' => "'199107132020121003",
                'nama' => 'Ahmad Fauzi, S.Kom.',
                'jabatan' => 'Pranata Komputer Ahli Pertama',
                'unit_kerja' => 'Subbagian Perencanaan, TI, dan Pelaporan',
                'golongan' => 'III/a',
                'email' => 'ahmad.fauzi@pa-penajam.go.id',
                'no_wa' => '081234567890',
            ],
            [
                'nip' => "'198411192011011012",
                'nama' => 'Siti Aminah, S.H.',
                'jabatan' => 'Kasubbag Kepegawaian, Organisasi, dan Tatalaksana',
                'unit_kerja' => 'Subbagian Kepegawaian, Organisasi, dan Tatalaksana',
                'golongan' => 'III/d',
                'email' => 'siti.aminah@pa-penajam.go.id',
                'no_wa' => '081298765432',
            ],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF0B192C'],
                ],
            ],
        ];
    }
}

class ReferensiJabatanSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Referensi Jabatan';
    }

    public function headings(): array
    {
        return ['Kode Jabatan', 'Nama Jabatan Resmi', 'Jenis Jabatan'];
    }

    public function collection(): Collection
    {
        return RefJabatan::query()
            ->orderBy('nama')
            ->get()
            ->map(fn ($item) => [
                'kode' => $item->kode ?? '-',
                'nama' => $item->nama,
                'jenis' => $item->jenis_jabatan?->value ?? '-',
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E3A8A'],
                ],
            ],
        ];
    }
}

class ReferensiUnitKerjaSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Referensi Unit Kerja';
    }

    public function headings(): array
    {
        return ['Kode Unit Kerja', 'Nama Unit Kerja Resmi'];
    }

    public function collection(): Collection
    {
        return RefUnitKerja::query()
            ->orderBy('nama')
            ->get()
            ->map(fn ($item) => [
                'kode' => $item->kode ?? '-',
                'nama' => $item->nama,
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E3A8A'],
                ],
            ],
        ];
    }
}

class ReferensiPangkatSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Referensi Golongan Pangkat';
    }

    public function headings(): array
    {
        return ['Kode / Golongan', 'Nama Pangkat Resmi', 'Ruang', 'Tingkat'];
    }

    public function collection(): Collection
    {
        return RefPangkat::query()
            ->orderBy('urutan')
            ->get()
            ->map(fn ($item) => [
                'kode' => $item->kode,
                'nama' => $item->nama,
                'ruang' => $item->ruang ?? '-',
                'tingkat' => $item->tingkat ?? '-',
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E3A8A'],
                ],
            ],
        ];
    }
}
