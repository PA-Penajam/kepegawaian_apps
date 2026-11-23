<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk menambahkan security headers pada setiap response web.
 *
 * Melindungi aplikasi dari clickjacking, MIME sniffing, dan membatasi
 * origin resource yang boleh dimuat. HSTS dan CSP ketat berbasis nonce
 * hanya aktif di production agar tidak mengganggu Vite HMR saat development.
 */
class SecurityHeadersMiddleware
{
    /**
     * Menangani request masuk dan menyisipkan security headers pada response.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate nonce sebelum view dirender agar Blade dan Vite menyematkan nonce yang sama
        $nonce = app()->isProduction() ? Vite::useCspNonce() : null;

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($nonce !== null) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                // 'unsafe-inline' tanpa nonce tetap diperlukan oleh atribut style inline React/Radix UI
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https://fonts.bunny.net",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]));
        }

        return $response;
    }
}
