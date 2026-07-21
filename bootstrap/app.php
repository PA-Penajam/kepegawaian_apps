<?php

use App\Exceptions\Handler;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyHmacSignature;
use App\Http\Middleware\VerifyIamPermission;
use App\Http\Middleware\VerifyIamSignature;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use App\Keycloak\Exceptions\KeycloakException;
use App\Keycloak\Http\Middleware\EmergencyBypass;
use App\Keycloak\Http\Middleware\KeycloakTokenRefresh;
use App\Keycloak\Http\Middleware\VerifyKeycloakPermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/keycloak.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'permission' => VerifyIamPermission::class,
            'verify.hmac' => VerifyHmacSignature::class,
            'iam.signature' => VerifyIamSignature::class,
            'iam.permission' => VerifyIamPermission::class,
            'keycloak.refresh' => KeycloakTokenRefresh::class,
            'keycloak.emergency' => EmergencyBypass::class,
            'keycloak.permission' => VerifyKeycloakPermission::class,
        ]);

        // Middleware group 'keycloak' untuk authenticated routes via Keycloak
        // Order: KeycloakTokenRefresh → EmergencyBypass → VerifyKeycloakPermission
        $middleware->appendToGroup('keycloak', [
            KeycloakTokenRefresh::class,
            EmergencyBypass::class,
            VerifyKeycloakPermission::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Redirect unauthenticated user ke SSO login bukan langsung ke /login
        // agar alur kepegawaian-apps identik dengan aplikasi lain dalam ekosistem SSO
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->to(
                route('sso.login', [
                    'app' => config('iam.app_slug', 'kepegawaian'),
                    'redirect' => $request->url(),
                ])
            );
        });

        // Req 5.3: Circuit breaker OPEN → reject dengan 503 + pesan yang informatif
        $exceptions->render(function (KeycloakCircuitOpenException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Layanan autentikasi sedang tidak tersedia. Silakan coba beberapa saat lagi.',
                    'error' => 'service_unavailable',
                ], 503);
            }

            // Redirect ke emergency login jika emergency enabled dan route ada
            if (config('keycloak.emergency.enabled')) {
                return redirect()->route('emergency.login.form')
                    ->with('warning', 'Keycloak tidak tersedia. Gunakan login darurat jika diperlukan.');
            }

            abort(503, 'Layanan autentikasi sedang tidak tersedia. Silakan coba beberapa saat lagi.');
        });

        // Keycloak exception umum: handle berdasarkan error code
        $exceptions->render(function (KeycloakException $e, Request $request) {
            $statusCode = match ($e->getCode()) {
                KeycloakException::INVALID_TOKEN => 401,
                KeycloakException::TOKEN_EXPIRED => 401,
                KeycloakException::USER_NOT_FOUND => 403,
                KeycloakException::CODE_EXCHANGE_FAILED => 502,
                KeycloakException::REFRESH_FAILED => 401,
                default => 500,
            };

            $userMessage = match ($e->getCode()) {
                KeycloakException::INVALID_TOKEN => 'Token tidak valid. Silakan login kembali.',
                KeycloakException::TOKEN_EXPIRED => 'Sesi telah berakhir. Silakan login kembali.',
                KeycloakException::USER_NOT_FOUND => 'NIP tidak terdaftar dalam sistem kepegawaian.',
                KeycloakException::CODE_EXCHANGE_FAILED => 'Autentikasi gagal. Silakan coba login kembali.',
                KeycloakException::REFRESH_FAILED => 'Sesi telah berakhir. Silakan login kembali.',
                default => 'Terjadi kesalahan pada layanan autentikasi. Silakan coba lagi.',
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $userMessage,
                    'error' => 'keycloak_error',
                ], $statusCode);
            }

            // Untuk error autentikasi → redirect ke login
            if (in_array($statusCode, [401, 403])) {
                return redirect()->route('keycloak.login')
                    ->with('error', $userMessage);
            }

            abort($statusCode, $userMessage);
        });

        // Daftarkan global exception handler untuk pesan user-friendly
        (new Handler)($exceptions);
    })->create();
