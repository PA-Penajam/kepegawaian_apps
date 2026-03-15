<?php

use App\Enums\GolonganDarah;

describe('GolonganDarah Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(GolonganDarah::cases())->toHaveCount(4);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(GolonganDarah::cases(), 'value');
        expect($values)->toContain('A');
        expect($values)->toContain('B');
        expect($values)->toContain('AB');
        expect($values)->toContain('O');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(GolonganDarah::A->label())->toBe('A');
        expect(GolonganDarah::B->label())->toBe('B');
        expect(GolonganDarah::AB->label())->toBe('AB');
        expect(GolonganDarah::O->label())->toBe('O');
    });

    it('dapat mengakses value', function () {
        expect(GolonganDarah::A->value)->toBe('A');
    });
});
