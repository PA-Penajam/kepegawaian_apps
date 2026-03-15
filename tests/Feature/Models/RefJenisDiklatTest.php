<?php

use App\Models\RefJenisDiklat;
use Database\Seeders\RefJenisDiklatSeeder;

describe('RefJenisDiklat', function () {
    it('menyediakan daftar jenis diklat melalui seeder', function () {
        (new RefJenisDiklatSeeder)->run();

        expect(RefJenisDiklat::count())->toBe(5);
        expect(RefJenisDiklat::query()->pluck('nama')->all())->toContain('Prajabatan');
        expect(RefJenisDiklat::query()->pluck('nama')->all())->toContain('Teknis Peradilan');
    });

    it('mendukung soft delete', function () {
        $jenisDiklat = RefJenisDiklat::factory()->create();

        $jenisDiklat->delete();

        expect($jenisDiklat->trashed())->toBeTrue();
        expect(RefJenisDiklat::query()->find($jenisDiklat->id))->toBeNull();
        expect(RefJenisDiklat::withTrashed()->find($jenisDiklat->id))->not->toBeNull();
    });
});
