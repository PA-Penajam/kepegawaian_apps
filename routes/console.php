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

// Notifikasi KGB setiap hari jam 07:00
Schedule::command('kgb:notify')->dailyAt('07:00');

// Notifikasi KP setiap hari jam 07:00
Schedule::command('kp:notify')->dailyAt('07:00');
