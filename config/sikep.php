<?php

return [
    'kode_satker' => env('SIKEP_KODE_SATKER', 'W1-U1'),

    'adapter' => env('SIKEP_ADAPTER', 'null'),

    'kp' => [
        'lookahead_months' => env('SIKEP_KP_LOOKAHEAD_MONTHS', 6),
        'checklist_template_kode' => env('SIKEP_KP_CHECKLIST_KODE', 'checklist-kp-reguler'),
        'pejabat_penetap' => env('SIKEP_KP_PEJABAT_PENETAP', 'Biro Kepegawaian Mahkamah Agung RI'),
    ],

    'penandatangan' => [
        'kenaikan_pangkat' => env('SIKEP_PENANDATANGAN_KP', 'ketua_pengadilan'),
        'cuti' => env('SIKEP_PENANDATANGAN_CUTI', 'sekretaris'),
    ],
];
