<?php

namespace App\Listeners\UsulanKenaikanPangkat;

use App\Events\UsulanKenaikanPangkat\UsulanKpSkTerbit;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SinkronkanRiwayatPangkat
{
    public function handle(UsulanKpSkTerbit $event): void
    {
        DB::transaction(function () use ($event): void {
            $usulan = $event->usulan->fresh();

            if (! $usulan instanceof UsulanKenaikanPangkat) {
                return;
            }

            /** @var Pegawai $pegawai */
            $pegawai = Pegawai::query()
                ->whereKey($usulan->pegawai_id)
                ->lockForUpdate()
                ->firstOrFail();

            $riwayatAktif = RiwayatPangkat::query()
                ->where('pegawai_id', $pegawai->id)
                ->aktif()
                ->orderByDesc('tmt')
                ->first();

            $tmtBaru = $event->tmtPangkatBaru
                ?? $usulan->tmt_pangkat_baru?->toDateString()
                ?? sprintf('%04d-%02d-01', $usulan->periode_usul_tahun, $usulan->periode_usul_bulan);

            $mkgTahun = $event->masaKerjaTahun ?? $usulan->masa_kerja_tahun;
            $mkgBulan = $event->masaKerjaBulan ?? $usulan->masa_kerja_bulan;

            if ($mkgTahun === null || $mkgBulan === null) {
                [$calculatedTahun, $calculatedBulan] = $this->hitungMasaKerjaDariRiwayat(
                    $riwayatAktif,
                    $tmtBaru,
                );
                $mkgTahun ??= $calculatedTahun;
                $mkgBulan ??= $calculatedBulan;
            }

            RiwayatPangkat::query()
                ->where('pegawai_id', $pegawai->id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);

            RiwayatPangkat::query()->create([
                'pegawai_id' => $pegawai->id,
                'ref_pangkat_id' => $usulan->ref_pangkat_tujuan_id,
                'no_sk' => $usulan->nomor_sk,
                'tanggal_sk' => $usulan->tanggal_sk,
                'tmt' => $tmtBaru,
                'pejabat_penetap' => config('sikep.kp.pejabat_penetap', 'Biro Kepegawaian Mahkamah Agung RI'),
                'is_aktif' => true,
                'masa_kerja_tahun' => $mkgTahun,
                'masa_kerja_bulan' => $mkgBulan,
            ]);

            $pegawai->update(['ref_pangkat_id' => $usulan->ref_pangkat_tujuan_id]);

            activity()
                ->performedOn($usulan)
                ->withProperties(['usulan_kenaikan_pangkat_id' => $usulan->id])
                ->log("Riwayat pangkat disinkronkan dari usulan KP {$usulan->id}");
        });
    }

    /**
     * Hitung estimasi masa kerja golongan dari riwayat pangkat aktif sebelumnya.
     *
     * @return array{0: int, 1: int}
     */
    private function hitungMasaKerjaDariRiwayat(?RiwayatPangkat $riwayatAktif, string $tmtBaru): array
    {
        if ($riwayatAktif === null) {
            return [0, 0];
        }

        $totalBulanDasar = ((int) $riwayatAktif->masa_kerja_tahun * 12) + (int) $riwayatAktif->masa_kerja_bulan;

        if ($riwayatAktif->tmt === null) {
            return [intdiv($totalBulanDasar, 12), $totalBulanDasar % 12];
        }

        $tmtBaruCarbon = Carbon::parse($tmtBaru)->startOfDay();
        $tmtLamaCarbon = $riwayatAktif->tmt->copy()->startOfDay();

        $selisihBulan = $tmtBaruCarbon->greaterThan($tmtLamaCarbon)
            ? (int) $tmtLamaCarbon->diffInMonths($tmtBaruCarbon)
            : 0;

        $totalBulan = $totalBulanDasar + $selisihBulan;

        return [intdiv($totalBulan, 12), $totalBulan % 12];
    }
}
