<?php

use App\Models\RefPangkat;
use Database\Seeders\RefPangkatSeeder;

describe('RefPangkat', function () {
    it('menyediakan 20 data pangkat (17 PNS + 3 PPPK) melalui seeder', function () {
        (new RefPangkatSeeder)->run();

        expect(RefPangkat::count())->toBe(20);
        expect(
            RefPangkat::query()
                ->where('golongan', 'III')
                ->where('ruang', 'a')
                ->value('nama')
        )->toBe('Penata Muda');
    });

    it('mendukung soft delete', function () {
        $pangkat = RefPangkat::factory()->create();

        $pangkat->delete();

        expect($pangkat->trashed())->toBeTrue();
        expect(RefPangkat::query()->find($pangkat->id))->toBeNull();
        expect(RefPangkat::withTrashed()->find($pangkat->id))->not->toBeNull();
    });
});
