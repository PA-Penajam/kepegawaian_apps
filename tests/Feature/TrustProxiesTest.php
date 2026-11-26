<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withServerVariables;

it('menghasilkan URL pagination HTTPS saat request melewati trusted proxy dengan CF-Visitor HTTPS', function (): void {
    config(['app.trusted_proxies' => '10.0.0.0/8,127.0.0.1']);
    URL::forceScheme(null);

    $admin = Pegawai::factory()->admin()->create();
    Pegawai::factory()->count(24)->create();

    actingAs($admin);

    $response = withServerVariables([
        'REMOTE_ADDR' => '10.0.1.3',
        'HTTP_HOST' => 'kepegawaian.pa-penajam.go.id',
        'HTTP_X_FORWARDED_HOST' => 'kepegawaian.pa-penajam.go.id',
        'HTTP_X_FORWARDED_PROTO' => 'http',
        'HTTP_X_FORWARDED_PORT' => '80',
        'HTTP_CF_VISITOR' => '{"scheme":"https"}',
    ])->get('http://kepegawaian.pa-penajam.go.id/iam/users');

    $response->assertOk();

    $page = $response->viewData('page');
    $usersPaginator = $page['props']['users'] ?? [];

    expect($usersPaginator['next_page_url'] ?? null)
        ->toBe('https://kepegawaian.pa-penajam.go.id/iam/users?page=2');

    $serializedLinks = json_encode($usersPaginator['links'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($serializedLinks)
        ->toContain('https://kepegawaian.pa-penajam.go.id/iam/users?page=2')
        ->not->toContain('http://kepegawaian.pa-penajam.go.id');
});

it('mempercayai header X-Forwarded-Proto HTTPS dari trusted proxy tanpa CF-Visitor', function (): void {
    config(['app.trusted_proxies' => '10.0.0.0/8,127.0.0.1']);
    URL::forceScheme(null);

    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $response = withServerVariables([
        'REMOTE_ADDR' => '10.0.1.3',
        'HTTP_HOST' => 'kepegawaian.pa-penajam.go.id',
        'HTTP_X_FORWARDED_HOST' => 'kepegawaian.pa-penajam.go.id',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_PORT' => '443',
    ])->get('http://kepegawaian.pa-penajam.go.id/iam/users');

    $response->assertOk();
    expect(request()->isSecure())->toBeTrue();
});

it('tidak mempercayai header X-Forwarded-Proto maupun CF-Visitor dari IP yang bukan trusted proxy', function (): void {
    config(['app.trusted_proxies' => '10.0.0.0/8,127.0.0.1']);
    URL::forceScheme(null);

    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $response = withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_HOST' => 'kepegawaian.pa-penajam.go.id',
        'HTTP_X_FORWARDED_HOST' => 'kepegawaian.pa-penajam.go.id',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_PORT' => '443',
        'HTTP_CF_VISITOR' => '{"scheme":"https"}',
    ])->get('http://kepegawaian.pa-penajam.go.id/iam/users');

    $response->assertOk();
    expect(request()->isSecure())->toBeFalse();
});
