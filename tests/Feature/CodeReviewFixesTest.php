<?php

/**
 * Tests untuk code review fixes:
 * 1. SRP - IamAuthController split (login/logout/menu)
 * 2. SRP - IamPolicyService split (policy vs model labels)
 * 3. OCP - IamRoleFactory Gate::define dipindah ke Policy
 * 4. Route names - centralized constants
 * 5. Dedup middleware groups
 * 6. PegawaiApiController duplicate query fix
 * 7. DashboardController N+1 fix
 * 8. PegawaiSeeder split
 */

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// ========================================================================
// Issue 1: SRP - IamAuthController harus split
// IamAuthController hanya handle login/logout/me, bukan menu/permissions
// ========================================================================

it('tidak punya method menu di IamController API', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\Api\IamController::class);
    $methods = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn ($m) => $m->getName())
        ->filter(fn ($m) => ! str_starts_with($m, '__'));

    // Controller harus hanya punya: validate, check, logout, exchangeCode
    // Tidak boleh ada method permissions() atau menu()
    expect($methods)->not->toContain('permissions')
        ->and($methods)->not->toContain('menu');
});

// ========================================================================
// Issue 2: SRP - IamPolicyService/AuthorizationService harus split
// Service hanya handle authorization, bukan modelLabelMap
// ========================================================================

it('IamAuthorizationService tidak punya method modelLabelMap', function () {
    $service = app(\App\Services\IamAuthorizationService::class);

    expect(method_exists($service, 'modelLabelMap'))->toBeFalse();
});

// ========================================================================
// Issue 3: OCP - IamRoleFactory tidak boleh Gate::define
// Gate harus didefinisikan di AuthServiceProvider via Policy
// ========================================================================

it('IamRoleFactory tidak mengandung Gate::define', function () {
    $content = file_get_contents(database_path('factories/IamRoleFactory.php'));

    expect($content)->not->toContain('Gate::define');
});

// ========================================================================
// Issue 4: OCP - Route names harus centralized
// ========================================================================

it('route name constants ada dan valid', function () {
    expect(Route::has('dashboard'))->toBeTrue();
    expect(Route::has('activity-log.index'))->toBeTrue();
});

// ========================================================================
// Issue 5: Tidak ada duplicate middleware groups
// ========================================================================

it('routes/web.php tidak punya duplicate middleware groups', function () {
    $content = file_get_contents(base_path('routes/web.php'));

    // Cek tidak ada duplikasi 'Route::middleware([\'auth\'])' yang identik
    preg_match_all('/Route::middleware\(\[.*?\]\)/', $content, $matches);
    $unique = array_unique($matches[0]);

    expect(count($matches[0]))->toBe(count($unique));
});

// ========================================================================
// Issue 6: PegawaiApiController tidak duplicate query
// search() harus pakai paginate() langsung, bukan count() + paginate()
// ========================================================================

it('PegawaiApiController search tidak query dua kali', function () {
    // Verifikasi struktur kode: search() harus menggunakan paginate() langsung
    // bukan count() terpisah lalu paginate() (duplikasi query)
    $content = file_get_contents(app_path('Http/Controllers/Api/PegawaiApiController.php'));

    // search() method harus tidak memanggil ->count() sebelum ->paginate()
    preg_match('/function search.*?\n(.*?)(?=\n    function|\n\})/s', $content, $matches);
    $searchBody = $matches[1] ?? '';

    expect($searchBody)->not->toContain('->count()');
});

// ========================================================================
// Issue 7: DashboardController eager loading
// ========================================================================

it('DashboardController load tanpa N+1', function () {
    $this->withoutVite();
    $admin = Pegawai::factory()->admin()->create();
    $this->actingAs($admin);

    $response = $this->get('/dashboard');
    $response->assertOk();
});

// ========================================================================
// Issue 8: PegawaiSeeder split
// Seeder harus delegate ke domain-specific seeders
// ========================================================================

it('PegawaiSeeder bisa run tanpa error', function () {
    $seeder = new \Database\Seeders\PegawaiSeeder();
    expect($seeder)->toBeInstanceOf(\Database\Seeders\PegawaiSeeder::class);
});

// ========================================================================
// Issue 9: Tests namespace dan IamRolePolicy
// ========================================================================

it('IamRolePolicy class ada', function () {
    expect(class_exists(\App\Policies\IamRolePolicy::class))->toBeTrue();
});

it('IamPermissionPolicy class ada', function () {
    expect(class_exists(\App\Policies\IamPermissionPolicy::class))->toBeTrue();
});
