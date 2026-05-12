<?php

namespace App\Listeners\UsulanKenaikanPangkat;

use App\Events\UsulanKenaikanPangkat\UsulanKpSkTerbit;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Illuminate\Support\Facades\DB;

class SinkronkanRiwayatPangkat
{
    public function handle(UsulanKpSkTerbit $event): void
    {
        DB::transaction(function () use ($event): void {
            $usulan = $event->usulan->fresh();

            RiwayatPangkat::query()
                ->where('pegawai_id', $usulan->pegawai_id)
                ->update(['is_aktif' => false]);

            RiwayatPangkat::query()->create([
                'pegawai_id' => $usulan->pegawai_id,
                'ref_pangkat_id' => $usulan->ref_pangkat_tujuan_id,
                'no_sk' => $usulan->nomor_sk,
                'tanggal_sk' => $usulan->tanggal_sk,
                'tmt' => $usulan->tanggal_sk,
                'pejabat_penetap' => config('sikep.kp.pejabat_penetap', 'Biro Kepegawaian Mahkamah Agung RI'),
                'is_aktif' => true,
                'masa_kerja_tahun' => 0,
                'masa_kerja_bulan' => 0,
            ]);

            Pegawai::query()
                ->whereKey($usulan->pegawai_id)
                ->update(['ref_pangkat_id' => $usulan->ref_pangkat_tujuan_id]);

            activity()
                ->performedOn($usulan)
                ->withProperties(['usulan_kenaikan_pangkat_id' => $usulan->id])
                ->log("Riwayat pangkat disinkronkan dari usulan KP {$usulan->id}");
        });
    }
}
