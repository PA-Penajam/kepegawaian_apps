<?php

use App\Enums\StatusKepegawaian;

describe('StatusKepegawaian Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(StatusKepegawaian::cases())->toHaveCount(3);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(StatusKepegawaian::cases(), 'value');
        expect($values)->toContain('pns');
        expect($values)->toContain('pppk');
        expect($values)->toContain('honorer');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(StatusKepegawaian::PNS->label())->toBe('PNS');
        expect(StatusKepegawaian::PPPK->label())->toBe('PPPK');
        expect(StatusKepegawaian::Honorer->label())->toBe('Honorer');
    });

    it('dapat mengakses value', function () {
        expect(StatusKepegawaian::PNS->value)->toBe('pns');
    });
});
