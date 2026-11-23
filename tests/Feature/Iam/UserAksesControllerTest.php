<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;

test('UserAksesController menginvalidasi cache aplikasi terkait tanpa memanggil Cache::flush global', function () {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $role = IamRole::where('iam_application_id', $app->id)->where('slug', 'operator')->firstOrFail();

    Cache::put('iam_app:kepegawaian', $app, 3600);
    Cache::put('kunci_lain_aman', 'nilai-utuh', 3600);

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $target), [
            'iam_role_id' => $role->id,
        ])
        ->assertRedirect();

    expect(Cache::has('iam_app:kepegawaian'))->toBeFalse()
        ->and(Cache::get('kunci_lain_aman'))->toBe('nilai-utuh');

    Cache::put('iam_app:kepegawaian', $app, 3600);

    $this->actingAs($admin)
        ->delete(route('iam.users.akses.destroy', [$target, $role]))
        ->assertRedirect();

    expect(Cache::has('iam_app:kepegawaian'))->toBeFalse()
        ->and(Cache::get('kunci_lain_aman'))->toBe('nilai-utuh');
});

test('StoreUserAksesRequest menolak role yang sudah di-soft-delete dengan 422', function () {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();

    $deletedRole = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Terhapus',
        'slug' => 'role-terhapus',
    ]);
    $deletedRole->delete();

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $target), [
            'iam_role_id' => $deletedRole->id,
        ])
        ->assertSessionHasErrors('iam_role_id');
});

test('StoreUserAksesRequest menolak iam_role_id bertipe array dengan 422', function () {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $role = IamRole::where('iam_application_id', $app->id)->where('slug', 'operator')->firstOrFail();

    $this->actingAs($admin)
        ->postJson(route('iam.users.akses.store', $target), [
            'iam_role_id' => [$role->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('iam_role_id');
});

test('UserAksesController::index dan show mengecualikan role milik aplikasi yang sudah di-soft-delete', function () {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create(['nama_lengkap' => 'AAA Target Soft Delete App']);
    IamUserRole::where('user_id', $target->id)->delete();

    $deletedApp = IamApplication::factory()->create(['is_system' => false]);
    $orphanRole = IamRole::factory()->create([
        'iam_application_id' => $deletedApp->id,
        'nama' => 'Role Aplikasi Terhapus',
        'slug' => 'role-app-terhapus',
    ]);

    IamUserRole::create([
        'user_id' => $target->id,
        'iam_role_id' => $orphanRole->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $deletedApp->delete();

    $this->actingAs($admin)
        ->get(route('iam.users.index', ['search' => $target->nip]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('iam/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id)
            ->where('users.data.0.iam_roles_count', 0)
            ->where('users.data.0.iam_roles', []));

    $this->actingAs($admin)
        ->get(route('iam.users.akses', $target))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('iam/users/akses')
            ->where('user.id', $target->id)
            ->where('akses', []));
});

test('UserAksesController::index dan show mengecualikan IamRole yang sudah di-soft-delete sementara role aktif tetap tampil', function () {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create(['nama_lengkap' => 'AAA Target Soft Delete Role']);
    IamUserRole::where('user_id', $target->id)->delete();

    $activeApp = IamApplication::factory()->create(['is_system' => false]);
    $activeRole = IamRole::factory()->create([
        'iam_application_id' => $activeApp->id,
        'nama' => 'Role Aktif',
        'slug' => 'role-aktif',
    ]);
    $deletedRole = IamRole::factory()->create([
        'iam_application_id' => $activeApp->id,
        'nama' => 'Role Terhapus',
        'slug' => 'role-terhapus-akses',
    ]);

    IamUserRole::create([
        'user_id' => $target->id,
        'iam_role_id' => $activeRole->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);
    IamUserRole::create([
        'user_id' => $target->id,
        'iam_role_id' => $deletedRole->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $deletedRole->delete();

    $this->actingAs($admin)
        ->get(route('iam.users.index', ['search' => $target->nip]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('iam/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.iam_roles_count', 1)
            ->has('users.data.0.iam_roles', 1)
            ->where('users.data.0.iam_roles.0.id', $activeRole->id)
            ->where('users.data.0.iam_roles.0.application.id', $activeApp->id));

    $this->actingAs($admin)
        ->get(route('iam.users.akses', $target))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('iam/users/akses')
            ->has('akses', 1)
            ->where('akses.0.role.id', $activeRole->id)
            ->where('akses.0.role.application.id', $activeApp->id));

    // Jika role yang tersisa juga dihapus, daftar akses kosong tanpa null dereference
    $activeRole->delete();

    $this->actingAs($admin)
        ->get(route('iam.users.akses', $target))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('iam/users/akses')
            ->where('akses', []));
});

test('StoreUserAksesRequest menolak iam_role_id milik IamApplication yang sudah di-soft-delete dengan 422', function () {
    $admin = Pegawai::factory()->admin()->create();
    $target = Pegawai::factory()->create();
    $deletedApp = IamApplication::factory()->create(['is_system' => false]);
    $orphanRole = IamRole::factory()->create([
        'iam_application_id' => $deletedApp->id,
        'nama' => 'Role App Terhapus',
        'slug' => 'role-app-terhapus-store',
    ]);
    $deletedApp->delete();

    $this->actingAs($admin)
        ->postJson(route('iam.users.akses.store', $target), [
            'iam_role_id' => $orphanRole->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('iam_role_id');

    $this->actingAs($admin)
        ->post(route('iam.users.akses.store', $target), [
            'iam_role_id' => $orphanRole->id,
        ])
        ->assertSessionHasErrors('iam_role_id');

    expect(IamUserRole::where('user_id', $target->id)->where('iam_role_id', $orphanRole->id)->exists())->toBeFalse();
});
