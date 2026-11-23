<?php

namespace Tests\E2E\IamUi\Support;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\SyncConsumer;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Helper terisolasi untuk suite E2E Domain IAM, SSO Gateway, SyncConsumer, dan Frontend UI.
 */
class IamUiE2eHelper
{
    /**
     * Membuat pegawai baru beserta role kustom dengan permission spesifik pada aplikasi IAM tertentu.
     *
     * @param  string[]  $permissionSlugs
     * @param  array<string, mixed>  $pegawaiAttributes
     */
    public static function buatPegawaiDenganPermission(
        array $permissionSlugs,
        array $pegawaiAttributes = [],
        string $appSlug = 'kepegawaian',
    ): Pegawai {
        $app = IamApplication::query()->where('slug', $appSlug)->first()
            ?? IamApplication::factory()->create([
                'slug' => $appSlug,
                'nama' => Str::title($appSlug),
                'is_active' => true,
            ]);

        $pegawai = Pegawai::factory()->create($pegawaiAttributes);

        $role = IamRole::query()->create([
            'iam_application_id' => $app->id,
            'nama' => 'E2E Role '.Str::random(6),
            'slug' => 'e2e-role-'.strtolower(Str::random(8)),
            'is_system' => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = IamPermission::query()->firstOrCreate(
                [
                    'iam_application_id' => $app->id,
                    'slug' => $slug,
                ],
                [
                    'nama' => Str::title(str_replace('.', ' ', $slug)),
                    'group' => explode('.', $slug)[0] ?: 'umum',
                ],
            );

            IamRolePermission::query()->firstOrCreate([
                'iam_role_id' => $role->id,
                'iam_permission_id' => $permission->id,
            ]);
        }

        IamUserRole::query()->create([
            'user_id' => $pegawai->id,
            'iam_role_id' => $role->id,
            'assigned_at' => now(),
        ]);

        return $pegawai->fresh();
    }

    /**
     * Membuat pegawai dengan role pada aplikasi IAM non-kepegawaian untuk menguji isolasi lintas aplikasi.
     *
     * @param  string[]  $permissionSlugs
     * @param  array<string, mixed>  $pegawaiAttributes
     * @return array{pegawai: Pegawai, app: IamApplication, role: IamRole, permissions: array<int, IamPermission>}
     */
    public static function buatPegawaiRoleAplikasiLain(
        string $appSlug,
        array $permissionSlugs,
        array $pegawaiAttributes = [],
    ): array {
        $app = IamApplication::query()->where('slug', $appSlug)->first()
            ?? IamApplication::factory()->create([
                'slug' => $appSlug,
                'nama' => Str::title($appSlug),
                'url' => "https://{$appSlug}.test",
                'is_active' => true,
            ]);

        $pegawai = Pegawai::factory()->create($pegawaiAttributes);

        $role = IamRole::query()->create([
            'iam_application_id' => $app->id,
            'nama' => 'Role Lintas '.Str::random(6),
            'slug' => 'role-lintas-'.strtolower(Str::random(8)),
            'is_system' => false,
        ]);

        $permissions = [];
        foreach ($permissionSlugs as $slug) {
            $permission = IamPermission::query()->firstOrCreate(
                [
                    'iam_application_id' => $app->id,
                    'slug' => $slug,
                ],
                [
                    'nama' => Str::title(str_replace('.', ' ', $slug)),
                    'group' => explode('.', $slug)[0] ?: 'umum',
                ],
            );

            IamRolePermission::query()->firstOrCreate([
                'iam_role_id' => $role->id,
                'iam_permission_id' => $permission->id,
            ]);

            $permissions[] = $permission;
        }

        IamUserRole::query()->create([
            'user_id' => $pegawai->id,
            'iam_role_id' => $role->id,
            'assigned_at' => now(),
        ]);

        return [
            'pegawai' => $pegawai->fresh(),
            'app' => $app,
            'role' => $role,
            'permissions' => $permissions,
        ];
    }

    /**
     * Menyusun header autentikasi HMAC-SHA256 (payload kanonik METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP)
     * dan header anti-replay X-Nonce unik per request untuk endpoint sinkronisasi.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function buatHeaderHmacSync(
        string $method,
        string $path,
        string $secret,
        array $query = [],
        string $rawBody = '',
        ?int $timestamp = null,
        ?string $nonce = null,
        bool $withNonce = true,
    ): array {
        $ts = $timestamp ?? now()->timestamp;
        $signedQuery = collect($query)->sortKeys()->all();
        $queryString = http_build_query($signedQuery);
        $payload = strtoupper($method)
            .':'.$path
            .':'.$queryString
            .':'.hash('sha256', $rawBody)
            .':'.$ts;

        $headers = [
            'X-Timestamp' => (string) $ts,
            'X-Signature' => hash_hmac('sha256', $payload, $secret),
            'Accept' => 'application/json',
        ];

        if ($withNonce) {
            $headers['X-Nonce'] = $nonce ?? (string) Str::uuid();
        }

        return $headers;
    }

    /**
     * Mengirim request GET /api/v1/pegawai/sync menggunakan bearer token, HMAC secret, dan X-Nonce.
     *
     * @param  array<string, mixed>  $query
     */
    public static function kirimRequestSync(
        TestCase $testCase,
        string $tokenPlain,
        string $secret,
        array $query = ['page' => 1, 'per_page' => 5],
        ?int $timestamp = null,
        ?string $nonce = null,
    ): TestResponse {
        $path = '/api/v1/pegawai/sync';
        $effectiveNonce = $nonce ?? (string) Str::uuid();
        $headers = self::buatHeaderHmacSync(
            method: 'GET',
            path: $path,
            secret: $secret,
            query: $query,
            rawBody: '',
            timestamp: $timestamp,
            nonce: $effectiveNonce,
            withNonce: true,
        );

        // Reset guard agar auth:sanctum membaca Bearer token aktual dari header request.
        auth()->forgetGuards();

        $queryString = http_build_query($query);
        $uri = $queryString !== '' ? "{$path}?{$queryString}" : $path;

        return $testCase->withToken($tokenPlain)
            ->withHeaders($headers)
            ->get($uri);
    }

    /**
     * Menghasilkan header standar request Inertia XHR beserta versi aset aktif.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function headerInertia(array $extra = []): array
    {
        $version = (string) ((new HandleInertiaRequests)->version(request()) ?? '');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            ...$extra,
        ];
    }

    /**
     * Memalsukan respons HTTP IdP SSO PA Penajam untuk pertukaran kode token, profil user, dan logout.
     */
    public static function fakeSsoIdp(string $nip, string $nama, ?string $email = null): void
    {
        $baseUrl = rtrim((string) config('sso.base_url'), '/');

        Http::fake([
            "{$baseUrl}/oauth/token" => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'e2e-access-token',
                'refresh_token' => 'e2e-refresh-token',
            ], 200),
            "{$baseUrl}/api/user" => Http::response([
                'data' => [
                    'sub' => $nip,
                    'nip' => $nip,
                    'name' => $nama,
                    'email' => $email ?? "{$nip}@example.test",
                ],
            ], 200),
            "{$baseUrl}/api/logout" => Http::response([
                'message' => 'Berhasil keluar dari SSO.',
            ], 200),
        ]);
    }

    /**
     * Menyusun payload valid untuk pembaruan data pegawai melalui endpoint controller web.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payloadUpdatePegawai(Pegawai $pegawai, array $overrides = []): array
    {
        return [
            'nip' => $pegawai->nip,
            'nama_lengkap' => $pegawai->nama_lengkap,
            'tempat_lahir' => $pegawai->tempat_lahir ?? 'Penajam',
            'tanggal_lahir' => $pegawai->tanggal_lahir?->format('Y-m-d') ?? '1990-01-01',
            'jenis_kelamin' => $pegawai->jenis_kelamin instanceof JenisKelamin
                ? $pegawai->jenis_kelamin->value
                : JenisKelamin::LakiLaki->value,
            'agama' => $pegawai->agama instanceof Agama
                ? $pegawai->agama->value
                : Agama::Islam->value,
            'status_perkawinan' => $pegawai->status_perkawinan instanceof StatusPerkawinan
                ? $pegawai->status_perkawinan->value
                : StatusPerkawinan::BelumMenikah->value,
            'status_kepegawaian' => $pegawai->status_kepegawaian instanceof StatusKepegawaian
                ? $pegawai->status_kepegawaian->value
                : StatusKepegawaian::PNS->value,
            'status_pegawai' => $pegawai->status_pegawai instanceof StatusPegawai
                ? $pegawai->status_pegawai->value
                : StatusPegawai::Aktif->value,
            'tanggal_masuk' => $pegawai->tanggal_masuk?->format('Y-m-d') ?? '2020-01-01',
            'email' => $pegawai->email,
            ...$overrides,
        ];
    }

    /**
     * Membaca isi file workspace secara aman untuk verifikasi kontrak UI/desain publik.
     */
    public static function bacaFile(string $relativePath): string
    {
        $fullPath = base_path($relativePath);

        if (! is_file($fullPath)) {
            throw new RuntimeException("File kontrak tidak ditemukan: {$relativePath}");
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new RuntimeException("Gagal membaca file kontrak: {$relativePath}");
        }

        return $content;
    }

    /**
     * Daftar 7+1 sub-halaman pengelolaan data pegawai.
     *
     * @return string[]
     */
    public static function daftarSubHalamanPegawai(): array
    {
        return [
            'resources/js/pages/kepegawaian/pegawai/keluarga.tsx',
            'resources/js/pages/kepegawaian/pegawai/riwayat-pangkat.tsx',
            'resources/js/pages/kepegawaian/pegawai/riwayat-jabatan.tsx',
            'resources/js/pages/kepegawaian/pegawai/riwayat-pendidikan.tsx',
            'resources/js/pages/kepegawaian/pegawai/riwayat-diklat.tsx',
            'resources/js/pages/kepegawaian/pegawai/penghargaan.tsx',
            'resources/js/pages/kepegawaian/pegawai/hukuman-disiplin.tsx',
            'resources/js/pages/kepegawaian/pegawai/dokumen-pegawai.tsx',
        ];
    }

    /**
     * Mengekstrak blok fungsi handler TypeScript dari kode sumber TSX berdasarkan nama fungsi.
     */
    public static function ekstrakBlokFungsi(string $source, string $namaFungsi): string
    {
        if (! preg_match('/\b(?:const|function)\s+'.preg_quote($namaFungsi, '/').'\b/', $source, $match, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startOffset = (int) $match[0][1];
        $tail = substr($source, $startOffset);

        if (preg_match('/\n\s{4}(?:const|function|return\s*\()/', $tail, $nextMatch, PREG_OFFSET_CAPTURE, 10)) {
            return substr($tail, 0, (int) $nextMatch[0][1]);
        }

        return $tail;
    }

    /**
     * Mendeteksi opsi kontrak testConnection yang dipakai pada handleTestConnection di iam/sinkronisasi/index.tsx.
     *
     * @return 'json'|'inertia'
     */
    public static function deteksiKontrakTestConnectionFrontend(): string
    {
        $source = self::bacaFile('resources/js/pages/iam/sinkronisasi/index.tsx');
        $handlerBlock = self::ekstrakBlokFungsi($source, 'handleTestConnection');

        expect($handlerBlock)->not->toBeEmpty();

        if (preg_match('/\bfetch\s*\(/', $handlerBlock) === 1) {
            expect($handlerBlock)->not->toContain('router.post')
                ->and($handlerBlock)->toMatch('/X-CSRF-TOKEN/i')
                ->and($handlerBlock)->toMatch('/Accept[\'"]?\s*:\s*[\'"]application\/json[\'"]/i');

            return 'json';
        }

        expect($handlerBlock)->toContain('router.post')
            ->and($handlerBlock)->toMatch('/\bonFinish\b|setTestingSlug\(\s*null\s*\)/');

        return 'inertia';
    }

    /**
     * Memverifikasi keselarasan kontrak antara SyncConsumerController::testConnection()
     * dan handleTestConnection pada iam/sinkronisasi/index.tsx untuk kedua opsi sah PROJECT.md:177.
     */
    public static function assertTestConnectionContract(
        TestResponse $response,
        bool $expectedSuccess,
        ?string $mode = null,
    ): void {
        $resolvedMode = $mode ?? self::deteksiKontrakTestConnectionFrontend();

        if ($resolvedMode === 'json') {
            if ($expectedSuccess) {
                $response->assertSuccessful();
            } else {
                expect($response->getStatusCode() === 200 || $response->getStatusCode() >= 400)->toBeTrue();
            }

            $message = (string) $response->json('message');

            expect($response->json('success'))->toBe($expectedSuccess)
                ->and($message)->not->toBeEmpty()
                ->and($message)->not->toContain('SQLSTATE');

            return;
        }

        $response->assertRedirect();

        $flash = session('test_connection');
        $message = is_array($flash) ? (string) ($flash['message'] ?? '') : '';

        expect($flash)->toBeArray()
            ->and($flash['success'] ?? null)->toBe($expectedSuccess)
            ->and($message)->not->toBeEmpty()
            ->and($message)->not->toContain('SQLSTATE');
    }

    /**
     * Mengirim permintaan test-connection sesuai kontrak frontend aktual dan memverifikasi responsnya.
     */
    public static function jalankanDanVerifikasiTestConnection(
        TestCase $testCase,
        Pegawai $admin,
        SyncConsumer $consumer,
        bool $expectedSuccess,
    ): void {
        $mode = self::deteksiKontrakTestConnectionFrontend();
        $endpoint = route('iam.sinkronisasi.test-connection', $consumer);

        $response = $mode === 'json'
            ? $testCase->actingAs($admin)->postJson($endpoint)
            : $testCase->actingAs($admin)->from(route('iam.sinkronisasi.index'))->post($endpoint);

        self::assertTestConnectionContract($response, $expectedSuccess, $mode);
    }

    /**
     * Memverifikasi bahwa SyncConnectionTester mengirimkan header X-Nonce, X-Signature,
     * X-Timestamp, dan Authorization Bearer pada request HTTP sinkronisasi.
     */
    public static function assertPermintaanSyncConnectionTesterTerkirim(): void
    {
        Http::assertSent(function (HttpClientRequest $request): bool {
            $auth = (string) ($request->header('Authorization')[0] ?? '');
            $nonce = trim((string) ($request->header('X-Nonce')[0] ?? ''));
            $signature = trim((string) ($request->header('X-Signature')[0] ?? ''));
            $timestamp = trim((string) ($request->header('X-Timestamp')[0] ?? ''));

            return str_contains($request->url(), '/api/v1/pegawai/sync')
                && str_starts_with($auth, 'Bearer ')
                && $nonce !== ''
                && $signature !== ''
                && $timestamp !== '';
        });
    }

    /**
     * Memverifikasi app-sidebar.tsx memuat tautan navigasi baik lewat path literal maupun helper Wayfinder.
     *
     * @param  string[]  $slugs
     */
    public static function assertSidebarMemilikiTautan(string $sidebarSource, array $slugs): void
    {
        $polaPerSlug = [
            'usulan' => '/\/kenaikan-pangkat\/usulan|@\/routes\/kenaikan-pangkat\/usulan|@\/routes\/kenaikan-pangkat[\s\S]*\busulan\b|UsulanKenaikanPangkatController/',
            'approval' => '/\/kenaikan-pangkat\/approval|@\/routes\/kenaikan-pangkat\/approval|@\/routes\/kenaikan-pangkat[\s\S]*\bapproval\b|ApprovalController/',
            'admin-sk' => '/\/kenaikan-pangkat\/admin-sk|@\/routes\/kenaikan-pangkat\/admin-sk|@\/routes\/kenaikan-pangkat[\s\S]*\b(?:adminSk|admin_sk)\b|SkAdminController/',
            'eligible' => '/\/kenaikan-pangkat\/eligible|@\/routes\/kenaikan-pangkat\/eligible|@\/routes\/kenaikan-pangkat[\s\S]*\beligible\b/',
            'checklist-template' => '/\/admin\/checklist-template|@\/routes\/admin\/checklist-template|@\/routes\/admin[\s\S]*\bchecklistTemplate\b|ChecklistTemplateController/',
            'activity-log' => '/\/activity-log|@\/routes\/activity-log|ActivityLogController/',
        ];

        foreach ($slugs as $slug) {
            expect($sidebarSource)->toMatch($polaPerSlug[$slug]);
        }
    }
}
