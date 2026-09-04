<?php

use App\Models\Pegawai;

test('endpoint konfirmasi password lokal tidak tersedia bagi pengguna terautentikasi', function () {
    $user = Pegawai::factory()->create();

    $this->actingAs($user)->get(route('password.confirm'))->assertNotFound();
    $this->actingAs($user)->post(route('password.confirm.store'), [])->assertNotFound();
    $this->actingAs($user)->get(route('password.confirmation'))->assertNotFound();
});

test('konfirmasi password lokal menolak pengguna tamu ke SSO', function () {
    $this->get(route('password.confirm'))->assertRedirectContains(route('auth.sso.login'));
});
