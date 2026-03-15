<?php

use App\Enums\HubunganKeluarga;

describe('HubunganKeluarga Enum', function () {
    it('memiliki kasus yang benar', function () {
        expect(HubunganKeluarga::cases())->toHaveCount(5);
    });

    it('memiliki value yang benar', function () {
        $values = array_column(HubunganKeluarga::cases(), 'value');
        expect($values)->toContain('Suami');
        expect($values)->toContain('Istri');
        expect($values)->toContain('Anak');
        expect($values)->toContain('AyahKandung');
        expect($values)->toContain('IbuKandung');
    });

    it('memiliki label yang benar untuk setiap kasus', function () {
        expect(HubunganKeluarga::Suami->label())->toBe('Suami');
        expect(HubunganKeluarga::Istri->label())->toBe('Istri');
        expect(HubunganKeluarga::Anak->label())->toBe('Anak');
        expect(HubunganKeluarga::AyahKandung->label())->toBe('Ayah Kandung');
        expect(HubunganKeluarga::IbuKandung->label())->toBe('Ibu Kandung');
    });

    it('dapat mengakses value', function () {
        expect(HubunganKeluarga::Suami->value)->toBe('Suami');
    });
});
