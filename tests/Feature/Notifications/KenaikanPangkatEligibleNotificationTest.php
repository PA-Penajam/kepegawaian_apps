<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Notifications\KenaikanPangkatEligibleNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

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

test('notification contains correct subject for sudah eligible', function () {
    Carbon::setTestNow('2026-01-01');

    $tmtKp = Carbon::parse('2024-01-01');
    $batasUsul = Carbon::parse('2025-10-01');
    $notification = new KenaikanPangkatEligibleNotification(
        tmtKpBerikutnya: $tmtKp,
        periodeUsul: 'Oktober 2026',
        batasUsul: $batasUsul,
        sisaHariUsul: 100,
        status: 'Sudah Eligible',
    );

    $mailData = $notification->toMail(createPegawaiWithKp('2024-01-01'));

    expect($mailData->subject)->toBe('Kenaikan Pangkat Eligible — Periode Oktober 2026');

    Carbon::setTestNow();
});

test('notification contains correct subject for mendekati eligible', function () {
    Carbon::setTestNow('2026-01-01');

    $tmtKp = Carbon::parse('2026-04-01');
    $batasUsul = Carbon::parse('2026-04-01');
    $notification = new KenaikanPangkatEligibleNotification(
        tmtKpBerikutnya: $tmtKp,
        periodeUsul: 'April 2026',
        batasUsul: $batasUsul,
        sisaHariUsul: 45,
        status: 'Mendekati Eligible',
    );

    $mailData = $notification->toMail(createPegawaiWithKp('2022-04-01'));

    expect($mailData->subject)->toBe('Pengingat Kenaikan Pangkat Mendekati Eligible — Periode April 2026');

    Carbon::setTestNow();
});

test('notification via method returns mail channel', function () {
    Carbon::setTestNow('2026-01-01');

    $tmtKp = Carbon::parse('2026-04-01');
    $batasUsul = Carbon::parse('2026-04-01');
    $notification = new KenaikanPangkatEligibleNotification(
        tmtKpBerikutnya: $tmtKp,
        periodeUsul: 'April 2026',
        batasUsul: $batasUsul,
        sisaHariUsul: 45,
        status: 'Mendekati Eligible',
    );

    $pegawai = createPegawaiWithKp('2022-04-01');

    expect($notification->via($pegawai))->toContain('mail');

    Carbon::setTestNow();
});

test('notification to array returns correct data', function () {
    Carbon::setTestNow('2026-01-01');

    $tmtKp = Carbon::parse('2026-04-01');
    $batasUsul = Carbon::parse('2026-04-01');
    $notification = new KenaikanPangkatEligibleNotification(
        tmtKpBerikutnya: $tmtKp,
        periodeUsul: 'April 2026',
        batasUsul: $batasUsul,
        sisaHariUsul: 45,
        status: 'Mendekati Eligible',
    );

    $pegawai = createPegawaiWithKp('2022-04-01');
    $array = $notification->toArray($pegawai);

    expect($array)->toHaveKeys(['tmt_kp_berikutnya', 'periode_usul', 'batas_usul', 'sisa_hari_usul', 'status'])
        ->and($array['periode_usul'])->toBe('April 2026')
        ->and($array['sisa_hari_usul'])->toBe(45)
        ->and($array['status'])->toBe('Mendekati Eligible');

    Carbon::setTestNow();
});

test('command runs successfully without errors', function () {
    $this->artisan('kp:notify')
        ->assertSuccessful();
});
