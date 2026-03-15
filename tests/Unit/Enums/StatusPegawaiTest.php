<?php

use App\Enums\StatusPegawai;

describe('StatusPegawai Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(StatusPegawai::cases())->toHaveCount(5);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(StatusPegawai::cases(), 'value');
        expect($values)->toContain('aktif');
        expect($values)->toContain('mutasi_keluar');
        expect($values)->toContain('pensiun');
        expect($values)->toContain('meninggal');
        expect($values)->toContain('diberhentikan');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(StatusPegawai::Aktif->label())->toBe('Aktif');
        expect(StatusPegawai::MutasiKeluar->label())->toBe('Mutasi Keluar');
        expect(StatusPegawai::Pensiun->label())->toBe('Pensiun');
        expect(StatusPegawai::Meninggal->label())->toBe('Meninggal');
        expect(StatusPegawai::Diberhentikan->label())->toBe('Diberhentikan');
    });

    it('dapat mengakses value', function () {
        expect(StatusPegawai::Aktif->value)->toBe('aktif');
    });
});
