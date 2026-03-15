<?php

use App\Enums\JenisKelamin;

describe('JenisKelamin Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(JenisKelamin::cases())->toHaveCount(2);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(JenisKelamin::cases(), 'value');
        expect($values)->toContain('laki_laki');
        expect($values)->toContain('perempuan');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(JenisKelamin::LakiLaki->label())->toBe('Laki-Laki');
        expect(JenisKelamin::Perempuan->label())->toBe('Perempuan');
    });

    it('dapat mengakses value', function () {
        expect(JenisKelamin::LakiLaki->value)->toBe('laki_laki');
    });
});
