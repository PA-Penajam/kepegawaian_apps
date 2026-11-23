<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Pegawai;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Vite as FoundationVite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\Rules\Password;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeaders;

it('menyertakan security headers dasar pada response web', function () {
    get(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('menyertakan security headers pada halaman terautentikasi', function () {
    actingAs(Pegawai::factory()->create());

    get('/dashboard')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('tidak menyertakan HSTS dan CSP di luar production', function () {
    $response = get(route('login'));

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('menyertakan HSTS dan CSP ketat berbasis nonce saat environment production', function () {
    $originalEnv = app()['env'];

    try {
        app()['env'] = 'production';
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();

        $response = get(route('login'));
        $response->assertSuccessful()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        expect($csp)->not->toBeEmpty()
            ->and($csp)->toMatch("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/")
            ->and($csp)->not->toContain("script-src 'self' 'unsafe-inline'")
            ->and($csp)->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net")
            ->and($csp)->toContain("img-src 'self' data: blob: https:")
            ->and($csp)->toContain("font-src 'self' data: https://fonts.bunny.net");

        preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]{40})'/", $csp, $matches);
        $nonce = $matches[1] ?? null;

        expect($nonce)->not->toBeNull();
        expect($response->getContent())->toContain('nonce="'.$nonce.'"');
    } finally {
        app()['env'] = $originalEnv;
        app()->forgetInstance(FoundationVite::class);
        Vite::clearResolvedInstances();
    }
});

it('mengembalikan 409 X-Inertia-Location saat request Inertia belum terautentikasi atau memanggil sso login', function () {
    $inertiaVersion = (string) ((new HandleInertiaRequests)->version(request()) ?? '');
    $inertiaHeaders = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $inertiaVersion,
    ];

    $unauthenticatedResponse = withHeaders($inertiaHeaders)->get('/dashboard');

    expect($unauthenticatedResponse->getStatusCode())->toBe(409)
        ->and($unauthenticatedResponse->headers->get('X-Inertia-Location'))->toBe(route('auth.sso.login'));

    $ssoLoginResponse = withHeaders($inertiaHeaders)->get(route('auth.sso.login'));

    expect($ssoLoginResponse->getStatusCode())->toBe(409)
        ->and($ssoLoginResponse->headers->get('X-Inertia-Location'))->toContain(config('sso.base_url').'/oauth/authorize');
});

it('mengaktifkan session secure cookie otomatis saat production atau skema https jika belum disetel eksplisit', function () {
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
