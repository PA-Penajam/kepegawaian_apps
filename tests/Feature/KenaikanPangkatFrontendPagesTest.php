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
            'pangkat_asal_id',
            'pangkat_tujuan_id',
            'periode_bulan',
            'periode_tahun',
            'catatan_pengusul',
            'errors.',
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
