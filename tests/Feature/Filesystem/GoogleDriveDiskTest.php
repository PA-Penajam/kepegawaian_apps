<?php

use Google\Client as GoogleClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Masbug\Flysystem\GoogleDriveAdapter;

it('disk google terdaftar pada konfigurasi filesystems', function (): void {
    $config = config('filesystems.disks.google');

    expect($config)->toBeArray()
        ->and($config['driver'])->toBe('google')
        ->and($config)->toHaveKeys(['clientId', 'clientSecret', 'refreshToken', 'folder']);
});

it('disk google mendukung operasi storage fake tanpa memanggil API eksternal', function (): void {
    Storage::fake('google');

    $stored = Storage::disk('google')->put('dokumen/test-sk.pdf', 'isi-berkas-dummy');

    expect($stored)->toBeTrue()
        ->and(Storage::disk('google')->exists('dokumen/test-sk.pdf'))->toBeTrue()
        ->and(Storage::disk('google')->get('dokumen/test-sk.pdf'))->toBe('isi-berkas-dummy');

    Storage::disk('google')->delete('dokumen/test-sk.pdf');

    expect(Storage::disk('google')->exists('dokumen/test-sk.pdf'))->toBeFalse();
});

it('driver google menyelesaikan GoogleDriveAdapter melalui container tanpa panggilan jaringan', function (): void {
    $mockClient = Mockery::mock(GoogleClient::class)->shouldIgnoreMissing();
    $mockClient->shouldReceive('setClientId')->once()->with('test-client-id');
    $mockClient->shouldReceive('setClientSecret')->once()->with('test-client-secret');
    $mockClient->shouldReceive('refreshToken')->once()->with('test-refresh-token')->andReturn([]);

    app()->instance(GoogleClient::class, $mockClient);

    config([
        'filesystems.disks.google' => [
            'driver' => 'google',
            'clientId' => 'test-client-id',
            'clientSecret' => 'test-client-secret',
            'refreshToken' => 'test-refresh-token',
            'folder' => '/',
        ],
    ]);

    Storage::forgetDisk('google');
    $disk = Storage::disk('google');

    expect($disk)->toBeInstanceOf(FilesystemAdapter::class)
        ->and($disk->getAdapter())->toBeInstanceOf(GoogleDriveAdapter::class);
});
