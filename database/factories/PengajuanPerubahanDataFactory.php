<?php

namespace Database\Factories;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengajuanPerubahanDataFactory extends Factory
{
    protected $model = PengajuanPerubahanData::class;

    public function definition(): array
    {
        $pengajuId = Pegawai::factory();

        return [
            'nomor_pengajuan' => 'PGJ-'.now()->format('YmdHis').'-'.str()->upper(str()->random(6)),
            'pengaju_id' => $pengajuId,
            'subject_pegawai_id' => fn (array $attrs) => $attrs['pengaju_id'],
            'validator_id' => null,
            'jenis_pengaju' => 'pegawai',
            'domain' => 'profil_pribadi',
            'aksi' => 'update',
            'scope_key' => fn (array $attrs) => "profil_pribadi:update:pegawai:{$attrs['subject_pegawai_id']}",
            'target_type' => 'pegawai',
            'target_id' => fn (array $attrs) => $attrs['subject_pegawai_id'],
            'status' => StatusPengajuanPerubahanData::Pending,
            'before_payload' => ['nama_lengkap' => 'Nama Lama'],
            'after_payload' => ['nama_lengkap' => 'Nama Baru'],
            'changed_fields' => ['nama_lengkap'],
            'lampiran_paths' => [],
            'alasan_penolakan' => null,
            'submitted_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
        ];
    }
}
