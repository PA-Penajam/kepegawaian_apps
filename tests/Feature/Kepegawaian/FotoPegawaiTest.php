<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;

test('foto_url returns null when foto is null', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    expect($pegawai->foto_url)->toBeNull();
});

test('foto_url returns full storage URL when foto path is set', function () {
    Storage::fake('public');
    Storage::disk('public')->put('fotos/test.webp', 'fake-content');

    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/test.webp']);

    expect($pegawai->foto_url)
        ->toBeString()
        ->toContain('fotos/test.webp');
});

test('foto_url is included in model serialization via appends', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    $array = $pegawai->toArray();

    expect($array)->toHaveKey('foto_url');
});
