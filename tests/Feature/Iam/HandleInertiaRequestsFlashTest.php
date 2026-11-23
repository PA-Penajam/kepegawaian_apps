<?php

use App\Models\Pegawai;

test('flash.api_secret_once dishare ke Inertia props', function () {
    $admin = Pegawai::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->withSession(['api_secret_once' => 'PLAIN_SECRET_TEST_64_CHARS_DEMO'])
        ->get('/iam/aplikasi');

    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['flash']['api_secret_once'])->toBe('PLAIN_SECRET_TEST_64_CHARS_DEMO');
});

test('flash.success dan flash.error tetap di-share (regression)', function () {
    $admin = Pegawai::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->withSession([
            'success' => 'Berhasil disimpan',
            'error' => 'Ada error',
        ])
        ->get('/iam/aplikasi');

    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['flash']['success'])->toBe('Berhasil disimpan');
    expect($page['props']['flash']['error'])->toBe('Ada error');
});

test('flash.sync_token_once dan flash.test_connection dishare ke Inertia props', function () {
    $admin = Pegawai::factory()->admin()->create();

    $syncToken = [
        'consumer_id' => '01JTEST',
        'consumer_slug' => 'wfa-task',
        'plaintext' => '1|plain-token',
        'expires_at' => null,
        'hmac_secret' => 'secret-hex',
    ];
    $testConn = [
        'success' => true,
        'message' => 'Koneksi berhasil.',
    ];

    $response = $this->actingAs($admin)
        ->withSession([
            'sync_token_once' => $syncToken,
            'test_connection' => $testConn,
        ])
        ->get('/iam/sinkronisasi');

    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['flash']['sync_token_once'])->toBe($syncToken);
    expect($page['props']['flash']['test_connection'])->toBe($testConn);
});
