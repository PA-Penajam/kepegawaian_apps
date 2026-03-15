<?php

use App\Enums\JenisJabatan;
use App\Models\RefJabatan;
use Database\Seeders\RefJabatanSeeder;

describe('RefJabatan', function () {
    it('menyediakan data jabatan pa penajam dan cast enum jenis jabatan', function () {
        (new RefJabatanSeeder)->run();

        expect(RefJabatan::count())->toBeGreaterThanOrEqual(10);

        $ketua = RefJabatan::query()->where('nama', 'Ketua')->firstOrFail();

        expect($ketua->jenis_jabatan)->toBe(JenisJabatan::Struktural);
        expect($ketua->eselon)->toBe('II');
    });

    it('mendukung soft delete', function () {
        $jabatan = RefJabatan::factory()->create();

        $jabatan->delete();

        expect($jabatan->trashed())->toBeTrue();
        expect(RefJabatan::query()->find($jabatan->id))->toBeNull();
        expect(RefJabatan::withTrashed()->find($jabatan->id))->not->toBeNull();
    });
});
