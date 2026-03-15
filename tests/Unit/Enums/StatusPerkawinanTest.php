<?php

use App\Enums\StatusPerkawinan;

describe('StatusPerkawinan Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(StatusPerkawinan::cases())->toHaveCount(4);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(StatusPerkawinan::cases(), 'value');
        expect($values)->toContain('belum_kawin');
        expect($values)->toContain('kawin');
        expect($values)->toContain('cerai_hidup');
        expect($values)->toContain('cerai_mati');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(StatusPerkawinan::BelumKawin->label())->toBe('Belum Kawin');
        expect(StatusPerkawinan::Kawin->label())->toBe('Kawin');
        expect(StatusPerkawinan::CeraiHidup->label())->toBe('Cerai Hidup');
        expect(StatusPerkawinan::CeraiMati->label())->toBe('Cerai Mati');
    });

    it('dapat mengakses value', function () {
        expect(StatusPerkawinan::Kawin->value)->toBe('kawin');
    });
});
