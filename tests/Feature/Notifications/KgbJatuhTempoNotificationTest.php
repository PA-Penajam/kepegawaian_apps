<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Notifications\KgbJatuhTempoNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

function createPegawaiWithKgb(string $nextKgbDate): Pegawai
{
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('notification contains correct subject for jatuh tempo kgb', function () {
    Carbon::setTestNow('2026-01-01');

    $tanggalKgb = Carbon::parse('2026-01-15');
    $notification = new KgbJatuhTempoNotification(
        kgbDate: $tanggalKgb,
        sisaHari: -5,
        status: 'Sudah Jatuh Tempo',
    );

    $mailData = $notification->toMail(createPegawaiWithKgb('2026-01-15'));

    expect($mailData->subject)->toBe('KGB Sudah Jatuh Tempo — 15 January 2026');

    Carbon::setTestNow();
});

test('notification contains correct subject for mendekati kgb', function () {
    Carbon::setTestNow('2026-01-01');

    $tanggalKgb = Carbon::parse('2026-03-15');
    $notification = new KgbJatuhTempoNotification(
        kgbDate: $tanggalKgb,
        sisaHari: 45,
        status: 'Mendekati',
    );

    $mailData = $notification->toMail(createPegawaiWithKgb('2026-03-15'));

    expect($mailData->subject)->toBe('Pengingat KGB Mendekati Jatuh Tempo — 15 March 2026');

    Carbon::setTestNow();
});

test('notification via method returns mail channel', function () {
    Carbon::setTestNow('2026-01-01');

    $tanggalKgb = Carbon::parse('2026-02-15');
    $notification = new KgbJatuhTempoNotification(
        kgbDate: $tanggalKgb,
        sisaHari: 30,
        status: 'Segera',
    );

    $pegawai = createPegawaiWithKgb('2026-02-15');

    expect($notification->via($pegawai))->toContain('mail');

    Carbon::setTestNow();
});

test('command runs successfully without errors', function () {
    $this->artisan('kgb:notify')
        ->assertSuccessful();
});

test('command shows correct count when no notifications sent', function () {
    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 0 pegawai.')
        ->assertSuccessful();
});
