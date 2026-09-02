<?php

use App\Exceptions\Handler;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SsoTokenRefresh;
use App\Http\Middleware\ValidatePegawaiStatus;
use App\Http\Middleware\VerifyHmacSignature;
use App\Http\Middleware\VerifyIamPermission;
use App\Http\Middleware\VerifyIamSignature;
use App\Services\Sso\Exceptions\SsoException;
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
                ->group(base_path('routes/sso.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'permission' => VerifyIamPermission::class,
            'pegawai.status' => ValidatePegawaiStatus::class,
            'verify.hmac' => VerifyHmacSignature::class,
            'iam.signature' => VerifyIamSignature::class,
            'iam.permission' => VerifyIamPermission::class,
            'sso.refresh' => SsoTokenRefresh::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->web(append: [
            SsoTokenRefresh::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Redirect unauthenticated user ke SSO PA Penajam (auth.sso.login), bukan ke IAM internal (sso.login) atau /login lokal.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('auth.sso.login');
        });

        // Handler untuk Exception SSO PA Penajam
        $exceptions->render(function (SsoException $e, Request $request) {
            $statusCode = match ($e->getCode()) {
                SsoException::USER_INFO_FAILED => 401,
                SsoException::REFRESH_TOKEN_FAILED => 401,
                SsoException::CODE_EXCHANGE_FAILED => 502,
                SsoException::SSO_UNREACHABLE => 503,
                default => 500,
            };

            $userMessage = $e->getMessage() ?: 'Terjadi kesalahan pada layanan SSO PA Penajam. Silakan coba lagi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $userMessage,
                    'error' => 'sso_error',
                ], $statusCode);
            }

            return redirect()->route('login')->with('error', $userMessage);
        });

        // Daftarkan global exception handler untuk pesan user-friendly
        (new Handler)($exceptions);
    })->create();
