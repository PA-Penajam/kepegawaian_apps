<?php

// config/iam.php
return [
    'token_ttl_hours' => env('IAM_TOKEN_TTL_HOURS', 8),
    'sso_code_ttl_seconds' => env('IAM_SSO_CODE_TTL', 60),
    'app_slug' => env('IAM_APP_SLUG', 'kepegawaian'),

    'slug' => [
        // Format: {resource}.{action} atau {module}.{resource}.{action}
        // Lowercase, antar-segment titik, antar-kata strip
        'pattern' => '/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*){1,2}$/',
        'min_segments' => 2,
        'max_segments' => 3,
    ],

    'standard_actions' => [
        'view'        => 'Lihat list/detail (read-only)',
        'create'      => 'Buat record baru',
        'update'      => 'Ubah record yang sudah ada',
        'delete'      => 'Hapus record',
        'manage'      => 'Umbrella: view+create+update+delete',
        'approve'     => 'Setujui/tolak workflow',
        'process'     => 'Eksekusi workflow yang sudah disetujui',
        'read'        => 'Read-only untuk laporan/log',
        'submit'      => 'Ajukan ke tahap berikutnya',
        'verify'      => 'Verifikasi dokumen/data',
        'cancel-own'  => 'Batalkan milik sendiri',
        'cancel-any'  => 'Batalkan milik siapapun (admin)',
        'view-own'    => 'Lihat milik sendiri',
        'view-team'   => 'Lihat milik tim',
        'view-all'    => 'Lihat semua (admin/auditor)',
        'audit'       => 'Akses audit log/trail',
        'reassign'    => 'Alihkan tanggung jawab',
        'adjust'      => 'Penyesuaian numerik (mis. saldo)',
    ],

    'standard_roles' => [
        'admin'     => 'Akses penuh app',
        'operator'  => 'Operasional harian',
        'pimpinan'  => 'Atasan/penyetuju workflow',
        'pegawai'   => 'Pengguna umum',
        'auditor'   => 'Read-only laporan + log',
        'viewer'    => 'Read-only umum',
        'validator' => 'Validator pengajuan/dokumen',
    ],

    'docs_url' => '/docs/sso-api/rbac-convention.md',
];
