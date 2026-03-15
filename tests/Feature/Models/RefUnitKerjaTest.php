<?php

use App\Models\RefUnitKerja;
use Database\Seeders\RefUnitKerjaSeeder;

describe('RefUnitKerja', function () {
    it('memiliki relasi parent dan children yang bekerja', function () {
        $parent = RefUnitKerja::factory()->create();
        $child = RefUnitKerja::factory()->create(['parent_id' => $parent->id]);

        expect($child->parent)->not->toBeNull();
        expect($child->parent->is($parent))->toBeTrue();
        expect($parent->children)->toHaveCount(1);
        expect($parent->children->first()?->is($child))->toBeTrue();
    });

    it('menyediakan dua unit kerja level atas melalui seeder', function () {
        (new RefUnitKerjaSeeder)->run();

        expect(RefUnitKerja::query()->whereNull('parent_id')->count())->toBe(2);
        expect(RefUnitKerja::query()->whereNotNull('parent_id')->count())->toBeGreaterThanOrEqual(3);
    });

    it('mendukung soft delete', function () {
        $unitKerja = RefUnitKerja::factory()->create();

        $unitKerja->delete();

        expect($unitKerja->trashed())->toBeTrue();
        expect(RefUnitKerja::query()->find($unitKerja->id))->toBeNull();
        expect(RefUnitKerja::withTrashed()->find($unitKerja->id))->not->toBeNull();
    });
});
