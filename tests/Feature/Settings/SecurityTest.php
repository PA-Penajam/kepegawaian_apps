<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('halaman keamanan menegaskan akun dikelola SSO', function () {
    $user = Pegawai::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('ssoManaged', true),
        );
});

test('route perubahan password lokal tidak tersedia', function () {
    expect(Route::has('user-password.update'))->toBeFalse();
});
