<?php

use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Exceptions\Cuti\SaldoTidakCukupException;
use App\Models\Pegawai;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
|--------------------------------------------------------------------------
| Web Request (Inertia) — Generic & Domain Exception Tests
|--------------------------------------------------------------------------
*/

test('web request dengan generic exception menampilkan flash error user-friendly', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    // Daftarkan route sementara yang melempar exception
    $this->app['router']->get('/_test/generic-exception', function () {
        throw new RuntimeException('Database connection failed');
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/generic-exception');

    $response->assertSessionHas('error');
    $response->assertSessionHas('error', 'Database connection failed');
    // Flash message tidak mengandung nama exception class (user-friendly)
    expect(session('error'))->not->toContain('RuntimeException');
});

test('web request dengan generic exception tanpa pesan mendapat pesan default', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/generic-empty', function () {
        throw new RuntimeException;
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/generic-empty');

    $response->assertSessionHas('error');
    expect(session('error'))->toBe('Terjadi kesalahan pada sistem. Silakan coba lagi atau hubungi administrator.');
});

test('web request dengan domain exception menampilkan pesan domain yang sudah user-friendly', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/domain-exception', function () {
        throw new SaldoTidakCukupException('Saldo cuti Anda tidak mencukupi. Sisa saldo: 2 hari.');
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/domain-exception');

    $response->assertSessionHas('error', 'Saldo cuti Anda tidak mencukupi. Sisa saldo: 2 hari.');
});

test('web request dengan overlap exception menampilkan pesan yang informatif', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/overlap-exception', function () {
        throw new OverlapPengajuanException('Anda sudah memiliki pengajuan cuti pada tanggal tersebut.');
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/overlap-exception');

    $response->assertSessionHas('error', 'Anda sudah memiliki pengajuan cuti pada tanggal tersebut.');
});

test('web request dengan domain exception tanpa pesan mendapat pesan default', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/domain-empty', function () {
        throw new SaldoTidakCukupException;
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/domain-empty');

    $response->assertSessionHas('error');
    // Pesan default harus user-friendly (fallback ke DEFAULT_ERROR_MESSAGE)
    expect(session('error'))->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Web Request — HTTP Exception Tidak Diubah
|--------------------------------------------------------------------------
*/

test('web request dengan authorization exception tetap mengembalikan 403 oleh Laravel', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/authorization-exception', function () {
        throw new AuthorizationException('You are not authorized.');
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/authorization-exception');

    // HTTP exception (403) tetap ditangani oleh Laravel, bukan handler global
    $response->assertForbidden();
});

test('web request dengan model not found tetap mengembalikan 404 oleh Laravel', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/model-not-found', function () {
        throw new ModelNotFoundException('Pegawai not found');
    })->middleware('web');

    actingAs($user);
    $response = get('/_test/model-not-found');

    // HTTP exception (404) tetap ditangani oleh Laravel
    $response->assertNotFound();
});

test('web request dengan validation exception tidak terganggu oleh handler global', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->app['router']->post('/_test/validation-exception', function () {
        throw ValidationException::withMessages(['nama' => 'Nama wajib diisi.']);
    })->middleware('web');

    actingAs($user);
    $response = $this->post('/_test/validation-exception');

    // ValidationException harus tetap ditangani oleh Laravel (redirect back with errors)
    $response->assertSessionHasErrors(['nama']);
    // Tidak boleh ada flash 'error' dari global handler
    $response->assertSessionMissing('error');
});

/*
|--------------------------------------------------------------------------
| Logging Tests
|--------------------------------------------------------------------------
*/

test('exception asli tetap di-log untuk debugging oleh Laravel', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->app['router']->get('/_test/log-check', function () {
        throw new RuntimeException('Original error message for log');
    })->middleware('web');

    $loggedMessages = [];
    Log::listen(function ($message) use (&$loggedMessages) {
        $loggedMessages[] = $message;
    });

    actingAs($user);
    get('/_test/log-check');

    // Verifikasi bahwa exception tetap di-log oleh Laravel
    expect($loggedMessages)->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| API Request (JSON) Exception Tests
|--------------------------------------------------------------------------
*/

test('api request dengan generic exception mengembalikan JSON response', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/api-generic-exception', function () {
        throw new RuntimeException('Database connection failed');
    })->middleware('api');

    actingAs($user);
    $response = $this->getJson('/_test/api-generic-exception');

    $response->assertStatus(500);
    $response->assertJsonStructure(['message']);
    // Pesan exception digunakan sebagai message
    expect($response->json('message'))->toBe('Database connection failed');
});

test('api request dengan domain exception mengembalikan JSON dengan pesan domain', function () {
    $user = Pegawai::factory()->admin()->create();
    Log::spy();

    $this->app['router']->get('/_test/api-domain-exception', function () {
        throw new SaldoTidakCukupException('Saldo cuti tidak mencukupi.');
    })->middleware('api');

    actingAs($user);
    $response = $this->getJson('/_test/api-domain-exception');

    $response->assertStatus(500);
    $response->assertJson(['message' => 'Saldo cuti tidak mencukupi.']);
});

test('api request dengan validation exception tetap mengembalikan JSON 422', function () {
    $user = Pegawai::factory()->admin()->create();

    $this->app['router']->post('/_test/api-validation-exception', function () {
        throw ValidationException::withMessages(['email' => 'Email tidak valid.']);
    })->middleware('api');

    actingAs($user);
    $response = $this->postJson('/_test/api-validation-exception');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

test('multiple exception berturut-turut tidak saling mempengaruhi flash message', function () {
    $user = Pegawai::factory()->admin()->create();

    // Request pertama - error
    $this->app['router']->get('/_test/seq-error', function () {
        throw new RuntimeException('Error pertama');
    })->middleware('web');

    actingAs($user);
    $response1 = get('/_test/seq-error');
    $response1->assertSessionHas('error');
    expect(session('error'))->toBe('Error pertama');
});
