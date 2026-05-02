<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Handler global untuk menangani exception aplikasi.
 *
 * Menyediakan pesan error yang user-friendly untuk web (Inertia) dan API (JSON)
 * pada exception non-HTTP (business logic errors, database errors, dll).
 *
 * HTTP exception (403, 404, 429, dll) tetap ditangani oleh Laravel secara default
 * agar status code HTTP tetap benar. Hanya exception yang TIDAK memiliki
 * HTTP status code yang dikonversi ke flash error / JSON error.
 */
class Handler
{
    /**
     * Pesan error default untuk exception umum.
     */
    private const DEFAULT_ERROR_MESSAGE = 'Terjadi kesalahan pada sistem. Silakan coba lagi atau hubungi administrator.';

    /**
     * Daftarkan semua exception handler ke konfigurasi Laravel.
     */
    public function __invoke(Exceptions $exceptions): void
    {
        // Tangani semua exception yang TIDAK punya HTTP status code.
        // Ini mencakup: RuntimeException, PDOException, LogicException, dll.
        // HttpException (403, 404, 429, dll) dan exception yang sudah ditangani
        // handler lain akan di-skip.
        $exceptions->render(function (Throwable $e, Request $request) {
            // Skip exception yang sudah ditangani handler lain
            if ($this->shouldSkip($e)) {
                return null;
            }

            $userMessage = $e->getMessage() ?: self::DEFAULT_ERROR_MESSAGE;

            // API request → JSON response
            if ($this->isApiRequest($request)) {
                return response()->json([
                    'message' => $userMessage,
                ], 500);
            }

            // Web (Inertia) request → redirect back dengan flash error
            return back()->with('error', $userMessage);
        });
    }

    /**
     * Tentukan apakah exception ini harus di-skip (tidak ditangani oleh handler global).
     *
     * Exception yang di-skip:
     * - HttpException dan subclass-nya (403, 404, 405, 429, dll)
     * - AuthenticationException (sudah ditangani SSO handler)
     * - ValidationException (ditangani Laravel)
     */
    private function shouldSkip(Throwable $e): bool
    {
        // HTTP exception — biarkan Laravel menangani dengan status code yang benar
        if ($e instanceof HttpException) {
            return true;
        }

        // Authentication — sudah ditangani oleh SSO handler di bootstrap/app.php
        if ($e instanceof AuthenticationException) {
            return true;
        }

        // Validation — ditangani oleh Laravel secara native
        if ($e instanceof ValidationException) {
            return true;
        }

        return false;
    }

    /**
     * Tentukan apakah request ini memerlukan JSON response.
     *
     * Request dianggap API jika:
     * - Mengirim header Accept: application/json, ATAU
     * - Prefix route-nya adalah "api"
     */
    private function isApiRequest(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*');
    }
}
