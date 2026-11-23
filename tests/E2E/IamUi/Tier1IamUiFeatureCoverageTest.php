<?php

use App\Enums\StatusPegawai;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\PegawaiSyncPull;
use App\Models\SyncConsumer;
use App\Providers\AppServiceProvider;
use App\Services\Cuti\ApproverResolverService;
use App\Services\Iam\SyncConsumerCredentialService;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite as FoundationVite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
    ->group('tier1');

test('F-10: gateway SSO menerima redirect dengan skema, host, dan port yang cocok untuk aplikasi eksternal', function (): void {
    IamApplication::factory()->create([
        'slug' => 'attendance-port',
        'url' => 'https://att.local:8443',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();

    $response = $this->actingAs($pegawai)->get('/sso/login?'.http_build_query([
        'app' => 'attendance-port',
        'redirect' => 'https://att.local:8443/auth/callback',
        'state' => 'csrf-state-valid',
    ]));

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');

    expect($location)->toStartWith('https://att.local:8443/auth/callback?code=')
        ->and($location)->toContain('&state=csrf-state-valid');
});

test('F-10: gateway SSO menerima redirect valid untuk aplikasi kepegawaian sendiri (selfSlug) saat callback', function (): void {
    $appUrl = rtrim((string) config('app.url'), '/');
    $pegawai = Pegawai::factory()->create();

    session([
        'sso_app' => config('iam.app_slug', 'kepegawaian'),
        'sso_redirect' => "{$appUrl}/dashboard",
        'sso_state' => 'self-state',
    ]);

    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    $response->assertRedirect("{$appUrl}/dashboard");
});

test('F-13 & F-62: HandleInertiaRequests membagikan flash sync_token_once dan alert-error memiliki guard array kosong', function (): void {
    $admin = Pegawai::factory()->admin()->create();

    $storeResponse = $this->actingAs($admin)->post(route('iam.sinkronisasi.store'), [
        'nama' => 'Aplikasi Portal',
        'slug' => 'aplikasi-portal',
        'base_url' => 'https://portal.test',
    ]);

    $storeResponse->assertRedirect();
    $storeResponse->assertSessionHas('sync_token_once');

    $flashPayload = session('sync_token_once');

    // Verifikasi HandleInertiaRequests membagikan flash.sync_token_once pada request Inertia berikutnya
    $this->actingAs($admin)
        ->withSession(['sync_token_once' => $flashPayload])
        ->get(route('iam.sinkronisasi.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('iam/sinkronisasi/index')
            ->where('flash.sync_token_once.consumer_slug', 'aplikasi-portal')
            ->where('flash.sync_token_once.plaintext', $flashPayload['plaintext'])
            ->where('flash.sync_token_once.hmac_secret', $flashPayload['hmac_secret'])
        );

    $alertErrorSource = IamUiE2eHelper::bacaFile('resources/js/components/alert-error.tsx');
    expect($alertErrorSource)->toMatch('/\b(?:errors|uniqueErrors)\.length\s*(?:===|!==|<=|<)\s*0|!(?:errors|uniqueErrors)\??\.length/');
});

test('F-13 & F-62: testConnection selaras dengan kontrak UI serta mengirim X-Nonce, dan UI sinkronisasi memiliki kontrol edit is_active, processing dinamis, serta SyncTokenModal X-Nonce', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'portal-uji',
        'is_active' => true,
    ]);

    $credentials = app(SyncConsumerCredentialService::class);
    $credentials->ensureHmacSecret($consumer);
    $credentials->issueToken($consumer);

    Http::fake([
        '*' => Http::response(['data' => [], 'meta' => ['total' => 0]], 200),
    ]);

    IamUiE2eHelper::jalankanDanVerifikasiTestConnection($this, $admin, $consumer, true);
    IamUiE2eHelper::assertPermintaanSyncConnectionTesterTerkirim();

    // Simulasikan kegagalan koneksi agar endpoint mengembalikan pesan error bersih tanpa stack trace / SQLSTATE
    Http::fake([
        '*' => fn () => throw new RuntimeException('SQLSTATE[HY000]: Connection refused internal detail'),
    ]);

    IamUiE2eHelper::jalankanDanVerifikasiTestConnection($this, $admin, $consumer, false);

    $sinkronisasiSource = IamUiE2eHelper::bacaFile('resources/js/pages/iam/sinkronisasi/index.tsx');
    expect($sinkronisasiSource)->toContain('X-Nonce')
        ->and($sinkronisasiSource)->not->toContain('?page=1=1')
        ->and($sinkronisasiSource)->toContain('is_active');

    // Verifikasi kontrol UI is_active benar-benar ada di dalam blok JSX modal Edit Konsumen
    $editModalMatched = preg_match('/Edit Konsumen[\s\S]*?<\/Dialog>/s', $sinkronisasiSource, $editModalParts);
    expect($editModalMatched)->toBe(1);
    $editModalBlock = $editModalParts[0];

    expect($editModalBlock)->toMatch('/<(?:Switch|Checkbox|input|Input)\b(?:(?!\/>|<\/(?:Switch|Checkbox|input|Input)>)[\s\S])*?(?:editForm\.data\.is_active|editForm\.setData\(\s*[\'"]is_active[\'"])|<Select\b(?:(?!<\/Select>)[\s\S])*?(?:editForm\.data\.is_active|editForm\.setData\(\s*[\'"]is_active[\'"])/s');

    // Verifikasi ConfirmDeleteDialog tidak meng-hardcode processing={false}
    $deleteDialogMatched = preg_match('/<ConfirmDeleteDialog\b[\s\S]*?\/>/s', $sinkronisasiSource, $deleteDialogParts);
    expect($deleteDialogMatched)->toBe(1);
    $deleteDialogBlock = $deleteDialogParts[0];

    expect($deleteDialogBlock)->toContain('processing={')
        ->and($deleteDialogBlock)->not->toMatch('/processing=\{\s*false\s*\}/');

    // Verifikasi SyncTokenModal mencantumkan header X-Nonce pada dokumentasi snippet
    $syncTokenModalSource = IamUiE2eHelper::bacaFile('resources/js/components/iam/SyncTokenModal.tsx');
    expect($syncTokenModalSource)->toContain('X-Nonce');
});

test('F-14 & F-63: halaman activity-log merender prop activities, subjectTypes, dan filter subject_type', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'ASN Awal']);

    $this->actingAs($admin);
    $pegawai->update(['nama_lengkap' => 'ASN Diperbarui']);

    $this->actingAs($admin)
        ->get(route('activity-log.index', ['subject_type' => 'Pegawai']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activity-log/index')
            ->has('activities.data')
            ->has('activities.links')
            ->has('activities.last_page')
            ->where('subjectTypes', fn ($types): bool => collect($types)->contains('Pegawai'))
        );

    expect(Activity::query()->where('subject_type', Pegawai::class)->exists())->toBeTrue();
});

test('F-14 & F-63: komponen UI activity-log dan 4 halaman paginasi memakai root paginator serta pagination memakai Inertia Link berbahasa Indonesia', function (): void {
    $activityLogSource = IamUiE2eHelper::bacaFile('resources/js/pages/activity-log/index.tsx');
    expect($activityLogSource)->not->toContain('<SelectItem value=""')
        ->and($activityLogSource)->toContain('value="all"')
        ->and($activityLogSource)->not->toContain('activities.meta')
        ->and($activityLogSource)->toContain('links={activities.links}');

    $halamanLain = [
        'resources/js/pages/admin/checklist-template/index.tsx',
        'resources/js/pages/kepegawaian/monitoring/kenaikan-pangkat/index.tsx',
        'resources/js/pages/kenaikan-pangkat/usulan/index.tsx',
        'resources/js/pages/kenaikan-pangkat/eligible/index.tsx',
    ];

    foreach ($halamanLain as $path) {
        $source = IamUiE2eHelper::bacaFile($path);
        expect($source)->not->toMatch('/<PaginationWrapper\s+meta=\{[^}]+\.meta\}/');
    }

    $paginationSource = IamUiE2eHelper::bacaFile('resources/js/components/ui/pagination.tsx');
    expect($paginationSource)->toContain("from '@inertiajs/react'")
        ->and($paginationSource)->toContain('<Link')
        ->and($paginationSource)->toContain('Sebelumnya')
        ->and($paginationSource)->toContain('Berikutnya');
});

test('F-20: Pegawai, HandleInertiaRequests, dan ApproverResolverService hanya memuat permission dari aplikasi kepegawaian', function (): void {
    $pegawai = IamUiE2eHelper::buatPegawaiDenganPermission(['pegawai.view', 'cuti.pengajuan.verify']);
    $otherApp = IamApplication::factory()->create(['slug' => 'sikep-lain', 'is_active' => true]);
    $otherRole = IamRole::factory()->create(['iam_application_id' => $otherApp->id, 'slug' => 'other-admin']);
    $otherPerm = IamPermission::factory()->create(['iam_application_id' => $otherApp->id, 'slug' => 'lintas.app.only']);
    $otherRole->permissions()->attach($otherPerm->id);
    $pegawai->iamRoles()->attach($otherRole->id, ['assigned_at' => now()]);

    $fresh = $pegawai->fresh();
    expect($fresh->hasPermission('pegawai.view'))->toBeTrue()
        ->and($fresh->hasPermission('lintas.app.only'))->toBeFalse();

    $this->actingAs($fresh)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.permissions', fn ($perms): bool => collect($perms)->contains('pegawai.view')
                && ! collect($perms)->contains('lintas.app.only'))
        );

    $pemohon = Pegawai::factory()->create();
    $snapshot = app(ApproverResolverService::class)->resolveSnapshot($pemohon->nip);
    expect($snapshot['petugas_kepegawaian'])->toBe($fresh->nip);
});

test('F-21: RefRoleController terisolasi pada aplikasi kepegawaian dan melakukan invalidasi cache terarah', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();
    $otherApp = IamApplication::factory()->create(['slug' => 'simpan-pinjam', 'is_active' => true]);

    IamRole::factory()->create([
        'iam_application_id' => $otherApp->id,
        'nama' => 'Role Aplikasi Lain',
        'slug' => 'role-aplikasi-lain',
    ]);
    IamPermission::factory()->create([
        'iam_application_id' => $otherApp->id,
        'nama' => 'Izin Aplikasi Lain',
        'slug' => 'izin.aplikasi.lain',
    ]);

    $permKepegawaian = IamPermission::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('referensi.roles.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('referensi/roles/index')
            ->where('roles.data', fn ($roles): bool => collect($roles)->every(
                fn ($r) => $r['slug'] !== 'role-aplikasi-lain'
            ))
            ->where('permissions', fn ($perms): bool => collect($perms)->every(
                fn ($p) => $p['id'] !== null && ! str_contains((string) $p['nama'], 'Izin Aplikasi Lain')
            ))
        );

    $staleApp = (clone $kepegawaianApp)->forceFill(['nama' => 'stale-app-data']);

    Cache::put('iam_app:kepegawaian', $staleApp, 3600);
    Cache::put('kunci_non_iam_tetap_aman', 'utuh', 3600);

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Operator Arsip Baru',
            'keterangan' => 'Role kustom kepegawaian',
            'permissions' => [$permKepegawaian->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    $createdRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'operator-arsip-baru')
        ->first();

    expect($createdRole)->not->toBeNull()
        ->and(Cache::has('iam_app:kepegawaian'))->toBeFalse()
        ->and(Cache::get('kunci_non_iam_tetap_aman'))->toBe('utuh');

    // Update role wajib menginvalidasi cache iam_app:kepegawaian tanpa menghapus kunci non-IAM
    Cache::put('iam_app:kepegawaian', $staleApp, 3600);
    Cache::put('kunci_non_iam_tetap_aman', 'utuh', 3600);

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $createdRole), [
            'nama' => 'Operator Arsip Diperbarui',
            'keterangan' => 'Role kustom kepegawaian diperbarui',
            'permissions' => [$permKepegawaian->id],
        ])
        ->assertRedirect(route('referensi.roles.index'));

    expect($createdRole->fresh()->nama)->toBe('Operator Arsip Diperbarui')
        ->and(Cache::has('iam_app:kepegawaian'))->toBeFalse()
        ->and(Cache::get('kunci_non_iam_tetap_aman'))->toBe('utuh');

    // Destroy role wajib menginvalidasi cache iam_app:kepegawaian tanpa menghapus kunci non-IAM
    Cache::put('iam_app:kepegawaian', $staleApp, 3600);
    Cache::put('kunci_non_iam_tetap_aman', 'utuh', 3600);

    $this->actingAs($admin)
        ->delete(route('referensi.roles.destroy', $createdRole))
        ->assertRedirect(route('referensi.roles.index'));

    expect(IamRole::query()->whereKey($createdRole->id)->exists())->toBeFalse()
        ->and(Cache::has('iam_app:kepegawaian'))->toBeFalse()
        ->and(Cache::get('kunci_non_iam_tetap_aman'))->toBe('utuh');
});

test('F-22: UserAksesController menetapkan dan mencabut role tanpa menghapus kunci cache lain', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();
    $operatorRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'operator')
        ->firstOrFail();

    Cache::put('sentinel_cache_lain', 'tetap_ada', 3600);

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $target), [
            'iam_role_id' => $operatorRole->id,
        ])
        ->assertRedirect();

    expect($target->fresh()->hasPermission('pegawai.update'))->toBeTrue()
        ->and(Cache::get('sentinel_cache_lain'))->toBe('tetap_ada');

    $this->actingAs($admin)
        ->delete(route('iam.users.akses.destroy', [$target, $operatorRole]))
        ->assertRedirect();

    expect($target->fresh()->hasPermission('pegawai.update'))->toBeFalse()
        ->and(Cache::get('sentinel_cache_lain'))->toBe('tetap_ada');
});

test('F-24 (SP): SsoAuthController logout menggunakan Inertia::location ke IdP GET /logout saat X-Inertia', function (): void {
    $pegawai = Pegawai::factory()->create();
    $baseUrl = rtrim((string) config('sso.base_url'), '/');

    $response = $this->actingAs($pegawai)
        ->withHeaders(IamUiE2eHelper::headerInertia())
        ->post(route('auth.sso.logout'));

    expect($response->getStatusCode())->toBe(409);

    $location = (string) $response->headers->get('X-Inertia-Location');
    expect($location)->toStartWith("{$baseUrl}/logout")
        ->and(auth()->check())->toBeFalse();
});

test('F-60 (SP): config sso dan .env.example menyetel default scope profile email saat otorisasi OAuth2', function (): void {
    $envExample = IamUiE2eHelper::bacaFile('.env.example');

    expect($envExample)->toContain('SSO_SCOPES="profile email"')
        ->and(config('sso.scopes'))->toBe(['profile', 'email']);

    $response = $this->get(route('auth.sso.login'));
    $location = (string) ($response->headers->get('X-Inertia-Location') ?? $response->headers->get('Location'));

    expect($location)->toContain(rtrim((string) config('sso.base_url'), '/').'/oauth/authorize')
        ->and(urldecode($location))->toContain('scope=profile email');
});

test('F-25: SyncConsumer ber-ability sync:pegawai dapat menarik /api/v1/pegawai/sync dengan HMAC dan X-Nonce valid', function (): void {
    Pegawai::factory()->count(2)->create();
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'client-sync-resmi',
        'is_active' => true,
    ]);

    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $issued = $service->issueToken($consumer);

    $tokenModel = $consumer->tokens()->firstOrFail();
    expect($tokenModel->abilities)->toContain('sync:pegawai');

    $response = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $issued['plaintext'],
        secret: $secret,
    );

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'synced_at']);

    expect(PegawaiSyncPull::query()->where('sync_consumer_id', $consumer->id)->exists())->toBeTrue();
});

test('F-72: StoreSyncConsumerRequest menerima base_url berskema http dan https yang valid', function (): void {
    $admin = Pegawai::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('iam.sinkronisasi.store'), [
            'nama' => 'Client HTTPS',
            'slug' => 'client-https-valid',
            'base_url' => 'https://client.pa-penajam.go.id',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('iam.sinkronisasi.store'), [
            'nama' => 'Client HTTP Lokal',
            'slug' => 'client-http-valid',
            'base_url' => 'http://localhost:8080',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('F-27 (SP): middleware meloloskan Pegawai Aktif dan penonaktifan di PegawaiController menginvalidasi sesi DB', function (): void {
    $admin = Pegawai::factory()->admin()->create(['status_pegawai' => StatusPegawai::Aktif]);
    $target = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);

    $this->actingAs($target)->get(route('dashboard'))->assertSuccessful();

    DB::table('sessions')->insert([
        'id' => 'sess-target-aktif-123',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->put(route('kepegawaian.pegawai.update', $target), IamUiE2eHelper::payloadUpdatePegawai($target, [
            'status_pegawai' => StatusPegawai::Pensiun->value,
        ]))
        ->assertRedirect();

    expect($target->fresh()->status_pegawai)->toBe(StatusPegawai::Pensiun)
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

test('F-37: mode production menyertakan CSP nonce dengan fonts.bunny.net serta blob: dan unauthenticated Inertia mendapat 409', function (): void {
    $originalEnv = app()['env'];

    try {
        app()['env'] = 'production';
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();

        $response = $this->get(route('login'));
        $response->assertSuccessful()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        expect($csp)->toMatch("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/")
            ->and($csp)->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net")
            ->and($csp)->toContain("font-src 'self' data: https://fonts.bunny.net")
            ->and($csp)->toContain("img-src 'self' data: blob: https:");
    } finally {
        app()['env'] = $originalEnv;
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();
    }

    $unauthenticated = $this->withHeaders(IamUiE2eHelper::headerInertia())
        ->get(route('dashboard'));

    expect($unauthenticated->getStatusCode())->toBe(409)
        ->and($unauthenticated->headers->get('X-Inertia-Location'))->toBe(route('auth.sso.login'));
});

test('F-61: AppServiceProvider menyetel session.secure otomatis di production HTTPS jika belum disetel eksplisit', function (): void {
    $originalEnv = app()['env'];
    $originalAppUrl = config('app.url');
    $originalSessionSecure = config('session.secure');

    try {
        app()['env'] = 'production';
        config([
            'app.url' => 'https://simpeg.pa-penajam.go.id',
            'session.secure' => null,
        ]);

        (new AppServiceProvider(app()))->boot();
        expect(config('session.secure'))->toBeTrue();

        config(['session.secure' => false]);
        (new AppServiceProvider(app()))->boot();
        expect(config('session.secure'))->toBeFalse();
    } finally {
        app()['env'] = $originalEnv;
        config([
            'app.url' => $originalAppUrl,
            'session.secure' => $originalSessionSecure,
        ]);
        URL::forceScheme(null);
        DB::prohibitDestructiveCommands(app()->isProduction());
        Password::defaults(fn (): ?Password => null);
    }
});

test('F-41, F-42 & F-49: token .dark CSS, kontras WCAG AA di dashboard & roles, serta desain Border-First tanpa Neo-Brutalism', function (): void {
    $css = IamUiE2eHelper::bacaFile('resources/css/app.css');
    expect($css)->toContain('.dark {')
        ->and($css)->toContain('--background:')
        ->and($css)->toContain('--foreground:')
        ->and($css)->toContain('--primary:')
        ->and($css)->toContain('--accent-foreground:');

    $dashboard = IamUiE2eHelper::bacaFile('resources/js/pages/dashboard.tsx');
    expect($dashboard)->toContain('text-accent-foreground');

    $rolesIndex = IamUiE2eHelper::bacaFile('resources/js/pages/referensi/roles/index.tsx');
    expect($rolesIndex)->toContain('text-primary-foreground')
        ->and($rolesIndex)->not->toContain('text-black');

    $designMd = IamUiE2eHelper::bacaFile('DESIGN.md');
    expect($designMd)->toContain('Border-First Elevation');

    $halamanBorderFirst = [
        'resources/js/pages/referensi/roles/index.tsx',
        'resources/js/pages/activity-log/index.tsx',
        'resources/js/pages/iam/users/akses.tsx',
        'resources/js/pages/kepegawaian/pegawai/show.tsx',
        'resources/js/pages/self-service/detail.tsx',
        'resources/js/components/ui/card.tsx',
        'resources/js/components/ui/badge.tsx',
        'resources/js/components/ui/button.tsx',
    ];

    foreach ($halamanBorderFirst as $path) {
        $source = IamUiE2eHelper::bacaFile($path);
        expect($source)->not->toContain('shadow-[4px_4px_0_')
            ->and($source)->not->toContain('shadow-[8px_8px_0_')
            ->and($source)->not->toContain('drop-shadow-[4px_4px_0_')
            ->and($source)->not->toContain('border-2 border-foreground');
    }
});

test('F-43 & F-44: settings/profile berbahasa Indonesia memakai nama_lengkap serta DashboardHeader & iam/users/akses memakai nama_lengkap', function (): void {
    $profileSource = IamUiE2eHelper::bacaFile('resources/js/pages/settings/profile.tsx');
    expect($profileSource)->toContain('nama_lengkap')
        ->and($profileSource)->not->toContain('name="name"')
        ->and($profileSource)->not->toContain('<DeleteUser')
        ->and($profileSource)->not->toContain('Profile information');

    $dashboardHeader = IamUiE2eHelper::bacaFile('resources/js/components/dashboard/DashboardHeader.tsx');
    expect($dashboardHeader)->toContain('nama_lengkap')
        ->and($dashboardHeader)->not->toContain('auth?.user?.name');

    $userAkses = IamUiE2eHelper::bacaFile('resources/js/pages/iam/users/akses.tsx');
    expect($userAkses)->toContain('user.nama_lengkap')
        ->and($userAkses)->not->toContain('user.name')
        ->and($userAkses)->not->toContain('assignedByUser?.name');
});

test('F-45, F-46 & F-47: verify-email berbahasa Indonesia tanpa nested Form, ConfirmDeleteDialog konsisten, dan 7+1 sub-halaman pegawai pakai ConfirmDeleteDialog + AlertError', function (): void {
    $verifyEmail = IamUiE2eHelper::bacaFile('resources/js/pages/auth/verify-email.tsx');
    expect($verifyEmail)->not->toContain('Please verify your email address')
        ->and($verifyEmail)->not->toContain('Resend verification email')
        ->and(substr_count($verifyEmail, '<Form'))->toBeGreaterThanOrEqual(1);

    // Pastikan Form logout tidak bersarang di dalam Form pengiriman ulang verifikasi email
    expect($verifyEmail)->not->toMatch('/<Form\b(?:(?!<\/Form>)[\s\S])*<Form\b[^>]*action="\/auth\/sso\/logout"/s');

    $confirmDelete = IamUiE2eHelper::bacaFile('resources/js/components/confirm-delete-dialog.tsx');
    expect($confirmDelete)->not->toMatch('/\R\s*\?\s+Tindakan ini tidak dapat dibatalkan\./');

    foreach (IamUiE2eHelper::daftarSubHalamanPegawai() as $path) {
        $source = IamUiE2eHelper::bacaFile($path);
        expect($source)->toContain('ConfirmDeleteDialog')
            ->and($source)->not->toContain('window.confirm')
            ->and($source)->toContain('AlertError')
            ->and($source)->toContain('errorsToArray');
    }
});

test('F-48: app-sidebar memuat tautan navigasi berbasis izin untuk Kenaikan Pangkat, Checklist Template, dan Activity Log', function (): void {
    $sidebar = IamUiE2eHelper::bacaFile('resources/js/components/app-sidebar.tsx');

    IamUiE2eHelper::assertSidebarMemilikiTautan($sidebar, [
        'usulan',
        'approval',
        'admin-sk',
        'eligible',
        'checklist-template',
        'activity-log',
    ]);
});

test('F-64, F-65 & F-73 (FE): locale id di .env.example & app.blade.php, aksesibilitas password-input, SettingsLayout SSR-safe, dan lazy dashboard 4-kolom', function (): void {
    $envExample = IamUiE2eHelper::bacaFile('.env.example');
    expect($envExample)->toContain('APP_LOCALE=id');

    $blade = IamUiE2eHelper::bacaFile('resources/views/app.blade.php');
    expect($blade)->toContain('app()->getLocale()');

    $passwordInput = IamUiE2eHelper::bacaFile('resources/js/components/password-input.tsx');
    expect($passwordInput)->not->toContain('tabIndex={-1}');

    $settingsLayout = IamUiE2eHelper::bacaFile('resources/js/layouts/settings/layout.tsx');
    expect($settingsLayout)->not->toMatch('/typeof window === [\'"]undefined[\'"]\)\s*\{\s*return null/s')
        ->and($settingsLayout)->not->toContain('Manage your profile and account settings');

    $dashboard = IamUiE2eHelper::bacaFile('resources/js/pages/dashboard.tsx');
    expect($dashboard)->toContain('lazy(')
        ->and($dashboard)->toContain('DashboardHeavySection');

    $skeleton = IamUiE2eHelper::bacaFile('resources/js/components/dashboard/DashboardDistribusiSkeleton.tsx');
    expect($skeleton)->toContain('lg:grid-cols-4');

    $chart = IamUiE2eHelper::bacaFile('resources/js/components/dashboard/GolonganBarChart.tsx');
    expect($chart)->not->toContain('bg-white')
        ->and($chart)->toMatch('/bg-(popover|card)/');
});
