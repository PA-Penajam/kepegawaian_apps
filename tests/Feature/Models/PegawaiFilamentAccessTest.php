<?php

namespace Tests\Feature\Models;

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\Pegawai;
use Filament\Panel;

it('mengizinkan admin mengakses panel Filament', function (): void {
    $pegawai = Pegawai::factory()->admin()->create();

    expect($pegawai->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('mengizinkan operator mengakses panel Filament', function (): void {
    $pegawai = Pegawai::factory()->operator()->create();

    expect($pegawai->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('menolak viewer mengakses panel Filament', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    expect($pegawai->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('menolak admin dari aplikasi lain mengakses panel Filament kepegawaian', function (): void {
    $pegawai = Pegawai::factory()->create();
    $attendance = IamApplication::factory()->create(['slug' => 'attendance']);
    $attendanceAdmin = IamRole::factory()->create([
        'iam_application_id' => $attendance->id,
        'slug' => 'admin',
    ]);
    $pegawai->iamRoles()->attach($attendanceAdmin, ['assigned_at' => now()]);

    expect($pegawai->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});
