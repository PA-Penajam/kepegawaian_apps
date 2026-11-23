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
    ->group('tier3');

test('siklus SyncConsumer lintas fitur: store -> flash Inertia sync_token_once -> pull HMAC X-Nonce -> nonaktif is_active -> testConnection', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    Pegawai::factory()->count(2)->create();

    $storeResponse = $this->actingAs($admin)->post(route('iam.sinkronisasi.store'), [
        'nama' => 'Aplikasi Integrasi PA',
        'slug' => 'integrasi-pa',
        'base_url' => 'https://integrasi.pa-penajam.go.id',
        'deskripsi' => 'Konsumen terintegrasi',
    ]);

    $storeResponse->assertRedirect()
        ->assertSessionHas('sync_token_once');

    $flashToken = session('sync_token_once');
    $consumer = SyncConsumer::query()->where('slug', 'integrasi-pa')->firstOrFail();

    // Verifikasi Inertia props menerima sync_token_once sebelum dipakai client
    $this->actingAs($admin)
        ->withSession(['sync_token_once' => $flashToken])
        ->get(route('iam.sinkronisasi.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('iam/sinkronisasi/index')
            ->where('flash.sync_token_once.consumer_id', $consumer->id)
            ->where('flash.sync_token_once.plaintext', $flashToken['plaintext'])
            ->where('flash.sync_token_once.hmac_secret', $flashToken['hmac_secret'])
        );

    // Client menarik data pegawai dengan token + HMAC + X-Nonce
    $noncePertama = (string) Str::uuid();
    $syncResponse = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $flashToken['plaintext'],
        secret: $flashToken['hmac_secret'],
        nonce: $noncePertama,
    );

    $syncResponse->assertSuccessful()
        ->assertJsonStructure(['data', 'meta', 'synced_at']);

    expect(PegawaiSyncPull::query()->where('sync_consumer_id', $consumer->id)->count())->toBe(1);

    // Admin menonaktifkan SyncConsumer melalui update
    $this->actingAs($admin)->put(route('iam.sinkronisasi.update', $consumer), [
        'nama' => $consumer->nama,
        'slug' => $consumer->slug,
        'base_url' => $consumer->base_url,
        'is_active' => false,
    ])->assertRedirect();

    expect($consumer->fresh()->is_active)->toBeFalse();

    // Pull berikutnya harus ditolak saat konsumen nonaktif
    $blockedPull = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $flashToken['plaintext'],
        secret: $flashToken['hmac_secret'],
        nonce: (string) Str::uuid(),
    );

    expect($blockedPull->getStatusCode())->toBeIn([401, 403]);

    // Pengujian testConnection selaras dengan kontrak UI serta mengirim header X-Nonce
    Http::fake(['*' => Http::response(['message' => 'Konsumen tidak aktif'], 403)]);
    IamUiE2eHelper::jalankanDanVerifikasiTestConnection($this, $admin, $consumer, false);
    IamUiE2eHelper::assertPermintaanSyncConnectionTesterTerkirim();
});

test('isolasi IAM lintas aplikasi terintegrasi dengan RefRoleController, UserAksesController, dan preservasi cache', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

    // Pegawai memiliki hak iam.manage hanya di aplikasi persediaan, bukan kepegawaian
    $lintas = IamUiE2eHelper::buatPegawaiRoleAplikasiLain(
        appSlug: 'persediaan',
        permissionSlugs: ['iam.manage', 'pegawai.view'],
    );
    $pegawai = $lintas['pegawai'];
    $foreignRole = $lintas['role'];
    $foreignPerm = $lintas['permissions'][0];

    expect($pegawai->hasPermission('iam.manage'))->toBeFalse();
    $this->actingAs($pegawai)->get(route('iam.sinkronisasi.index'))->assertForbidden();

    // Admin tidak dapat mengelola role/permission aplikasi persediaan lewat RefRoleController
    $this->actingAs($admin)->get(route('referensi.roles.edit', $foreignRole))->assertNotFound();
    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Role Campur',
            'permissions' => [$foreignPerm->id],
        ])
        ->assertSessionHasErrors('permissions.0');

    // Simpan cache eksternal yang tidak boleh terhapus oleh operasi IAM
    Cache::put('cache_aplikasi_eksternal_penting', 'nilai_terjaga', 3600);

    $permKepegawaian = IamPermission::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'pegawai.view')
        ->firstOrFail();

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Koordinator Data',
            'keterangan' => 'Khusus kepegawaian',
            'permissions' => [$permKepegawaian->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    $newRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'koordinator-data')
        ->firstOrFail();

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $pegawai), [
            'iam_role_id' => $newRole->id,
        ])
        ->assertRedirect();

    expect($pegawai->fresh()->hasPermission('pegawai.view'))->toBeTrue()
        ->and($pegawai->fresh()->hasPermission('iam.manage'))->toBeFalse()
        ->and(Cache::get('cache_aplikasi_eksternal_penting'))->toBe('nilai_terjaga');
});

test('siklus autentikasi SSO di SP: login PKCE scope profile email -> CSP nonce production -> blokir nonaktif -> front-channel logout', function (): void {
    $pegawai = Pegawai::factory()->create([
        'nip' => '199203042020121005',
        'nama_lengkap' => 'Siti Aminah, S.H.',
        'status_pegawai' => StatusPegawai::Aktif,
    ]);

    // 1. Login SSO mengirim scope profile email
    $loginResponse = $this->get(route('auth.sso.login'));
    $authUrl = (string) ($loginResponse->headers->get('X-Inertia-Location') ?? $loginResponse->headers->get('Location'));
    expect(urldecode($authUrl))->toContain('scope=profile email')
        ->and($authUrl)->toContain('code_challenge_method=S256');

    $oauthState = session('sso.oauth_state');
    IamUiE2eHelper::fakeSsoIdp($pegawai->nip, $pegawai->nama_lengkap, $pegawai->email);

    // 2. Callback berhasil login
    $this->get(route('auth.sso.callback', [
        'code' => 'kode-otorisasi-pkce',
        'state' => $oauthState['state'],
    ]))->assertRedirect('/dashboard');

    // 3. Akses dashboard di mode production HTTPS memeriksa CSP nonce & session.secure
    $originalEnv = app()['env'];
    $originalAppUrl = config('app.url');
    $originalSessionSecure = config('session.secure');

    try {
        app()['env'] = 'production';
        config(['app.url' => 'https://simpeg.pa-penajam.go.id', 'session.secure' => null]);
        (new AppServiceProvider(app()))->boot();
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();

        $dashResponse = $this->actingAs($pegawai)->get(route('dashboard'));
        $dashResponse->assertSuccessful()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $csp = (string) $dashResponse->headers->get('Content-Security-Policy');
        expect($csp)->toMatch("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/")
            ->and(config('session.secure'))->toBeTrue();
    } finally {
        app()['env'] = $originalEnv;
        config(['app.url' => $originalAppUrl, 'session.secure' => $originalSessionSecure]);
        URL::forceScheme(null);
        DB::prohibitDestructiveCommands(app()->isProduction());
        Password::defaults(fn (): ?Password => null);
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();
    }

    // 4. Penonaktifan pegawai memaksa logout pada request berikutnya
    $pegawai->update(['status_pegawai' => StatusPegawai::Pensiun]);
    $deprovisionResponse = $this->actingAs($pegawai->fresh())->get(route('dashboard'));
    expect($deprovisionResponse->getStatusCode())->toBeIn([302, 401, 409])
        ->and(Auth::check())->toBeFalse();

    // 5. Pegawai aktif melakukan front-channel logout via Inertia::location
    $pegawaiAktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $logoutResponse = $this->actingAs($pegawaiAktif)
        ->withHeaders(IamUiE2eHelper::headerInertia())
        ->post(route('auth.sso.logout'));

    expect($logoutResponse->getStatusCode())->toBe(409)
        ->and((string) $logoutResponse->headers->get('X-Inertia-Location'))
        ->toStartWith(rtrim((string) config('sso.base_url'), '/').'/logout');
});

test('integrasi Activity Log, Paginasi Inertia, nama_lengkap, dan Sidebar navigasi', function (): void {
    $admin = Pegawai::factory()->admin()->create([
        'nama_lengkap' => 'Admin Pengelola Audit',
    ]);

    // Buat 22 log aktivitas agar paginasi > 1 halaman aktif
    $this->actingAs($admin);
    for ($i = 1; $i <= 22; $i++) {
        $target = Pegawai::factory()->create(['nama_lengkap' => "Pegawai Log {$i}"]);
        $target->update(['nama_lengkap' => "Pegawai Log Updated {$i}"]);
    }

    expect(Activity::query()->count())->toBeGreaterThan(20);

    $this->actingAs($admin)
        ->get(route('activity-log.index', ['subject_type' => 'Pegawai', 'page' => 2]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activity-log/index')
            ->where('auth.user.nama_lengkap', 'Admin Pengelola Audit')
            ->has('activities.data')
            ->has('activities.links')
            ->where('activities.current_page', 2)
            ->where('subjectTypes', fn ($types): bool => collect($types)->contains('Pegawai'))
        );

    $activityLogSource = IamUiE2eHelper::bacaFile('resources/js/pages/activity-log/index.tsx');
    $paginationSource = IamUiE2eHelper::bacaFile('resources/js/components/ui/pagination.tsx');
    $sidebarSource = IamUiE2eHelper::bacaFile('resources/js/components/app-sidebar.tsx');

    expect($activityLogSource)->toContain('value="all"')
        ->and($activityLogSource)->not->toContain('<SelectItem value=""')
        ->and($activityLogSource)->not->toContain('activities.meta')
        ->and($paginationSource)->toContain('<Link');

    IamUiE2eHelper::assertSidebarMemilikiTautan($sidebarSource, [
        'activity-log',
        'checklist-template',
    ]);
});

test('integritas Design System, Dark Mode, Kontras WCAG AA, Dialogs, dan 7+1 sub-halaman pegawai', function (): void {
    $css = IamUiE2eHelper::bacaFile('resources/css/app.css');
    $dashboard = IamUiE2eHelper::bacaFile('resources/js/pages/dashboard.tsx');
    $rolesIndex = IamUiE2eHelper::bacaFile('resources/js/pages/referensi/roles/index.tsx');
    $verifyEmail = IamUiE2eHelper::bacaFile('resources/js/pages/auth/verify-email.tsx');
    $profile = IamUiE2eHelper::bacaFile('resources/js/pages/settings/profile.tsx');
    $alertDialog = IamUiE2eHelper::bacaFile('resources/js/components/ui/alert-dialog.tsx');
    $passwordInput = IamUiE2eHelper::bacaFile('resources/js/components/password-input.tsx');

    expect($css)->toContain('.dark {')
        ->and($dashboard)->toContain('text-accent-foreground')
        ->and($dashboard)->toContain('DashboardHeavySection')
        ->and($rolesIndex)->toContain('text-primary-foreground')
        ->and($rolesIndex)->not->toContain('shadow-[4px_4px_0_')
        ->and($verifyEmail)->not->toMatch('/<Form\b(?:(?!<\/Form>)[\s\S])*<Form\b[^>]*action="\/auth\/sso\/logout"/s')
        ->and($profile)->toContain('nama_lengkap')
        ->and($profile)->not->toContain('name="name"')
        ->and($profile)->not->toContain('Profile information')
        ->and($profile)->not->toContain('<DeleteUser')
        ->and($alertDialog)->not->toMatch('/function\s+AlertDialogAction\b[\s\S]*?bg-primary\s+text-primary-foreground/s')
        ->and($passwordInput)->not->toContain('tabIndex={-1}');

    foreach (IamUiE2eHelper::daftarSubHalamanPegawai() as $path) {
        $content = IamUiE2eHelper::bacaFile($path);
        expect($content)->toContain('ConfirmDeleteDialog')
            ->and($content)->not->toContain('window.confirm')
            ->and($content)->toContain('AlertError');
    }
});

test('integrasi SSO Gateway: alur tamu login PKCE menuju callback selfSlug menolak redirect asing namun meloloskan host cocok', function (): void {
    $appUrl = rtrim((string) config('app.url'), '/');
    $selfSlug = (string) config('iam.app_slug', 'kepegawaian');
    $pegawai = Pegawai::factory()->create([
        'nip' => '199304052021121001',
        'status_pegawai' => StatusPegawai::Aktif,
    ]);

    // Tamu mencoba menginisiasi SSO login dengan redirect eksternal pada selfSlug harus ditolak
    $this->get('/sso/login?'.http_build_query([
        'app' => $selfSlug,
        'redirect' => 'https://attacker.test/steal',
    ]))->assertStatus(422);

    // Tamu menginisiasi SSO login dengan redirect sah ke aplikasi kepegawaian sendiri
    $this->get('/sso/login?'.http_build_query([
        'app' => $selfSlug,
        'redirect' => "{$appUrl}/dashboard",
        'state' => 'state-sah',
    ]))->assertRedirect(route('auth.sso.login'));

    $this->get(route('auth.sso.login'));
    $oauthState = session('sso.oauth_state');
    IamUiE2eHelper::fakeSsoIdp($pegawai->nip, $pegawai->nama_lengkap, $pegawai->email);

    $this->get(route('auth.sso.callback', [
        'code' => 'auth-code-sah',
        'state' => $oauthState['state'],
    ]))->assertRedirect(route('sso.callback'));

    $this->get(route('sso.callback'))->assertRedirect("{$appUrl}/dashboard");
});
