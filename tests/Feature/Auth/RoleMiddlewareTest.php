<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Route::middleware('role:admin')->get('/test-role/admin', fn () => 'ok');
    Route::middleware('role:admin,operator')->get('/test-role/admin-or-operator', fn () => 'ok');
});

test('guests are redirected to the login page for role protected routes', function () {
    $response = get('/test-role/admin');

    $response->assertRedirect(route('login'));
});

test('viewers cannot access admin only routes', function () {
    $user = Pegawai::factory()->create();

    actingAs($user);

    $response = get('/test-role/admin');

    $response->assertForbidden();
});

test('admins can access admin only routes', function () {
    $user = Pegawai::factory()->admin()->create();

    actingAs($user);

    $response = get('/test-role/admin');

    $response->assertOk();
});

test('operators can access admin or operator routes', function () {
    $user = Pegawai::factory()->operator()->create();

    actingAs($user);

    $response = get('/test-role/admin-or-operator');

    $response->assertOk();
});

test('pegawai role helpers match the assigned role', function () {
    $admin = Pegawai::factory()->admin()->create();
    $operator = Pegawai::factory()->operator()->create();
    $viewer = Pegawai::factory()->viewer()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->isOperator())->toBeFalse()
        ->and($admin->isViewer())->toBeFalse()
        ->and($operator->isAdmin())->toBeFalse()
        ->and($operator->isOperator())->toBeTrue()
        ->and($operator->isViewer())->toBeFalse()
        ->and($viewer->isAdmin())->toBeFalse()
        ->and($viewer->isOperator())->toBeFalse()
        ->and($viewer->isViewer())->toBeTrue();
});
