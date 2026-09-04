<?php

use App\Models\Pegawai;

test('beranda mengarahkan pengguna tamu ke login lokal', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login', absolute: false));
});

test('beranda mengarahkan pengguna terautentikasi ke dashboard', function () {
    $user = Pegawai::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard', absolute: false));
});
