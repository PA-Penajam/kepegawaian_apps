<?php

namespace Database\Factories\Cuti;

use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CutiPengajuan>
 */
class CutiPengajuanFactory extends Factory
{
    protected $model = CutiPengajuan::class;

    public function definition(): array
    {
        $fakerId = fake('id_ID');
        $tanggalMulai = $fakerId->dateTimeBetween('+1 week', '+2 months');
        $durasiHari = $fakerId->numberBetween(1, 12);
        $tanggalSelesai = (clone $tanggalMulai)->modify("+{$durasiHari} days");
        $jumlahHariKerja = max(1, (int) ceil($durasiHari * 5 / 7));

        return [
            'nomor_pengajuan' => 'CUTI-'.now()->format('Ym').'-'.$fakerId->unique()->numerify('####'),
            'pegawai_nip' => Pegawai::query()->inRandomOrder()->value('nip'),
            'jenis_cuti_kode' => CutiJenisMaster::query()->inRandomOrder()->value('kode') ?? 'CT',
            'tanggal_mulai' => $tanggalMulai->format('Y-m-d'),
            'tanggal_selesai' => $tanggalSelesai->format('Y-m-d'),
            'jumlah_hari_kerja' => $jumlahHariKerja,
            'alasan' => $fakerId->sentence(10),
            'alamat_selama_cuti' => $fakerId->optional()->address(),
            'nomor_telp_selama_cuti' => $fakerId->optional()->numerify('08##########'),
            'state' => 'DRAFT',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'state' => 'DIAJUKAN',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'state' => 'DISETUJUI',
            'submitted_at' => now()->subDays(3),
            'approved_at' => now(),
        ]);
    }
}
