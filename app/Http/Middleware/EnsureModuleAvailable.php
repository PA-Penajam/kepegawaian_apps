<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk mengalihkan modul yang sedang dalam tahap pengembangan ke halaman informasi
 * serta memblokir aksi mutasi maupun permintaan JSON pada modul tersebut.
 */
class EnsureModuleAvailable
{
    /**
     * Memeriksa ketersediaan modul sebelum meneruskan permintaan ke controller.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! (bool) config('modules.under_development_enabled', true)) {
            return $next($request);
        }

        $moduleConfig = config("modules.items.{$module}");

        if (! is_array($moduleConfig) || ! (bool) ($moduleConfig['under_development'] ?? false)) {
            return $next($request);
        }

        $title = (string) ($moduleConfig['title'] ?? 'Modul Dalam Pengembangan');
        $description = (string) ($moduleConfig['description'] ?? 'Halaman ini sedang dalam tahap pengembangan.');

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json([
                'message' => "{$title} sedang dalam tahap pengembangan.",
                'module' => $module,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = Inertia::render('under-development', [
            'moduleKey' => $module,
            'moduleTitle' => $title,
            'moduleDescription' => $description,
        ])->toResponse($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $response;
    }
}
