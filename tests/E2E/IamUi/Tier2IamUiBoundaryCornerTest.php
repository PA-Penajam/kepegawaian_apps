<?php

use App\Enums\StatusPegawai;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\SyncConsumer;
use App\Services\Cuti\ApproverResolverService;
use App\Services\Iam\IamSsoStateStore;
use App\Services\Iam\SyncConsumerCredentialService;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\E2E\IamUi\Support\IamUiE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/IamUiE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
        $this->seed(IamSeeder::class);
    })
    ->group('tier2');

test('F-10: /sso/login menolak redirect dengan skema non-HTTP seperti javascript dan ftp', function (string $badRedirect): void {
    IamApplication::factory()->create([
        'slug' => 'attendance-scheme',
        'url' => 'https://att.local',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();

    $response = $this->actingAs($pegawai)->get('/sso/login?'.http_build_query([
        'app' => 'attendance-scheme',
        'redirect' => $badRedirect,
    ]));

    expect($response->getStatusCode())->toBe(422);
})->with([
    'skema javascript' => 'javascript:alert(1)',
    'skema ftp' => 'ftp://att.local/callback',
    'skema data' => 'data:text/html,<script>alert(1)</script>',
]);

test('F-10: /sso/login menolak host eksternal, subdomain spoofing, dan port berbeda dari app url', function (string $badRedirect): void {
    IamApplication::factory()->create([
        'slug' => 'attendance-strict',
        'url' => 'https://att.local:8443',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();

    $response = $this->actingAs($pegawai)->get('/sso/login?'.http_build_query([
        'app' => 'attendance-strict',
        'redirect' => $badRedirect,
    ]));

    expect($response->getStatusCode())->toBe(422);
})->with([
    'host eksternal' => 'https://evil.test:8443/steal',
    'subdomain spoofing' => 'https://att.local.evil.test:8443/steal',
    'userinfo confusion' => 'https://att.local@evil.test:8443/steal',
    'port berbeda' => 'https://att.local:9999/callback',
    'skema downgrade http' => 'http://att.local:8443/callback',
]);

test('F-10: /sso/login menolak tamu tanpa autentikasi dengan redirect skema, host, atau port tidak sah sebelum menyimpan state ke IamSsoStateStore', function (string $badRedirect): void {
    IamApplication::factory()->create([
        'slug' => 'attendance-strict',
        'url' => 'https://att.local:8443',
        'is_active' => true,
    ]);

    $response = $this->get('/sso/login?'.http_build_query([
        'app' => 'attendance-strict',
        'redirect' => $badRedirect,
        'state' => 'state-tamu-jahat',
    ]));

    expect($response->getStatusCode())->toBe(422)
        ->and(session('sso_app'))->toBeNull()
        ->and(session('sso_redirect'))->toBeNull()
        ->and(session('sso_state_key'))->toBeNull()
        ->and(app(IamSsoStateStore::class)->hasPending())->toBeFalse();
})->with([
    'skema ftp tamu' => 'ftp://att.local:8443/callback',
    'host eksternal tamu' => 'https://evil.test:8443/steal',
    'subdomain spoofing tamu' => 'https://att.local.evil.test:8443/steal',
    'port berbeda tamu' => 'https://att.local:9999/callback',
    'skema downgrade http tamu' => 'http://att.local:8443/callback',
]);

test('F-10: /sso/callback menolak Open Redirect ke host atau port asing termasuk pada selfSlug kepegawaian', function (string $badRedirect): void {
    $pegawai = Pegawai::factory()->create();

    session([
        'sso_app' => config('iam.app_slug', 'kepegawaian'),
        'sso_redirect' => $badRedirect,
        'sso_state' => 'tampered-state',
    ]);

    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    expect($response->getStatusCode())->toBeIn([403, 422])
        ->and($response->headers->get('Location'))->toBeNull();
})->with([
    'host eksternal di selfSlug' => 'https://evil.test/phish',
    'skema javascript di selfSlug' => 'javascript:alert(document.cookie)',
    'port berbeda di selfSlug' => 'http://localhost:9999/dashboard',
]);

test('F-20: role pada aplikasi IAM non-kepegawaian dengan slug sama tidak memberi izin di kepegawaian', function (): void {
    $lintas = IamUiE2eHelper::buatPegawaiRoleAplikasiLain(
        appSlug: 'persediaan',
        permissionSlugs: ['iam.manage', 'pegawai.view', 'cuti.pengajuan.verify'],
    );
    $pegawai = $lintas['pegawai'];

    expect($pegawai->hasPermission('iam.manage'))->toBeFalse()
        ->and($pegawai->hasPermission('pegawai.view'))->toBeFalse();

    $this->actingAs($pegawai)
        ->get(route('iam.users.index'))
        ->assertForbidden();

    $this->actingAs($pegawai)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.permissions', fn ($perms): bool => ! collect($perms)->contains('iam.manage'))
        );

    $pemohon = Pegawai::factory()->create();
    $snapshot = app(ApproverResolverService::class)->resolveSnapshot($pemohon->nip);
    expect($snapshot['petugas_kepegawaian'])->not->toBe($pegawai->nip);
});

test('F-21: RefRoleController menolak edit, update, dan delete terhadap role aplikasi lain dengan 404', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $otherApp = IamApplication::factory()->create(['slug' => 'absensi-lain', 'is_active' => true]);
    $foreignRole = IamRole::factory()->create([
        'iam_application_id' => $otherApp->id,
        'nama' => 'Role Asing',
        'slug' => 'role-asing',
    ]);

    $this->actingAs($admin)
        ->get(route('referensi.roles.edit', $foreignRole))
        ->assertNotFound();

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $foreignRole), [
            'nama' => 'Role Dibajak',
        ])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('referensi.roles.destroy', $foreignRole))
        ->assertNotFound();

    expect($foreignRole->fresh()->nama)->toBe('Role Asing');
});

test('F-21: RefRoleController menolak pengubahan atau penghapusan role sistem (is_system)', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();
    $systemRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'admin')
        ->firstOrFail();

    expect($systemRole->is_system)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $systemRole), [
            'nama' => 'Admin Diubah',
        ]);

    expect($systemRole->fresh()->nama)->not->toBe('Admin Diubah')
        ->and($systemRole->fresh()->slug)->toBe('admin');

    $this->actingAs($admin)
        ->delete(route('referensi.roles.destroy', $systemRole))
        ->assertRedirect(route('referensi.roles.index'));

    expect(IamRole::query()->whereKey($systemRole->id)->exists())->toBeTrue();
});

test('F-21: RefRoleController menolak permission lintas aplikasi pada store dan update dengan 422', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();
    $otherApp = IamApplication::factory()->create(['slug' => 'aplikasi-asing', 'is_active' => true]);

    $foreignPerm = IamPermission::factory()->create([
        'iam_application_id' => $otherApp->id,
        'slug' => 'asing.view',
    ]);

    $this->actingAs($admin)
        ->post(route('referensi.roles.store'), [
            'nama' => 'Role Terkontaminasi',
            'permissions' => [$foreignPerm->id],
        ])
        ->assertSessionHasErrors('permissions.0');

    $localRole = IamRole::factory()->create([
        'iam_application_id' => $kepegawaianApp->id,
        'nama' => 'Role Lokal Aman',
        'slug' => 'role-lokal-aman',
    ]);

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $localRole), [
            'nama' => 'Role Lokal Aman',
            'permissions' => [$foreignPerm->id],
        ])
        ->assertSessionHasErrors('permissions.0');
});

test('F-22: UserAksesController tidak memanggil Cache::flush dan menjaga kunci cache aplikasi lain', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();
    $validatorRole = IamRole::query()
        ->where('iam_application_id', $kepegawaianApp->id)
        ->where('slug', 'validator')
        ->firstOrFail();

    Cache::put('cache_modul_cuti_penting', 'data_cuti_aman', 3600);
    Cache::put('cache_sso_session_lain', 'token_sso_aman', 3600);

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $target), [
            'iam_role_id' => $validatorRole->id,
        ])
        ->assertRedirect();

    expect(Cache::get('cache_modul_cuti_penting'))->toBe('data_cuti_aman')
        ->and(Cache::get('cache_sso_session_lain'))->toBe('token_sso_aman');

    $this->actingAs($admin)
        ->delete(route('iam.users.akses.destroy', [$target, $validatorRole]))
        ->assertRedirect();

    expect(Cache::get('cache_modul_cuti_penting'))->toBe('data_cuti_aman')
        ->and(Cache::get('cache_sso_session_lain'))->toBe('token_sso_aman');
});

test('F-22: StoreUserAksesRequest menolak role yang sudah di-soft-delete dengan 422', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $kepegawaianApp = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

    $deletedRole = IamRole::factory()->create([
        'iam_application_id' => $kepegawaianApp->id,
        'nama' => 'Role Dihapus',
        'slug' => 'role-dihapus',
    ]);
    $deletedRole->delete();

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $target), [
            'iam_role_id' => $deletedRole->id,
        ])
        ->assertSessionHasErrors('iam_role_id');
});

test('F-25: SyncConsumer dengan token app:kepegawaian tanpa ability sync:pegawai ditolak 403 di /api/v1/pegawai/sync', function (): void {
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'wrong-ability-client',
        'is_active' => true,
    ]);
    $secret = app(SyncConsumerCredentialService::class)->ensureHmacSecret($consumer);
    $legacyToken = $consumer->createToken('legacy-wrong-scope', ['app:kepegawaian'], now()->addHour());

    $response = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $legacyToken->plainTextToken,
        secret: $secret,
    );

    $response->assertForbidden();
});

test('F-25: SyncConsumer berstatus is_active false ditolak mengakses /api/v1/pegawai/sync', function (): void {
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'inactive-client',
        'is_active' => true,
    ]);
    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $issued = $service->issueToken($consumer);

    $consumer->update(['is_active' => false]);

    $response = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $issued['plaintext'],
        secret: $secret,
    );

    expect($response->getStatusCode())->toBeIn([401, 403]);
});

test('F-25: SyncConsumer tanpa hmac_secret tidak fallback ke global KEPEGAWAIAN_HMAC_SECRET', function (): void {
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'no-secret-client',
        'is_active' => true,
    ]);
    $consumer->forceFill(['hmac_secret' => null])->save();
    $issued = app(SyncConsumerCredentialService::class)->issueToken($consumer);

    $globalSecret = (string) config('kepegawaian.secret_key');
    expect($globalSecret)->not->toBeEmpty();

    $response = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $issued['plaintext'],
        secret: $globalSecret,
    );

    expect($response->getStatusCode())->toBeIn([401, 403, 500])
        ->and($response->getStatusCode())->not->toBe(200);
});

test('F-25: VerifyHmacSignature menolak request tanpa X-Nonce dan menolak replay attack dengan X-Nonce yang sama', function (): void {
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'nonce-guard-client',
        'is_active' => true,
    ]);
    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $issued = $service->issueToken($consumer);

    // Request tanpa X-Nonce harus ditolak 401
    $headersNoNonce = IamUiE2eHelper::buatHeaderHmacSync(
        method: 'GET',
        path: '/api/v1/pegawai/sync',
        secret: $secret,
        query: ['page' => 1, 'per_page' => 5],
        nonce: null,
        withNonce: false,
    );
    auth()->forgetGuards();
    $this->withToken($issued['plaintext'])
        ->withHeaders($headersNoNonce)
        ->get('/api/v1/pegawai/sync?page=1&per_page=5')
        ->assertUnauthorized();

    // Request pertama dengan X-Nonce unik berhasil, replay dengan X-Nonce yang sama ditolak 401
    $reusedNonce = (string) Str::uuid();
    IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $issued['plaintext'],
        secret: $secret,
        nonce: $reusedNonce,
    )->assertSuccessful();

    IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $issued['plaintext'],
        secret: $secret,
        nonce: $reusedNonce,
    )->assertUnauthorized();
});

test('F-25: VerifyHmacSignature menolak request dengan X-Timestamp kedaluwarsa lebih dari 5 menit', function (int $offsetSeconds): void {
    $consumer = SyncConsumer::factory()->create([
        'slug' => 'stale-ts-client',
        'is_active' => true,
    ]);
    $service = app(SyncConsumerCredentialService::class);
    $secret = $service->ensureHmacSecret($consumer);
    $issued = $service->issueToken($consumer);

    $response = IamUiE2eHelper::kirimRequestSync(
        testCase: $this,
        tokenPlain: $issued['plaintext'],
        secret: $secret,
        timestamp: now()->timestamp + $offsetSeconds,
    );

    $response->assertUnauthorized();
})->with([
    'lampau 6 menit' => -360,
    'masa depan 6 menit' => 360,
]);

test('F-72: StoreSyncConsumerRequest dan UpdateSyncConsumerRequest menolak base_url berskema non-HTTP seperti javascript, file, data, ftp, dan gopher', function (string $unsafeUrl): void {
    $admin = Pegawai::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('iam.sinkronisasi.store'), [
            'nama' => 'Konsumen Berbahaya',
            'slug' => 'konsumen-berbahaya-'.strtolower(Str::random(4)),
            'base_url' => $unsafeUrl,
        ])
        ->assertSessionHasErrors('base_url');

    $consumer = SyncConsumer::factory()->create();

    $this->actingAs($admin)
        ->put(route('iam.sinkronisasi.update', $consumer), [
            'nama' => $consumer->nama,
            'slug' => $consumer->slug,
            'base_url' => $unsafeUrl,
        ])
        ->assertSessionHasErrors('base_url');
})->with([
    'skema javascript' => 'javascript:alert(1)',
    'skema file' => 'file:///etc/passwd',
    'skema data' => 'data:text/html,<script>alert(1)</script>',
    'skema ftp' => 'ftp://files.example.com',
    'skema gopher' => 'gopher://files.example.com',
]);

test('F-27 (SP): Pegawai yang berubah menjadi nonaktif otomatis ter-logout pada request web berikutnya', function (): void {
    $pegawai = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);

    $this->actingAs($pegawai)->get(route('dashboard'))->assertSuccessful();

    $pegawai->update(['status_pegawai' => StatusPegawai::Pensiun]);

    $webResponse = $this->actingAs($pegawai->fresh())->get(route('dashboard'));
    expect($webResponse->getStatusCode())->toBeIn([302, 401, 409])
        ->and(Auth::check())->toBeFalse();

    $jsonResponse = $this->actingAs($pegawai->fresh())->getJson(route('dashboard'));
    $jsonResponse->assertUnauthorized();
});

test('F-27 (SP): Pegawai yang di-soft-delete otomatis ter-logout pada request web berikutnya', function (): void {
    $pegawai = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif]);

    $this->actingAs($pegawai)->get(route('dashboard'))->assertSuccessful();

    $pegawai->delete();

    $webResponse = $this->actingAs($pegawai->fresh())->get(route('dashboard'));
    expect($webResponse->getStatusCode())->toBeIn([302, 401, 409])
        ->and(Auth::check())->toBeFalse();

    $jsonResponse = $this->actingAs($pegawai->fresh())->getJson(route('dashboard'));
    $jsonResponse->assertUnauthorized();
});

test('F-43: mode SSO-only menonaktifkan DELETE /settings/profile dan UpdatePegawaiRequest mengabaikan mutasi password lokal', function (): void {
    $pegawai = Pegawai::factory()->withPassword('SandiLama!123')->create();
    $hashAwal = $pegawai->password;

    $deleteResponse = $this->actingAs($pegawai)->delete(route('profile.destroy'), [
        'password' => 'SandiLama!123',
    ]);

    expect($deleteResponse->getStatusCode())->toBeIn([403, 404, 405])
        ->and($pegawai->fresh()->deleted_at)->toBeNull();

    $admin = Pegawai::factory()->admin()->create();
    $this->actingAs($admin)
        ->put(route('kepegawaian.pegawai.update', $pegawai), IamUiE2eHelper::payloadUpdatePegawai($pegawai, [
            'password' => 'SandiBaruTidakBolehBerubah!99',
            'password_confirmation' => 'SandiBaruTidakBolehBerubah!99',
        ]))
        ->assertRedirect();

    expect(Hash::check('SandiLama!123', $pegawai->fresh()->password))->toBeTrue()
        ->and($pegawai->fresh()->password)->toBe($hashAwal);
});

test('F-46 & F-47: alert-dialog tidak menimpa variant destructive dengan bg-primary dan inertia-error-listener bebas window.alert/confirm', function (): void {
    $alertDialogSource = IamUiE2eHelper::bacaFile('resources/js/components/ui/alert-dialog.tsx');
    expect($alertDialogSource)->not->toMatch('/function\s+AlertDialogAction\b[\s\S]*?bg-primary\s+text-primary-foreground/s');

    $listenerSource = IamUiE2eHelper::bacaFile('resources/js/lib/inertia-error-listener.ts');
    expect($listenerSource)->not->toContain('window.confirm(')
        ->and($listenerSource)->not->toContain('window.alert(')
        ->and($listenerSource)->not->toMatch('/\balert\(/');
});
