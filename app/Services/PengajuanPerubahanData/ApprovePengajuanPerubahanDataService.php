<?php

namespace App\Services\PengajuanPerubahanData;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ApprovePengajuanPerubahanDataService
{
    private const ALLOWED_PEGAWAI_FIELDS = [
        'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
        'status_perkawinan', 'alamat', 'no_telepon', 'email',
    ];

    private const ALLOWED_KELUARGA_FIELDS = [
        'hubungan', 'nama', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'pekerjaan', 'pendidikan', 'keterangan',
    ];

    public function handle(PengajuanPerubahanData $pengajuan, Pegawai $validator): void
    {
        DB::transaction(function () use ($pengajuan, $validator): void {
            $locked = PengajuanPerubahanData::query()
                ->whereKey($pengajuan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== StatusPengajuanPerubahanData::Pending) {
                throw new \DomainException('Pengajuan perubahan data sudah diproses sebelumnya.');
            }

            if ($locked->pengaju_id === $validator->id || $locked->subject_pegawai_id === $validator->id) {
                throw new \DomainException('Validator tidak boleh menyetujui pengajuan perubahan data miliknya sendiri.');
            }

            if ($locked->domain === 'profil_pribadi') {
                if ($locked->target_id !== $locked->subject_pegawai_id) {
                    throw new \DomainException('Target pegawai tidak sesuai dengan pegawai subjek pengajuan.');
                }

                $pegawaiPayload = Arr::only($locked->after_payload, self::ALLOWED_PEGAWAI_FIELDS);
                if (array_key_exists('email', $pegawaiPayload) && filled($pegawaiPayload['email'])) {
                    $email = trim((string) $pegawaiPayload['email']);
                    $pegawaiPayload['email'] = $email;

                    $emailConflict = Pegawai::withTrashed()
                        ->where('email', $email)
                        ->whereKeyNot($locked->target_id)
                        ->exists();

                    if ($emailConflict) {
                        throw new \DomainException('Email sudah digunakan oleh pegawai lain.');
                    }
                }

                Pegawai::query()->whereKey($locked->target_id)->lockForUpdate()->firstOrFail()
                    ->update($pegawaiPayload);
            }

            if (in_array($locked->domain, ['pasangan', 'anak', 'orang_tua', 'keluarga_lain'], true)) {
                $this->applyKeluargaMutation($locked);
            }

            $locked->update([
                'status' => StatusPengajuanPerubahanData::Approved,
                'validator_id' => $validator->id,
                'approved_at' => now(),
            ]);
        });

        Cache::forget('pending_pengajuan_count');
        $pengajuan->refresh();
    }

    private function applyKeluargaMutation(PengajuanPerubahanData $pengajuan): void
    {
        if ($pengajuan->aksi === 'create') {
            Pegawai::query()->findOrFail($pengajuan->subject_pegawai_id)
                ->keluarga()
                ->create(Arr::only($pengajuan->after_payload, self::ALLOWED_KELUARGA_FIELDS));

            return;
        }

        if ($pengajuan->aksi === 'update') {
            $keluarga = Keluarga::query()->findOrFail($pengajuan->target_id);
            if ($keluarga->pegawai_id !== $pengajuan->subject_pegawai_id) {
                throw new \DomainException('Data keluarga tidak sesuai dengan pegawai subjek pengajuan.');
            }

            $keluarga->update(Arr::only($pengajuan->after_payload, self::ALLOWED_KELUARGA_FIELDS));

            return;
        }

        if ($pengajuan->aksi === 'delete') {
            $keluarga = Keluarga::query()->findOrFail($pengajuan->target_id);
            if ($keluarga->pegawai_id !== $pengajuan->subject_pegawai_id) {
                throw new \DomainException('Data keluarga tidak sesuai dengan pegawai subjek pengajuan.');
            }

            $keluarga->delete();
        }
    }
}
