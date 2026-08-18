<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Pegawai;

use function Pest\Laravel\actingAs;

test('inertia request dengan 403 HttpException merender halaman error inertia', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->app['router']->get('/_test/http-403', function () {
        abort(403, 'Akses ditolak.');
    })->middleware('web');

    $version = app(HandleInertiaRequests::class)->version(request());

    actingAs($user);
    $response = $this->get('/_test/http-403', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ]);

    $response->assertStatus(403);
    $page = $response->original;
    expect($page['component'] ?? null)->toBe('errors/error')
        ->and($page['props']['status'] ?? null)->toBe(403)
        ->and($page['props']['message'] ?? null)->toBe('Akses ditolak.');
});

test('inertia request dengan 404 HttpException merender halaman error inertia', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->app['router']->get('/_test/http-404', function () {
        abort(404, 'Halaman berkas tidak ditemukan.');
    })->middleware('web');

    $version = app(HandleInertiaRequests::class)->version(request());

    actingAs($user);
    $response = $this->get('/_test/http-404', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ]);

    $response->assertStatus(404);
    $page = $response->original;
    expect($page['component'] ?? null)->toBe('errors/error')
        ->and($page['props']['status'] ?? null)->toBe(404)
        ->and($page['props']['message'] ?? null)->toBe('Halaman berkas tidak ditemukan.');
});

test('inertia request dengan 503 HttpException merender halaman error pemeliharaan', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->app['router']->get('/_test/http-503', function () {
        abort(503, 'Sistem dalam pemeliharaan.');
    })->middleware('web');

    $version = app(HandleInertiaRequests::class)->version(request());

    actingAs($user);
    $response = $this->get('/_test/http-503', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
    ]);

    $response->assertStatus(503);
    $page = $response->original;
    expect($page['component'] ?? null)->toBe('errors/error')
        ->and($page['props']['status'] ?? null)->toBe(503)
        ->and($page['props']['message'] ?? null)->toBe('Sistem dalam pemeliharaan.');
});

test('api request dengan http exception mengembalikan respons JSON terstruktur', function () {
    $this->app['router']->get('/_test/api-403', function () {
        abort(403, 'API access forbidden.');
    })->middleware('api');

    $response = $this->getJson('/_test/api-403');

    $response->assertStatus(403);
    $response->assertJson([
        'message' => 'API access forbidden.',
    ]);
});
