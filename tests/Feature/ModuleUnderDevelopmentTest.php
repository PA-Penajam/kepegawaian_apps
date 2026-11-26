<?php

use App\Models\Pegawai;
use Database\Seeders\CutiPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('menampilkan halaman dalam pengembangan untuk modul non-cuti dan non-konfigurasi termasuk bagi admin', function (): void {
    config(['modules.under_development_enabled' => true]);
    $admin = Pegawai::factory()->admin()->create();

    actingAs($admin);

    $cases = [
        [route('self-service.index'), 'self_service'],
        [route('self-service.detail'), 'self_service'],
        [route('kepegawaian.pengajuan.index'), 'validasi_pengajuan'],
        [route('monitoring.kgb.index'), 'monitoring'],
        [route('monitoring.kenaikan-pangkat.index'), 'monitoring'],
        [route('kenaikan-pangkat.usulan.index'), 'kenaikan_pangkat'],
        [route('kenaikan-pangkat.eligible'), 'kenaikan_pangkat'],
        [route('kenaikan-pangkat.approval.inbox'), 'kenaikan_pangkat'],
        [route('kenaikan-pangkat.admin-sk.index'), 'kenaikan_pangkat'],
        [route('admin.checklist-template.index'), 'kenaikan_pangkat'],
    ];

    foreach ($cases as [$url, $moduleKey]) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('under-development')
                ->where('moduleKey', $moduleKey)
                ->has('moduleTitle')
                ->has('moduleDescription')
            );
    }
});

it('memblokir aksi mutasi dan permintaan JSON pada modul dalam pengembangan dengan status 503', function (): void {
    config(['modules.under_development_enabled' => true]);
    $admin = Pegawai::factory()->admin()->create();

    actingAs($admin);

    $this->post(route('kenaikan-pangkat.usulan.store'), [])
        ->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page
            ->component('under-development')
            ->where('moduleKey', 'kenaikan_pangkat')
        );

    $this->postJson(route('admin.checklist-template.store'), [])
        ->assertStatus(503)
        ->assertJsonPath('module', 'kenaikan_pangkat');
});

it('tetap mengizinkan modul dashboard, cuti, data pegawai, settings, iam, dan referensi saat mode pengembangan aktif', function (): void {
    $this->seed(CutiPermissionSeeder::class);
    config(['modules.under_development_enabled' => true]);
    $admin = Pegawai::factory()->admin()->create();

    actingAs($admin);

    $activeRoutes = [
        '/dashboard',
        route('cuti.saya'),
        route('kepegawaian.pegawai.index'),
        route('profile.edit'),
        route('iam.aplikasi.index'),
        route('activity-log.index'),
        route('referensi.roles.index'),
    ];

    foreach ($activeRoutes as $url) {
        $response = $this->get($url)->assertSuccessful();
        $page = $response->viewData('page');

        expect($page['component'])->not->toBe('under-development');
    }
});

it('membagikan daftar modul dalam pengembangan melalui shared props Inertia dan menghormati master toggle', function (): void {
    $admin = Pegawai::factory()->admin()->create();

    actingAs($admin);

    config(['modules.under_development_enabled' => true]);
    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.under_development', [
                'self_service',
                'validasi_pengajuan',
                'monitoring',
                'kenaikan_pangkat',
            ])
        );

    config(['modules.under_development_enabled' => false]);
    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.under_development', [])
        );

    $this->get(route('self-service.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('self-service/index'));
});
