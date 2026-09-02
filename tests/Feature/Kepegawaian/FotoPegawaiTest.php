<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;

test('foto_url returns null when foto is null', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    expect($pegawai->foto_url)->toBeNull();
});

test('foto_url returns full storage URL when foto path is set', function () {
    Storage::fake('public');
    Storage::disk('public')->put('fotos/test.webp', 'fake-content');

    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/test.webp']);

    expect($pegawai->foto_url)
        ->toBeString()
        ->toContain('fotos/test.webp');
});

test('foto_url is included in model serialization via appends', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    $array = $pegawai->toArray();

    expect($array)->toHaveKey('foto_url');
});

use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('guest tidak bisa upload foto pegawai', function () {
    $pegawai = Pegawai::factory()->create();

    post(route('kepegawaian.pegawai.foto.update', $pegawai))
        ->assertRedirectContains(route('auth.sso.login'));
});

test('pegawai biasa tidak bisa upload foto pegawai lain', function () {
    $user = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($user);

    post(route('kepegawaian.pegawai.foto.update', $pegawai))
        ->assertForbidden();
});

test('admin dapat upload foto pegawai dengan file valid', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);
    actingAs($admin);

    $file = UploadedFile::fake()->image('foto.jpg', 300, 300);

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertRedirect();

    $pegawai->refresh();
    expect($pegawai->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($pegawai->foto);
});

test('upload foto gagal jika ukuran file lebih dari 2MB', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->create('foto.jpg', 3000, 'image/jpeg');

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertSessionHasErrors('foto');
});

test('upload foto gagal jika bukan file gambar', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertSessionHasErrors('foto');
});

test('upload foto baru menggantikan foto lama di storage', function () {
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);
    actingAs($admin);

    // Upload pertama
    $file1 = UploadedFile::fake()->image('foto1.jpg', 300, 300);
    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file1]);

    $pegawai->refresh();
    $path1 = $pegawai->foto;

    // Upload kedua — harus menimpa path yang sama
    $file2 = UploadedFile::fake()->image('foto2.jpg', 300, 300);
    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file2]);

    $pegawai->refresh();
    expect($pegawai->foto)->toBe($path1);
    Storage::disk('public')->assertExists($pegawai->foto);
});
