<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
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
        // Tangani HttpException khusus (403, 404, 419, 429, 500, 503) untuk Inertia & API
        $exceptions->render(function (HttpException $e, Request $request) {
            $statusCode = $e->getStatusCode();

            if (in_array($statusCode, [403, 404, 419, 429, 500, 503])) {
                $userMessage = match ($statusCode) {
                    403 => 'Akses dibatasi. Anda tidak memiliki izin untuk halaman ini.',
                    404 => 'Halaman yang Anda cari tidak ditemukan.',
                    419 => 'Sesi kedaluwarsa. Silakan muat ulang halaman.',
                    429 => 'Terlalu banyak permintaan. Silakan tunggu beberapa saat.',
                    500 => 'Kendala server internal.',
                    503 => 'Layanan sedang dalam pemeliharaan.',
                    default => 'Terjadi kesalahan pada sistem.',
                };

                if ($this->isApiRequest($request)) {
                    return response()->json([
                        'message' => $e->getMessage() ?: $userMessage,
                    ], $statusCode);
                }

                if ($request->hasHeader('X-Inertia')) {
                    return Inertia::render('errors/error', [
                        'status' => $statusCode,
                        'message' => $e->getMessage() ?: null,
                    ])->toResponse($request)->setStatusCode($statusCode);
                }
            }

            return null;
        });

        // Tangani semua exception yang TIDAK punya HTTP status code.
        // Ini mencakup: RuntimeException, PDOException, LogicException, dll.
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

            // Web request → hindari redirect loop ke dirinya sendiri
            // Jika Vite manifest hilang (public/build belum di-build) jangan
            // gunakan back() karena fallback-nya adalah URL saat ini → loop 302
            if ($e instanceof ViteManifestNotFoundException) {
                return response(
                    '<h1>Build frontend belum tersedia</h1><p>Jalankan <code>npm install && npm run build</code> atau <code>npm run dev</code> untuk generate Vite manifest.</p>',
                    500
                );
            }

            // Cegah loop back() ke URL yang sama (terjadi pada request pertama tanpa referer)
            $previous = url()->previous();
            $current = $request->url();
            if ($previous === $current) {
                return response(
                    '<h1>Terjadi kesalahan</h1><p>'.e($userMessage).'</p>',
                    500,
                    ['Content-Type' => 'text/html; charset=utf-8']
                );
            }

            // Inertia request → redirect back dengan flash error (aman, Inertia handle 302)
            // Browser biasa → redirect back dengan flash error
            return back()->with('error', $userMessage);
        });
    }

    /**
     * Tentukan apakah exception ini harus di-skip (tidak ditangani oleh handler global).
     *
     * Exception yang di-skip:
     * - HttpException dan subclass-nya (403, 404, 405, 429, dll)
     * - HttpResponseException (short-circuit response, misalnya dari throttle middleware)
     * - AuthenticationException (sudah ditangani SSO handler)
     * - ValidationException (ditangani Laravel)
     */
    private function shouldSkip(Throwable $e): bool
    {
        // HTTP exception — biarkan Laravel menangani dengan status code yang benar
        if ($e instanceof HttpException) {
            return true;
        }

        // Authorization exception (Laravel akan konversi ke 403)
        if ($e instanceof AuthorizationException) {
            return true;
        }

        // Model not found exception (Laravel akan konversi ke 404)
        if ($e instanceof ModelNotFoundException) {
            return true;
        }

        // HttpResponseException — short-circuit response dari framework (throttle, dll)
        // Response yang dibungkus di dalamnya sudah merupakan response final yang valid
        if ($e instanceof HttpResponseException) {
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
