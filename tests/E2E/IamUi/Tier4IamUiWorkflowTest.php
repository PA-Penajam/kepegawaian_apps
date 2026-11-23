<?php

use App\Enums\StatusPegawai;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\PegawaiSyncPull;
use App\Models\SyncConsumer;
use App\Providers\AppServiceProvider;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite as FoundationVite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\E2E\IamUi\Support\IamUiE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/IamUiE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
        $this->seed(IamSeeder::class);
    })
    ->group('tier4');

test('alur end-to-end Admin IAM & Sinkronisasi: kelola role referensi tanpa rusak cache SSO, daftarkan SyncConsumer, pull HMAC X-Nonce anti-replay, uji koneksi, dan audit Activity Log', function (): void {
    $admin = Pegawai::factory()->admin()->create(['nama_lengkap' => 'Admin IAM Utama']);
    $anggota = Pegawai::factory()->create(['nama_lengkap' => 'Staf Sinkronisasi']);
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

    // Kunci cache SSO/eksternal yang wajib tetap terjaga selama seluruh mutasi IAM
    Cache::put('sso_jwks_atau_sesi_eksternal', 'tetap_valid', 3600);

    // 1. Kelola role referensi khusus kepegawaian
    $permView = IamPermission::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'pegawai.view')
        ->firstOrFail();

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Operator Sinkronisasi',
            'keterangan' => 'Role khusus pengelolaan data sinkronisasi',
            'permissions' => [$permView->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    $role = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'operator-sinkronisasi')
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $role), [
            'nama' => 'Operator Sinkronisasi Aktif',
            'keterangan' => 'Diperbarui admin',
            'permissions' => [$permView->id],
            'pegawai_ids' => [$anggota->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    expect($anggota->fresh()->hasPermission('pegawai.view'))->toBeTrue()
        ->and(Cache::get('sso_jwks_atau_sesi_eksternal'))->toBe('tetap_valid');

    // 2. Daftarkan SyncConsumer baru dan verifikasi flash sync_token_once via Inertia
    $this->actingAs($admin)
        ->post(route('iam.sinkronisasi.store'), [
            'nama' => 'SIKEP Integrasi Daerah',
            'slug' => 'sikep-integrasi-daerah',
            'base_url' => 'https://sikep-daerah.test',
            'deskripsi' => 'Konsumen utama sinkronisasi pegawai',
        ])
        ->assertRedirect()
        ->assertSessionHas('sync_token_once');

    $flashCredentials = session('sync_token_once');
    $consumer = SyncConsumer::query()->where('slug', 'sikep-integrasi-daerah')->firstOrFail();

    $this->actingAs($admin)
        ->withSession(['sync_token_once' => $flashCredentials])
        ->get(route('iam.sinkronisasi.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('iam/sinkronisasi/index')
            ->where('flash.sync_token_once.consumer_slug', 'sikep-integrasi-daerah')
            ->where('flash.sync_token_once.plaintext', $flashCredentials['plaintext'])
            ->where('flash.sync_token_once.hmac_secret', $flashCredentials['hmac_secret'])
        );

    // 3. Tarik API /api/v1/pegawai/sync dengan HMAC + X-Nonce unik, lalu blokir replay attack
    $nonceUnik = (string) Str::uuid();
    $pullResponse = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $flashCredentials['plaintext'],
        secret: $flashCredentials['hmac_secret'],
        query: ['page' => 1, 'per_page' => 10],
        nonce: $nonceUnik,
    );

    $pullResponse->assertSuccessful()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'synced_at']);

    // Replay attack memakai X-Nonce yang sama harus ditolak 401
    IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $flashCredentials['plaintext'],
        secret: $flashCredentials['hmac_secret'],
        query: ['page' => 1, 'per_page' => 10],
        nonce: $nonceUnik,
    )->assertUnauthorized();

    expect(PegawaiSyncPull::query()->where('sync_consumer_id', $consumer->id)->count())->toBe(1);

    // 4. Uji koneksi SyncConsumer selaras dengan kontrak UI & header X-Nonce, lalu periksa riwayat Activity Log terpaginasi
    Http::fake([
        '*' => Http::response(['data' => [], 'meta' => ['total' => 2]], 200),
    ]);

    IamUiE2eHelper::jalankanDanVerifikasiTestConnection($this, $admin, $consumer, true);
    IamUiE2eHelper::assertPermintaanSyncConnectionTesterTerkirim();

    expect($consumer->fresh()->last_connection_test_status)->toBe('success')
        ->and(Activity::query()->count())->toBeGreaterThan(0);

    $this->actingAs($admin)
        ->get(route('activity-log.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activity-log/index')
            ->has('activities.data')
            ->has('activities.links')
            ->has('subjectTypes')
        );
});

test('alur end-to-end Sesi SSO & Kepatuhan UI Produksi: login PKCE scope profile email, Dashboard CSP nonce & nama_lengkap, profil SSO-only aman, penonaktifan cabut sesi, dan front-channel logout', function (): void {
    $pegawai = Pegawai::factory()->admin()->create([
        'nip' => '199105152020121007',
        'nama_lengkap' => 'Rizky Pratama, S.Kom.',
        'status_pegawai' => StatusPegawai::Aktif,
    ]);

    // 1. Login lokal diblokir (SSO-only), login PKCE mengirim scope profile email
    $this->post('/login', ['nip' => $pegawai->nip, 'password' => 'password'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $loginRedirect = $this->get(route('auth.sso.login'));
    $authUrl = (string) ($loginRedirect->headers->get('X-Inertia-Location') ?? $loginRedirect->headers->get('Location'));
    expect(urldecode($authUrl))->toContain('scope=profile email')
        ->and($authUrl)->toContain('code_challenge_method=S256');

    $oauthState = session('sso.oauth_state');
    IamUiE2eHelper::fakeSsoIdp($pegawai->nip, $pegawai->nama_lengkap, $pegawai->email);

    $this->get(route('auth.sso.callback', [
        'code' => 'kode-e2e-workflow',
        'state' => $oauthState['state'],
    ]))->assertRedirect('/dashboard');

    expect(Auth::id())->toBe($pegawai->id);

    // 2. Akses Dashboard dan Settings Profile pada environment production HTTPS
    $originalEnv = app()['env'];
    $originalAppUrl = config('app.url');
    $originalSessionSecure = config('session.secure');

    try {
        app()['env'] = 'production';
        config(['app.url' => 'https://simpeg.pa-penajam.go.id', 'session.secure' => null]);
        (new AppServiceProvider(app()))->boot();
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();

        $dashboardResponse = $this->actingAs($pegawai)->get(route('dashboard'));
        $dashboardResponse->assertSuccessful()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('auth.user.nama_lengkap', 'Rizky Pratama, S.Kom.')
                ->where('auth.sso', true)
            );

        $csp = (string) $dashboardResponse->headers->get('Content-Security-Policy');
        expect($csp)->toMatch("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/")
            ->and($csp)->toContain("font-src 'self' data: https://fonts.bunny.net")
            ->and(config('session.secure'))->toBeTrue();

        $this->actingAs($pegawai)
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('auth.user.nama_lengkap', 'Rizky Pratama, S.Kom.')
            );
    } finally {
        app()['env'] = $originalEnv;
        config(['app.url' => $originalAppUrl, 'session.secure' => $originalSessionSecure]);
        URL::forceScheme(null);
        DB::prohibitDestructiveCommands(app()->isProduction());
        Password::defaults(fn (): ?Password => null);
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();
    }

    // 3. Verifikasi kepatuhan UI kontrak: Sidebar, Dark Mode, WCAG AA, Border-First DESIGN.md, & profil SSO-only aman
    $sidebar = IamUiE2eHelper::bacaFile('resources/js/components/app-sidebar.tsx');
    $css = IamUiE2eHelper::bacaFile('resources/css/app.css');
    $dashboardSource = IamUiE2eHelper::bacaFile('resources/js/pages/dashboard.tsx');
    $designMd = IamUiE2eHelper::bacaFile('DESIGN.md');

    IamUiE2eHelper::assertSidebarMemilikiTautan($sidebar, [
        'usulan',
        'checklist-template',
        'activity-log',
    ]);

    expect($css)->toContain('.dark {')
        ->and($dashboardSource)->toContain('text-accent-foreground')
        ->and($designMd)->toContain('Border-First Elevation');

    $deleteProfileResponse = $this->actingAs($pegawai)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);
    expect($deleteProfileResponse->getStatusCode())->toBeIn([403, 404, 405])
        ->and($pegawai->fresh()->deleted_at)->toBeNull();

    // 4. Penonaktifan pegawai oleh admin mencabut sesi DB & memaksa logout seketika
    $adminLain = Pegawai::factory()->admin()->create(['status_pegawai' => StatusPegawai::Aktif]);
    DB::table('sessions')->insert([
        'id' => 'sess-e2e-pegawai-aktif',
        'user_id' => $pegawai->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestWorkflow',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($adminLain)
        ->put(route('kepegawaian.pegawai.update', $pegawai), IamUiE2eHelper::payloadUpdatePegawai($pegawai, [
            'status_pegawai' => StatusPegawai::Diberhentikan->value,
        ]))
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $pegawai->id)->count())->toBe(0);

    $nextWebRequest = $this->actingAs($pegawai->fresh())->get(route('dashboard'));
    expect($nextWebRequest->getStatusCode())->toBeIn([302, 401, 409])
        ->and(Auth::check())->toBeFalse();

    // 5. Pegawai aktif melakukan front-channel logout menuju IdP GET /logout via Inertia::location
    $logoutResponse = $this->actingAs($adminLain)
        ->withHeaders(IamUiE2eHelper::headerInertia())
        ->post(route('auth.sso.logout'));

    expect($logoutResponse->getStatusCode())->toBe(409)
        ->and((string) $logoutResponse->headers->get('X-Inertia-Location'))
        ->toStartWith(rtrim((string) config('sso.base_url'), '/').'/logout')
        ->and(Auth::check())->toBeFalse();
});
