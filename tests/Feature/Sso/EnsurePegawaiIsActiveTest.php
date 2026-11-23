<?php

use App\Enums\StatusPegawai;
use App\Http\Middleware\EnsurePegawaiIsActive;
use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

test('EnsurePegawaiIsActive meloloskan pegawai aktif dan mengeluarkan pegawai nonaktif serta soft-deleted', function () {
    $this->withoutVite();

    $aktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $this->actingAs($aktif)->get(route('dashboard'))->assertOk();

    $nonaktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Pensiun]);
    $this->actingAs($nonaktif)->get(route('dashboard'))->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();

    $this->actingAs($nonaktif)->getJson(route('dashboard'))->assertUnauthorized();

    $dihapus = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $dihapus->delete();

    $webInertia = $this->actingAs($dihapus->fresh())
        ->withHeaders(['X-Inertia' => 'true'])
        ->get(route('dashboard'));
    expect($webInertia->getStatusCode())->toBe(409);
    expect(Auth::check())->toBeFalse();
});

test('PegawaiController update ke nonaktif dan destroy menghapus baris sesi database pegawai', function () {
    $admin = Pegawai::factory()->admin()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $target = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);

    DB::table('sessions')->insert([
        'id' => 'sess-target-update-1',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->put(route('kepegawaian.pegawai.update', $target), [
            'nip' => $target->nip,
            'nama_lengkap' => $target->nama_lengkap,
            'tempat_lahir' => $target->tempat_lahir,
            'tanggal_lahir' => $target->tanggal_lahir?->toDateString(),
            'jenis_kelamin' => $target->jenis_kelamin?->value,
            'agama' => $target->agama?->value,
            'status_perkawinan' => $target->status_perkawinan?->value,
            'email' => $target->email,
            'status_kepegawaian' => $target->status_kepegawaian->value,
            'status_pegawai' => StatusPegawai::Diberhentikan->value,
            'tanggal_masuk' => $target->tanggal_masuk?->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);

    DB::table('sessions')->insert([
        'id' => 'sess-target-delete-2',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->delete(route('kepegawaian.pegawai.destroy', $target))
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

test('pegawai nonaktif dan soft-deleted yang mengakses sso login atau callback otomatis di-logout tanpa IamSsoCode', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'app-satker',
        'url' => 'http://satker.local',
        'is_active' => true,
    ]);

    $nonaktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Pensiun]);

    $this->actingAs($nonaktif)
        ->get('/sso/login?app=app-satker&redirect=http://satker.local/callback')
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(IamSsoCode::count())->toBe(0);

    $this->actingAs($nonaktif)
        ->withSession(['sso_app' => $app->slug, 'sso_redirect' => 'http://satker.local/callback'])
        ->get('/sso/callback')
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(IamSsoCode::count())->toBe(0);

    $dihapus = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $dihapus->delete();
    $dihapus = $dihapus->fresh();

    $this->actingAs($dihapus)
        ->get('/sso/login?app=app-satker&redirect=http://satker.local/callback')
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(IamSsoCode::count())->toBe(0);

    $this->actingAs($dihapus)
        ->withSession(['sso_app' => $app->slug, 'sso_redirect' => 'http://satker.local/callback'])
        ->get('/sso/callback')
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
    expect(IamSsoCode::count())->toBe(0);
});

test('SsoController menjaga defense-in-depth saat middleware EnsurePegawaiIsActive dilewati untuk pegawai nonaktif atau soft-deleted', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'app-satker-2',
        'url' => 'http://satker2.local',
        'is_active' => true,
    ]);

    $nonaktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Diberhentikan]);
    $dihapus = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $dihapus->delete();
    $dihapus = $dihapus->fresh();

    foreach ([$nonaktif, $dihapus] as $pegawai) {
        $this->withoutMiddleware(EnsurePegawaiIsActive::class)
            ->actingAs($pegawai)
            ->get('/sso/login?app=app-satker-2&redirect=http://satker2.local/callback')
            ->assertRedirect(route('login'));
        expect(Auth::check())->toBeFalse();
        expect(IamSsoCode::count())->toBe(0);

        $this->withoutMiddleware(EnsurePegawaiIsActive::class)
            ->actingAs($pegawai)
            ->withSession(['sso_app' => $app->slug, 'sso_redirect' => 'http://satker2.local/callback'])
            ->get('/sso/callback')
            ->assertRedirect(route('login'));
        expect(Auth::check())->toBeFalse();
        expect(IamSsoCode::count())->toBe(0);

        $selfRedirect = rtrim((string) config('app.url'), '/').'/dashboard';
        $this->withoutMiddleware(EnsurePegawaiIsActive::class)
            ->actingAs($pegawai)
            ->withSession(['sso_app' => config('iam.app_slug', 'kepegawaian'), 'sso_redirect' => $selfRedirect])
            ->get('/sso/callback')
            ->assertRedirect(route('login'));
        expect(Auth::check())->toBeFalse();
    }
});

test('tamu dan pegawai aktif tetap dapat melewati sso login dan callback secara normal', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'app-aktif',
        'url' => 'http://aktif.local',
        'is_active' => true,
    ]);

    $this->get('/sso/login?app=app-aktif&redirect=http://aktif.local/callback')
        ->assertRedirect(route('auth.sso.login'));

    $aktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);

    $loginResponse = $this->actingAs($aktif)
        ->get('/sso/login?app=app-aktif&redirect=http://aktif.local/callback');
    $loginResponse->assertRedirect();
    expect($loginResponse->headers->get('Location'))->toStartWith('http://aktif.local/callback?code=');
    expect(IamSsoCode::where('user_id', $aktif->id)->count())->toBe(1);

    $callbackResponse = $this->actingAs($aktif)
        ->withSession(['sso_app' => $app->slug, 'sso_redirect' => 'http://aktif.local/callback'])
        ->get('/sso/callback');
    $callbackResponse->assertRedirect();
    expect($callbackResponse->headers->get('Location'))->toStartWith('http://aktif.local/callback?code=');
    expect(IamSsoCode::where('user_id', $aktif->id)->count())->toBe(2);
});
