<?php

use App\Rules\ValidIamSlug;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

function validateSlug(string $slug): ?string
{
    $validator = Validator::make(
        ['slug' => $slug],
        ['slug' => [new ValidIamSlug]],
    );

    return $validator->errors()->first('slug') ?: null;
}

test('menerima slug 2-segment canonical', function () {
    foreach (['pegawai.view', 'cuti.create', 'barang.manage', 'iam.manage'] as $slug) {
        expect(validateSlug($slug))->toBeNull("Slug '{$slug}' seharusnya valid");
    }
});

test('menerima slug 3-segment canonical', function () {
    foreach (['cuti.pengajuan.approve-langsung', 'kenaikan-pangkat.usulan.create', 'checklist.template.update'] as $slug) {
        expect(validateSlug($slug))->toBeNull("Slug '{$slug}' seharusnya valid");
    }
});

test('menolak slug tanpa titik (single segment)', function () {
    $error = validateSlug('iam-manage');
    expect($error)->not->toBeNull()
        ->and($error)->toContain('format');
});

test('menolak slug dengan uppercase', function () {
    expect(validateSlug('Pegawai.View'))->not->toBeNull();
    expect(validateSlug('pegawai.View'))->not->toBeNull();
});

test('menolak slug dengan underscore', function () {
    expect(validateSlug('pegawai_view'))->not->toBeNull();
    expect(validateSlug('cuti.pengajuan_create'))->not->toBeNull();
});

test('menolak slug 4-segment (terlalu dalam)', function () {
    expect(validateSlug('a.b.c.d'))->not->toBeNull();
});

test('menolak slug dengan karakter terlarang', function () {
    expect(validateSlug('cuti.pengajuan@create'))->not->toBeNull();
    expect(validateSlug('cuti.pengajuan create'))->not->toBeNull();
    expect(validateSlug(''))->not->toBeNull();
});

test('menolak slug yang dimulai dengan strip atau angka', function () {
    expect(validateSlug('-cuti.view'))->not->toBeNull();
    expect(validateSlug('1cuti.view'))->not->toBeNull();
});
