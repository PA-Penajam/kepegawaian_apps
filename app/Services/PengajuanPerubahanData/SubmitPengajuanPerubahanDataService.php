<?php

namespace App\Services\PengajuanPerubahanData;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;

class SubmitPengajuanPerubahanDataService
{
    public function handle(Pegawai $pengaju, array $payload, string $jenisPengaju): PengajuanPerubahanData
    {
        $subjectPegawaiId = $this->resolveSubjectPegawaiId($payload);
        $beforePayload = $this->resolveBeforePayload($payload);
        $changedFields = array_keys($payload['after_payload']);
        $scopeKey = $this->makeScopeKey($subjectPegawaiId, $payload);

        $pengajuan = PengajuanPerubahanData::query()->create([
            'nomor_pengajuan'    => 'PGJ-'.now()->format('YmdHis').'-'.str()->upper(str()->random(6)),
            'pengaju_id'         => $pengaju->id,
            'subject_pegawai_id' => $subjectPegawaiId,
            'jenis_pengaju'      => $jenisPengaju,
            'domain'             => $payload['domain'],
            'aksi'               => $payload['aksi'],
            'scope_key'          => $scopeKey,
            'target_type'        => $payload['target_type'],
            'target_id'          => $payload['target_id'] ?? null,
            'status'             => StatusPengajuanPerubahanData::Pending,
            'before_payload'     => $beforePayload,
            'after_payload'      => $payload['after_payload'],
            'changed_fields'     => $changedFields,
            'lampiran_paths'     => [],
            'submitted_at'       => now(),
        ]);

        $lampiranPaths = collect($payload['lampiran'] ?? [])
            ->map(fn ($file) => $file->store("pengajuan/{$pengajuan->id}", 'local'))
            ->filter()
            ->values()
            ->all();

        if ($lampiranPaths) {
            $pengajuan->update(['lampiran_paths' => $lampiranPaths]);
        }

        return $pengajuan;
    }

    private function resolveSubjectPegawaiId(array $payload): string
    {
        if ($payload['target_type'] === 'pegawai') {
            return (string) $payload['target_id'];
        }

        if (! empty($payload['subject_pegawai_id'])) {
            return (string) $payload['subject_pegawai_id'];
        }

        if (($payload['aksi'] ?? '') === 'create' || empty($payload['target_id'])) {
            throw new \InvalidArgumentException('subject_pegawai_id wajib dikirim untuk aksi create keluarga.');
        }

        return (string) Keluarga::query()->findOrFail($payload['target_id'])->pegawai_id;
    }

    private function resolveBeforePayload(array $payload): array
    {
        if ($payload['domain'] === 'profil_pribadi') {
            $targetPegawai = Pegawai::query()->findOrFail($payload['target_id']);

            return array_filter([
                'nama_lengkap'      => $targetPegawai->nama_lengkap,
                'nik'               => $targetPegawai->nik ?? null,
                'tempat_lahir'      => $targetPegawai->tempat_lahir ?? null,
                'tanggal_lahir'     => $targetPegawai->tanggal_lahir?->toDateString() ?? null,
                'status_perkawinan' => $targetPegawai->status_perkawinan?->value ?? $targetPegawai->getRawOriginal('status_perkawinan'),
                'alamat'            => $targetPegawai->alamat ?? null,
                'no_telepon'        => $targetPegawai->no_telepon ?? null,
                'email'             => $targetPegawai->email ?? null,
            ], fn ($v) => $v !== null);
        }

        if ($payload['aksi'] === 'create') {
            return [];
        }

        $keluarga = Keluarga::query()->findOrFail($payload['target_id']);

        return [
            'hubungan' => $keluarga->getRawOriginal('hubungan'),
            'nama' => $keluarga->nama,
            'tempat_lahir' => $keluarga->tempat_lahir,
            'tanggal_lahir' => $keluarga->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $keluarga->getRawOriginal('jenis_kelamin'),
            'pekerjaan' => $keluarga->pekerjaan,
            'pendidikan' => $keluarga->pendidikan,
            'keterangan' => $keluarga->keterangan,
        ];
    }

    private function makeScopeKey(string $subjectPegawaiId, array $payload): string
    {
        if ($payload['target_id'] ?? null) {
            return "{$payload['domain']}:{$payload['aksi']}:{$payload['target_type']}:{$payload['target_id']}";
        }

        return "{$payload['domain']}:{$payload['aksi']}:{$subjectPegawaiId}:".sha1(json_encode($payload['after_payload']));
    }
}
