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
