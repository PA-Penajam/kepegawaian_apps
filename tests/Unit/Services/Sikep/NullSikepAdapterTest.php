<?php

use App\Services\Sikep\NullSikepAdapter;
use App\Services\Sikep\SikepAdapter;
use App\Services\Sikep\UsulanKenaikanPangkatDto;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Log::spy();
});

it('pushUsulan returns null untuk null input', function (): void {
    $adapter = new NullSikepAdapter;

    $result = $adapter->pushUsulan(null);

    expect($result)->toBeNull();
});

it('pushUsulan returns null untuk UsulanKenaikanPangkatDto', function (): void {
    $adapter = new NullSikepAdapter;

    $usulan = new UsulanKenaikanPangkatDto(
        nip: '199001012020011001',
        nama_lengkap: 'John Doe',
        pangkat_asal_kode: 'III/c',
        pangkat_tujuan_kode: 'III/d',
        tmt_pangkat_asal: '2020-01-01',
        periode_bulan: 1,
        periode_tahun: 2026,
    );

    $result = $adapter->pushUsulan($usulan);

    expect($result)->toBeNull();
});

it('isConfigured returns false', function (): void {
    $adapter = new NullSikepAdapter;

    expect($adapter->isConfigured())->toBeFalse();
});

it('pullStatusUsulan returns null', function (): void {
    $adapter = new NullSikepAdapter;

    expect($adapter->pullStatusUsulan('x'))->toBeNull();
});

it('pullSkFinal returns null', function (): void {
    $adapter = new NullSikepAdapter;

    expect($adapter->pullSkFinal('x'))->toBeNull();
});

it('pushes binding to app container', function (): void {
    $instance = app(SikepAdapter::class);

    expect($instance)->toBeInstanceOf(NullSikepAdapter::class);
});

it('logs info when pushUsulan called', function (): void {
    $adapter = new NullSikepAdapter;

    $adapter->pushUsulan(null);

    Log::assertLogged('info', fn ($message, $context) => str_contains($message, 'NullSikepAdapter::pushUsulan called'));
});
