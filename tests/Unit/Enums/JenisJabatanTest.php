<?php

use App\Enums\JenisJabatan;

describe('JenisJabatan Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(JenisJabatan::cases())->toHaveCount(3);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(JenisJabatan::cases(), 'value');
        expect($values)->toContain('struktural');
        expect($values)->toContain('fungsional');
        expect($values)->toContain('pelaksana');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(JenisJabatan::Struktural->label())->toBe('Struktural');
        expect(JenisJabatan::Fungsional->label())->toBe('Fungsional');
        expect(JenisJabatan::Pelaksana->label())->toBe('Pelaksana');
    });

    it('dapat mengakses value', function () {
        expect(JenisJabatan::Struktural->value)->toBe('struktural');
    });
});
