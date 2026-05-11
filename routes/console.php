<?php

use App\Models\IamSsoCode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwalkan pruning untuk SSO codes yang sudah expired
Schedule::command('model:prune', ['--model' => IamSsoCode::class])->hourly();

// Notifikasi deadline usulan KP setiap hari jam 07:00
Schedule::command('sikep:notifikasi-deadline-kp')->dailyAt('07:00');

// Notifikasi KP setiap hari jam 07:30
Schedule::command('sikep:notifikasi-kp')->dailyAt('07:30');

// Carry-over saldo cuti tahunan setiap 1 Januari jam 00:05
Schedule::command('cuti:carry-over')
    ->yearlyOn(1, 1, '00:05')
    ->withoutOverlapping()
    ->onOneServer();

// Dispatch pending event cuti ke consumer webhook setiap menit
Schedule::command('cuti:dispatch-events')->everyMinute()->withoutOverlapping();

// Expire draft cuti yang sudah lebih dari 7 hari setiap hari jam 00:30
Schedule::command('cuti:expire-drafts')->dailyAt('00:30')->withoutOverlapping();
