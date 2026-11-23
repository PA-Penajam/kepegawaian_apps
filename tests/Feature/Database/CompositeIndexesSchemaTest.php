<?php

use Illuminate\Support\Facades\Schema;

it('tabel monitoring dan iam memiliki indeks komposit yang diperlukan', function (): void {
    $riwayatPangkatIndexes = collect(Schema::getIndexes('riwayat_pangkat'))->keyBy('name');
    expect($riwayatPangkatIndexes->has('idx_riwayat_pangkat_pegawai_aktif_deleted'))->toBeTrue()
        ->and($riwayatPangkatIndexes->get('idx_riwayat_pangkat_pegawai_aktif_deleted')['columns'])
        ->toBe(['pegawai_id', 'is_aktif', 'deleted_at']);

    $riwayatJabatanIndexes = collect(Schema::getIndexes('riwayat_jabatan'))->keyBy('name');
    expect($riwayatJabatanIndexes->has('idx_riwayat_jabatan_pegawai_aktif_deleted'))->toBeTrue()
        ->and($riwayatJabatanIndexes->get('idx_riwayat_jabatan_pegawai_aktif_deleted')['columns'])
        ->toBe(['pegawai_id', 'is_aktif', 'deleted_at']);

    $hukumanDisiplinIndexes = collect(Schema::getIndexes('hukuman_disiplin'))->keyBy('name');
    expect($hukumanDisiplinIndexes->has('idx_hukuman_disiplin_pegawai_deleted_selesai'))->toBeTrue()
        ->and($hukumanDisiplinIndexes->get('idx_hukuman_disiplin_pegawai_deleted_selesai')['columns'])
        ->toBe(['pegawai_id', 'deleted_at', 'tmt_selesai']);

    $iamSsoCodesIndexes = collect(Schema::getIndexes('iam_sso_codes'))->keyBy('name');
    expect($iamSsoCodesIndexes->has('idx_iam_sso_codes_expires_at'))->toBeTrue()
        ->and($iamSsoCodesIndexes->get('idx_iam_sso_codes_expires_at')['columns'])
        ->toBe(['expires_at']);

    $cutiLedgerIndexes = collect(Schema::getIndexes('cuti_saldo_ledger'))->keyBy('name');
    expect($cutiLedgerIndexes->has('idx_pegawai_jenis_tahun'))->toBeTrue();
});

it('tiga tabel cuti yatim tidak ada pada skema database', function (): void {
    expect(Schema::hasTable('cuti_konfigurasi'))->toBeFalse()
        ->and(Schema::hasTable('cuti_pengajuan_periode'))->toBeFalse()
        ->and(Schema::hasTable('cuti_jenis_per_status_pegawai'))->toBeFalse();
});
