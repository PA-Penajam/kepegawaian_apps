<?php

use App\Models\RefJenisPenghargaan;
use Database\Seeders\RefJenisPenghargaanSeeder;

describe('RefJenisPenghargaan', function () {
    it('menyediakan daftar jenis penghargaan melalui seeder', function () {
        (new RefJenisPenghargaanSeeder)->run();

        expect(RefJenisPenghargaan::count())->toBe(4);
        expect(RefJenisPenghargaan::query()->pluck('nama')->all())->toContain('Satya Lencana Karya Satya 10 Tahun');
        expect(RefJenisPenghargaan::query()->pluck('nama')->all())->toContain('Penghargaan Lainnya');
    });

    it('mendukung soft delete', function () {
        $jenisPenghargaan = RefJenisPenghargaan::factory()->create();

        $jenisPenghargaan->delete();

        expect($jenisPenghargaan->trashed())->toBeTrue();
        expect(RefJenisPenghargaan::query()->find($jenisPenghargaan->id))->toBeNull();
        expect(RefJenisPenghargaan::withTrashed()->find($jenisPenghargaan->id))->not->toBeNull();
    });
});
