<?php

use App\Enums\JenjangPendidikan;

describe('JenjangPendidikan Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(JenjangPendidikan::cases())->toHaveCount(10);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(JenjangPendidikan::cases(), 'value');
        expect($values)->toContain('sd');
        expect($values)->toContain('smp');
        expect($values)->toContain('sma');
        expect($values)->toContain('d1');
        expect($values)->toContain('d2');
        expect($values)->toContain('d3');
        expect($values)->toContain('d4');
        expect($values)->toContain('s1');
        expect($values)->toContain('s2');
        expect($values)->toContain('s3');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(JenjangPendidikan::SD->label())->toBe('SD');
        expect(JenjangPendidikan::SMP->label())->toBe('SMP');
        expect(JenjangPendidikan::SMA->label())->toBe('SMA');
        expect(JenjangPendidikan::D1->label())->toBe('D1');
        expect(JenjangPendidikan::D2->label())->toBe('D2');
        expect(JenjangPendidikan::D3->label())->toBe('D3');
        expect(JenjangPendidikan::D4->label())->toBe('D4');
        expect(JenjangPendidikan::S1->label())->toBe('S1');
        expect(JenjangPendidikan::S2->label())->toBe('S2');
        expect(JenjangPendidikan::S3->label())->toBe('S3');
    });

    it('dapat mengakses value', function () {
        expect(JenjangPendidikan::S1->value)->toBe('s1');
    });
});
