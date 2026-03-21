<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\Pegawai;

beforeEach(function () {
    // Gunakan data dari IamSeeder
    $this->kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
    $this->adminRole = IamRole::where('iam_application_id', $this->kepegawaian->id)
        ->where('slug', 'admin')->first();

    $this->admin = Pegawai::factory()->admin()->create();
});

it('menolak update role milik aplikasi lain (IDOR)', function () {
    $app2 = IamApplication::factory()->create(['is_active' => true]);
    $roleApp2 = $app2->roles()->create(['nama' => 'Viewer', 'slug' => 'viewer']);

    $response = $this->actingAs($this->admin)
        ->put("/iam/aplikasi/{$this->kepegawaian->id}/roles/{$roleApp2->id}", [
            'nama' => 'Hacked', 'slug' => 'hacked',
        ]);

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_roles', ['id' => $roleApp2->id, 'nama' => 'Viewer']);
});

it('menolak hapus role milik aplikasi lain (IDOR)', function () {
    $app2 = IamApplication::factory()->create(['is_active' => true]);
    $roleApp2 = $app2->roles()->create(['nama' => 'Editor', 'slug' => 'editor']);

    $response = $this->actingAs($this->admin)
        ->delete("/iam/aplikasi/{$this->kepegawaian->id}/roles/{$roleApp2->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_roles', ['id' => $roleApp2->id]);
});

it('menolak slug role duplikat dalam satu aplikasi', function () {
    // Slug 'viewer' sudah ada dari IamSeeder
    $response = $this->actingAs($this->admin)
        ->from("/iam/aplikasi/{$this->kepegawaian->id}")
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/roles", [
            'nama' => 'Viewer Duplicate', 'slug' => 'viewer',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('slug');
});
