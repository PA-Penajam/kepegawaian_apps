<?php

use App\Models\RefJenisHukumanDisiplin;
use Database\Seeders\RefJenisHukumanDisiplinSeeder;

describe('RefJenisHukumanDisiplin', function () {
    it('menyediakan daftar hukuman disiplin melalui seeder', function () {
        (new RefJenisHukumanDisiplinSeeder)->run();

        expect(RefJenisHukumanDisiplin::count())->toBe(6);
        expect(
            RefJenisHukumanDisiplin::query()
                ->where('nama', 'Pemberhentian Dengan Tidak Hormat')
                ->value('tingkat')
        )->toBe('berat');
    });

    it('mendukung soft delete', function () {
        $jenisHukuman = RefJenisHukumanDisiplin::factory()->create();

        $jenisHukuman->delete();

        expect($jenisHukuman->trashed())->toBeTrue();
        expect(RefJenisHukumanDisiplin::query()->find($jenisHukuman->id))->toBeNull();
        expect(RefJenisHukumanDisiplin::withTrashed()->find($jenisHukuman->id))->not->toBeNull();
    });
});
