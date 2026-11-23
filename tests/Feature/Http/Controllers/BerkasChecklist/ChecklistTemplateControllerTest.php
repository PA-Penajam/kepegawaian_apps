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
        ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
        ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
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

it('menolak duplikat items kode pada store dan update checklist template dengan 422', function (): void {
    $user = checklistTemplateUserWith('checklist.template.create', 'checklist.template.update');

    actingAs($user)
        ->postJson('/admin/checklist-template', [
            'kode' => 'KP-DUP',
            'nama' => 'Template Duplikat',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['kode' => 'SK_PANGKAT', 'nama' => 'SK 1', 'wajib' => true, 'urutan' => 1],
                ['kode' => 'SK_PANGKAT', 'nama' => 'SK 2', 'wajib' => true, 'urutan' => 2],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.1.kode');

    $template = BerkasChecklistTemplate::factory()->create(['kode' => 'KP-EDIT-DUP', 'jenis' => 'kp']);
    $item = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'kode' => 'ITEM_SATU',
    ]);

    actingAs($user)
        ->putJson("/admin/checklist-template/{$template->id}", [
            'kode' => 'KP-EDIT-DUP',
            'nama' => 'Template Edit Duplikat',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['id' => $item->id, 'kode' => 'SAMA', 'nama' => 'Item 1', 'wajib' => true, 'urutan' => 1],
                ['kode' => 'SAMA', 'nama' => 'Item 2', 'wajib' => false, 'urutan' => 2],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.1.kode');
});

it('update checklist template mendukung hapus dan tambah ulang kode sama serta swap kode antar item tanpa error 500', function (): void {
    $user = checklistTemplateUserWith('checklist.template.update');
    $template = BerkasChecklistTemplate::factory()->create(['kode' => 'KP-SWAP', 'jenis' => 'kp']);
    $itemA = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'kode' => 'ITEM_A',
        'nama' => 'Item A Lama',
        'urutan' => 1,
    ]);
    $itemB = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'kode' => 'ITEM_B',
        'nama' => 'Item B Lama',
        'urutan' => 2,
    ]);

    // (a) Swap kode antar item aktif (ITEM_A <-> ITEM_B)
    actingAs($user)
        ->put("/admin/checklist-template/{$template->id}", [
            'kode' => 'KP-SWAP',
            'nama' => 'Template Swap',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['id' => $itemA->id, 'kode' => 'ITEM_B', 'nama' => 'Item A Jadi B', 'wajib' => true, 'urutan' => 1],
                ['id' => $itemB->id, 'kode' => 'ITEM_A', 'nama' => 'Item B Jadi A', 'wajib' => false, 'urutan' => 2],
            ],
        ])
        ->assertRedirect(route('admin.checklist-template.index'))
        ->assertSessionHas('success');

    expect($itemA->fresh()->kode)->toBe('ITEM_B')
        ->and($itemB->fresh()->kode)->toBe('ITEM_A');

    // (b) Hapus item & tambah ulang kode yang sama tanpa id (restore soft-deleted)
    actingAs($user)
        ->put("/admin/checklist-template/{$template->id}", [
            'kode' => 'KP-SWAP',
            'nama' => 'Template Replace',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['kode' => 'ITEM_A', 'nama' => 'Item A Pengganti', 'wajib' => true, 'urutan' => 1],
            ],
        ])
        ->assertRedirect(route('admin.checklist-template.index'))
        ->assertSessionHas('success');

    expect($template->items()->count())->toBe(1)
        ->and($template->items()->first()->kode)->toBe('ITEM_A')
        ->and($template->items()->first()->nama)->toBe('Item A Pengganti');
});

it('menolak payload items asosiatif non-list pada store dan update dengan 422 bukan 500 TypeError', function (): void {
    $user = checklistTemplateUserWith('checklist.template.create', 'checklist.template.update');

    actingAs($user)
        ->postJson('/admin/checklist-template', [
            'kode' => 'KP-ASSOC-STORE',
            'nama' => 'Template Asosiatif Store',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                'abc' => ['kode' => 'ITEM_X', 'nama' => 'Item X', 'wajib' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    $template = BerkasChecklistTemplate::factory()->create(['kode' => 'KP-ASSOC-UPD', 'jenis' => 'kp']);

    actingAs($user)
        ->putJson("/admin/checklist-template/{$template->id}", [
            'kode' => 'KP-ASSOC-UPD',
            'nama' => 'Template Asosiatif Update',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                'abc' => ['kode' => 'ITEM_X', 'nama' => 'Item X', 'wajib' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

it('menolak items.*.id milik template lain atau yang sudah soft-deleted pada update dengan 422 bukan 404', function (): void {
    $user = checklistTemplateUserWith('checklist.template.update');
    $template1 = BerkasChecklistTemplate::factory()->create(['kode' => 'KP-TPL-1', 'jenis' => 'kp']);
    $template2 = BerkasChecklistTemplate::factory()->create(['kode' => 'KP-TPL-2', 'jenis' => 'kp']);

    $foreignItem = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template2->id,
        'kode' => 'FOREIGN_ITEM',
    ]);

    actingAs($user)
        ->putJson("/admin/checklist-template/{$template1->id}", [
            'kode' => 'KP-TPL-1',
            'nama' => 'Template 1 Update',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['id' => $foreignItem->id, 'kode' => 'ITEM_VALID', 'nama' => 'Item Valid', 'wajib' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.id');

    $trashedOwnItem = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template1->id,
        'kode' => 'TRASHED_OWN',
    ]);
    $trashedOwnItem->delete();

    actingAs($user)
        ->putJson("/admin/checklist-template/{$template1->id}", [
            'kode' => 'KP-TPL-1',
            'nama' => 'Template 1 Update Trashed',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['id' => $trashedOwnItem->id, 'kode' => 'ITEM_VALID', 'nama' => 'Item Valid', 'wajib' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.id');
});

it('menolak duplikat items.*.kode berbeda huruf besar-kecil pada store dan update dengan 422', function (): void {
    $user = checklistTemplateUserWith('checklist.template.create', 'checklist.template.update');

    actingAs($user)
        ->postJson('/admin/checklist-template', [
            'kode' => 'KP-CASE-DUP',
            'nama' => 'Template Case Insensitive',
            'jenis' => 'kp',
            'aktif' => true,
            'items' => [
                ['kode' => 'ITEM_A', 'nama' => 'Item 1', 'wajib' => true],
                ['kode' => 'item_a', 'nama' => 'Item 2', 'wajib' => false],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.1.kode');
});
