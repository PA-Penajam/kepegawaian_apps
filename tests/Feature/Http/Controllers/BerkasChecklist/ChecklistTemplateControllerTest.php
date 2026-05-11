<?php

use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function grantChecklistTemplatePermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => 'test-checklist'],
        ['nama' => 'Checklist Test', 'url' => 'https://local']
    );

    $role = IamRole::query()->firstOrCreate(
        ['slug' => 'checklist-template-admin', 'iam_application_id' => $app->id],
        ['nama' => 'Checklist Template Admin']
    );

    $permission = IamPermission::query()->firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug]
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
}

function checklistTemplateUserWith(string ...$permissions): Pegawai
{
    $pegawai = Pegawai::factory()->create();

    foreach ($permissions as $permission) {
        grantChecklistTemplatePermission($pegawai, $permission);
    }

    return $pegawai;
}

it('menampilkan index checklist template untuk user berizin', function (): void {
    $user = checklistTemplateUserWith('checklist.template.view');
    $template = BerkasChecklistTemplate::factory()->create(['jenis' => 'kp']);
    BerkasChecklistItem::factory()->count(2)->create([
        'berkas_checklist_template_id' => $template->id,
    ]);

    actingAs($user)
        ->withoutVite()
        ->get('/admin/checklist-template?jenis=kp')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/checklist-template/index')
            ->where('filters.jenis', 'kp')
            ->has('templates.data', 1)
            ->where('templates.data.0.items_count', 2)
        );
});

it('menyimpan checklist template valid beserta items', function (): void {
    $user = checklistTemplateUserWith('checklist.template.create');

    actingAs($user)
        ->post('/admin/checklist-template', [
            'kode' => 'KP-REGULER',
            'nama' => 'Kenaikan Pangkat Reguler',
            'jenis' => 'kp',
            'deskripsi' => 'Template KP reguler.',
            'aktif' => true,
            'items' => [
                ['kode' => 'SK', 'nama' => 'SK Terakhir', 'wajib' => true, 'urutan' => 1],
                ['kode' => 'PAK', 'nama' => 'PAK', 'wajib' => false, 'urutan' => 2],
            ],
        ])
        ->assertRedirect(route('admin.checklist-template.index'))
        ->assertSessionHas('success', 'Template checklist berhasil ditambahkan.');

    $this->assertDatabaseHas('berkas_checklist_templates', [
        'kode' => 'KP-REGULER',
        'nama' => 'Kenaikan Pangkat Reguler',
        'jenis' => 'kp',
    ]);
    $this->assertDatabaseHas('berkas_checklist_items', [
        'kode' => 'SK',
        'nama' => 'SK Terakhir',
        'wajib' => true,
        'urutan' => 1,
    ]);
});

it('tidak menghapus checklist template yang sudah dipakai', function (): void {
    $user = checklistTemplateUserWith('checklist.template.delete');
    $template = BerkasChecklistTemplate::factory()->create();
    BerkasChecklistSubmission::factory()->create([
        'berkas_checklist_template_id' => $template->id,
    ]);

    actingAs($user)
        ->delete("/admin/checklist-template/{$template->id}")
        ->assertRedirect(route('admin.checklist-template.index'))
        ->assertSessionHas('error', 'Template checklist tidak dapat dihapus karena sudah digunakan.');

    expect($template->fresh())->not->toBeNull();
});

it('menolak akses index checklist template tanpa permission', function (): void {
    $user = Pegawai::factory()->create();

    actingAs($user)
        ->withoutVite()
        ->get('/admin/checklist-template')
        ->assertForbidden();
});
