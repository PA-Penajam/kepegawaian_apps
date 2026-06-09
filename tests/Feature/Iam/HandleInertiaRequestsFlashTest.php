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
