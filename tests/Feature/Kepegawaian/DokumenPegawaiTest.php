<?php

use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use App\Models\RefJenisDokumen;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use League\Flysystem\CorruptedPathDetected;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

function dokumenPegawaiIndexUrl(Pegawai $pegawai): string
{
    return route('kepegawaian.pegawai.dokumen.index', $pegawai);
}

function dokumenPegawaiItemUrl(string $action, Pegawai $pegawai, DokumenPegawai $dokumenPegawai): string
{
    return route("kepegawaian.pegawai.dokumen.{$action}", [$pegawai, $dokumenPegawai]);
}

function makeDokumenPegawaiPayload(array $overrides = []): array
{
    return array_merge([
        'jenis_dokumen' => 'KTP',
        'nomor_dokumen' => '64xxxxxxxxxx',
        'tanggal_dokumen' => '2024-01-15',
        'file' => UploadedFile::fake()->create('ktp.pdf', 256, 'application/pdf'),
        'keterangan' => 'Dokumen identitas utama',
    ], $overrides);
}

test('viewers are forbidden from accessing the dokumen pegawai page', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->create();

    actingAs($user);

    get(dokumenPegawaiIndexUrl($pegawai))
        ->assertForbidden();
});

test('admins can view all dokumen pegawai and legacy unsafe URIs are sanitized', function () {
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->admin()->create();

    DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'KTP',
        'file_path' => 'dokumen-pegawai/aman.pdf',
    ]);

    DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'NPWP',
        'file_path' => 'javascript:alert(1)',
    ]);

    actingAs($user);

    get(dokumenPegawaiIndexUrl($pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/dokumen-pegawai')
            ->where('pegawai.id', $pegawai->id)
            ->has('dokumen', 2)
            ->where('dokumen.0.has_file', true)
            ->where('dokumen.1.file_path', null)
            ->where('dokumen.1.download_url', null)
            ->where('dokumen.1.has_file', false),
        );
});

test('file_path tidak dapat diisi melalui mass assignment', function () {
    $dokumen = new DokumenPegawai;
    $dokumen->fill([
        'jenis_dokumen' => 'KTP',
        'file_path' => 'javascript:alert(1)',
    ]);

    expect($dokumen->file_path)->toBeNull();
});

test('operators can store dokumen pegawai to google drive disk and link ref_jenis_dokumen', function () {
    Storage::fake('google');

    $refJenis = RefJenisDokumen::factory()->create(['nama' => 'SK Pangkat']);
    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $payload = makeDokumenPegawaiPayload([
        'ref_jenis_dokumen_id' => $refJenis->id,
        'jenis_dokumen' => 'SK Pangkat',
    ]);

    actingAs($user);

    post(dokumenPegawaiIndexUrl($pegawai), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    $dokumenPegawai = DokumenPegawai::query()
        ->where('pegawai_id', $pegawai->id)
        ->firstWhere('jenis_dokumen', 'SK Pangkat');

    expect($dokumenPegawai)->not->toBeNull();
    expect($dokumenPegawai->ref_jenis_dokumen_id)->toBe($refJenis->id);
    expect($dokumenPegawai->jenisDokumen->is($refJenis))->toBeTrue();
    expect($dokumenPegawai->pegawai->is($pegawai))->toBeTrue();
    expect($dokumenPegawai->tanggal_dokumen?->toDateString())->toBe('2024-01-15');
    Storage::disk('google')->assertExists($dokumenPegawai->file_path);
});

test('store menolak mime berbahaya dan ukuran file melebihi 10MB', function () {
    Storage::fake('google');

    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();

    actingAs($user);

    from(dokumenPegawaiIndexUrl($pegawai))->post(dokumenPegawaiIndexUrl($pegawai), makeDokumenPegawaiPayload([
        'file' => UploadedFile::fake()->create('xss.html', 50, 'text/html'),
    ]))
        ->assertSessionHasErrors(['file']);

    from(dokumenPegawaiIndexUrl($pegawai))->post(dokumenPegawaiIndexUrl($pegawai), makeDokumenPegawaiPayload([
        'file' => UploadedFile::fake()->create('besar.pdf', 15360, 'application/pdf'),
    ]))
        ->assertSessionHasErrors(['file']);
});

test('operators can update metadata without replacing file and replace or delete removes old file on google disk', function () {
    Storage::fake('google');
    Storage::disk('google')->put('dokumen-pegawai/lama.pdf', 'konten-lama');

    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();
    $dokumenPegawai = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'file_path' => 'dokumen-pegawai/lama.pdf',
    ]);

    actingAs($user);

    // Update metadata tanpa file baru: pertahankan file_path lama
    patch(dokumenPegawaiItemUrl('update', $pegawai, $dokumenPegawai), [
        'jenis_dokumen' => 'NPWP',
        'nomor_dokumen' => '09.999.999.9-999.999',
        'tanggal_dokumen' => '2024-02-20',
        'file_path' => 'javascript:alert(1)',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    $dokumenPegawai->refresh();
    expect($dokumenPegawai->jenis_dokumen)->toBe('NPWP');
    expect($dokumenPegawai->file_path)->toBe('dokumen-pegawai/lama.pdf');
    Storage::disk('google')->assertExists('dokumen-pegawai/lama.pdf');

    // Update dengan file baru: hapus file lama di google disk
    patch(dokumenPegawaiItemUrl('update', $pegawai, $dokumenPegawai), [
        'jenis_dokumen' => 'NPWP',
        'file' => UploadedFile::fake()->create('baru.jpg', 200, 'image/jpeg'),
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    $dokumenPegawai->refresh();
    expect($dokumenPegawai->file_path)->not->toBe('dokumen-pegawai/lama.pdf');
    Storage::disk('google')->assertMissing('dokumen-pegawai/lama.pdf');
    Storage::disk('google')->assertExists($dokumenPegawai->file_path);

    $pathBaru = $dokumenPegawai->file_path;

    // Destroy menghapus file di google disk dan melakukan soft delete
    delete(dokumenPegawaiItemUrl('destroy', $pegawai, $dokumenPegawai))
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    Storage::disk('google')->assertMissing($pathBaru);
    expect(DokumenPegawai::query()->find($dokumenPegawai->id))->toBeNull();
    expect(DokumenPegawai::query()->withTrashed()->find($dokumenPegawai->id)?->trashed())->toBeTrue();
});

test('download dokumen memeriksa izin view dan kepemilikan pegawai', function () {
    Storage::fake('google');
    Storage::disk('google')->put('dokumen-pegawai/file-a.pdf', 'pdf-bytes');

    $pegawaiA = Pegawai::factory()->create();
    $pegawaiB = Pegawai::factory()->create();
    $dokumenA = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawaiA->id,
        'jenis_dokumen' => 'KTP',
        'file_path' => 'dokumen-pegawai/file-a.pdf',
    ]);

    $admin = Pegawai::factory()->admin()->create();
    $viewer = Pegawai::factory()->create();

    actingAs($admin);
    get(route('kepegawaian.pegawai.dokumen.download', [$pegawaiA, $dokumenA]))
        ->assertOk();

    // IDOR lintas pegawai -> 404
    get(route('kepegawaian.pegawai.dokumen.download', [$pegawaiB, $dokumenA]))
        ->assertNotFound();

    // Tanpa izin pegawai.view -> 403
    actingAs($viewer);
    get(route('kepegawaian.pegawai.dokumen.download', [$pegawaiA, $dokumenA]))
        ->assertForbidden();
});

test('jenis dokumen is required when storing dokumen pegawai', function () {
    Storage::fake('google');

    $pegawai = Pegawai::factory()->create();
    $user = Pegawai::factory()->operator()->create();

    actingAs($user);

    from(dokumenPegawaiIndexUrl($pegawai))->post(dokumenPegawaiIndexUrl($pegawai), makeDokumenPegawaiPayload([
        'jenis_dokumen' => '',
    ]))
        ->assertSessionHasErrors(['jenis_dokumen'])
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));
});

test('pegawai has many dokumen pegawai records', function () {
    $pegawai = Pegawai::factory()->create();

    DokumenPegawai::factory()->count(2)->create([
        'pegawai_id' => $pegawai->id,
    ]);

    expect($pegawai->dokumenPegawai)->toHaveCount(2);
});

test('download_url menggunakan path relatif dan URI berbahaya dengan kontrol ASCII disaring', function (string $unsafeUri) {
    Storage::fake('google');

    $pegawai = Pegawai::factory()->create();
    $admin = Pegawai::factory()->admin()->create();

    $dokumenAman = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'KTP',
        'file_path' => 'dokumen-pegawai/ktp-aman.pdf',
    ]);

    $dokumenBerbahaya = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'NPWP',
        'file_path' => $unsafeUri,
    ]);

    $expectedRelativeUrl = route('kepegawaian.pegawai.dokumen.download', [
        'pegawai' => $pegawai,
        'dokumen' => $dokumenAman,
    ], false);

    actingAs($admin);

    get(dokumenPegawaiIndexUrl($pegawai))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pegawai/dokumen-pegawai')
            ->has('dokumen', 2)
            ->where('dokumen.0.has_file', true)
            ->where('dokumen.0.download_url', $expectedRelativeUrl)
            ->where('dokumen.1.file_path', null)
            ->where('dokumen.1.download_url', null)
            ->where('dokumen.1.has_file', false),
        );

    expect($expectedRelativeUrl)->toStartWith('/')
        ->and($expectedRelativeUrl)->not->toStartWith('//');

    get(route('kepegawaian.pegawai.dokumen.download', [$pegawai, $dokumenBerbahaya]))
        ->assertNotFound();
})->with([
    'tab di skema javascript' => "java\tscript:alert(1)",
    'null byte di awal javascript' => "\x00javascript:alert(1)",
    'karakter kontrol di skema data' => "da\rta:text/html,<script>alert(1)</script>",
    'karakter kontrol di skema vbscript' => "\x1Fvbscript:msgbox(1)",
    'unicode NBSP sebelum skema javascript' => "\u{00A0}javascript:alert(1)",
    'unicode ZWSP sebelum skema javascript' => "\u{200B}javascript:alert(1)",
    'unicode NEL sebelum skema javascript' => "\u{0085}javascript:alert(1)",
    'protokol relatif ganda' => '//evil.test/x',
    'protokol relatif slash backslash' => '/\\evil.test/x',
    'url eksternal https' => 'https://evil.test/x',
    'null byte di tengah path' => "dokumen-pegawai/aman\x00.pdf",
    'path traversal' => 'dokumen-pegawai/../../etc/passwd',
]);

test('download mengembalikan 404 bersih jika penyimpanan google melempar FilesystemException', function () {
    $pegawai = Pegawai::factory()->create();
    $admin = Pegawai::factory()->admin()->create();
    $dokumen = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'KTP',
        'file_path' => 'dokumen-pegawai/valid-path.pdf',
    ]);

    $mockDisk = Mockery::mock(FilesystemAdapter::class);
    $mockDisk->shouldReceive('exists')
        ->once()
        ->with('dokumen-pegawai/valid-path.pdf')
        ->andThrow(CorruptedPathDetected::forPath('dokumen-pegawai/valid-path.pdf'));

    Storage::shouldReceive('disk')
        ->once()
        ->with('google')
        ->andReturn($mockDisk);

    actingAs($admin);

    from(dokumenPegawaiIndexUrl($pegawai))
        ->get(route('kepegawaian.pegawai.dokumen.download', [$pegawai, $dokumen]))
        ->assertNotFound();
});

test('update dan destroy tetap berhasil meski pembersihan file lama melempar FilesystemException', function () {
    $pegawai = Pegawai::factory()->create();
    $operator = Pegawai::factory()->operator()->create();
    $dokumen = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'KTP',
        'file_path' => 'dokumen-pegawai/korup-lama.pdf',
    ]);

    $mockDisk = Mockery::mock(FilesystemAdapter::class);
    $mockDisk->shouldReceive('putFile')
        ->once()
        ->andReturn('dokumen-pegawai/baru-aman.pdf');
    $mockDisk->shouldReceive('exists')
        ->with('dokumen-pegawai/korup-lama.pdf')
        ->once()
        ->andThrow(CorruptedPathDetected::forPath('dokumen-pegawai/korup-lama.pdf'));
    $mockDisk->shouldReceive('exists')
        ->with('dokumen-pegawai/baru-aman.pdf')
        ->once()
        ->andThrow(CorruptedPathDetected::forPath('dokumen-pegawai/baru-aman.pdf'));

    Storage::shouldReceive('disk')
        ->with('google')
        ->andReturn($mockDisk);

    actingAs($operator);

    patch(dokumenPegawaiItemUrl('update', $pegawai, $dokumen), [
        'jenis_dokumen' => 'KTP',
        'file' => UploadedFile::fake()->create('baru.pdf', 120, 'application/pdf'),
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    expect($dokumen->fresh()?->file_path)->toBe('dokumen-pegawai/baru-aman.pdf');

    delete(dokumenPegawaiItemUrl('destroy', $pegawai, $dokumen->fresh()))
        ->assertSessionHasNoErrors()
        ->assertRedirect(dokumenPegawaiIndexUrl($pegawai));

    expect(DokumenPegawai::query()->find($dokumen->id))->toBeNull();
});
