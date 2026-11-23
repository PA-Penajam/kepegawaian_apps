<?php

use App\Models\NomorSurat\NomorSuratSequence;
use App\Services\NomorSurat\DatabaseNomorSuratService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('generate membuat sequence dengan timestamps dan menaikkan nomor urut secara berurutan', function () {
    $svc = app(DatabaseNomorSuratService::class);

    $n1 = $svc->generate('KP.01.1', 5, 2026);
    $n2 = $svc->generate('KP.01.1', 5, 2026);

    expect($n1)->toBe('W1-U1/1/KP.01.1/V/2026')
        ->and($n2)->toBe('W1-U1/2/KP.01.1/V/2026');

    $sequence = NomorSuratSequence::where('klasifikasi', 'KP.01.1')->where('tahun', 2026)->first();
    expect($sequence)->not->toBeNull()
        ->and($sequence->next_number)->toBe(3)
        ->and($sequence->created_at)->not->toBeNull()
        ->and($sequence->updated_at)->not->toBeNull();
});

test('reserve, confirm, dan release menegakkan hole policy serta guard status reserved', function () {
    $svc = app(DatabaseNomorSuratService::class);

    $res1 = $svc->reserve('KP.01.1', 11, 2026);
    expect($res1->status)->toBe('reserved')
        ->and($res1->nomor_lengkap)->toBe('W1-U1/1/KP.01.1/XI/2026');

    $svc->confirm((string) $res1->id);
    expect($res1->fresh()->status)->toBe('confirmed')
        ->and($res1->fresh()->confirmed_at)->not->toBeNull();

    // Konfirmasi ulang atau release setelah confirmed harus ditolak DomainException
    expect(fn () => $svc->confirm((string) $res1->id))->toThrow(DomainException::class);
    expect(fn () => $svc->release((string) $res1->id))->toThrow(DomainException::class);

    $res2 = $svc->reserve('KP.01.1', 11, 2026);
    expect($res2->nomor_urut)->toBe(2);
    $svc->release((string) $res2->id);
    expect($res2->fresh()->status)->toBe('released');

    // Confirm atau release ulang setelah released harus ditolak DomainException
    expect(fn () => $svc->confirm((string) $res2->id))->toThrow(DomainException::class);
    expect(fn () => $svc->release((string) $res2->id))->toThrow(DomainException::class);

    // Nomor berikutnya tetap 3 (hole policy)
    expect($svc->generate('KP.01.1', 11, 2026))->toBe('W1-U1/3/KP.01.1/XI/2026');
});

test('confirm dan release melempar ModelNotFoundException untuk ID tidak ada', function () {
    $svc = app(DatabaseNomorSuratService::class);

    expect(fn () => $svc->confirm('01j00000000000000000000000'))->toThrow(ModelNotFoundException::class);
    expect(fn () => $svc->release('01j00000000000000000000000'))->toThrow(ModelNotFoundException::class);
});

test('generate dan reserve menolak bulan di luar rentang 1..12', function (int $bulan) {
    $svc = app(DatabaseNomorSuratService::class);

    expect(fn () => $svc->generate('KP.01.1', $bulan, 2026))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->reserve('KP.01.1', $bulan, 2026))->toThrow(InvalidArgumentException::class);
})->with([0, -1, 13, 99]);
