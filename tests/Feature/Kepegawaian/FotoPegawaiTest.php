<?php

use App\Models\Pegawai;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('foto_url returns null when foto is null', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    expect($pegawai->foto_url)->toBeNull();
});

test('foto_url returns authorized route URL when foto path is set', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fotos/test.webp', 'fake-content');

    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/test.webp']);

    expect($pegawai->foto_url)
        ->toBeString()
        ->toBe(route('kepegawaian.pegawai.foto.show', $pegawai));
});

test('foto_url is included in model serialization via appends', function () {
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    $array = $pegawai->toArray();

    expect($array)->toHaveKey('foto_url');
});

test('foto tidak dapat diisi melalui mass assignment', function () {
    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/asli.webp']);

    $pegawai->fill(['foto' => 'fotos/injeksi.webp']);

    expect($pegawai->foto)->toBe('fotos/asli.webp');
});

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

test('admin dapat upload foto pegawai ke disk local dengan file valid', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);
    actingAs($admin);

    $file = UploadedFile::fake()->image('foto.jpg', 300, 300);

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertRedirect();

    $pegawai->refresh();
    expect($pegawai->foto)->not->toBeNull();
    Storage::disk('local')->assertExists($pegawai->foto);
    Storage::disk('public')->assertMissing($pegawai->foto);
});

test('upload foto gagal jika ukuran file lebih dari 2MB', function () {
    Storage::fake('local');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->create('foto.jpg', 3000, 'image/jpeg');

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertSessionHasErrors('foto');
});

test('upload foto gagal jika bukan file gambar', function () {
    Storage::fake('local');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');

    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file])
        ->assertSessionHasErrors('foto');
});

test('upload foto baru menggantikan foto lama di disk local', function () {
    Storage::fake('local');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);
    actingAs($admin);

    $file1 = UploadedFile::fake()->image('foto1.jpg', 300, 300);
    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file1]);

    $pegawai->refresh();
    $path1 = $pegawai->foto;

    $file2 = UploadedFile::fake()->image('foto2.jpg', 300, 300);
    post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $file2]);

    $pegawai->refresh();
    expect($pegawai->foto)->toBe($path1);
    Storage::disk('local')->assertExists($pegawai->foto);
});

test('pengguna berizin pegawai.view dapat mengakses foto pegawai', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fotos/terotorisasi.webp', 'binary-webp-content');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/terotorisasi.webp']);

    actingAs($admin);

    get(route('kepegawaian.pegawai.foto.show', $pegawai))
        ->assertOk();
});

test('viewer tanpa izin pegawai.view ditolak 403 saat mengakses foto pegawai', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fotos/terotorisasi.webp', 'binary-webp-content');

    $viewer = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/terotorisasi.webp']);

    actingAs($viewer);

    get(route('kepegawaian.pegawai.foto.show', $pegawai))
        ->assertForbidden();
});

test('endpoint foto pegawai mengembalikan 404 jika berkas tidak ada di disk local', function () {
    Storage::fake('local');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/tidak-ada.webp']);

    actingAs($admin);

    get(route('kepegawaian.pegawai.foto.show', $pegawai))
        ->assertNotFound();
});

test('viewer dapat mengakses foto miliknya sendiri via foto_url dan self-service foto, namun ditolak 403 untuk foto pegawai lain', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fotos/viewer-sendiri.webp', 'viewer-webp');
    Storage::disk('local')->put('fotos/pegawai-lain.webp', 'other-webp');

    $viewer = Pegawai::factory()->viewer()->create(['foto' => 'fotos/viewer-sendiri.webp']);
    $other = Pegawai::factory()->create(['foto' => 'fotos/pegawai-lain.webp']);

    actingAs($viewer);

    get((string) $viewer->foto_url)
        ->assertOk();

    get(route('self-service.foto'))
        ->assertOk();

    get(route('kepegawaian.pegawai.foto.show', $other))
        ->assertForbidden();
});
