<?php

use App\Enums\Agama;

describe('Agama Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(Agama::cases())->toHaveCount(6);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(Agama::cases(), 'value');
        expect($values)->toContain('islam');
        expect($values)->toContain('kristen');
        expect($values)->toContain('katolik');
        expect($values)->toContain('hindu');
        expect($values)->toContain('buddha');
        expect($values)->toContain('konghucu');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(Agama::Islam->label())->toBe('Islam');
        expect(Agama::Kristen->label())->toBe('Kristen');
        expect(Agama::Katolik->label())->toBe('Katolik');
        expect(Agama::Hindu->label())->toBe('Hindu');
        expect(Agama::Buddha->label())->toBe('Buddha');
        expect(Agama::Konghucu->label())->toBe('Konghucu');
    });

    it('dapat mengakses value', function () {
        expect(Agama::Islam->value)->toBe('islam');
    });
});
