<?php

use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Notifications\KenaikanPangkatDeadlineNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

function createUsulanKpWithBatasUsul(int $daysUntilDeadline, string $state = 'DRAFT'): UsulanKenaikanPangkat
{
    $batasUsul = Carbon::today()->addDays($daysUntilDeadline)->startOfMonth();
    $pegawai = Pegawai::factory()->create();
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
        'tmt' => $batasUsul->copy()->addMonthNoOverflow()->subYears(4)->toDateString(),
    ]);

    return UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'created_by' => $pegawai->id,
        'state' => $state,
    ]);
}

it('NotifikasiDeadlineUsulanKp mengirim notifikasi untuk usulan draft dengan batas usul dalam threshold', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-22 08:00:00'));
    Notification::fake();
    $usulan = createUsulanKpWithBatasUsul(10);

    expect(Artisan::call('sikep:notifikasi-deadline-kp', ['--threshold-days' => 14]))->toBe(0);

    Notification::assertSentTo($usulan->pegawai, KenaikanPangkatDeadlineNotification::class);
});

it('NotifikasiDeadlineUsulanKp melewati usulan dengan batas usul di luar threshold', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-12 08:00:00'));
    Notification::fake();
    $usulan = createUsulanKpWithBatasUsul(20);

    expect(Artisan::call('sikep:notifikasi-deadline-kp', ['--threshold-days' => 14]))->toBe(0);

    Notification::assertNotSentTo($usulan->pegawai, KenaikanPangkatDeadlineNotification::class);
});

it('NotifikasiDeadlineUsulanKp melewati usulan yang bukan draft atau perlu perbaikan', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-22 08:00:00'));
    Notification::fake();
    $usulan = createUsulanKpWithBatasUsul(10, 'DIAJUKAN');

    expect(Artisan::call('sikep:notifikasi-deadline-kp', ['--threshold-days' => 14]))->toBe(0);

    Notification::assertNotSentTo($usulan->pegawai, KenaikanPangkatDeadlineNotification::class);
});

it('NotifikasiDeadlineUsulanKp idempotent dalam satu hari', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-22 08:00:00'));
    $usulan = createUsulanKpWithBatasUsul(10);

    expect(Artisan::call('sikep:notifikasi-deadline-kp', ['--threshold-days' => 14]))->toBe(0);
    expect(Artisan::call('sikep:notifikasi-deadline-kp', ['--threshold-days' => 14]))->toBe(0);

    expect($usulan->pegawai->notifications()->where('type', KenaikanPangkatDeadlineNotification::class)->count())->toBe(1);
});
