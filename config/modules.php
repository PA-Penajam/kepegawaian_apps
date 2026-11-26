<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode Pengembangan Modul
    |--------------------------------------------------------------------------
    |
    | Mengatur modul non-inti (di luar Modul Cuti, Data Pegawai, Dashboard, dan
    | Konfigurasi Aplikasi) agar menampilkan halaman "Halaman Dalam Pengembangan"
    | dan menolak aksi mutasi ketika aplikasi dirilis bertahap.
    |
    */
    'under_development_enabled' => filter_var(
        env('MODULES_UNDER_DEVELOPMENT_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),

    'items' => [
        'self_service' => [
            'under_development' => filter_var(
                env('MODULE_SELF_SERVICE_UNDER_DEVELOPMENT', true),
                FILTER_VALIDATE_BOOL,
            ),
            'title' => 'Layanan Mandiri (Self Service)',
            'description' => 'Fitur Layanan Mandiri untuk melihat profil kepegawaian, riwayat data, dan status KGB/KP pribadi sedang dalam tahap pengembangan sebelum diaktifkan.',
        ],
        'validasi_pengajuan' => [
            'under_development' => filter_var(
                env('MODULE_VALIDASI_PENGAJUAN_UNDER_DEVELOPMENT', true),
                FILTER_VALIDATE_BOOL,
            ),
            'title' => 'Validasi Pengajuan Perubahan Data',
            'description' => 'Fitur verifikasi dan persetujuan pengajuan perubahan biodata maupun riwayat pegawai oleh validator sedang dalam tahap pengembangan.',
        ],
        'monitoring' => [
            'under_development' => filter_var(
                env('MODULE_MONITORING_UNDER_DEVELOPMENT', true),
                FILTER_VALIDATE_BOOL,
            ),
            'title' => 'Monitoring KGB & Kenaikan Pangkat',
            'description' => 'Fitur pemantauan proyeksi Kenaikan Gaji Berkala (KGB) dan Kenaikan Pangkat pegawai beserta ekspor laporannya sedang dalam tahap pengembangan.',
        ],
        'kenaikan_pangkat' => [
            'under_development' => filter_var(
                env('MODULE_KENAIKAN_PANGKAT_UNDER_DEVELOPMENT', true),
                FILTER_VALIDATE_BOOL,
            ),
            'title' => 'Modul Kenaikan Pangkat',
            'description' => 'Layanan Usulan Kenaikan Pangkat, Pegawai Eligible KP, Inbox Approval KP, Admin SK KP, dan Checklist Template sedang dalam tahap pengembangan.',
        ],
    ],
];
