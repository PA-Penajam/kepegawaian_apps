<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Notifications\KenaikanPangkatEligibleNotification;
use Carbon\Carbon;
use Tests\TestCase;

function createPegawaiWithKp(string $tmtPangkat, bool $isAktif = true): Pegawai
{
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => $tmtPangkat,
        'is_aktif' => $isAktif,
    ]);

    return $pegawai;
}

test('notification signature uses periode bulan + tahun', function () {
    $notif = new KenaikanPangkatEligibleNotification(4, 2026, '15 April 2026');

    expect($notif->periodeBulan)->toBe(4)
        ->and($notif->periodeTahun)->toBe(2026)
        ->and($notif->batasUsul)->toBe('15 April 2026');
});

test('notification sends to database + mail channel', function () {
    $notif = new KenaikanPangkatEligibleNotification(4, 2026, '15 April 2026');

    $channels = $notif->via(createPegawaiWithKp('2022-04-01'));

    expect($channels)->toContain('database')
        ->and($channels)->toContain('mail');
});

test('command kp:notify supports --bulan --tahun', function () {
    /** @var TestCase $this */
    Carbon::setTestNow('2026-01-01');

    $pegawai = createPegawaiWithKp('2022-04-01');

    $this->artisan('sikep:notifikasi-kp --bulan=4 --tahun=2026')
        ->assertSuccessful();

    expect($pegawai->notifications()->count())->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('command kp:notify does not send duplicate notifications', function () {
    /** @var TestCase $this */
    Carbon::setTestNow('2026-01-01');

    $pegawai = createPegawaiWithKp('2022-04-01');

    $this->artisan('sikep:notifikasi-kp --bulan=4 --tahun=2026')
        ->assertSuccessful();

    $this->artisan('sikep:notifikasi-kp --bulan=4 --tahun=2026')
        ->assertSuccessful();

    expect($pegawai->notifications()->count())->toBe(1);

    Carbon::setTestNow();
});
