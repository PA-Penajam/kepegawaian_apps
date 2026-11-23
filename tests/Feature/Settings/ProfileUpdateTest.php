<?php

use App\Models\Pegawai;

test('profile page is displayed', function () {
    $user = Pegawai::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = Pegawai::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'nama_lengkap' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->nama_lengkap)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = Pegawai::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'nama_lengkap' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('penghapusan akun mandiri diblokir pada mode sso-only', function () {
    $user = Pegawai::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response->assertForbidden();

    $this->assertAuthenticated();
    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->deleted_at)->toBeNull();
});
