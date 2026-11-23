<?php

use Illuminate\Support\Facades\File;

it('defines required kenaikan pangkat frontend pages', function (string $path, array $needles) {
    $fullPath = resource_path($path);

    expect(File::exists($fullPath))->toBeTrue();

    $contents = File::get($fullPath);

    foreach ($needles as $needle) {
        expect($contents)->toContain($needle);
    }
})->with([
    'eligible list' => [
        'js/pages/kenaikan-pangkat/eligible/index.tsx',
        [
            'Buat Usulan',
            '/kenaikan-pangkat/usulan/create?pegawai_id=',
            'bg-background',
        ],
    ],
    'usulan form' => [
        'js/pages/kenaikan-pangkat/usulan/form.tsx',
        [
            'useForm<',
            'pegawai_id',
            'ref_pangkat_tujuan_id',
            'periode_usul_bulan',
            'periode_usul_tahun',
            'catatan_pengusul',
            'errors.',
        ],
    ],
    'usulan show' => [
        'js/pages/kenaikan-pangkat/usulan/show.tsx',
        [
            'alasan-batalkan',
            'errors.file',
            'item.item?.nama',
        ],
    ],
    'admin sk' => [
        'js/pages/kenaikan-pangkat/admin-sk/index.tsx',
        [
            'tmt_pangkat_baru',
            'masa_kerja_tahun',
            'masa_kerja_bulan',
        ],
    ],
    'usulan index' => [
        'js/pages/kenaikan-pangkat/usulan/index.tsx',
        [
            'State',
            'Progres checklist',
            'stateLabels',
            'router.get',
            'search',
            'bg-background',
        ],
    ],
]);
